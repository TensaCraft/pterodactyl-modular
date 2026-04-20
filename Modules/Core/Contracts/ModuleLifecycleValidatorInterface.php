<?php

namespace Modules\Core\Contracts;

use Modules\Core\Data\ModuleManifestData;
use Modules\Core\Enums\ModuleAction;

interface ModuleLifecycleValidatorInterface
{
    public function validate(ModuleManifestData $manifest, ModuleAction $action): void;
}
