<?php

namespace Modules\Core\Services;

use Closure;
use Modules\Core\Contracts\FrontendRegistryBuilderInterface;
use Modules\Core\Contracts\FrontendRegistryStoreInterface;

class FrontendBuildPipelineService
{
    public function __construct(
        private readonly FrontendRegistryBuilderInterface $builder,
        private readonly FrontendRegistryStoreInterface $store,
        private readonly Closure $buildRunner,
    ) {
    }

    /**
     * Sync the frontend registry payload and optionally run the frontend build.
     *
     * @return array<string, mixed>
     */
    public function sync(bool $build = false, bool $production = false): array
    {
        $build = $build || $production;
        $payload = $this->builder->build();

        $this->store->write($payload);

        if ($build) {
            ($this->buildRunner)($production);
        }

        return $payload;
    }
}
