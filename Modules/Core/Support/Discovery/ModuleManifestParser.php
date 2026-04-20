<?php

namespace Modules\Core\Support\Discovery;

use Composer\Semver\Semver;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use JsonException;
use Modules\Core\Contracts\ModuleManifestParserInterface;
use Modules\Core\Data\ModuleManifestData;

class ModuleManifestParser implements ModuleManifestParserInterface
{
    public function parse(string $path): ModuleManifestData
    {
        $manifestPath = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'module.json';

        if (! File::exists($manifestPath)) {
            throw new InvalidArgumentException("Module manifest could not be found at [{$manifestPath}].");
        }

        try {
            $manifest = json_decode(File::get($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(
                "Malformed module manifest JSON at [{$manifestPath}]: {$exception->getMessage()}",
                previous: $exception,
            );
        }

        if (! is_array($manifest)) {
            throw new InvalidArgumentException("Module manifest at [{$manifestPath}] must decode to a JSON object.");
        }

        $missingKeys = collect(['name', 'slug', 'version'])
            ->filter(fn (string $key) => ! is_string(Arr::get($manifest, $key)) || Arr::get($manifest, $key) === '')
            ->values()
            ->all();

        if ($missingKeys !== []) {
            throw new InvalidArgumentException(
                "Module manifest at [{$manifestPath}] is missing required key(s): " . implode(', ', $missingKeys)
            );
        }

        return new ModuleManifestData(
            name: Arr::get($manifest, 'name'),
            slug: Arr::get($manifest, 'slug'),
            version: Arr::get($manifest, 'version'),
            description: Arr::get($manifest, 'description', ''),
            panelConstraint: Arr::get($manifest, 'panel_constraint', '*'),
            coreApiConstraint: Arr::get($manifest, 'core_api_constraint', '*'),
            priority: (int) Arr::get($manifest, 'priority', 100),
            isProtected: (bool) Arr::get($manifest, 'is_protected', false),
            hasFrontend: (bool) Arr::get($manifest, 'has_frontend', false),
            providers: Arr::get($manifest, 'providers', []),
            dependencies: Arr::get($manifest, 'dependencies', []),
            frontend: Arr::get($manifest, 'frontend', []),
            admin: Arr::get($manifest, 'admin', []),
            path: $path,
            conflicts: Arr::get($manifest, 'conflicts', []),
            hasMigrations: (bool) Arr::get($manifest, 'has_migrations', false),
            hasSeeders: (bool) Arr::get($manifest, 'has_seeders', false),
            hasQueueJobs: (bool) Arr::get($manifest, 'has_queue_jobs', false),
            hasSchedulerTasks: (bool) Arr::get($manifest, 'has_scheduler_tasks', false),
            permissions: Arr::get($manifest, 'permissions', []),
            settingsSchema: Arr::get($manifest, 'settings_schema'),
            lifecycleHooks: Arr::get($manifest, 'lifecycle_hooks', []),
        );
    }

    public function assertCompatible(ModuleManifestData $manifest): void
    {
        $panelVersion = (string) config('app.version', '0.0.0');
        $coreApiVersion = (string) config('modular.core.api_version', '1.0.0');

        if ($manifest->panelConstraint !== '*' && ! Semver::satisfies($panelVersion, $manifest->panelConstraint)) {
            throw new InvalidArgumentException(sprintf(
                'Module [%s] requires panel version [%s], current version is [%s].',
                $manifest->slug,
                $manifest->panelConstraint,
                $panelVersion,
            ));
        }

        if ($manifest->coreApiConstraint !== '*' && ! Semver::satisfies($coreApiVersion, $manifest->coreApiConstraint)) {
            throw new InvalidArgumentException(sprintf(
                'Module [%s] requires Core API version [%s], current version is [%s].',
                $manifest->slug,
                $manifest->coreApiConstraint,
                $coreApiVersion,
            ));
        }
    }
}
