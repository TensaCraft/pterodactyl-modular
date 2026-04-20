<?php

namespace Modules\Core\Contracts;

use Modules\Core\Data\ModuleLifecycleResult;
use Modules\Core\Enums\ModuleAction;

interface ModuleLifecycleServiceInterface
{
    public function queue(string $slug, ModuleAction $action): \Modules\Core\Models\CoreModuleOperation;

    public function createQueuedOperation(string $slug, ModuleAction $action): \Modules\Core\Models\CoreModuleOperation;

    public function runOperation(\Modules\Core\Models\CoreModuleOperation $operation): ModuleLifecycleResult;

    public function latestOperationFor(string $slug): ?\Modules\Core\Models\CoreModuleOperation;

    public function execute(string $slug, ModuleAction $action): ModuleLifecycleResult;
}
