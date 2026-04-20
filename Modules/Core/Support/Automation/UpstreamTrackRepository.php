<?php

namespace Modules\Core\Support\Automation;

use InvalidArgumentException;
use JsonException;

class UpstreamTrackRepository
{
    public function __construct(private readonly string $path)
    {
    }

    /**
     * @return array<int, UpstreamTrackConfig>
     */
    public function all(): array
    {
        return array_map(
            static fn (array $payload): UpstreamTrackConfig => UpstreamTrackConfig::fromArray($payload),
            $this->loadPayload()
        );
    }

    public function find(string $name): UpstreamTrackConfig
    {
        foreach ($this->all() as $track) {
            if ($track->name === $name) {
                return $track;
            }
        }

        throw new InvalidArgumentException(sprintf('Unknown upstream replay track [%s].', $name));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadPayload(): array
    {
        if (! is_file($this->path)) {
            throw new InvalidArgumentException(sprintf('Upstream track config file [%s] does not exist.', $this->path));
        }

        try {
            $payload = json_decode(file_get_contents($this->path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(
                sprintf('Upstream track config [%s] contains malformed JSON.', $this->path),
                previous: $exception,
            );
        }

        if (! is_array($payload)) {
            throw new InvalidArgumentException(sprintf('Upstream track config [%s] must decode to a JSON array.', $this->path));
        }

        return $payload;
    }
}
