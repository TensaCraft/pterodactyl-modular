<?php

namespace Modules\Core\Support\Registry;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use Modules\Core\Contracts\FrontendRegistryBuilderInterface;
use Modules\Core\Contracts\ModuleManifestLoaderInterface;
use Modules\Core\Contracts\ModuleStateRepositoryInterface;
use Modules\Core\Data\ModuleManifestData;
use Modules\Core\Enums\ModuleState;

class ModuleFrontendRegistryBuilder implements FrontendRegistryBuilderInterface
{
    public function __construct(
        private readonly ModuleManifestLoaderInterface $manifests,
        private readonly ModuleStateRepositoryInterface $states,
    ) {
    }

    public function build(): array
    {
        $manifests = Collection::make($this->manifests->discover());
        $states = $this->states->allBySlugs($manifests->pluck('slug')->all());

        return [
            'modules' => $manifests
                ->filter(fn (ModuleManifestData $manifest): bool => ($states[$manifest->slug]->state ?? null) === ModuleState::Enabled)
                ->map(fn (ModuleManifestData $manifest) => $this->buildModulePayload($manifest))
                ->values()
                ->all(),
        ];
    }

    private function buildModulePayload(ModuleManifestData $manifest): array
    {
        return [
            'slug' => $manifest->slug,
            'has_frontend' => $manifest->hasFrontend,
            'entrypoint' => $this->resolveEntrypoint($manifest),
            'zones' => $manifest->frontend['zones'] ?? [],
            'routes' => [
                'account' => $this->buildRoutes($manifest, 'account'),
                'server' => $this->buildRoutes($manifest, 'server'),
            ],
        ];
    }

    private function resolveEntrypoint(ModuleManifestData $manifest): ?string
    {
        if (is_string($manifest->frontend['entrypoint'] ?? null) && $manifest->frontend['entrypoint'] !== '') {
            return $manifest->frontend['entrypoint'];
        }

        if (! $manifest->hasFrontend) {
            return null;
        }

        return $this->normalizePath(
            $manifest->path . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'routes.tsx'
        );
    }

    /**
     * @return array<int, array{target: string, path: string, name: string|null, exact: bool, permission?: string|array<int, string>|null}>
     */
    private function buildRoutes(ModuleManifestData $manifest, string $target): array
    {
        return Collection::make($manifest->frontend['routes'] ?? [])
            ->filter(fn ($route) => is_array($route) && ($route['target'] ?? null) === $target)
            ->map(fn (array $route) => $this->normalizeRoute($route, $target))
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $route
     * @return array{target: string, path: string, name: string|null, exact: bool, permission?: string|array<int, string>|null}
     */
    private function normalizeRoute(array $route, string $target): array
    {
        $path = $route['path'] ?? null;

        if (! is_string($path) || $path === '') {
            throw new InvalidArgumentException(sprintf(
                'Module [%s] defines a frontend route with an invalid path for target [%s].',
                $route['name'] ?? 'unknown',
                $target,
            ));
        }

        $normalized = [
            'target' => $target,
            'path' => $path,
            'name' => $this->normalizeName($route['name'] ?? null),
            'exact' => (bool) ($route['exact'] ?? false),
        ];

        if ($target === 'server') {
            $normalized['permission'] = $this->normalizePermission($route['permission'] ?? null);
        }

        return $normalized;
    }

    private function normalizeName(mixed $name): ?string
    {
        if (! is_string($name)) {
            return null;
        }

        $trimmed = trim($name);

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * @return string|array<int, string>|null
     */
    private function normalizePermission(mixed $permission): string|array|null
    {
        if (is_string($permission)) {
            $trimmed = trim($permission);

            return $trimmed !== '' ? $trimmed : null;
        }

        if (! is_array($permission)) {
            return null;
        }

        $normalized = Collection::make($permission)
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn (string $value) => trim($value))
            ->values()
            ->all();

        return $normalized !== [] ? $normalized : null;
    }

    private function normalizePath(string $path): string
    {
        $relative = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path);

        return str_replace(DIRECTORY_SEPARATOR, '/', $relative);
    }
}
