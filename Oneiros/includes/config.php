<?php
/**
 * Oneiros — Configuration
 * !! EDIT BEFORE GOING LIVE !!
 */
return [
    'db_host'    => 'localhost',
    'db_name'    => 'oneiroo9i0c0_dream',         // ← your DB name
    'db_user'    => 'oneiroo9i0c0_dream',     // ← your DB user
    'db_pass'    => 'Machobri47!',         // ← your DB password
    'db_charset' => 'utf8mb4',

    // ─── Security ───
    // Generate a long random string. Suggested method:
    // visit https://www.random.org/strings/ and request a 64-char string
    'jwt_secret'         => 'IMWel9RfAQIHbBvCdJU3nxnYb565MgcaXiIfOWZnkkOEhxtOwugpowjNxRbldoDw',
    'jwt_refresh_secret' => 'e4GMWLIAxKuKXgQ1fF9klrQEfU2Y7EO0GaujpCgc0J8svwrq9zj0VIGx8CCJivw1',
    'jwt_access_ttl'     => 900,     // 15 minutes (in seconds)
    'jwt_refresh_ttl'    => 604800,  // 7 days

    'frontend_url' => 'https://oneiros.co.za',  // ← Change to 'https://yourdomain.com' after go-live

    'upload_dir'      => __DIR__ . '/../uploads',
    'upload_url_path' => '/uploads',
    'max_file_size'   => 10 * 1024 * 1024,  // 10 MB

    'app_name'    => 'Oneiros',
    'app_version' => 'v1.1.0',

    'environment' => 'production',
    'debug'       => false,
];


