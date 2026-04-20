<?php

namespace Modules\Core\Support\Registry;

use Illuminate\Support\Facades\File;
use Modules\Core\Contracts\FrontendRegistryStoreInterface;

class FilesystemFrontendRegistryStore implements FrontendRegistryStoreInterface
{
    public function __construct(private readonly string $path)
    {
    }

    public function read(): array
    {
        if (! File::exists($this->path)) {
            return ['modules' => []];
        }

        return json_decode(File::get($this->path), true, flags: JSON_THROW_ON_ERROR);
    }

    public function write(array $payload): void
    {
        File::ensureDirectoryExists(dirname($this->path));
        File::put(
            $this->path,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
    }
}
