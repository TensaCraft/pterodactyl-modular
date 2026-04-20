<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;
use Pterodactyl\Models\Location;
use Pterodactyl\Models\Node;
use Pterodactyl\Services\Allocations\AssignmentService;
use Pterodactyl\Services\Nodes\NodeCreationService;
use Symfony\Component\Yaml\Yaml;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require __DIR__ . '/LocalDemoCatalog.php';

$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
/** @var Kernel $kernel */
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$locationShort = trim((string) env('LOCAL_WINGS_LOCATION_SHORT', 'local'));
$locationLong = trim((string) env('LOCAL_WINGS_LOCATION_LONG', 'Local Docker Node'));
$nodeName = trim((string) env('LOCAL_WINGS_NODE_NAME', 'Local Wings'));
$nodeDescription = trim((string) env('LOCAL_WINGS_NODE_DESCRIPTION', 'Local Docker Wings runtime for modular testing.'));
$nodeFqdn = trim((string) env('LOCAL_WINGS_NODE_FQDN', 'wings.local.proxy'));
$nodeScheme = trim((string) env('LOCAL_WINGS_NODE_SCHEME', 'http'));
$behindProxy = filter_var(env('LOCAL_WINGS_BEHIND_PROXY', false), FILTER_VALIDATE_BOOL);
$daemonListen = (int) env('LOCAL_WINGS_DAEMON_LISTEN', 8080);
$daemonSftp = (int) env('LOCAL_WINGS_SFTP_PORT', 2022);
$daemonBase = trim((string) env('LOCAL_WINGS_DAEMON_BASE', '/var/lib/pterodactyl/volumes'));
$memory = (int) env('LOCAL_WINGS_NODE_MEMORY', 16384);
$disk = (int) env('LOCAL_WINGS_NODE_DISK', 102400);
$uploadSize = (int) env('LOCAL_WINGS_UPLOAD_SIZE', 100);
$allocationIp = trim((string) env('LOCAL_WINGS_ALLOCATION_IP', '127.0.0.1'));
$allocationPorts = array_values(array_filter(array_map(
    static fn (string $port): string => trim($port),
    explode(',', (string) env('LOCAL_WINGS_ALLOCATION_PORTS', implode(',', localDemoDefaultCatalog()['allocation_ports'])))
)));
$panelUrl = trim((string) env('LOCAL_WINGS_PANEL_URL', 'http://panel.local.proxy'));
$allowedOrigin = trim((string) env('LOCAL_WINGS_ALLOWED_ORIGIN', $panelUrl));
$configPath = trim((string) env('LOCAL_WINGS_CONFIG_PATH', base_path('.docker/wings/etc/config.yml')));
$dockerNetworkName = trim((string) env('LOCAL_WINGS_DOCKER_NETWORK_NAME', 'pterodactyl_nw'));
$dockerNetworkMode = trim((string) env('LOCAL_WINGS_DOCKER_NETWORK_MODE', $dockerNetworkName));
$dockerNetworkDriver = trim((string) env('LOCAL_WINGS_DOCKER_NETWORK_DRIVER', 'bridge'));
$dockerNetworkSubnet = trim((string) env('LOCAL_WINGS_DOCKER_NETWORK_SUBNET', '172.31.0.0/16'));
$dockerNetworkGateway = trim((string) env('LOCAL_WINGS_DOCKER_NETWORK_GATEWAY', '172.31.0.1'));
$dockerNetworkInterface = trim((string) env('LOCAL_WINGS_DOCKER_NETWORK_INTERFACE', '127.0.0.1'));

/** @var Location $location */
$location = Location::query()->firstOrCreate(
    ['short' => $locationShort],
    ['long' => $locationLong],
);

$nodePayload = [
    'public' => true,
    'name' => $nodeName,
    'location_id' => $location->id,
    'description' => $nodeDescription,
    'fqdn' => $nodeFqdn,
    'scheme' => $nodeScheme,
    'behind_proxy' => $behindProxy,
    'memory' => $memory,
    'memory_overallocate' => 0,
    'disk' => $disk,
    'disk_overallocate' => 0,
    'upload_size' => $uploadSize,
    'daemonBase' => $daemonBase,
    'daemonSFTP' => $daemonSftp,
    'daemonListen' => $daemonListen,
    'maintenance_mode' => false,
];

/** @var NodeCreationService $creator */
$creator = $app->make(NodeCreationService::class);
/** @var Node|null $node */
$node = Node::query()->where('name', $nodeName)->first();

if ($node === null) {
    $node = $creator->handle($nodePayload);
} else {
    $node->forceFill($nodePayload)->save();
}

/** @var AssignmentService $allocations */
$allocations = $app->make(AssignmentService::class);
$allocations->handle($node, [
    'allocation_ip' => $allocationIp,
    'allocation_alias' => null,
    'allocation_ports' => $allocationPorts,
]);

$configuration = $node->getConfiguration();
$configuration['remote'] = $panelUrl;
$configuration['api']['host'] = '0.0.0.0';
$configuration['api']['port'] = $daemonListen;
$configuration['api']['ssl']['enabled'] = false;
$configuration['system']['data'] = $daemonBase;

if ($allowedOrigin !== '') {
    $configuration['allowed_origins'] = [$allowedOrigin];
}

$configuration['docker']['network'] = [
    'interface' => $dockerNetworkInterface,
    'name' => $dockerNetworkName,
    'driver' => $dockerNetworkDriver,
    'network_mode' => $dockerNetworkMode,
    'is_internal' => false,
    'enable_icc' => true,
    'network_mtu' => 1500,
    'dns' => ['1.1.1.1', '1.0.0.1'],
    'interfaces' => [
        'v4' => [
            'subnet' => $dockerNetworkSubnet,
            'gateway' => $dockerNetworkGateway,
        ],
    ],
];

File::ensureDirectoryExists(dirname($configPath));
File::put($configPath, Yaml::dump($configuration, 6, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE));

echo json_encode([
    'location_id' => $location->id,
    'node_id' => $node->id,
    'node_fqdn' => $node->fqdn,
    'daemon_listen' => $node->daemonListen,
    'daemon_sftp' => $node->daemonSFTP,
    'allocation_ip' => $allocationIp,
    'allocation_ports' => $allocationPorts,
    'config_path' => $configPath,
    'docker_network' => [
        'name' => $dockerNetworkName,
        'subnet' => $dockerNetworkSubnet,
        'gateway' => $dockerNetworkGateway,
        'interface' => $dockerNetworkInterface,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
