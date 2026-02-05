<?php

$localConfig = __DIR__ . '/config.local.php';
$config = null;
if (file_exists($localConfig)) {
    $config = require $localConfig;
}

if (!$config) {
    $config = [
        'db_host' => 'localhost',
        'db_name' => 'REDACTED',
        'db_user' => 'REDACTED',
        'db_pass' => 'REDACTED',
        'admin_token' => 'REDACTED',
    ];
}

$envMap = [
    'db_host' => 'DB_HOST',
    'db_name' => 'DB_NAME',
    'db_user' => 'DB_USER',
    'db_pass' => 'DB_PASS',
    'admin_token' => 'ADMIN_TOKEN',
    'jwt_secret' => 'JWT_SECRET',
];

foreach ($envMap as $key => $envKey) {
    $value = getenv($envKey);
    if ($value !== false && $value !== '') {
        $config[$key] = $value;
    }
}

return $config;
