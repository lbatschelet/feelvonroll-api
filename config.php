<?php

$localConfig = __DIR__ . '/config.local.php';
if (file_exists($localConfig)) {
    return require $localConfig;
}

return [
    'db_host' => 'localhost',
    'db_name' => 'REDACTED',
    'db_user' => 'REDACTED',
    'db_pass' => 'REDACTED',
    'admin_token' => 'REDACTED',
];
