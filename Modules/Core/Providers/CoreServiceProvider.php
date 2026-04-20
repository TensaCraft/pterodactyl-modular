<?php

namespace Modules\Core\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Modules\Core\Console\RebuildModuleRegistryCommand;
use Modules\Core\Console\SyncModuleFrontendCommand;
use Modules\Core\Contracts\FrontendRegistryBuilderInterface;
use Modules\Core\Contracts\FrontendRegistryStoreInterface;
use Modules\Core\Contracts\ModuleImportServiceInterface;
use Modules\Core\Contracts\ModuleLifecycleServiceInterface;
use Modules\Core\Contracts\ModuleLifecycleValidatorInterface;
use Modules\Core\Contracts\ModuleManifestLoaderInterface;
use Modules\Core\Contracts\ModuleManifestParserInterface;
use Modules\Core\Contracts\ModuleRuntimeExecutorInterface;
use Modules\Core\Contracts\ModuleStateRepositoryInterface;
use Modules\Core\Repositories\EloquentModuleStateRepository;
use Modules\Core\Services\ModuleImportService;
use Modules\Core\Support\Registry\FilesystemFrontendRegistryStore;
use Modules\Core\Support\Registry\ModuleFrontendRegistryBuilder;
use Modules\Core\Support\Navigation\ModuleAdminNavigationRegistry;
use Modules\Core\Support\Discovery\ModuleManifestLoader;
use Modules\Core\Support\Discovery\ModuleManifestParser;
use Modules\Core\Services\FrontendBuildPipelineService;
use Modules\Core\Services\ModuleLifecycleService;
use Modules\Core\Services\ModuleLifecycleValidator;
use Modules\Core\Services\ModuleRuntimeExecutor;
use Symfony\Component\Process\Process;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FrontendRegistryStoreInterface::class, function () {
            return new FilesystemFrontendRegistryStore(config('modular.registries.frontend'));
        });
        $this->app->singleton(ModuleManifestParser::class, ModuleManifestParser::class);
        $this->app->singleton(ModuleManifestParserInterface::class, function ($app) {
            return $app->make(ModuleManifestParser::class);
        });
        $this->app->singleton(ModuleManifestLoader::class, ModuleManifestLoader::class);
        $this->app->singleton(ModuleManifestLoaderInterface::class, function ($app) {
            return $app->make(ModuleManifestLoader::class);
        });
        $this->app->singleton(ModuleFrontendRegistryBuilder::class, ModuleFrontendRegistryBuilder::class);
        $this->app->singleton(FrontendRegistryBuilderInterface::class, function ($app) {
            return $app->make(ModuleFrontendRegistryBuilder::class);
        });
        $this->app->singleton(ModuleAdminNavigationRegistry::class, ModuleAdminNavigationRegistry::class);
        $this->app->singleton(FrontendBuildPipelineService::class, function ($app) {
            return new FrontendBuildPipelineService(
                $app->make(FrontendRegistryBuilderInterface::class),
                $app->make(FrontendRegistryStoreInterface::class),
                static function (bool $production): void {
                    $script = $production ? 'build:production' : 'build';
                    $process = new Process(['yarn', 'run', $script], base_path());
                    $process->setTimeout(10 * 60);
                    $process->mustRun();
                },
            );
        });
        $this->app->bind(ModuleStateRepositoryInterface::class, EloquentModuleStateRepository::class);
        $this->app->singleton(ModuleLifecycleValidatorInterface::class, ModuleLifecycleValidator::class);
        $this->app->singleton(ModuleRuntimeExecutorInterface::class, ModuleRuntimeExecutor::class);
        $this->app->singleton(ModuleLifecycleServiceInterface::class, ModuleLifecycleService::class);
        $this->app->singleton(ModuleImportServiceInterface::class, ModuleImportService::class);
    }

    public function boot(): void
    {
        $this->commands([
            RebuildModuleRegistryCommand::class,
            SyncModuleFrontendCommand::class,
        ]);

        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'core-module');
        $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'core-module');
        View::composer('layouts.admin', function ($view): void {
            $view->with('modularAdminNavigation', $this->app->make(ModuleAdminNavigationRegistry::class)->build());
        });

        $routesPath = __DIR__ . '/../Routes/admin.php';

        if (is_file($routesPath)) {
            $this->loadRoutesFrom($routesPath);
        }
    }
}
