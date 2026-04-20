<?php

namespace Modules\Core\Contracts;

use Illuminate\Support\Collection;
use Modules\Core\Data\ModuleImportResult;
use Modules\Core\Models\CoreModuleOperation;

interface ModuleImportServiceInterface
{
    public function queueArchive(string $archivePath, bool $install = false, bool $enable = false, bool $replaceExisting = false): CoreModuleOperation;

    public function createQueuedArchiveOperation(string $archivePath, bool $install = false, bool $enable = false, bool $replaceExisting = false): CoreModuleOperation;

    public function queueGit(string $repository, ?string $reference = null, bool $install = false, bool $enable = false, bool $replaceExisting = false): CoreModuleOperation;

    public function createQueuedGitOperation(string $repository, ?string $reference = null, bool $install = false, bool $enable = false, bool $replaceExisting = false): CoreModuleOperation;

    public function runOperation(CoreModuleOperation $operation): ModuleImportResult;

    public function latestOperations(int $limit = 20): Collection;

    public function clearOperations(): int;
}
