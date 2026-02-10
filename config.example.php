<?php

return [
    'db_host' => 'localhost',
    'db_name' => 'your_db_name',
    'db_user' => 'your_db_user',
    'db_pass' => 'your_db_password',
    'admin_token' => 'set_a_long_random_token',
    'jwt_secret' => 'set_a_long_random_secret',
    'api_debug' => false,

    /* SMTP settings for password reset emails */
    'smtp_host' => 'smtp.forwardemail.net',
    'smtp_port' => 587,
    'smtp_user' => '',
    'smtp_pass' => '',
    'smtp_from' => 'noreply@feelvonroll.ch',
    'smtp_from_name' => 'feelvonRoll Admin',

    /* Public URL of the admin frontend (used for building reset links) */
    'app_url' => 'https://admin.feelvonroll.ch',
];
