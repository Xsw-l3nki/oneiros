<?php
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/xml; charset=UTF-8');
header('X-Robots-Tag: noindex');

$base = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$now  = date('Y-m-d');

// Count public dreams for priority signal
$dreamCount = 0;
try {
    $row = Database::fetchOne('SELECT COUNT(*) AS cnt FROM dreams WHERE privacy = "public" AND is_removed = 0', []);
    $dreamCount = (int)($row['cnt'] ?? 0);
} catch (Exception $e) {}

echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9
        http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">' . PHP_EOL;

$pages = [
    ['loc' => $base . '/',              'priority' => '1.0', 'changefreq' => 'daily'],
    ['loc' => $base . '/oneiros.php',   'priority' => '0.9', 'changefreq' => 'daily'],
];

foreach ($pages as $p) {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($p['loc']) . "</loc>\n";
    echo "    <lastmod>$now</lastmod>\n";
    echo "    <changefreq>{$p['changefreq']}</changefreq>\n";
    echo "    <priority>{$p['priority']}</priority>\n";
    echo "  </url>\n";
}

echo '</urlset>';
