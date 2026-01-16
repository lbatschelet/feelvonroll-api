<?php

$localConfig = __DIR__ . '/config.local.php';
if (file_exists($localConfig)) {
    return require $localConfig;
}

return [
    'db_host' => getenv('DB_HOST') ?: 'localhost',
    'db_name' => getenv('DB_NAME') ?: '',
    'db_user' => getenv('DB_USER') ?: '',
    'db_pass' => getenv('DB_PASS') ?: '',
    'admin_token' => getenv('ADMIN_TOKEN') ?: '',
];
