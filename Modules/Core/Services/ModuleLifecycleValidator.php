<?php

namespace Modules\Core\Services;

use InvalidArgumentException;
use Modules\Core\Contracts\ModuleManifestLoaderInterface;
use Modules\Core\Contracts\ModuleLifecycleValidatorInterface;
use Modules\Core\Contracts\ModuleStateRepositoryInterface;
use Modules\Core\Data\ModuleManifestData;
use Modules\Core\Enums\ModuleAction;
use Modules\Core\Enums\ModuleState;
use Modules\Core\Exceptions\ModuleConflictException;
use Modules\Core\Exceptions\ModuleDependencyViolationException;

class ModuleLifecycleValidator implements ModuleLifecycleValidatorInterface
{
    public function __construct(
        private readonly ModuleManifestLoaderInterface $manifests,
        private readonly ModuleStateRepositoryInterface $states,
    ) {
    }

    public function validate(ModuleManifestData $manifest, ModuleAction $action): void
    {
        if ($action === ModuleAction::Disable) {
            $this->assertProtectedCoreCanBeDisabled($manifest);
            $this->assertNoEnabledDependents($manifest);
        }

        if ($action === ModuleAction::Delete) {
            $this->assertProtectedCoreCanBeDisabled($manifest);
            $this->assertNoEnabledDependents($manifest);
        }

        if ($action === ModuleAction::Enable) {
            $this->assertDependenciesAreEnabled($manifest);
        }

        if ($action === ModuleAction::Update) {
            $this->assertModuleCanBeUpdated($manifest);
        }

        if (in_array($action, [ModuleAction::Enable, ModuleAction::Install], true)) {
            $this->assertNoDeclaredConflictsAreActive($manifest);
        }
    }

    private function assertProtectedCoreCanBeDisabled(ModuleManifestData $manifest): void
    {
        if ($manifest->slug === config('modular.core.slug') && $manifest->isProtected) {
            throw ModuleConflictException::protectedCoreCannotBeDisabled();
        }
    }

    private function assertDependenciesAreEnabled(ModuleManifestData $manifest): void
    {
        foreach ($manifest->dependencies as $dependency) {
            $module = $this->states->findBySlug($dependency);

            if ($module === null || $module->state !== ModuleState::Enabled) {
                throw ModuleDependencyViolationException::forMissingDependency($manifest->slug, $dependency);
            }
        }
    }

    private function assertNoEnabledDependents(ModuleManifestData $manifest): void
    {
        $dependents = collect($this->manifests->discover())
            ->filter(fn (ModuleManifestData $candidate): bool => in_array($manifest->slug, $candidate->dependencies, true))
            ->values();

        if ($dependents->isEmpty()) {
            return;
        }

        $states = $this->states->allBySlugs($dependents->pluck('slug')->all());

        foreach ($dependents as $dependent) {
            $module = $states[$dependent->slug] ?? null;

            if ($module !== null && $module->state === ModuleState::Enabled) {
                throw ModuleDependencyViolationException::forEnabledDependent($manifest->slug, $dependent->slug);
            }
        }
    }

    private function assertNoDeclaredConflictsAreActive(ModuleManifestData $manifest): void
    {
        foreach ($manifest->conflicts as $conflict) {
            $module = $this->states->findBySlug($conflict);

            if ($module !== null && $module->state !== ModuleState::Disabled && $module->state !== ModuleState::Discovered) {
                throw ModuleConflictException::forDeclaredConflict($manifest->slug, $conflict);
            }
        }
    }

    private function assertModuleCanBeUpdated(ModuleManifestData $manifest): void
    {
        $module = $this->states->findBySlug($manifest->slug);

        if ($module === null || in_array($module->state, [ModuleState::Discovered, ModuleState::Incompatible], true)) {
            throw new InvalidArgumentException(sprintf(
                'Module [%s] must be installed before it can be updated.',
                $manifest->slug,
            ));
        }
    }
}
