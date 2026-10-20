<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
ensure_installed();
header('Content-Type: application/xml; charset=utf-8');

function xml_e(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

$urls = [
    [absolute_url(url('index.php')), '1.0', 'daily'],
    [absolute_url(url('catalog.php')), '0.9', 'daily'],
    [absolute_url(url('promotions.php')), '0.9', 'daily'],
    [absolute_url(url('contacts.php')), '0.5', 'monthly'],
];
foreach (categories() as $cat) {
    $urls[] = [absolute_url(url('catalog.php?cat=' . urlencode($cat['slug']))), '0.8', 'weekly'];
}
$products = db()->query('SELECT id, created_at FROM products ORDER BY id')->fetchAll();
foreach ($products as $p) {
    $urls[] = [absolute_url(url('product.php?id=' . (int) $p['id'])), '0.8', 'weekly', substr((string) $p['created_at'], 0, 10)];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $row) {
    echo "  <url>\n";
    echo '    <loc>' . xml_e($row[0]) . "</loc>\n";
    if (!empty($row[3])) echo '    <lastmod>' . xml_e($row[3]) . "</lastmod>\n";
    echo '    <changefreq>' . xml_e($row[2]) . "</changefreq>\n";
    echo '    <priority>' . xml_e($row[1]) . "</priority>\n";
    echo "  </url>\n";
}
echo '</urlset>';
