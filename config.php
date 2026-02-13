<?php
/**
 * Configuration loader.
 * Wrapped in a function to prevent variable leakage into the calling scope.
 *
 * Reads from config.local.php first, then overrides with environment variables.
 */

return (function () {
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
        'github_token' => 'GITHUB_TOKEN',
        'github_repo' => 'GITHUB_REPO',
        'api_debug' => 'API_DEBUG',
    ];

    foreach ($envMap as $configKey => $envKey) {
        $value = getenv($envKey);
        if ($value !== false && $value !== '') {
            $config[$configKey] = $value;
        }
    }

    $defaults = [
        'db_host' => 'localhost',
        'db_name' => '',
        'db_user' => '',
        'db_pass' => '',
        'admin_token' => '',
        'jwt_secret' => '',
        'github_token' => '',
        'github_repo' => 'lbatschelet/feelvonroll',
        'api_debug' => false,
    ];

    foreach ($defaults as $key => $default) {
        if (!isset($config[$key])) {
            $config[$key] = $default;
        }
    }

    if (!defined('API_DEBUG')) {
        $flag = $config['api_debug'] ?? false;
        define('API_DEBUG', $flag === true || $flag === 1 || $flag === '1');
    }

    return $config;
})();
