<?php

namespace Modules\Core\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Core\Contracts\ModuleImportServiceInterface;
use Modules\Core\Contracts\ModuleLifecycleServiceInterface;
use Modules\Core\Contracts\ModuleManifestParserInterface;
use Modules\Core\Contracts\ModuleStateRepositoryInterface;
use Modules\Core\Data\ModuleImportResult;
use Modules\Core\Data\ModuleManifestData;
use Modules\Core\Enums\ModuleAction;
use Modules\Core\Enums\ModuleActionStatus;
use Modules\Core\Enums\ModuleState;
use Modules\Core\Jobs\RunModuleImportOperationJob;
use Modules\Core\Models\CoreModuleOperation;
use Symfony\Component\Process\Process;
use ZipArchive;

class ModuleImportService implements ModuleImportServiceInterface
{
    public function __construct(
        private readonly ModuleManifestParserInterface $parser,
        private readonly ModuleStateRepositoryInterface $states,
        private readonly ModuleLifecycleServiceInterface $lifecycle,
        private readonly Dispatcher $dispatcher,
        private readonly Filesystem $filesystem,
    ) {
    }

    public function queueArchive(string $archivePath, bool $install = false, bool $enable = false, bool $replaceExisting = false): CoreModuleOperation
    {
        $operation = $this->createQueuedArchiveOperation($archivePath, $install, $enable, $replaceExisting);

        return $this->dispatchOperation($operation);
    }

    public function createQueuedArchiveOperation(string $archivePath, bool $install = false, bool $enable = false, bool $replaceExisting = false): CoreModuleOperation
    {
        return $this->createQueuedOperation(
            ModuleAction::ImportArchive,
            [
                'source' => [
                    'type' => 'archive',
                    'path' => $archivePath,
                ],
            ],
            $install,
            $enable,
            $replaceExisting,
        );
    }

    public function queueGit(string $repository, ?string $reference = null, bool $install = false, bool $enable = false, bool $replaceExisting = false): CoreModuleOperation
    {
        $operation = $this->createQueuedGitOperation($repository, $reference, $install, $enable, $replaceExisting);

        return $this->dispatchOperation($operation);
    }

    public function createQueuedGitOperation(string $repository, ?string $reference = null, bool $install = false, bool $enable = false, bool $replaceExisting = false): CoreModuleOperation
    {
        return $this->createQueuedOperation(
            ModuleAction::ImportGit,
            [
                'source' => [
                    'type' => 'git',
                    'repository' => $repository,
                    'reference' => $reference,
                ],
            ],
            $install,
            $enable,
            $replaceExisting,
        );
    }

    public function runOperation(CoreModuleOperation $operation): ModuleImportResult
    {
        if (! $this->claimOperation($operation)) {
            throw new InvalidArgumentException(sprintf('Module import operation [%d] is not queued.', $operation->id));
        }

        $workspace = $this->makeWorkspace();
        $targetPath = null;
        $backupPath = null;
        $persisted = false;
        $existingModule = null;
        $previousState = null;

        try {
            $moduleRoot = match ($operation->operation) {
                ModuleAction::ImportArchive => $this->extractArchive($operation, $workspace),
                ModuleAction::ImportGit => $this->cloneRepository($operation, $workspace),
                default => throw new InvalidArgumentException(sprintf(
                    'Unsupported module import operation [%s].',
                    $operation->operation->value,
                )),
            };

            $this->updatePayload($operation, [
                'progress' => 35,
                'steps' => $this->completeStep($operation, 'fetch'),
            ]);

            $manifest = $this->parser->parse($moduleRoot);
            $this->assertCompatibleManifest($manifest);
            $existingModule = $this->states->findBySlug($manifest->slug);
            $previousState = $existingModule?->state;
            $replaceExisting = (bool) ($operation->payload['options']['replace_existing'] ?? false);

            $targetPath = $this->resolveTargetPath($moduleRoot, $manifest, $replaceExisting ? $existingModule : null, $replaceExisting);
            $this->assertTargetIsImportable($targetPath, $manifest, $replaceExisting, $existingModule);

            if ($replaceExisting && $existingModule !== null && $this->filesystem->isDirectory($targetPath)) {
                $backupPath = $this->backupExistingModule($targetPath, $workspace);
            }

            $this->filesystem->copyDirectory($moduleRoot, $targetPath);
            $manifest = $this->parser->parse($targetPath);
            $this->syncModuleComposerAutoload($targetPath, $manifest, $operation);
            $module = $this->states->upsertFromManifest($manifest, $previousState ?? ModuleState::Discovered);
            $persisted = true;

            $this->updateMetadata($operation, $manifest);
            $this->updatePayload($operation, [
                'progress' => 70,
                'steps' => $this->completeStep($operation, 'register'),
            ]);

            $followUpOperationIds = $this->runFollowUpOperations($manifest, $operation, $previousState);
            $module = $this->states->findBySlug($manifest->slug) ?? $module;

            $this->finishOperation($operation, ModuleActionStatus::Succeeded, [
                'progress' => 100,
                'steps' => $this->completeAllSteps($operation),
                'follow_up_operation_ids' => $followUpOperationIds,
            ]);

            return new ModuleImportResult(
                module: $module->refresh(),
                operation: $operation->refresh(),
            );
        } catch (\Throwable $exception) {
            if (is_string($backupPath) && is_string($targetPath)) {
                $this->restoreBackedUpModule($backupPath, $targetPath, $existingModule);
            } elseif (! $persisted && is_string($targetPath) && $this->filesystem->isDirectory($targetPath)) {
                $this->filesystem->deleteDirectory($targetPath);
            }

            $this->finishOperation($operation, ModuleActionStatus::Failed, [
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        } finally {
            $this->cleanup($operation, $workspace);
        }
    }

    public function latestOperations(int $limit = 20): Collection
    {
        return CoreModuleOperation::query()
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function clearOperations(): int
    {
        return CoreModuleOperation::query()->delete();
    }

    private function createQueuedOperation(ModuleAction $action, array $payload, bool $install, bool $enable, bool $replaceExisting): CoreModuleOperation
    {
        $options = [
            'install' => $install || $enable,
            'enable' => $enable,
            'replace_existing' => $replaceExisting,
        ];

        $operation = new CoreModuleOperation();
        $operation->module_slug = null;
        $operation->operation = $action;
        $operation->status = ModuleActionStatus::Queued;
        $operation->payload = array_merge([
            'action' => $action->value,
            'progress' => 0,
            'steps' => $this->buildSteps($action, $options),
            'options' => $options,
        ], $payload);
        $operation->save();

        return $operation;
    }

    private function buildSteps(ModuleAction $action, array $options): array
    {
        $steps = [
            $this->step('fetch', $action === ModuleAction::ImportArchive ? 'Extract module archive' : 'Clone module repository'),
            $this->step('register', 'Validate manifest and register module'),
            $this->step('autoload', 'Sync module Composer autoload metadata'),
        ];

        if (($options['install'] ?? false) === true) {
            $steps[] = $this->step('install', 'Run module install lifecycle');
        }

        if (($options['enable'] ?? false) === true) {
            $steps[] = $this->step('enable', 'Run module enable lifecycle');
        }

        return $steps;
    }

    private function step(string $name, string $label): array
    {
        return [
            'name' => $name,
            'label' => $label,
            'status' => 'pending',
            'progress' => 0,
        ];
    }

    private function dispatchOperation(CoreModuleOperation $operation): CoreModuleOperation
    {
        try {
            $this->dispatcher->dispatch(new RunModuleImportOperationJob($operation->id));
        } catch (\Throwable $exception) {
            $this->finishOperation($operation, ModuleActionStatus::Failed, [
                'error' => sprintf('Failed to dispatch module import job: %s', $exception->getMessage()),
            ]);

            throw $exception;
        }

        return $operation;
    }

    private function claimOperation(CoreModuleOperation $operation): bool
    {
        $payload = $operation->payload ?? [];
        unset($payload['error']);

        $claimed = CoreModuleOperation::query()
            ->whereKey($operation->id)
            ->where('status', ModuleActionStatus::Queued->value)
            ->update([
                'status' => ModuleActionStatus::Running->value,
                'payload' => $payload,
                'started_at' => CarbonImmutable::now(),
                'finished_at' => null,
                'updated_at' => CarbonImmutable::now(),
            ]);

        if ($claimed === 1) {
            $operation->refresh();
        }

        return $claimed === 1;
    }

    private function finishOperation(CoreModuleOperation $operation, ModuleActionStatus $status, array $payload = []): void
    {
        $operation->forceFill([
            'status' => $status,
            'payload' => array_merge($operation->payload ?? [], $payload),
            'finished_at' => CarbonImmutable::now(),
        ])->save();
    }

    private function updatePayload(CoreModuleOperation $operation, array $payload): void
    {
        $operation->forceFill([
            'payload' => array_merge($operation->payload ?? [], $payload),
        ])->save();
    }

    private function completeStep(CoreModuleOperation $operation, string $stepName): array
    {
        return array_map(static function (array $step) use ($stepName): array {
            if (($step['name'] ?? null) !== $stepName) {
                return $step;
            }

            return array_merge($step, [
                'status' => 'completed',
                'progress' => 100,
            ]);
        }, $operation->payload['steps'] ?? []);
    }

    private function completeAllSteps(CoreModuleOperation $operation): array
    {
        return array_map(static fn (array $step): array => array_merge($step, [
            'status' => 'completed',
            'progress' => 100,
        ]), $operation->payload['steps'] ?? []);
    }

    private function extractArchive(CoreModuleOperation $operation, string $workspace): string
    {
        $archivePath = $operation->payload['source']['path'] ?? null;

        if (! is_string($archivePath) || $archivePath === '' || ! $this->filesystem->exists($archivePath)) {
            throw new InvalidArgumentException('Module archive could not be found.');
        }

        $zip = new ZipArchive();
        $opened = $zip->open($archivePath);

        if ($opened !== true) {
            throw new InvalidArgumentException(sprintf('Module archive [%s] could not be opened.', $archivePath));
        }

        $extractRoot = $workspace . DIRECTORY_SEPARATOR . 'archive';
        $this->filesystem->ensureDirectoryExists($extractRoot);

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entryName = str_replace('\\', '/', (string) $zip->getNameIndex($index));
            $normalizedEntry = trim($entryName, '/');

            if ($normalizedEntry === '') {
                continue;
            }

            if (str_starts_with($normalizedEntry, '..') || str_contains($normalizedEntry, '/../')) {
                throw new InvalidArgumentException(sprintf('Archive entry [%s] is not allowed.', $entryName));
            }

            $destination = $extractRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalizedEntry);

            if (str_ends_with($entryName, '/')) {
                $this->filesystem->ensureDirectoryExists($destination);
                continue;
            }

            $this->filesystem->ensureDirectoryExists(dirname($destination));
            $stream = $zip->getStream($entryName);

            if (! is_resource($stream)) {
                throw new InvalidArgumentException(sprintf('Archive entry [%s] could not be read.', $entryName));
            }

            $written = file_put_contents($destination, stream_get_contents($stream));
            fclose($stream);

            if ($written === false) {
                throw new InvalidArgumentException(sprintf('Archive entry [%s] could not be written.', $entryName));
            }
        }

        $zip->close();

        return $this->resolveImportedModuleRoot($extractRoot);
    }

    private function cloneRepository(CoreModuleOperation $operation, string $workspace): string
    {
        $repository = $operation->payload['source']['repository'] ?? null;
        $reference = $operation->payload['source']['reference'] ?? null;

        if (! is_string($repository) || $repository === '') {
            throw new InvalidArgumentException('Module repository must be provided.');
        }

        $target = $workspace . DIRECTORY_SEPARATOR . 'repository';
        $cloneCommand = ['git', 'clone'];

        if (! is_string($reference) || $reference === '' || $reference === 'HEAD') {
            $cloneCommand[] = '--depth=1';
        }

        $clone = new Process([...$cloneCommand, $repository, $target], base_path());
        $clone->setTimeout(10 * 60);
        $clone->mustRun();

        if (is_string($reference) && $reference !== '' && $reference !== 'HEAD') {
            $checkout = new Process(['git', 'checkout', $reference], $target);
            $checkout->setTimeout(10 * 60);
            $checkout->mustRun();
        }

        return $this->resolveImportedModuleRoot($target);
    }

    private function resolveImportedModuleRoot(string $root): string
    {
        $manifestFiles = collect($this->filesystem->allFiles($root))
            ->filter(static fn (\SplFileInfo $file): bool => $file->getFilename() === 'module.json')
            ->values();

        if ($manifestFiles->count() !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Imported source must contain exactly one module.json file, found [%d].',
                $manifestFiles->count(),
            ));
        }

        return $manifestFiles->first()->getPath();
    }

    private function assertCompatibleManifest(ModuleManifestData $manifest): void
    {
        $this->parser->assertCompatible($manifest);
    }

    private function resolveTargetPath(
        string $moduleRoot,
        ModuleManifestData $manifest,
        ?\Modules\Core\Models\CoreModule $existingModule = null,
        bool $replaceExisting = false,
    ): string
    {
        if ($replaceExisting && $existingModule !== null && $existingModule->manifest_path !== '') {
            return rtrim($existingModule->manifest_path, DIRECTORY_SEPARATOR);
        }

        $modulesRoot = rtrim((string) config('modular.paths.modules'), DIRECTORY_SEPARATOR);
        $directoryName = basename($moduleRoot);

        if (
            $directoryName === ''
            || $directoryName === '.'
            || $directoryName === DIRECTORY_SEPARATOR
            || in_array($directoryName, ['archive', 'repository'], true)
        ) {
            $directoryName = preg_replace('/[^A-Za-z0-9]+/', '', $manifest->name) ?: ucfirst($manifest->slug);
        }

        return $modulesRoot . DIRECTORY_SEPARATOR . $directoryName;
    }

    private function assertTargetIsImportable(
        string $targetPath,
        ModuleManifestData $manifest,
        bool $replaceExisting,
        ?\Modules\Core\Models\CoreModule $existingModule,
    ): void
    {
        if ($replaceExisting) {
            if ($manifest->slug === config('modular.core.slug') || ($existingModule?->is_protected ?? false)) {
                throw new InvalidArgumentException('The protected core module cannot be replaced.');
            }

            if ($existingModule === null) {
                if ($this->filesystem->exists($targetPath)) {
                    throw new InvalidArgumentException(sprintf('A module already exists at [%s].', $targetPath));
                }

                return;
            }

            $modulesRoot = rtrim((string) config('modular.paths.modules'), DIRECTORY_SEPARATOR);
            $normalizedModulesRoot = str_replace('\\', '/', $modulesRoot);
            $normalizedTargetPath = str_replace('\\', '/', $targetPath);

            if (! str_starts_with($normalizedTargetPath, $normalizedModulesRoot . '/')) {
                throw new InvalidArgumentException(sprintf(
                    'Managed module replacement path [%s] must stay inside [%s].',
                    $targetPath,
                    $modulesRoot,
                ));
            }

            return;
        }

        if ($this->filesystem->exists($targetPath)) {
            throw new InvalidArgumentException(sprintf('A module already exists at [%s].', $targetPath));
        }

        if ($existingModule !== null) {
            throw new InvalidArgumentException(sprintf('A module with slug [%s] already exists.', $manifest->slug));
        }
    }

    private function backupExistingModule(string $targetPath, string $workspace): string
    {
        $backupPath = $workspace . DIRECTORY_SEPARATOR . 'backup-' . basename($targetPath);

        if ($this->filesystem->isDirectory($backupPath)) {
            $this->filesystem->deleteDirectory($backupPath);
        }

        if (! $this->filesystem->moveDirectory($targetPath, $backupPath, true)) {
            throw new InvalidArgumentException(sprintf('Module path [%s] could not be prepared for replacement.', $targetPath));
        }

        return $backupPath;
    }

    private function restoreBackedUpModule(
        string $backupPath,
        string $targetPath,
        ?\Modules\Core\Models\CoreModule $existingModule,
    ): void {
        if ($this->filesystem->isDirectory($targetPath)) {
            $this->filesystem->deleteDirectory($targetPath);
        }

        $this->filesystem->moveDirectory($backupPath, $targetPath, true);

        if ($existingModule !== null) {
            $existingModule->save();
        }
    }

    private function updateMetadata(CoreModuleOperation $operation, ModuleManifestData $manifest): void
    {
        $operation->forceFill([
            'module_slug' => $manifest->slug,
            'payload' => array_merge($operation->payload ?? [], [
                'module' => [
                    'name' => $manifest->name,
                    'slug' => $manifest->slug,
                    'version' => $manifest->version,
                ],
            ]),
        ])->save();
    }

    private function syncModuleComposerAutoload(string $targetPath, ModuleManifestData $manifest, CoreModuleOperation $operation): void
    {
        $composerPath = $targetPath . DIRECTORY_SEPARATOR . 'composer.json';

        if (! $this->filesystem->exists($composerPath)) {
            $this->filesystem->put($composerPath, json_encode(
                $this->buildGeneratedComposerManifest($manifest, $targetPath),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            ) . PHP_EOL);
        }

        if (! $this->isManagedModulesPath($targetPath)) {
            $this->updatePayload($operation, [
                'progress' => 55,
                'steps' => $this->completeStep($operation, 'autoload'),
            ]);

            return;
        }

        $composer = json_decode($this->filesystem->get($composerPath), true);

        if (! is_array($composer)) {
            throw new InvalidArgumentException(sprintf('Composer manifest at [%s] must decode to a JSON object.', $composerPath));
        }

        $requiredPackages = $this->resolveComposerPackagesToInstall($composer);

        if ($requiredPackages === []) {
            $this->runComposerProcess(['dump-autoload', '--no-interaction', '--no-scripts']);
        } else {
            $command = ['update', '--with-all-dependencies', '--prefer-dist', '--no-interaction'];

            if (! app()->environment(['local', 'testing'])) {
                $command[] = '--no-dev';
            }

            $this->runComposerProcess([...$command, ...$requiredPackages]);
        }

        $this->updatePayload($operation, [
            'progress' => 55,
            'steps' => $this->completeStep($operation, 'autoload'),
        ]);
    }

    private function buildGeneratedComposerManifest(ModuleManifestData $manifest, string $targetPath): array
    {
        $autoloadPath = '';

        if ($this->filesystem->isDirectory($targetPath . DIRECTORY_SEPARATOR . 'app')) {
            $autoloadPath = 'app/';
        } elseif ($this->filesystem->isDirectory($targetPath . DIRECTORY_SEPARATOR . 'src')) {
            $autoloadPath = 'src/';
        }

        return [
            'name' => sprintf('tensacraft/module-%s', $manifest->slug),
            'type' => 'library',
            'autoload' => [
                'psr-4' => [
                    sprintf('Modules\\%s\\', Str::studly($manifest->slug)) => $autoloadPath,
                ],
            ],
        ];
    }

    private function isManagedModulesPath(string $targetPath): bool
    {
        $modulesRoot = str_replace('\\', '/', rtrim((string) config('modular.paths.modules'), DIRECTORY_SEPARATOR));
        $targetPath = str_replace('\\', '/', $targetPath);

        return str_starts_with($targetPath, $modulesRoot . '/')
            && $modulesRoot === str_replace('\\', '/', base_path('Modules'));
    }

    private function resolveComposerPackagesToInstall(array $composer): array
    {
        $requires = $composer['require'] ?? [];

        if (! is_array($requires)) {
            return [];
        }

        return array_values(array_filter(array_keys($requires), static function (string $package): bool {
            if ($package === 'php' || str_starts_with($package, 'ext-')) {
                return false;
            }

            return ! in_array($package, [
                'laravel/framework',
                'nwidart/laravel-modules',
                'pterodactyl/panel',
            ], true);
        }));
    }

    private function runComposerProcess(array $arguments): void
    {
        $composerBinary = strtoupper(substr(PHP_OS_FAMILY, 0, 3)) === 'WIN' ? 'composer.bat' : 'composer';
        $process = new Process([$composerBinary, ...$arguments], base_path());
        $process->setTimeout(15 * 60);
        $process->mustRun();
    }

    private function runFollowUpOperations(
        ModuleManifestData $manifest,
        CoreModuleOperation $operation,
        ?ModuleState $previousState,
    ): array
    {
        $options = $operation->payload['options'] ?? [];
        $operationIds = [];
        $installAfterImport = (bool) ($options['install'] ?? false);
        $enableAfterImport = (bool) ($options['enable'] ?? false);

        if (($options['replace_existing'] ?? false) === true && $previousState !== null) {
            $installAfterImport = true;
            $enableAfterImport = $previousState === ModuleState::Enabled || $enableAfterImport;
        }

        if ($installAfterImport) {
            $install = $this->lifecycle->execute($manifest->slug, ModuleAction::Install);
            $operationIds[] = $install->operation->id;
            $this->updatePayload($operation, [
                'progress' => 85,
                'steps' => $this->completeStep($operation, 'install'),
            ]);
        }

        if ($enableAfterImport) {
            $enable = $this->lifecycle->execute($manifest->slug, ModuleAction::Enable);
            $operationIds[] = $enable->operation->id;
            $this->updatePayload($operation, [
                'progress' => 95,
                'steps' => $this->completeStep($operation, 'enable'),
            ]);
        }

        return $operationIds;
    }

    private function makeWorkspace(): string
    {
        $root = rtrim((string) config('modular.paths.imports', storage_path('app/modular/imports')), DIRECTORY_SEPARATOR);
        $path = $root . DIRECTORY_SEPARATOR . 'workspace-' . uniqid('', true);
        $this->filesystem->ensureDirectoryExists($path);

        return $path;
    }

    private function cleanup(CoreModuleOperation $operation, string $workspace): void
    {
        if ($this->filesystem->isDirectory($workspace)) {
            $this->filesystem->deleteDirectory($workspace);
        }

        if ($operation->operation === ModuleAction::ImportArchive) {
            $archivePath = $operation->payload['source']['path'] ?? null;

            if (is_string($archivePath) && $archivePath !== '' && $this->filesystem->exists($archivePath)) {
                $this->filesystem->delete($archivePath);
            }
        }
    }
}
