<?php

namespace Modules\Core\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Contracts\FrontendRegistryBuilderInterface;
use Modules\Core\Contracts\FrontendRegistryStoreInterface;
use Modules\Core\Contracts\ModuleLifecycleServiceInterface;
use Modules\Core\Contracts\ModuleLifecycleValidatorInterface;
use Modules\Core\Contracts\ModuleManifestLoaderInterface;
use Modules\Core\Contracts\ModuleRuntimeExecutorInterface;
use Modules\Core\Contracts\ModuleStateRepositoryInterface;
use Modules\Core\Data\ModuleLifecycleResult;
use Modules\Core\Enums\ModuleAction;
use Modules\Core\Enums\ModuleActionStatus;
use Modules\Core\Enums\ModuleState;
use Modules\Core\Exceptions\ModuleOperationNotQueuedException;
use Modules\Core\Jobs\RunModuleLifecycleOperationJob;
use Modules\Core\Models\CoreModule;
use Modules\Core\Models\CoreModuleOperation;
use Modules\Core\Services\FrontendBuildPipelineService;

class ModuleLifecycleService implements ModuleLifecycleServiceInterface
{
    public function __construct(
        private readonly ModuleManifestLoaderInterface $manifests,
        private readonly ModuleStateRepositoryInterface $states,
        private readonly Dispatcher $dispatcher,
        private readonly ModuleLifecycleValidatorInterface $validator,
        private readonly ModuleRuntimeExecutorInterface $runtimeExecutor,
        private readonly FrontendBuildPipelineService $frontendPipeline,
    ) {
    }

    public function queue(string $slug, ModuleAction $action): CoreModuleOperation
    {
        $manifest = $this->manifests->findBySlug($slug);

        if ($manifest === null) {
            throw new InvalidArgumentException("Module manifest for slug [{$slug}] could not be found.");
        }

        $this->validator->validate($manifest, $action);

        $operation = $this->createQueuedOperation($slug, $action);

        try {
            $this->dispatcher->dispatch(new RunModuleLifecycleOperationJob($operation->id));
        } catch (\Throwable $exception) {
            $this->finishOperation(
                $operation,
                ModuleActionStatus::Failed,
                ['error' => sprintf('Failed to dispatch module operation job: %s', $exception->getMessage())],
            );

            throw $exception;
        }

        return $operation;
    }

    public function createQueuedOperation(string $slug, ModuleAction $action): CoreModuleOperation
    {
        $manifest = $this->manifests->findBySlug($slug);

        if ($manifest === null) {
            throw new InvalidArgumentException("Module manifest for slug [{$slug}] could not be found.");
        }

        $operation = new CoreModuleOperation();
        $operation->module_slug = $slug;
        $operation->operation = $action;
        $operation->status = ModuleActionStatus::Queued;
        $operation->payload = $this->createInitialPayload($action);
        $operation->save();

        return $operation;
    }

    public function runOperation(CoreModuleOperation $operation): ModuleLifecycleResult
    {
        if (! $this->claimOperation($operation)) {
            throw ModuleOperationNotQueuedException::forOperation($operation->id);
        }

        $manifest = $this->manifests->findBySlug($operation->module_slug);

        if ($manifest === null) {
            throw new InvalidArgumentException("Module manifest for slug [{$operation->module_slug}] could not be found.");
        }

        try {
            $this->validator->validate($manifest, $operation->operation);
            $module = $this->performOperation($manifest, $operation->operation);
        } catch (\Throwable $exception) {
            $this->finishOperation(
                $operation,
                ModuleActionStatus::Failed,
                ['error' => $exception->getMessage()],
            );

            throw $exception;
        }

        $this->finishOperation(
            $operation,
            ModuleActionStatus::Succeeded,
            $this->completePayload($operation),
        );

        return new ModuleLifecycleResult(
            module: $operation->operation === ModuleAction::Delete ? $module : $module->refresh(),
            operation: $operation->refresh(),
        );
    }

    public function latestOperationFor(string $slug): ?CoreModuleOperation
    {
        return CoreModuleOperation::query()
            ->where('module_slug', $slug)
            ->latest('id')
            ->first();
    }

    public function execute(string $slug, ModuleAction $action): ModuleLifecycleResult
    {
        return $this->runOperation($this->createQueuedOperation($slug, $action));
    }

    private function performOperation(\Modules\Core\Data\ModuleManifestData $manifest, ModuleAction $action): CoreModule
    {
        return match ($action) {
            ModuleAction::Install => $this->installModule($manifest),
            ModuleAction::Update => DB::transaction(fn () => $this->updateModule($manifest)),
            ModuleAction::Enable => DB::transaction(fn () => $this->enableModule($manifest)),
            ModuleAction::Disable => DB::transaction(fn () => $this->disableModule($manifest)),
            ModuleAction::Delete => DB::transaction(fn () => $this->deleteModule($manifest)),
            ModuleAction::RebuildRegistry => DB::transaction(function () use ($manifest): CoreModule {
                $module = $this->syncModuleState($manifest);

                $this->syncFrontendArtifacts();

                return $module;
            }),
        };
    }

    private function installModule(\Modules\Core\Data\ModuleManifestData $manifest): CoreModule
    {
        $this->runtimeExecutor->install($manifest);

        $module = $this->states->upsertFromManifest($manifest, ModuleState::Installed);

        if ($manifest->hasFrontend) {
            $this->syncFrontendArtifacts();
        }

        return $module;
    }

    private function updateModule(\Modules\Core\Data\ModuleManifestData $manifest): CoreModule
    {
        $existing = $this->states->findBySlug($manifest->slug);
        $state = $existing?->state ?? ModuleState::Installed;

        $this->runtimeExecutor->update($manifest);

        $module = $this->states->upsertFromManifest($manifest, $state);

        if ($manifest->hasFrontend) {
            $this->syncFrontendArtifacts();
        }

        return $module;
    }

    private function enableModule(\Modules\Core\Data\ModuleManifestData $manifest): CoreModule
    {
        $this->runtimeExecutor->enable($manifest);

        $module = $this->states->upsertFromManifest($manifest, ModuleState::Enabled);

        if ($manifest->hasFrontend) {
            $this->syncFrontendArtifacts();
        }

        return $module;
    }

    private function disableModule(\Modules\Core\Data\ModuleManifestData $manifest): CoreModule
    {
        $this->runtimeExecutor->disable($manifest);

        $module = $this->states->upsertFromManifest($manifest, ModuleState::Disabled);

        if ($manifest->hasFrontend) {
            $this->syncFrontendArtifacts();
        }

        return $module;
    }

    private function deleteModule(\Modules\Core\Data\ModuleManifestData $manifest): CoreModule
    {
        $module = $this->states->findBySlug($manifest->slug)
            ?? new CoreModule([
                'slug' => $manifest->slug,
                'name' => $manifest->name,
                'version' => $manifest->version,
                'description' => $manifest->description,
                'priority' => $manifest->priority,
                'state' => ModuleState::Discovered,
                'is_protected' => $manifest->isProtected,
                'has_frontend' => $manifest->hasFrontend,
                'panel_constraint' => $manifest->panelConstraint,
                'core_api_constraint' => $manifest->coreApiConstraint,
                'manifest_path' => $manifest->path,
            ]);

        $this->runtimeExecutor->delete($manifest);
        $this->states->deleteBySlug($manifest->slug);

        if ($manifest->hasFrontend) {
            $this->syncFrontendArtifacts();
        }

        return $module;
    }

    private function syncModuleState(\Modules\Core\Data\ModuleManifestData $manifest): CoreModule
    {
        $existing = $this->states->findBySlug($manifest->slug);

        return $this->states->upsertFromManifest($manifest, $existing?->state ?? ModuleState::Discovered);
    }

    private function claimOperation(CoreModuleOperation $operation): bool
    {
        $payload = $operation->payload ?? [];
        unset($payload['error']);

        $claimed = CoreModuleOperation::query()
            ->whereKey($operation->id)
            ->where('status', ModuleActionStatus::Queued->value)
            ->update([
                'status' => ModuleActionStatus::Running->value,
                'payload' => $payload,
                'started_at' => CarbonImmutable::now(),
                'finished_at' => null,
                'updated_at' => CarbonImmutable::now(),
            ]);

        if ($claimed === 1) {
            $operation->refresh();
        }

        return $claimed === 1;
    }

    private function finishOperation(CoreModuleOperation $operation, ModuleActionStatus $status, array $payload = []): void
    {
        $currentPayload = $operation->payload ?? [];

        $operation->forceFill([
            'status' => $status,
            'payload' => array_merge($currentPayload, $payload),
            'finished_at' => CarbonImmutable::now(),
        ])->save();
    }

    private function createInitialPayload(ModuleAction $action): array
    {
        return [
            'action' => $action->value,
            'progress' => 0,
            'steps' => $this->buildSteps($action),
        ];
    }

    private function buildSteps(ModuleAction $action): array
    {
        $steps = [
            $this->step('validation', 'Validate lifecycle prerequisites'),
        ];

        if ($action === ModuleAction::Install) {
            $steps[] = $this->step('migrations', 'Run module migrations');
        } elseif ($action === ModuleAction::Update) {
            $steps[] = $this->step('update', 'Run module update lifecycle');
        } elseif (in_array($action, [ModuleAction::Enable, ModuleAction::Disable], true)) {
            $steps[] = $this->step('activator', $action === ModuleAction::Enable ? 'Enable module activator' : 'Disable module activator');
        } elseif ($action === ModuleAction::Delete) {
            $steps[] = $this->step('delete', 'Delete module files and activator state');
        } elseif ($action === ModuleAction::RebuildRegistry) {
            $steps[] = $this->step('registry', 'Rebuild frontend registry');
        }

        $steps[] = $this->step('state', $action === ModuleAction::Delete ? 'Remove persisted module state' : 'Persist module state');

        return $steps;
    }

    private function step(string $name, string $label): array
    {
        return [
            'name' => $name,
            'label' => $label,
            'status' => 'pending',
            'progress' => 0,
        ];
    }

    private function completePayload(CoreModuleOperation $operation): array
    {
        $payload = $operation->payload ?? [];
        $steps = $payload['steps'] ?? [];

        if (! is_array($steps)) {
            $steps = [];
        }

        $payload['progress'] = 100;
        $payload['steps'] = array_map(
            static fn (array $step): array => array_merge($step, [
                'status' => 'completed',
                'progress' => 100,
            ]),
            $steps,
        );

        return $payload;
    }

    private function syncFrontendArtifacts(): void
    {
        $production = ! app()->environment(['local', 'testing']);

        $this->frontendPipeline->sync(build: $production, production: $production);
    }
}
