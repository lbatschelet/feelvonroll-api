<?php

$localConfig = __DIR__ . '/config.local.php';
$config = [];
if (file_exists($localConfig)) {
    $loaded = require $localConfig;
    if (is_array($loaded)) {
        $config = $loaded;
    }
}

$envMap = [
    'db_host' => 'DB_HOST',
    'db_name' => 'DB_NAME',
    'db_user' => 'DB_USER',
    'db_pass' => 'DB_PASS',
    'admin_token' => 'ADMIN_TOKEN',
    'jwt_secret' => 'JWT_SECRET',
    'api_debug' => 'API_DEBUG',
];

foreach ($envMap as $key => $envKey) {
    $value = getenv($envKey);
    if ($value !== false && $value !== '') {
        $config[$key] = $value;
    }
}

if (!isset($config['db_host'])) {
    $config['db_host'] = 'localhost';
}
if (!isset($config['db_name'])) {
    $config['db_name'] = '';
}
if (!isset($config['db_user'])) {
    $config['db_user'] = '';
}
if (!isset($config['db_pass'])) {
    $config['db_pass'] = '';
}
if (!isset($config['admin_token'])) {
    $config['admin_token'] = '';
}
if (!isset($config['jwt_secret'])) {
    $config['jwt_secret'] = '';
}
if (!isset($config['api_debug'])) {
    $config['api_debug'] = false;
}

if (!defined('API_DEBUG')) {
    $flag = $config['api_debug'] ?? false;
    define('API_DEBUG', $flag === true || $flag === 1 || $flag === '1');
}

return $config;
