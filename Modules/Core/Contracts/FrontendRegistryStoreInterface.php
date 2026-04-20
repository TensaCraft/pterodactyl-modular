<?php

namespace Modules\Core\Contracts;

interface FrontendRegistryStoreInterface
{
    public function read(): array;

    public function write(array $payload): void;
}
