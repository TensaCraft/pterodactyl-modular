<?php

namespace Modules\Core\Support\Discovery;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Modules\Core\Contracts\ModuleManifestLoaderInterface;
use Modules\Core\Contracts\ModuleManifestParserInterface;
use Modules\Core\Data\ModuleManifestData;

class ModuleManifestLoader implements ModuleManifestLoaderInterface
{
    public function __construct(private readonly ModuleManifestParserInterface $parser)
    {
    }

    public function discover(): array
    {
        $modulesPath = config('modular.paths.modules');

        return Collection::make(File::directories($modulesPath))
            ->map(function (string $path) {
                $manifestPath = $path . DIRECTORY_SEPARATOR . 'module.json';

                if (! File::exists($manifestPath)) {
                    return null;
                }

                return $this->parser->parse($path);
            })
            ->filter()
            ->sortBy([
                fn (ModuleManifestData $manifest) => $manifest->isProtected ? 0 : 1,
                fn (ModuleManifestData $manifest) => $manifest->priority,
                fn (ModuleManifestData $manifest) => $manifest->slug,
            ])
            ->values()
            ->all();
    }

    public function findBySlug(string $slug): ?ModuleManifestData
    {
        return collect($this->discover())
            ->firstWhere('slug', $slug);
    }
}
