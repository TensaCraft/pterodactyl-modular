<?php

namespace Modules\Core\Data;

use Modules\Core\Models\CoreModule;
use Modules\Core\Models\CoreModuleOperation;

final readonly class ModuleLifecycleResult
{
    public function __construct(
        public CoreModule $module,
        public CoreModuleOperation $operation,
    ) {
    }
}
