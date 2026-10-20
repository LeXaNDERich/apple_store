<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
header('Content-Type: text/plain; charset=utf-8');
echo "User-agent: *\n";
echo "Allow: /\n";
echo "Disallow: " . BASE_URL . "/admin/\n";
echo "Disallow: " . BASE_URL . "/api/\n";
echo "Disallow: " . BASE_URL . "/cart.php\n";
echo "Disallow: " . BASE_URL . "/checkout.php\n";
echo "Disallow: " . BASE_URL . "/profile.php\n";
echo "Disallow: " . BASE_URL . "/login.php\n";
echo "Sitemap: " . absolute_url(url('sitemap.php')) . "\n";
