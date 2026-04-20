<?php

namespace Modules\Core\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Core\Contracts\ModuleImportServiceInterface;
use Modules\Core\Enums\ModuleAction;
use Modules\Core\Models\CoreModuleOperation;

class RunModuleImportOperationJob implements ShouldQueue, ShouldBeUnique
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

    public function handle(ModuleImportServiceInterface $service): void
    {
        $operation = CoreModuleOperation::query()->find($this->operationId);

        if ($operation === null || $operation->status->value !== 'queued') {
            return;
        }

        if (! in_array($operation->operation, [ModuleAction::ImportArchive, ModuleAction::ImportGit], true)) {
            return;
        }

        $service->runOperation($operation);
    }

    public function uniqueId(): string
    {
        return (string) $this->operationId;
    }
}
