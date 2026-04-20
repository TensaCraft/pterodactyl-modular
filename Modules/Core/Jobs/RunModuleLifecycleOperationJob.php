<?php

namespace Modules\Core\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Core\Contracts\ModuleLifecycleServiceInterface;
use Modules\Core\Exceptions\ModuleOperationNotQueuedException;
use Modules\Core\Models\CoreModuleOperation;

class RunModuleLifecycleOperationJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(public int $operationId)
    {
        $this->queue = 'standard';
    }

    public function handle(ModuleLifecycleServiceInterface $service): void
    {
        $operation = CoreModuleOperation::query()->find($this->operationId);

        if ($operation === null || $operation->status->value !== 'queued') {
            return;
        }

        try {
            $service->runOperation($operation);
        } catch (ModuleOperationNotQueuedException) {
            return;
        }
    }

    public function uniqueId(): string
    {
        return (string) $this->operationId;
    }
}
