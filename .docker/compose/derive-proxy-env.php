<?php

declare(strict_types=1);

$appUrl = getenv('APP_URL');

if ($appUrl === false || trim($appUrl) === '') {
    fwrite(STDERR, "APP_URL is required to derive proxy environment values.\n");
    exit(1);
}

$parsed = parse_url($appUrl);

if (!is_array($parsed) || empty($parsed['host'])) {
    fwrite(STDERR, "APP_URL must be a valid absolute URL.\n");
    exit(1);
}

$host = $parsed['host'];
$scheme = $parsed['scheme'] ?? 'http';
$port = $parsed['port'] ?? null;

echo "PANEL_DOMAIN={$host}\n";
echo "PANEL_SCHEME={$scheme}\n";

if ($port !== null) {
    echo "PANEL_PORT={$port}\n";
}
