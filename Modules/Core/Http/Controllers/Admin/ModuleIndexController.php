<?php

namespace Modules\Core\Http\Controllers\Admin;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Core\Contracts\ModuleImportServiceInterface;
use Modules\Core\Contracts\ModuleManifestLoaderInterface;
use Modules\Core\Contracts\ModuleStateRepositoryInterface;
use Modules\Core\Enums\ModuleState;

class ModuleIndexController extends Controller
{
    public function __construct(
        private readonly ModuleManifestLoaderInterface $manifests,
        private readonly ModuleStateRepositoryInterface $states,
        private readonly ModuleImportServiceInterface $imports,
    ) {
    }

    public function index(): View
    {
        return view('core-module::admin.modules.index', [
            'modules' => $this->buildModuleRows(),
        ]);
    }

    public function logs(): View
    {
        return view('core-module::admin.modules.logs', [
            'operations' => $this->buildOperationRows(),
        ]);
    }

    public function installation(): View
    {
        return view('core-module::admin.modules.installation');
    }

    /**
     * @return Collection<int, object>
     */
    private function buildModuleRows(): Collection
    {
        $manifestRows = collect($this->manifests->discover())->values();
        $storedModules = $this->states->allBySlugs($manifestRows->pluck('slug')->all());
        $latestOperationsByModule = $this->buildOperationRows()
            ->filter(static fn (object $operation): bool => is_string($operation->moduleSlug) && $operation->moduleSlug !== '')
            ->unique('moduleSlug')
            ->keyBy('moduleSlug');

        return $manifestRows->map(function ($manifest) use ($storedModules, $latestOperationsByModule) {
            $storedModule = $storedModules[$manifest->slug] ?? null;
            $state = $storedModule?->state ?? ModuleState::Discovered;
            $latestOperation = $latestOperationsByModule->get($manifest->slug);

            return (object) [
                'name' => $manifest->name,
                'slug' => $manifest->slug,
                'version' => $manifest->version,
                'isProtected' => $manifest->isProtected,
                'hasFrontend' => $manifest->hasFrontend,
                'state' => $state,
                'stateText' => $this->formatTitle($state->value),
                'stateClass' => $this->moduleStateClass($state),
                'capabilities' => array_values(array_filter([
                    $manifest->isProtected ? ['text' => 'Protected', 'class' => 'label label-danger'] : null,
                    $manifest->hasFrontend ? ['text' => 'Frontend', 'class' => 'label bg-aqua'] : null,
                ])),
                'latestOperation' => $latestOperation,
                'canInstall' => $state === ModuleState::Discovered,
                'canEnable' => in_array($state, [ModuleState::Installed, ModuleState::Disabled], true),
                'canUpdate' => in_array($state, [ModuleState::Installed, ModuleState::Enabled, ModuleState::Disabled], true),
                'canDisable' => ! $manifest->isProtected && $state === ModuleState::Enabled,
                'canDelete' => ! $manifest->isProtected,
                'canRebuild' => $state !== ModuleState::Discovered,
            ];
        })->values();
    }

    /**
     * @return Collection<int, object>
     */
    private function buildOperationRows(): Collection
    {
        return $this->imports->latestOperations(20)
            ->map(function ($operation) {
                $status = $operation->status->value;
                $sourceType = $operation->payload['source']['type'] ?? null;
                $sourceReference = $operation->payload['source']['repository']
                    ?? $operation->payload['source']['path']
                    ?? null;
                $activeStep = collect($operation->payload['steps'] ?? [])
                    ->first(static fn (array $step): bool => ($step['status'] ?? null) !== 'completed')
                    ?? collect($operation->payload['steps'] ?? [])->last();

                return (object) [
                    'id' => $operation->id,
                    'moduleSlug' => $operation->module_slug,
                    'targetText' => is_string($operation->module_slug) && $operation->module_slug !== ''
                        ? $operation->module_slug
                        : sprintf('%s Import', $this->formatTitle((string) $sourceType)),
                    'action' => $operation->operation->value,
                    'actionText' => $this->formatTitle($operation->operation->value),
                    'status' => $status,
                    'statusText' => $this->formatTitle($status),
                    'statusClass' => $this->operationStatusClass($status),
                    'progress' => $operation->payload['progress'] ?? null,
                    'progressText' => is_numeric($operation->payload['progress'] ?? null)
                        ? sprintf('%d%%', (int) $operation->payload['progress'])
                        : 'n/a',
                    'error' => $operation->payload['error'] ?? null,
                    'detailText' => $operation->payload['error']
                        ?? $sourceReference
                        ?? ($activeStep['label'] ?? null),
                ];
            })
            ->values();
    }

    private function moduleStateClass(ModuleState $state): string
    {
        return match ($state) {
            ModuleState::Enabled => 'label label-success',
            ModuleState::Installed => 'label bg-aqua',
            ModuleState::Disabled => 'label label-warning',
            ModuleState::Error, ModuleState::Incompatible => 'label label-danger',
            default => 'label label-default',
        };
    }

    private function operationStatusClass(string $status): string
    {
        return match ($status) {
            'succeeded' => 'label label-success',
            'running' => 'label label-info',
            'queued' => 'label label-warning',
            'failed' => 'label label-danger',
            default => 'label label-default',
        };
    }

    private function formatTitle(string $value): string
    {
        return Str::of($value)
            ->replace('-', ' ')
            ->title()
            ->value();
    }
}
