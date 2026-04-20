<?php

namespace Pterodactyl\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Providers\CoreServiceProvider;
use Nwidart\Modules\LaravelModulesServiceProvider;

class ModulesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(config_path('modular.php'), 'modular');
        $this->ensureProtectedCoreIsEnabled();
        $this->app->register(LaravelModulesServiceProvider::class);

        if (class_exists(CoreServiceProvider::class)) {
            $this->app->register(CoreServiceProvider::class);
        }
    }

    public function boot(): void
    {
        foreach ([
            config('modular.paths.modules'),
            config('modular.paths.cache'),
            config('modular.paths.imports'),
            dirname((string) config('modular.registries.frontend')),
        ] as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }

            File::ensureDirectoryExists($path);
        }

        $this->synchronizeActivatorStatuses();
    }

    private function ensureProtectedCoreIsEnabled(): void
    {
        $statusesFile = $this->app['config']->get('modules.activators.file.statuses-file');
        $coreName = $this->app['config']->get('modular.core.name');

        if (! is_string($statusesFile) || $statusesFile === '' || ! is_string($coreName) || $coreName === '') {
            return;
        }

        File::ensureDirectoryExists(dirname($statusesFile));

        $statuses = [];

        if (File::exists($statusesFile)) {
            $decoded = json_decode(File::get($statusesFile), true);
            $statuses = is_array($decoded) ? $decoded : [];
        }

        if (($statuses[$coreName] ?? null) === true) {
            return;
        }

        $statuses[$coreName] = true;

        File::put($statusesFile, json_encode($statuses, JSON_PRETTY_PRINT));
    }

    private function synchronizeActivatorStatuses(): void
    {
        $statusesFile = $this->app['config']->get('modules.activators.file.statuses-file');
        $coreSlug = $this->app['config']->get('modular.core.slug');
        $moduleNames = $this->discoverModuleNames();

        if (! is_string($statusesFile) || $statusesFile === '' || ! is_array($moduleNames) || $moduleNames === []) {
            return;
        }

        File::ensureDirectoryExists(dirname($statusesFile));

        $activatorStatuses = [];

        if (File::exists($statusesFile)) {
            $decoded = json_decode(File::get($statusesFile), true);
            $activatorStatuses = is_array($decoded) ? $decoded : [];
        }

        $statuses = [];

        foreach ($moduleNames as $slug => $name) {
            $statuses[$name] = $slug === $coreSlug;
        }

        try {
            if (! Schema::hasTable('core_modules')) {
                File::put($statusesFile, json_encode($statuses, JSON_PRETTY_PRINT));

                return;
            }

            $this->ensureProtectedCoreStateIsPersisted($coreSlug);
            $this->synchronizePersistedStatesFromActivator($moduleNames, $activatorStatuses, $coreSlug);

            $states = DB::table('core_modules')
                ->select(['slug', 'state'])
                ->get();

            foreach ($states as $module) {
                $slug = $module->slug ?? null;

                if (! is_string($slug) || ! isset($moduleNames[$slug])) {
                    continue;
                }

                $statuses[$moduleNames[$slug]] = $slug === $coreSlug || ($module->state ?? null) === 'enabled';
            }
        } catch (\Throwable) {
            // Leave the activator file as-is if the database is unavailable during boot.
        }

        File::put($statusesFile, json_encode($statuses, JSON_PRETTY_PRINT));
    }

    /**
     * @param  array<string, string>  $moduleNames
     * @param  array<string, mixed>  $statuses
     */
    private function synchronizePersistedStatesFromActivator(array $moduleNames, array $statuses, mixed $coreSlug): void
    {
        foreach ($moduleNames as $slug => $name) {
            if (! is_string($name) || $name === '') {
                continue;
            }

            $existing = DB::table('core_modules')->where('slug', $slug)->first();

            if ($existing !== null) {
                continue;
            }

            $enabled = $slug === $coreSlug || ($statuses[$name] ?? null) === true;

            if ($enabled) {
                $this->persistManifestState($slug, 'enabled');
            }
        }
    }

    private function ensureProtectedCoreStateIsPersisted(mixed $coreSlug): void
    {
        if (! is_string($coreSlug) || $coreSlug === '') {
            return;
        }

        $this->persistManifestState($coreSlug, 'enabled');
    }

    /**
     * @return array<string, string>
     */
    private function discoverModuleNames(): array
    {
        $modulesPath = $this->app['config']->get('modular.paths.modules');

        if (! is_string($modulesPath) || $modulesPath === '' || ! File::isDirectory($modulesPath)) {
            return [];
        }

        $modules = [];

        foreach (File::directories($modulesPath) as $path) {
            $manifestPath = $path . DIRECTORY_SEPARATOR . 'module.json';

            if (! File::exists($manifestPath)) {
                continue;
            }

            $manifest = json_decode(File::get($manifestPath), true);

            if (! is_array($manifest)) {
                continue;
            }

            $slug = $manifest['slug'] ?? null;
            $name = $manifest['name'] ?? null;

            if (! is_string($slug) || $slug === '' || ! is_string($name) || $name === '') {
                continue;
            }

            $modules[$slug] = $name;
        }

        return $modules;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function discoverModuleManifest(string $targetSlug): ?array
    {
        $modulesPath = $this->app['config']->get('modular.paths.modules');

        if (! is_string($modulesPath) || $modulesPath === '' || ! File::isDirectory($modulesPath)) {
            return null;
        }

        foreach (File::directories($modulesPath) as $path) {
            $manifestPath = $path . DIRECTORY_SEPARATOR . 'module.json';

            if (! File::exists($manifestPath)) {
                continue;
            }

            $manifest = json_decode(File::get($manifestPath), true);

            if (! is_array($manifest) || ($manifest['slug'] ?? null) !== $targetSlug) {
                continue;
            }

            $name = $manifest['name'] ?? null;
            $version = $manifest['version'] ?? null;
            $description = $manifest['description'] ?? null;
            $panelConstraint = $manifest['panel_constraint'] ?? null;
            $coreApiConstraint = $manifest['core_api_constraint'] ?? null;

            if (! is_string($name) || $name === '' || ! is_string($version) || $version === '') {
                return null;
            }

            return [
                'slug' => $targetSlug,
                'name' => $name,
                'version' => $version,
                'description' => is_string($description) ? $description : null,
                'priority' => $manifest['priority'] ?? 0,
                'is_protected' => (bool) ($manifest['is_protected'] ?? false),
                'has_frontend' => (bool) ($manifest['has_frontend'] ?? false),
                'panel_constraint' => is_string($panelConstraint) ? $panelConstraint : null,
                'core_api_constraint' => is_string($coreApiConstraint) ? $coreApiConstraint : null,
                'manifest_path' => $path,
            ];
        }

        return null;
    }

    private function persistManifestState(string $slug, string $state): void
    {
        $manifest = $this->discoverModuleManifest($slug);

        if ($manifest === null) {
            return;
        }

        $now = now();
        $attributes = [
            'name' => $manifest['name'],
            'version' => $manifest['version'],
            'description' => $manifest['description'],
            'priority' => (int) ($manifest['priority'] ?? 0),
            'state' => $state,
            'is_protected' => (bool) ($manifest['is_protected'] ?? false),
            'has_frontend' => (bool) ($manifest['has_frontend'] ?? false),
            'panel_constraint' => $manifest['panel_constraint'],
            'core_api_constraint' => $manifest['core_api_constraint'],
            'manifest_path' => $manifest['manifest_path'],
            'updated_at' => $now,
        ];

        $query = DB::table('core_modules')->where('slug', $manifest['slug']);

        if ($query->exists()) {
            $query->update($attributes);

            return;
        }

        DB::table('core_modules')->insert(array_merge($attributes, [
            'slug' => $manifest['slug'],
            'created_at' => $now,
        ]));
    }
}
