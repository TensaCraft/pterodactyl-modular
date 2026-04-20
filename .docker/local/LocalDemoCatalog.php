<?php

declare(strict_types=1);

if (! function_exists('localDemoDefaultAllocationPorts')) {
    /**
     * @return array<int, string>
     */
    function localDemoDefaultAllocationPorts(int $startPort = 25565, int $count = 17): array
    {
        return array_map(
            static fn (int $port): string => (string) $port,
            range($startPort, $startPort + $count - 1)
        );
    }
}

if (! function_exists('localDemoDefaultCatalog')) {
    /**
     * @return array{
     *     admin_server_names: array<int, string>,
     *     secondary_users: array<int, array{
     *         username: string,
     *         email: string,
     *         first_name: string,
     *         last_name: string,
     *         password: string,
     *         server_name: string
     *     }>,
     *     allocation_ports: array<int, string>
     * }
     */
    function localDemoDefaultCatalog(): array
    {
        return [
            'admin_server_names' => [
                'Alpha Survival',
                'Beta Creative',
                'Crimson Skyblock',
                'Delta Dungeons',
                'Echo Events',
                'Forge Modpack',
                'Galaxy Proxy',
            ],
            'secondary_users' => [
                [
                    'username' => 'playerone',
                    'email' => 'player@panel.local.proxy',
                    'first_name' => 'Player',
                    'last_name' => 'One',
                    'password' => 'PlayerOne!2026',
                    'server_name' => 'Gamma Modpack',
                ],
                [
                    'username' => 'harbor',
                    'email' => 'harbor@panel.local.proxy',
                    'first_name' => 'Harbor',
                    'last_name' => 'Vale',
                    'password' => 'Harbor!2026',
                    'server_name' => 'Harbor Vanilla',
                ],
                [
                    'username' => 'ion',
                    'email' => 'ion@panel.local.proxy',
                    'first_name' => 'Ion',
                    'last_name' => 'Rush',
                    'password' => 'IonRush!2026',
                    'server_name' => 'Ion Minigames',
                ],
                [
                    'username' => 'jade',
                    'email' => 'jade@panel.local.proxy',
                    'first_name' => 'Jade',
                    'last_name' => 'Crest',
                    'password' => 'JadeCrest!2026',
                    'server_name' => 'Jade Economy',
                ],
                [
                    'username' => 'krypton',
                    'email' => 'krypton@panel.local.proxy',
                    'first_name' => 'Krypton',
                    'last_name' => 'Forge',
                    'password' => 'Krypton!2026',
                    'server_name' => 'Krypton Factions',
                ],
                [
                    'username' => 'lighthouse',
                    'email' => 'lighthouse@panel.local.proxy',
                    'first_name' => 'Light',
                    'last_name' => 'House',
                    'password' => 'Lighthouse!2026',
                    'server_name' => 'Lighthouse RPG',
                ],
                [
                    'username' => 'monsoon',
                    'email' => 'monsoon@panel.local.proxy',
                    'first_name' => 'Monsoon',
                    'last_name' => 'Peak',
                    'password' => 'Monsoon!2026',
                    'server_name' => 'Monsoon Hardcore',
                ],
                [
                    'username' => 'nova',
                    'email' => 'nova@panel.local.proxy',
                    'first_name' => 'Nova',
                    'last_name' => 'Drift',
                    'password' => 'NovaDrift!2026',
                    'server_name' => 'Nova OneBlock',
                ],
                [
                    'username' => 'obsidian',
                    'email' => 'obsidian@panel.local.proxy',
                    'first_name' => 'Obsidian',
                    'last_name' => 'Ward',
                    'password' => 'Obsidian!2026',
                    'server_name' => 'Obsidian Prison',
                ],
                [
                    'username' => 'pulse',
                    'email' => 'pulse@panel.local.proxy',
                    'first_name' => 'Pulse',
                    'last_name' => 'Shift',
                    'password' => 'PulseShift!2026',
                    'server_name' => 'Pulse Practice',
                ],
            ],
            'allocation_ports' => localDemoDefaultAllocationPorts(),
        ];
    }
}
