<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Allocations\AssignmentService;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Pterodactyl\Services\Servers\ServerCreationService;
use Pterodactyl\Services\Users\UserCreationService;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require __DIR__ . '/LocalDemoCatalog.php';

$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
/** @var Kernel $kernel */
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$catalog = localDemoDefaultCatalog();

$adminEmail = trim((string) env('LOCAL_DEMO_ADMIN_EMAIL', 'admin@panel.local.proxy'));
$adminUsername = trim((string) env('LOCAL_DEMO_ADMIN_USERNAME', 'tensacraft'));
$adminPassword = trim((string) env('LOCAL_DEMO_ADMIN_PASSWORD', 'TensaCraft!2026'));
$adminFirstName = trim((string) env('LOCAL_DEMO_ADMIN_FIRST_NAME', 'Tensa'));
$adminLastName = trim((string) env('LOCAL_DEMO_ADMIN_LAST_NAME', 'Craft'));
$nodeName = trim((string) env('LOCAL_DEMO_NODE_NAME', 'Local Wings'));
$eggName = trim((string) env('LOCAL_DEMO_EGG_NAME', 'Vanilla Minecraft'));
$adminServerNames = array_values(array_filter(array_map(
    static fn (string $value): string => trim($value),
    explode(',', (string) env('LOCAL_DEMO_SERVER_NAMES', implode(',', $catalog['admin_server_names'])))
)));
$allocationIp = trim((string) env('LOCAL_DEMO_ALLOCATION_IP', '127.0.0.1'));
$allocationPorts = array_values(array_filter(array_map(
    static fn (string $value): string => trim($value),
    explode(',', (string) env('LOCAL_DEMO_ALLOCATION_PORTS', implode(',', $catalog['allocation_ports'])))
)));
$memory = (int) env('LOCAL_DEMO_MEMORY', 1024);
$swap = (int) env('LOCAL_DEMO_SWAP', 0);
$disk = (int) env('LOCAL_DEMO_DISK', 4096);
$io = (int) env('LOCAL_DEMO_IO', 500);
$cpu = (int) env('LOCAL_DEMO_CPU', 0);
$compatIoWeight = max(10, min(1000, (int) env('LOCAL_DEMO_COMPAT_IO_WEIGHT', 500)));
$skipScripts = filter_var(env('LOCAL_DEMO_SKIP_SCRIPTS', true), FILTER_VALIDATE_BOOL);
$startOnCompletion = filter_var(env('LOCAL_DEMO_START_ON_COMPLETION', false), FILTER_VALIDATE_BOOL);

/** @var User|null $user */
$user = User::query()
    ->where('email', $adminEmail)
    ->orWhere('username', $adminUsername)
    ->first();

if ($user === null) {
    /** @var UserCreationService $userCreationService */
    $userCreationService = $app->make(UserCreationService::class);
    $user = $userCreationService->handle([
        'email' => $adminEmail,
        'username' => $adminUsername,
        'name_first' => $adminFirstName,
        'name_last' => $adminLastName,
        'password' => $adminPassword,
        'root_admin' => true,
        'language' => 'en',
    ]);
} else {
    /** @var Hasher $hasher */
    $hasher = $app->make(Hasher::class);

    $user->forceFill([
        'email' => $adminEmail,
        'username' => $adminUsername,
        'name_first' => $adminFirstName,
        'name_last' => $adminLastName,
        'root_admin' => true,
        'password' => $hasher->make($adminPassword),
    ])->save();
}

/** @var UserCreationService $userCreationService */
$userCreationService = $app->make(UserCreationService::class);
/** @var Hasher $hasher */
$hasher = $app->make(Hasher::class);

$secondaryUsers = collect($catalog['secondary_users'])
    ->map(function (array $spec) use ($userCreationService, $hasher): array {
        /** @var User|null $secondaryUser */
        $secondaryUser = User::query()
            ->where('email', $spec['email'])
            ->orWhere('username', $spec['username'])
            ->first();

        if ($secondaryUser === null) {
            $secondaryUser = $userCreationService->handle([
                'email' => $spec['email'],
                'username' => $spec['username'],
                'name_first' => $spec['first_name'],
                'name_last' => $spec['last_name'],
                'password' => $spec['password'],
                'root_admin' => false,
                'language' => 'en',
            ]);
        } else {
            $secondaryUser->forceFill([
                'email' => $spec['email'],
                'username' => $spec['username'],
                'name_first' => $spec['first_name'],
                'name_last' => $spec['last_name'],
                'root_admin' => false,
                'password' => $hasher->make($spec['password']),
            ])->save();
        }

        return [
            'user' => $secondaryUser,
            'server_name' => $spec['server_name'],
        ];
    })
    ->all();

$serverOwners = [];
foreach ($adminServerNames as $serverName) {
    $serverOwners[$serverName] = $user;
}

foreach ($secondaryUsers as $managedSecondaryUser) {
    $serverOwners[$managedSecondaryUser['server_name']] = $managedSecondaryUser['user'];
}

$serverNames = array_keys($serverOwners);

/** @var Node $node */
$node = Node::query()->where('name', $nodeName)->firstOrFail();

/** @var AssignmentService $assignmentService */
$assignmentService = $app->make(AssignmentService::class);
$assignmentService->handle($node, [
    'allocation_ip' => $allocationIp,
    'allocation_alias' => null,
    'allocation_ports' => $allocationPorts,
]);

/** @var Egg $egg */
$egg = Egg::query()->where('name', $eggName)->firstOrFail();
$dockerImage = trim((string) env('LOCAL_DEMO_DOCKER_IMAGE', (string) Arr::first($egg->docker_images)));
$startup = trim((string) env('LOCAL_DEMO_STARTUP', (string) $egg->startup));

$environment = [];
foreach ($egg->variables as $variable) {
    $environment[$variable->env_variable] = (string) env(
        'LOCAL_DEMO_ENV_' . $variable->env_variable,
        $variable->default_value ?? ''
    );
}

/** @var ServerCreationService $serverCreationService */
$serverCreationService = $app->make(ServerCreationService::class);
/** @var DaemonServerRepository $daemonServerRepository */
$daemonServerRepository = $app->make(DaemonServerRepository::class);

$created = [];
$existing = [];
$managedServerIds = [];

foreach ($serverNames as $index => $serverName) {
    $externalId = 'local-demo-' . Str::slug($serverName);
    $owner = $serverOwners[$serverName];

    /** @var Server|null $server */
    $server = Server::query()->where('external_id', $externalId)->first();
    if ($server !== null) {
        $server->forceFill([
            'owner_id' => $owner->id,
            'name' => $serverName,
            'description' => sprintf('Local demo server %d for modular dashboard testing.', $index + 1),
            'io' => $compatIoWeight,
        ])->save();

        $existing[] = [
            'id' => $server->id,
            'name' => $server->name,
            'external_id' => $externalId,
            'uuid' => $server->uuid,
            'allocation_id' => $server->allocation_id,
            'owner_id' => $server->owner_id,
        ];
        $managedServerIds[] = $server->id;

        continue;
    }

    /** @var Allocation $allocation */
    $allocation = Allocation::query()
        ->where('node_id', $node->id)
        ->whereNull('server_id')
        ->orderBy('port')
        ->firstOrFail();

    $server = $serverCreationService->handle([
        'external_id' => $externalId,
        'name' => $serverName,
        'description' => sprintf('Local demo server %d for modular dashboard testing.', $index + 1),
        'owner_id' => $owner->id,
        'allocation_id' => $allocation->id,
        'node_id' => $node->id,
        'memory' => $memory,
        'swap' => $swap,
        'disk' => $disk,
        'io' => $io,
        'cpu' => $cpu,
        'egg_id' => $egg->id,
        'startup' => $startup,
        'image' => $dockerImage,
        'environment' => $environment,
        'database_limit' => 0,
        'allocation_limit' => 0,
        'backup_limit' => 0,
        'oom_disabled' => true,
        'skip_scripts' => $skipScripts,
        'start_on_completion' => $startOnCompletion,
    ]);

    $created[] = [
        'id' => $server->id,
        'name' => $server->name,
        'external_id' => $externalId,
        'uuid' => $server->uuid,
        'allocation_id' => $server->allocation_id,
        'owner_id' => $server->owner_id,
    ];
    $managedServerIds[] = $server->id;
}

if (!empty($managedServerIds)) {
    \DB::table('servers')->whereIn('id', $managedServerIds)->update([
        'io' => $compatIoWeight,
    ]);

    foreach (Server::query()->whereIn('id', $managedServerIds)->get() as $server) {
        $daemonServerRepository->setServer($server)->sync();
    }
}

echo json_encode([
    'admin_user' => [
        'id' => $user->id,
        'username' => $user->username,
        'email' => $user->email,
    ],
    'secondary_users' => array_map(
        static fn (array $managedSecondaryUser): array => [
            'id' => $managedSecondaryUser['user']->id,
            'username' => $managedSecondaryUser['user']->username,
            'email' => $managedSecondaryUser['user']->email,
            'server_name' => $managedSecondaryUser['server_name'],
        ],
        $secondaryUsers
    ),
    'node' => [
        'id' => $node->id,
        'name' => $node->name,
        'fqdn' => $node->fqdn,
    ],
    'egg' => [
        'id' => $egg->id,
        'name' => $egg->name,
        'docker_image' => $dockerImage,
    ],
    'compat' => [
        'io_weight' => $compatIoWeight,
    ],
    'created' => $created,
    'existing' => $existing,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
