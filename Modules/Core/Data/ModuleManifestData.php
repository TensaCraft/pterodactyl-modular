<?php

namespace Modules\Core\Data;

final readonly class ModuleManifestData
{
    public function __construct(
        public string $name,
        public string $slug,
        public string $version,
        public string $description,
        public string $panelConstraint,
        public string $coreApiConstraint,
        public int $priority,
        public bool $isProtected,
        public bool $hasFrontend,
        public array $providers,
        public array $dependencies,
        public array $frontend,
        public array $admin,
        public string $path,
        public array $conflicts = [],
        public bool $hasMigrations = false,
        public bool $hasSeeders = false,
        public bool $hasQueueJobs = false,
        public bool $hasSchedulerTasks = false,
        public array $permissions = [],
        public mixed $settingsSchema = null,
        public array $lifecycleHooks = [],
    ) {
    }
}
