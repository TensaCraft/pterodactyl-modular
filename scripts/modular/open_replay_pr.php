<?php

declare(strict_types=1);

use Modules\Core\Support\Automation\UpstreamPullRequestPayloadBuilder;
use Modules\Core\Support\Automation\UpstreamTrackRepository;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;

require __DIR__ . '/../../vendor/autoload.php';

/**
 * @return array<string, string>
 */
function parse_options(array $argv): array
{
    $options = [];

    foreach (array_slice($argv, 1) as $argument) {
        if (! str_starts_with($argument, '--')) {
            continue;
        }

        $parts = explode('=', substr($argument, 2), 2);
        $options[$parts[0]] = $parts[1] ?? 'true';
    }

    return $options;
}

$options = parse_options($argv);
$trackName = $options['track'] ?? null;
$repositoryPath = isset($options['repo']) ? realpath($options['repo']) ?: getcwd() : getcwd();
$reportPath = $options['report'] ?? null;
$repository = $options['repository'] ?? getenv('GITHUB_REPOSITORY') ?: '';
$token = getenv('GITHUB_TOKEN') ?: '';

if (! is_string($trackName) || $trackName === '') {
    fwrite(STDERR, "Missing required option --track=<name>.\n");
    exit(1);
}

if (! is_string($reportPath) || $reportPath === '') {
    fwrite(STDERR, "Missing required option --report=<path>.\n");
    exit(1);
}

$tracks = new UpstreamTrackRepository($repositoryPath . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'modular' . DIRECTORY_SEPARATOR . 'upstream-tracks.json');
$track = $tracks->find($trackName);
$report = json_decode(file_get_contents($reportPath), true, flags: JSON_THROW_ON_ERROR);
$payload = (new UpstreamPullRequestPayloadBuilder())->build($track, $report);

if ($payload === null) {
    echo json_encode(['status' => 'skipped', 'reason' => 'Track does not publish pull requests.'], JSON_THROW_ON_ERROR) . PHP_EOL;
    exit(0);
}

if ($repository === '' || $token === '') {
    echo json_encode(['status' => 'skipped', 'reason' => 'Missing GITHUB_REPOSITORY or GITHUB_TOKEN.'], JSON_THROW_ON_ERROR) . PHP_EOL;
    exit(0);
}

[$owner] = explode('/', $repository, 2);

$client = HttpClient::create([
    'base_uri' => 'https://api.github.com/',
    'headers' => [
        'Accept' => 'application/vnd.github+json',
        'Authorization' => 'Bearer ' . $token,
        'User-Agent' => 'pterodactyl-modular-upstream-replay',
    ],
]);

try {
    $existing = $client->request('GET', sprintf('repos/%s/pulls', $repository), [
        'query' => [
            'state' => 'open',
            'head' => $owner . ':' . $payload['head'],
            'base' => $payload['base'],
        ],
    ])->toArray();

    if ($existing !== []) {
        $pullRequest = $existing[0];
        $response = $client->request('PATCH', sprintf('repos/%s/pulls/%d', $repository, $pullRequest['number']), [
            'json' => [
                'title' => $payload['title'],
                'body' => $payload['body'],
                'base' => $payload['base'],
            ],
        ])->toArray();

        echo json_encode([
            'status' => 'updated',
            'number' => $response['number'],
            'url' => $response['html_url'],
        ], JSON_THROW_ON_ERROR) . PHP_EOL;
        exit(0);
    }

    $response = $client->request('POST', sprintf('repos/%s/pulls', $repository), [
        'json' => $payload,
    ])->toArray();

    echo json_encode([
        'status' => 'created',
        'number' => $response['number'],
        'url' => $response['html_url'],
    ], JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (HttpExceptionInterface $exception) {
    $statusCode = $exception->getResponse()->getStatusCode();

    if (in_array($statusCode, [401, 403], true)) {
        echo json_encode([
            'status' => 'skipped',
            'reason' => 'GitHub token does not have permission to manage pull requests for this repository.',
            'http_status' => $statusCode,
        ], JSON_THROW_ON_ERROR) . PHP_EOL;
        exit(0);
    }

    throw $exception;
}
