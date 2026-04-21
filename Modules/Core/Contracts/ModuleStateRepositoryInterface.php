<?php

namespace Modules\Core\Contracts;

use Modules\Core\Data\ModuleManifestData;
use Modules\Core\Enums\ModuleState;
use Modules\Core\Models\CoreModule;

interface ModuleStateRepositoryInterface
{
    public function upsertFromManifest(ModuleManifestData $manifest, ModuleState $state): CoreModule;

    public function findBySlug(string $slug): ?CoreModule;

    /**
     * @param array<int, string> $slugs
     * @return array<string, CoreModule>
     */
    public function allBySlugs(array $slugs): array;

    public function deleteBySlug(string $slug): void;
}
