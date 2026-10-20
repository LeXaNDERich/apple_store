<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
ensure_installed();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function review_json(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    review_json(['ok' => false, 'error' => 'Метод не поддерживается'], 405);
}

$token = (string) ($_POST['csrf'] ?? '');
if ($token === '' || !hash_equals((string) ($_SESSION['csrf'] ?? ''), $token)) {
    review_json(['ok' => false, 'error' => 'Сессия устарела. Обновите страницу и попробуйте снова.'], 403);
}

$user = current_user();
if (!$user) {
    review_json(['ok' => false, 'error' => 'Войдите, чтобы оставить отзыв', 'need_login' => true], 401);
}

$productId = (int) ($_POST['product_id'] ?? 0);
$rating = (int) ($_POST['rating'] ?? 0);
$text = clean_review_text((string) ($_POST['text'] ?? ''));
$product = $productId > 0 ? product_by_id($productId) : null;

if (!$product) {
    review_json(['ok' => false, 'error' => 'Товар не найден'], 404);
}
if ($rating < 1 || $rating > 5) {
    review_json(['ok' => false, 'error' => 'Выберите оценку от 1 до 5 звёзд'], 422);
}
if (mb_strlen($text, 'UTF-8') < 3) {
    review_json(['ok' => false, 'error' => 'Текст отзыва — минимум 3 символа'], 422);
}
if (mb_strlen($text, 'UTF-8') > 2000) {
    review_json(['ok' => false, 'error' => 'Текст отзыва — максимум 2000 символов'], 422);
}
if (!user_bought_product((int) $user['id'], $productId)) {
    review_json(['ok' => false, 'error' => 'Отзыв можно оставить только после получения товара. Дождитесь статуса «Доставлен»'], 403);
}

$result = save_review((int) $user['id'], $productId, $rating, $text);
$stored = $result['review'];
$stats = review_stats_for_product($productId);
$distribution = review_distribution($productId);

review_json([
    'ok' => true,
    'action' => $result['action'],
    'message' => $result['action'] === 'updated' ? 'Отзыв обновлён' : 'Отзыв опубликован',
    'count' => $stats['count'],
    'avg' => $stats['avg'],
    'distribution' => $distribution,
    'review' => [
        'id' => (int) ($stored['id'] ?? 0),
        'name' => (string) $user['name'],
        'rating' => $rating,
        'text' => $text,
        'created_at' => date('d.m.Y', strtotime((string) ($stored['created_at'] ?? 'now'))),
    ],
]);
