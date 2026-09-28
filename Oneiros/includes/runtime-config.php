<?php
/** Central configuration, with optional local/environment overrides for deployment. */
$config = require __DIR__ . '/config.example.php';
foreach (['config.php', 'config.local.php'] as $configFile) {
    if (is_file(__DIR__ . '/' . $configFile)) {
        $overrides = require __DIR__ . '/' . $configFile;
        if (is_array($overrides)) $config = array_replace($config, $overrides);
    }
}
foreach (['db_host', 'db_port', 'db_name', 'db_user', 'db_pass', 'jwt_secret',
    'jwt_refresh_secret', 'frontend_url', 'mail_from', 'mail_enabled', 'image_api_key', 'image_model', 'debug',
    'payfast_merchant_id', 'payfast_merchant_key', 'payfast_passphrase', 'payfast_sandbox', 'paystack_secret_key', 'environment'] as $key) {
    $value = getenv('ONEIROS_' . strtoupper($key));
    if ($value !== false) {
        $config[$key] = in_array($key, ['debug', 'mail_enabled', 'payfast_sandbox'], true)
            ? filter_var($value, FILTER_VALIDATE_BOOLEAN) : $value;
    }
}
$config['db_port'] = (int)$config['db_port'];
$config['frontend_url'] = rtrim($config['frontend_url'], '/');
date_default_timezone_set('UTC');
return $config;
