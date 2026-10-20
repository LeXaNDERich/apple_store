<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
ensure_installed();

header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_GET['id'] ?? 0);
$product = $id > 0 ? product_by_id($id) : null;
if (!$product) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Товар не найден'], JSON_UNESCAPED_UNICODE);
    exit;
}

$reviews = product_reviews((int) $product['id']);
$stats = review_stats_for_product((int) $product['id']);
$distribution = review_distribution((int) $product['id']);
$access = review_access((int) $product['id']);
$productId = (int) $product['id'];
$pricing = product_purchase_pricing($product);
$promotionPercent = ($pricing['promotion'] && (float) $pricing['regular_price'] > 0 && (float) $pricing['price'] < (float) $pricing['regular_price'])
    ? (int) round((1 - ((float) $pricing['price'] / (float) $pricing['regular_price'])) * 100)
    : 0;
$reviewFormAction = product_url($product) . '#reviews';

ob_start();
include dirname(__DIR__) . '/includes/review_summary.php';
$summaryHtml = ob_get_clean();

ob_start();
include dirname(__DIR__) . '/includes/reviews_list.php';
$reviewsHtml = ob_get_clean();

ob_start();
include dirname(__DIR__) . '/includes/review_form.php';
$formHtml = ob_get_clean();

echo json_encode([
    'ok' => true,
    'id' => $productId,
    'name' => $product['name'],
    'url' => product_url($product),
    'image' => product_image_src($product),
    'alt' => 'Фото ' . $product['name'] . ' — отзывы',
    'short' => $product['short_desc'],
    'price' => format_price((float) $pricing['price']),
    'regular_price' => $pricing['promotion'] ? format_price((float) $pricing['regular_price']) : '',
    'promotion_percent' => $promotionPercent,
    'reviews_count' => $stats['count'],
    'avg' => $stats['avg'],
    'summary_html' => $summaryHtml,
    'reviews_html' => $reviewsHtml,
    'form_html' => $formHtml,
], JSON_UNESCAPED_UNICODE);
