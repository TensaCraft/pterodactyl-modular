<?php

declare(strict_types=1);

use Modules\Core\Support\Automation\HostPatchSurfaceValidator;
use Modules\Core\Support\Automation\UpstreamReplayPlanner;
use Modules\Core\Support\Automation\UpstreamTrackRepository;
use Symfony\Component\Process\Process;

require __DIR__ . '/../../vendor/autoload.php';

final class GitCommandException extends RuntimeException
{
    /**
     * @param array<int, string> $command
     */
    public function __construct(
        public readonly array $command,
        public readonly string $errorOutput,
    ) {
        parent::__construct(sprintf(
            "Git command failed: git %s\n%s",
            implode(' ', $command),
            trim($errorOutput) !== '' ? trim($errorOutput) : 'Unknown git error.'
        ));
    }
}

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

/**
 * @param array<int, string> $command
 */
function run_git(string $repositoryPath, array $command): string
{
    $process = new Process(array_merge(['git'], $command), $repositoryPath);
    $process->run();

    if (! $process->isSuccessful()) {
        throw new GitCommandException($command, $process->getErrorOutput() . PHP_EOL . $process->getOutput());
    }

    return $process->getOutput();
}

/**
 * @param array<string, mixed> $payload
 */
function write_report(string $path, array $payload): void
{
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), recursive: true);
    }

    file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}

/**
 * @return array<int, string>
 */
function load_host_patch_surface(string $path): array
{
    if (! is_file($path)) {
        throw new InvalidArgumentException(sprintf('Host patch surface file [%s] does not exist.', $path));
    }

    $payload = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

    if (! is_array($payload)) {
        throw new InvalidArgumentException(sprintf('Host patch surface file [%s] must decode to a JSON object.', $path));
    }

    $files = $payload['modified_upstream_files'] ?? null;

    if (! is_array($files)) {
        throw new InvalidArgumentException(sprintf('Host patch surface file [%s] must define modified_upstream_files as an array.', $path));
    }

    return array_values(array_filter(array_map(
        static fn (mixed $file): string => is_string($file) ? trim($file) : '',
        $files,
    )));
}

$options = parse_options($argv);
$mode = $options['mode'] ?? 'plan';
$trackName = $options['track'] ?? null;
$repositoryPath = isset($options['repo']) ? realpath($options['repo']) ?: getcwd() : getcwd();
$reportDirectory = $options['report-dir'] ?? $repositoryPath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'modular' . DIRECTORY_SEPARATOR . 'upstream-replay';
$patchBaseOverride = $options['patch-base-ref'] ?? null;
$patchSourceOverride = $options['patch-source-ref'] ?? null;

if (! is_string($trackName) || $trackName === '') {
    fwrite(STDERR, "Missing required option --track=<name>.\n");
    exit(1);
}

$tracks = new UpstreamTrackRepository($repositoryPath . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'modular' . DIRECTORY_SEPARATOR . 'upstream-tracks.json');
$hostPatchSurface = load_host_patch_surface($repositoryPath . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'modular' . DIRECTORY_SEPARATOR . 'host-patch-surface.json');
$track = $tracks->find($trackName)->withPatchRefs(
    is_string($patchBaseOverride) ? $patchBaseOverride : null,
    is_string($patchSourceOverride) ? $patchSourceOverride : null,
);
$planner = new UpstreamReplayPlanner(fn (array $command): string => run_git($repositoryPath, $command));
$surfaceValidator = new HostPatchSurfaceValidator(fn (array $command): string => run_git($repositoryPath, $command));
$plan = $planner->plan($track);
$surface = $surfaceValidator->validate($track, $hostPatchSurface);
$plan['host_patch_surface'] = $surface;
$reportPath = rtrim($reportDirectory, '/\\') . DIRECTORY_SEPARATOR . $track->name . '.json';

if ($mode === 'plan') {
    $plan['report_path'] = $reportPath;
    echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    exit(0);
}

if ($mode !== 'apply') {
    fwrite(STDERR, sprintf("Unsupported mode [%s]. Use plan or apply.\n", $mode));
    exit(1);
}

$report = array_merge($plan, [
    'status' => 'pending',
    'verification_status' => 'pending',
    'report_path' => $reportPath,
]);

try {
    if (! $surface['valid']) {
        $report['status'] = 'surface_mismatch';
        $report['error'] = sprintf(
            'Modified upstream files diverged from the declared host patch surface. Unexpected: [%s]; Missing: [%s].',
            implode(', ', $surface['unexpected']),
            implode(', ', $surface['missing']),
        );

        write_report($reportPath, $report);
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
        exit(2);
    }

    run_git($repositoryPath, ['checkout', '-B', $track->replayBranch, $plan['upstream_ref']]);

    foreach ($plan['patch_commits'] as $commit) {
        run_git($repositoryPath, ['cherry-pick', '--keep-redundant-commits', $commit]);
    }

    $report['status'] = 'applied';
    $report['replay_head'] = trim(run_git($repositoryPath, ['rev-parse', 'HEAD']));

    write_report($reportPath, $report);
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    exit(0);
} catch (Throwable $exception) {
    try {
        run_git($repositoryPath, ['cherry-pick', '--abort']);
    } catch (Throwable) {
        // ignore secondary cleanup failures
    }

    $report['status'] = 'conflict';
    $report['error'] = $exception->getMessage();

    write_report($reportPath, $report);
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    exit(2);
}
