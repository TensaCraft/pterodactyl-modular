<?php

namespace Modules\Core\Contracts;

use Modules\Core\Data\ModuleManifestData;

interface ModuleManifestParserInterface
{
    public function parse(string $path): ModuleManifestData;

    public function assertCompatible(ModuleManifestData $manifest): void;
}
