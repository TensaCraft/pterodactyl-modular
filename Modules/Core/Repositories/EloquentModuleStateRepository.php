<?php

namespace Modules\Core\Repositories;

use Modules\Core\Data\ModuleManifestData;
use Modules\Core\Enums\ModuleState;
use Modules\Core\Contracts\ModuleStateRepositoryInterface;
use Modules\Core\Models\CoreModule;

class EloquentModuleStateRepository implements ModuleStateRepositoryInterface
{
    public function upsertFromManifest(ModuleManifestData $manifest, ModuleState $state): CoreModule
    {
        return CoreModule::query()->updateOrCreate(
            ['slug' => $manifest->slug],
            [
                'name' => $manifest->name,
                'version' => $manifest->version,
                'description' => $manifest->description,
                'priority' => $manifest->priority,
                'state' => $state,
                'is_protected' => $manifest->isProtected,
                'has_frontend' => $manifest->hasFrontend,
                'panel_constraint' => $manifest->panelConstraint,
                'core_api_constraint' => $manifest->coreApiConstraint,
                'manifest_path' => $manifest->path,
            ],
        );
    }

    public function findBySlug(string $slug): ?CoreModule
    {
        return CoreModule::query()->where('slug', $slug)->first();
    }

    public function allBySlugs(array $slugs): array
    {
        return CoreModule::query()
            ->whereIn('slug', $slugs)
            ->get()
            ->keyBy('slug')
            ->all();
    }

    public function deleteBySlug(string $slug): void
    {
        CoreModule::query()->where('slug', $slug)->delete();
    }
}
