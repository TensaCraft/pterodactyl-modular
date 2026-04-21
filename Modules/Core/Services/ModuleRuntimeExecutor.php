<?php

namespace Modules\Core\Services;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Artisan;
use Modules\Core\Contracts\ModuleRuntimeExecutorInterface;
use Modules\Core\Data\ModuleManifestData;
use Nwidart\Modules\Contracts\ActivatorInterface;
use Nwidart\Modules\Contracts\RepositoryInterface as ModuleRepositoryInterface;

class ModuleRuntimeExecutor implements ModuleRuntimeExecutorInterface
{
    public function __construct(private readonly Container $container)
    {
    }

    public function enable(ModuleManifestData $manifest): void
    {
        $this->resolveModules()->enable($manifest->name);
    }

    public function disable(ModuleManifestData $manifest): void
    {
        $this->resolveModules()->disable($manifest->name);
    }

    public function delete(ModuleManifestData $manifest): void
    {
        $modules = $this->resolveModules();
        $modules->delete($manifest->name);

        if (method_exists($modules, 'resetModules')) {
            $modules->resetModules();
        }

        $this->container->forgetInstance(ActivatorInterface::class);
        $this->container->forgetInstance(ModuleRepositoryInterface::class);
        $this->container->forgetInstance('modules');
    }

    public function install(ModuleManifestData $manifest): void
    {
        $migrationPath = $manifest->path . DIRECTORY_SEPARATOR . 'Database' . DIRECTORY_SEPARATOR . 'Migrations';

        if (! is_dir($migrationPath)) {
            return;
        }

        Artisan::call('migrate', [
            '--path' => [$migrationPath],
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    public function update(ModuleManifestData $manifest): void
    {
        $this->install($manifest);
    }

    private function resolveModules(): ModuleRepositoryInterface
    {
        $this->container->forgetInstance(ActivatorInterface::class);
        $this->container->forgetInstance(ModuleRepositoryInterface::class);
        $this->container->forgetInstance('modules');

        $modules = $this->container->make(ModuleRepositoryInterface::class);

        if (method_exists($modules, 'resetModules')) {
            $modules->resetModules();
        }

        return $modules;
    }
}
