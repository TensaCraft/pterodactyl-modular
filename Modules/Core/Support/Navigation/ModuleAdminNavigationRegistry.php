<?php

namespace Modules\Core\Support\Navigation;

use Illuminate\Support\Collection;
use Modules\Core\Contracts\ModuleManifestLoaderInterface;
use Modules\Core\Contracts\ModuleStateRepositoryInterface;
use Modules\Core\Data\ModuleManifestData;
use Modules\Core\Enums\ModuleState;

class ModuleAdminNavigationRegistry
{
    public function __construct(
        private readonly ModuleManifestLoaderInterface $manifests,
        private readonly ModuleStateRepositoryInterface $states,
    ) {
    }

    /**
     * @return array{modules: array<int, array{slug: string, label: string, icon: string, path: string, active_pattern: string, priority: int}>}
     */
    public function build(): array
    {
        $manifests = Collection::make($this->manifests->discover());
        $states = $this->states->allBySlugs($manifests->pluck('slug')->all());

        $items = $manifests
            ->filter(fn (ModuleManifestData $manifest): bool => ($states[$manifest->slug]->state ?? null) === ModuleState::Enabled)
            ->flatMap(fn (ModuleManifestData $manifest): array => $this->buildItems($manifest))
            ->filter()
            ->sortBy([
                fn (array $item): int => $item['priority'],
                fn (array $item): string => $item['slug'],
                fn (array $item): string => $item['label'],
            ])
            ->values()
            ->all();

        return [
            'modules' => $items,
        ];
    }

    /**
     * @return array<int, array{slug: string, label: string, icon: string, path: string, active_pattern: string, priority: int}>
     */
    private function buildItems(ModuleManifestData $manifest): array
    {
        return Collection::make($manifest->admin['navigation'] ?? [])
            ->filter(fn ($item): bool => is_array($item))
            ->map(fn (array $item): ?array => $this->normalizeItem($manifest, $item))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $item
     * @return array{slug: string, label: string, icon: string, path: string, active_pattern: string, priority: int}|null
     */
    private function normalizeItem(ModuleManifestData $manifest, array $item): ?array
    {
        $label = $this->normalizeString($item['label'] ?? null);
        $path = $this->normalizePath($item['path'] ?? null);

        if ($label === null || $path === null) {
            return null;
        }

        $activePrefix = $this->normalizePath($item['active_prefix'] ?? null) ?? $path;

        return [
            'slug' => $manifest->slug,
            'label' => $label,
            'icon' => $this->normalizeString($item['icon'] ?? null) ?? 'fa-circle-o',
            'path' => $path,
            'active_pattern' => $this->activePattern($activePrefix),
            'priority' => (int) ($item['priority'] ?? 100),
        ];
    }

    private function normalizePath(mixed $path): ?string
    {
        $normalized = $this->normalizeString($path);

        if ($normalized === null) {
            return null;
        }

        return str_starts_with($normalized, '/') ? $normalized : '/' . $normalized;
    }

    private function normalizeString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    private function activePattern(string $path): string
    {
        $normalized = trim($path, '/');

        return $normalized === '' ? '*' : $normalized . '*';
    }
}
