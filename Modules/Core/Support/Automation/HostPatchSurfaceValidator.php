<?php

namespace Modules\Core\Support\Automation;

use Closure;

class HostPatchSurfaceValidator
{
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
            : array_values(array_filter(array_map('trim', preg_split('/\R+/', $diff) ?: [])));

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
}
