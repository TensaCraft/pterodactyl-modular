<?php

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Modules\Core\Services\FrontendBuildPipelineService;

class SyncModuleFrontendCommand extends Command
{
    protected $signature = 'modular:sync-frontend
        {--build : Run the frontend build after syncing the registries.}
        {--production : Run the frontend build in production mode. Implies --build.}';

    protected $description = 'Sync the modular frontend registry and optionally run the frontend build.';

    public function handle(FrontendBuildPipelineService $service): int
    {
        $payload = $service->sync(
            build: (bool) $this->option('build') || (bool) $this->option('production'),
            production: (bool) $this->option('production'),
        );

        $this->info(sprintf(
            'Module frontend registry sync completed for %d module(s).',
            count($payload['modules'] ?? []),
        ));

        return self::SUCCESS;
    }
}
