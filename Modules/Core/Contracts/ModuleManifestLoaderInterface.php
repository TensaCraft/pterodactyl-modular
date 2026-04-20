<?php

namespace Modules\Core\Contracts;

interface ModuleManifestLoaderInterface
{
    /**
     * @return array<\Modules\Core\Data\ModuleManifestData>
     */
    public function discover(): array;

    public function findBySlug(string $slug): ?\Modules\Core\Data\ModuleManifestData;
}
