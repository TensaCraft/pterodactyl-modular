<?php

namespace Modules\Core\Contracts;

use Modules\Core\Data\ModuleManifestData;

interface ModuleRuntimeExecutorInterface
{
    public function enable(ModuleManifestData $manifest): void;

    public function disable(ModuleManifestData $manifest): void;

    public function install(ModuleManifestData $manifest): void;

    public function update(ModuleManifestData $manifest): void;
}
