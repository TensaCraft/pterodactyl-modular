<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Modules\Core\Contracts\ModuleLifecycleServiceInterface;
use Modules\Core\Contracts\ModuleStateRepositoryInterface;
use Modules\Core\Enums\ModuleAction;
use Modules\Core\Enums\ModuleState;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
/** @var Kernel $kernel */
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$targetSlugs = array_values(array_filter(array_map(
    static fn (string $slug): string => trim($slug),
    explode(',', (string) env('LOCAL_BOOTSTRAP_MODULES', ''))
)));

/** @var ModuleLifecycleServiceInterface $lifecycle */
$lifecycle = $app->make(ModuleLifecycleServiceInterface::class);
/** @var ModuleStateRepositoryInterface $states */
$states = $app->make(ModuleStateRepositoryInterface::class);

$results = [];

foreach ($targetSlugs as $slug) {
    $state = $states->findBySlug($slug)?->state ?? ModuleState::Discovered;

    if ($state === ModuleState::Discovered) {
        $lifecycle->execute($slug, ModuleAction::Install);
        $state = $states->findBySlug($slug)?->state ?? ModuleState::Installed;
    }

    if ($state !== ModuleState::Enabled) {
        $lifecycle->execute($slug, ModuleAction::Enable);
        $state = $states->findBySlug($slug)?->state ?? ModuleState::Enabled;
    }

    $results[] = [
        'slug' => $slug,
        'state' => $state->value,
    ];
}

echo json_encode([
    'modules' => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
