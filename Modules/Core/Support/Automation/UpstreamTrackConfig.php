<?php

namespace Modules\Core\Support\Automation;

use InvalidArgumentException;

final readonly class UpstreamTrackConfig
{
    public function __construct(
        public string $name,
        public string $upstreamRef,
        public string $patchBaseRef,
        public string $patchSourceRef,
        public string $replayBranch,
        public ?string $prBaseBranch,
        public string $verificationProfile,
        public bool $openPr,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $required = [
            'name',
            'upstream_ref',
            'patch_base_ref',
            'patch_source_ref',
            'replay_branch',
            'verification_profile',
            'open_pr',
        ];

        foreach ($required as $key) {
            if (! array_key_exists($key, $payload)) {
                throw new InvalidArgumentException(sprintf('Upstream track config is missing required key [%s].', $key));
            }
        }

        $name = self::stringValue($payload, 'name');

        return new self(
            name: $name,
            upstreamRef: self::stringValue($payload, 'upstream_ref'),
            patchBaseRef: self::stringValue($payload, 'patch_base_ref'),
            patchSourceRef: self::stringValue($payload, 'patch_source_ref'),
            replayBranch: self::stringValue($payload, 'replay_branch'),
            prBaseBranch: self::nullableStringValue($payload, 'pr_base_branch'),
            verificationProfile: self::stringValue($payload, 'verification_profile'),
            openPr: self::boolValue($payload, 'open_pr'),
        );
    }

    public function upstreamRemoteRef(): string
    {
        return str_starts_with($this->upstreamRef, 'upstream/')
            ? $this->upstreamRef
            : 'upstream/' . $this->upstreamRef;
    }

    public function patchRange(): string
    {
        return sprintf('%s..%s', $this->patchBaseRef, $this->patchSourceRef);
    }

    public function withPatchRefs(?string $patchBaseRef = null, ?string $patchSourceRef = null): self
    {
        return new self(
            name: $this->name,
            upstreamRef: $this->upstreamRef,
            patchBaseRef: $patchBaseRef !== null && trim($patchBaseRef) !== '' ? trim($patchBaseRef) : $this->patchBaseRef,
            patchSourceRef: $patchSourceRef !== null && trim($patchSourceRef) !== '' ? trim($patchSourceRef) : $this->patchSourceRef,
            replayBranch: $this->replayBranch,
            prBaseBranch: $this->prBaseBranch,
            verificationProfile: $this->verificationProfile,
            openPr: $this->openPr,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function stringValue(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(sprintf('Upstream track config key [%s] must be a non-empty string.', $key));
        }

        return trim($value);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function nullableStringValue(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(sprintf('Upstream track config key [%s] must be null or a non-empty string.', $key));
        }

        return trim($value);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function boolValue(array $payload, string $key): bool
    {
        $value = $payload[$key] ?? null;

        if (! is_bool($value)) {
            throw new InvalidArgumentException(sprintf('Upstream track config key [%s] must be a boolean.', $key));
        }

        return $value;
    }
}
