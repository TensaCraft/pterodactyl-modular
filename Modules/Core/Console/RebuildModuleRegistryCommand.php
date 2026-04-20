<?php

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Modules\Core\Contracts\FrontendRegistryBuilderInterface;
use Modules\Core\Contracts\FrontendRegistryStoreInterface;

class RebuildModuleRegistryCommand extends Command
{
    protected $signature = 'modular:rebuild-registry';

    protected $description = 'Rebuild the modular frontend registry.';

    public function handle(
        FrontendRegistryBuilderInterface $builder,
        FrontendRegistryStoreInterface $store,
    ): int
    {
        $payload = $builder->build();

        $store->write($payload);
        $this->info(sprintf('Module frontend registry rebuilt for %d module(s).', count($payload['modules'])));

        return self::SUCCESS;
    }
}
