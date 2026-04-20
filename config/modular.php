<?php

return [
    'paths' => [
        'modules' => base_path('Modules'),
        'cache' => base_path('bootstrap/cache/modular'),
        'imports' => storage_path('app/modular/imports'),
    ],
    'core' => [
        'name' => 'Core',
        'slug' => 'core',
        'protected' => true,
        'api_version' => '1.0.0',
    ],
    'registries' => [
        'backend' => base_path('bootstrap/cache/modular/backend.php'),
        'frontend' => storage_path('app/modular/frontend-registry.json'),
    ],
];
