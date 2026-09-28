<?php
/** Copy this file to config.php and fill in your cPanel details. Never commit secrets. */
return [
    'db_host' => 'localhost',
    'db_port' => 3306,
    'db_name' => 'cpaneluser_oneiros',
    'db_user' => 'cpaneluser_oneiros',
    'db_pass' => 'CHANGE_ME',
    'db_charset' => 'utf8mb4',
    // Generate EACH secret with: php -r "echo bin2hex(random_bytes(48)), PHP_EOL;"
    'jwt_secret' => 'CHANGE_ME_GENERATE_A_RANDOM_SECRET',
    'jwt_refresh_secret' => 'CHANGE_ME_GENERATE_A_DIFFERENT_RANDOM_SECRET',
    'jwt_access_ttl' => 3600,
    'jwt_refresh_ttl' => 2592000,
    // Include a subfolder if used, e.g. https://example.com/oneiros (no final slash).
    'frontend_url' => 'https://example.com',
    'upload_dir' => __DIR__ . '/../uploads',
    'upload_url_path' => '/uploads',
    'max_file_size' => 10 * 1024 * 1024,
    'min_age' => 18,
    'mail_from' => 'noreply@example.com',
    'mail_enabled' => false,
    // Optional paid image provider. Keep the key server-side; blank enables local Dream Canvas only.
    'image_api_key' => '',
    'image_model' => 'gpt-image-1',
    // ─── Lucid passes: once-off payments, never recurring ───
    // Leave the gateway keys blank until you are ready to sell. Codes, gifts and referrals still work.
    'currency' => 'ZAR',
    'currency_symbol' => 'R',
    // PayFast (payfast.co.za): Settings → Integration. Set the same passphrase in PayFast → Settings → Security/Developer.
    // Use only letters and numbers in the passphrase (PayFast encodes other symbols inconsistently).
    'payfast_merchant_id' => '',
    'payfast_merchant_key' => '',
    'payfast_passphrase' => '',
    'payfast_sandbox' => false,   // true while testing with sandbox.payfast.co.za credentials
    // Paystack (paystack.com), optional second gateway: Settings → API Keys & Webhooks.
    'paystack_secret_key' => '',
    // Shown on receipts and the pricing page. Use your registered business details.
    'business_name' => 'Oneiros',
    'business_email' => 'hello@example.com',
    'business_details' => '',   // e.g. "Oneiros (Pty) Ltd · Reg 2026/000000/07 · Johannesburg"
    // Prices in cents. Remove a plan to hide it. Days are added to any time already remaining.
    'plans' => [
        'lucid-7'   => ['name' => 'Lucid Week',   'days' => 7,   'price_cents' => 2900],
        'lucid-30'  => ['name' => 'Lucid Month',  'days' => 30,  'price_cents' => 6900, 'featured' => true],
        'lucid-90'  => ['name' => 'Lucid Season', 'days' => 90,  'price_cents' => 16900],
        'lucid-365' => ['name' => 'Lucid Year',   'days' => 365, 'price_cents' => 49900],
    ],
    // One-off "support Oneiros" amounts in cents (supporters get a Patron mark, not premium time).
    'support_amounts' => [2500, 5000, 10000],
    // What the free tier includes; Lucid removes these limits.
    'free_visible_matches' => 5,
    'free_connection_requests_per_week' => 3,
    'free_ai_paintings_per_day' => 1,
    // Invite a friend: both receive these Lucid days once the friend saves their first dream.
    'referral_reward_days' => 7,
    'referral_max_rewards_per_year' => 12,
    'app_name' => 'Oneiros',
    'app_version' => '2.1.0',
    'environment' => 'production',
    'debug' => false,
];
