<?php

namespace Modules\Core\Support\Automation;

use Closure;

class HostPatchSurfaceValidator
{
    private const IGNORED_PATHS = [
        'README.md',
        'docker-compose.yml',
    ];

    private const IGNORED_PREFIXES = [
        '.docker/',
        '.github/',
        '.tools/',
        'Modules/',
        'docs/',
        'scripts/modular/',
        'tests/',
    ];

    /**
     * @param Closure(array<int, string>): string $git
     */
    public function __construct(private readonly Closure $git)
    {
    }

    /**
     * @param array<int, string> $expected
     * @return array{
     *   valid: bool,
     *   current: array<int, string>,
     *   expected: array<int, string>,
     *   unexpected: array<int, string>,
     *   missing: array<int, string>
     * }
     */
    public function validate(UpstreamTrackConfig $track, array $expected): array
    {
        $diff = trim(($this->git)([
            'diff',
            '--name-only',
            '--diff-filter=M',
            $track->patchRange(),
            '--',
            '.',
            ':(exclude)Modules/**',
            ':(exclude)docs/**',
            ':(exclude).tools/**',
            ':(exclude)tests/**',
        ]));

        $current = $diff === ''
            ? []
            : array_values(array_filter(
                array_map('trim', preg_split('/\R+/', $diff) ?: []),
                static fn (string $path): bool => $path !== '' && ! self::isProjectPath($path),
            ));

        $expected = array_values(array_unique(array_map('strval', $expected)));
        sort($current);
        sort($expected);

        $unexpected = array_values(array_diff($current, $expected));
        $missing = array_values(array_diff($expected, $current));

        return [
            'valid' => $unexpected === [] && $missing === [],
            'current' => $current,
            'expected' => $expected,
            'unexpected' => $unexpected,
            'missing' => $missing,
        ];
    }

    private static function isProjectPath(string $path): bool
    {
        $path = str_replace('\\', '/', $path);

        if (in_array($path, self::IGNORED_PATHS, true)) {
            return true;
        }

        foreach (self::IGNORED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
