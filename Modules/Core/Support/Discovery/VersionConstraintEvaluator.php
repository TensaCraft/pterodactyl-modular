<?php

namespace Modules\Core\Support\Discovery;

final class VersionConstraintEvaluator
{
    public function satisfies(string $version, string $constraint): bool
    {
        $constraint = trim($constraint);

        if ($constraint === '' || $constraint === '*') {
            return true;
        }

        $normalizedVersion = $this->normalizeVersion($version);

        foreach (preg_split('/\s*\|\|\s*/', $constraint) ?: [] as $alternative) {
            if ($this->matchesAll($normalizedVersion, trim($alternative))) {
                return true;
            }
        }

        return false;
    }

    private function matchesAll(string $version, string $constraint): bool
    {
        $tokens = preg_split('/\s*,\s*|\s+/', $constraint) ?: [];

        foreach ($tokens as $token) {
            $token = trim($token);

            if ($token === '' || $token === '*') {
                continue;
            }

            if (! $this->matchesToken($version, $token)) {
                return false;
            }
        }

        return true;
    }

    private function matchesToken(string $version, string $token): bool
    {
        if (str_starts_with($token, '^')) {
            [$lower, $upper] = $this->caretBounds(substr($token, 1));

            return $this->compare($version, '>=', $lower) && $this->compare($version, '<', $upper);
        }

        if (str_starts_with($token, '~')) {
            [$lower, $upper] = $this->tildeBounds(substr($token, 1));

            return $this->compare($version, '>=', $lower) && $this->compare($version, '<', $upper);
        }

        if (preg_match('/[*xX]/', $token) === 1) {
            [$lower, $upper] = $this->wildcardBounds($token);

            return $this->compare($version, '>=', $lower) && $this->compare($version, '<', $upper);
        }

        if (preg_match('/^(<=|>=|<|>|=|==)\s*(.+)$/', $token, $matches) === 1) {
            return $this->compare($version, $matches[1], $matches[2]);
        }

        return $this->compare($version, '==', $token);
    }

    private function compare(string $version, string $operator, string $otherVersion): bool
    {
        return version_compare($version, $this->normalizeVersion($otherVersion), $operator);
    }

    private function caretBounds(string $version): array
    {
        $parts = $this->normalizeParts($version);
        $upper = $parts;

        if ($parts[0] > 0) {
            $upper[0]++;
            $upper[1] = 0;
            $upper[2] = 0;
        } elseif ($parts[1] > 0) {
            $upper[1]++;
            $upper[2] = 0;
        } else {
            $upper[2]++;
        }

        return [$this->partsToVersion($parts), $this->partsToVersion($upper)];
    }

    private function tildeBounds(string $version): array
    {
        $parts = $this->normalizeParts($version);
        $specifiedParts = $this->specifiedPartCount($version);
        $upper = $parts;

        if ($specifiedParts <= 2) {
            $upper[0]++;
            $upper[1] = 0;
            $upper[2] = 0;
        } else {
            $upper[1]++;
            $upper[2] = 0;
        }

        return [$this->partsToVersion($parts), $this->partsToVersion($upper)];
    }

    private function wildcardBounds(string $version): array
    {
        $segments = preg_split('/\./', strtolower(trim($version))) ?: [];
        $normalized = [0, 0, 0];
        $wildcardIndex = null;

        foreach ($segments as $index => $segment) {
            if ($index > 2) {
                break;
            }

            if (in_array($segment, ['*', 'x'], true)) {
                $wildcardIndex = $index;
                break;
            }

            $normalized[$index] = (int) $segment;
        }

        if ($wildcardIndex === null) {
            return [$this->partsToVersion($normalized), $this->incrementAt($normalized, 2)];
        }

        return [$this->partsToVersion($normalized), $this->incrementAt($normalized, $wildcardIndex)];
    }

    private function incrementAt(array $parts, int $index): string
    {
        $parts[$index]++;

        for ($cursor = $index + 1; $cursor <= 2; $cursor++) {
            $parts[$cursor] = 0;
        }

        return $this->partsToVersion($parts);
    }

    private function normalizeVersion(string $version): string
    {
        return $this->partsToVersion($this->normalizeParts($version));
    }

    private function normalizeParts(string $version): array
    {
        $version = trim($version);
        $version = ltrim($version, 'vV');

        if (preg_match('/^\d+(?:\.\d+){0,2}/', $version, $matches) !== 1) {
            return [0, 0, 0];
        }

        $segments = array_map('intval', explode('.', $matches[0]));

        while (count($segments) < 3) {
            $segments[] = 0;
        }

        return array_slice($segments, 0, 3);
    }

    private function specifiedPartCount(string $version): int
    {
        $version = ltrim(trim($version), 'vV');
        $segments = preg_split('/\./', $version) ?: [];

        return min(3, count(array_filter($segments, static fn (string $segment) => $segment !== '')));
    }

    private function partsToVersion(array $parts): string
    {
        return implode('.', array_slice($parts, 0, 3));
    }
}
