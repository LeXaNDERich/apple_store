<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/images.php';

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function absolute_url(string $path): string
{
    if (preg_match('~^https?://~i', $path)) {
        return $path;
    }
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '') {
        return $path;
    }
    $https = (string) ($_SERVER['HTTPS'] ?? '');
    $scheme = ($https !== '' && strtolower($https) !== 'off') ? 'https' : 'http';
    return $scheme . '://' . $host . '/' . ltrim($path, '/');
}

function redirect($path)
{
    $target = preg_match('~^https?://~i', $path) ? $path : url($path);
    header('Location: ' . $target);
    exit;
}

function format_price($n)
{
    return number_format((float) $n, 0, ',', ' ') . ' ₽';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $token = (string) ($_POST['csrf'] ?? '');
    if ($token === '' || !hash_equals((string) ($_SESSION['csrf'] ?? ''), $token)) {
        http_response_code(403);
        exit('Неверный CSRF-токен');
    }
}

function flash(string $key, ?string $value = null): ?string
{
    if ($value !== null) {
        $_SESSION['flash'][$key] = $value;
        return $value;
    }
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function metrika_queue_goal(string $goal, array $params = []): void
{
    if (!isset($_SESSION['metrika_goals']) || !is_array($_SESSION['metrika_goals'])) {
        $_SESSION['metrika_goals'] = [];
    }

    $_SESSION['metrika_goals'][] = [
        'goal' => $goal,
        'params' => $params,
    ];
}

function metrika_pull_goals(): array
{
    $goals = $_SESSION['metrika_goals'] ?? [];
    unset($_SESSION['metrika_goals']);

    return is_array($goals) ? $goals : [];
}

function current_user(): ?array
{
    static $cached = false;
    if ($cached !== false) {
        return $cached;
    }
    if (empty($_SESSION['user_id'])) {
        $cached = null;
        return null;
    }
    $st = db()->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([(int) $_SESSION['user_id']]);
    $cached = $st->fetch() ?: null;
    return $cached;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        flash('error', 'Войдите, чтобы продолжить.');
        redirect('login.php');
    }
    return $user;
}

function require_admin(): array
{
    $user = current_user();
    if (!$user || ($user['role'] ?? '') !== 'admin') {
        redirect('admin/login.php');
    }
    return $user;
}

function cart(): array
{
    return $_SESSION['cart'] ?? [];
}

function cart_count(): int
{
    $n = 0;
    foreach (cart() as $item) {
        $n += (int) ($item['qty'] ?? 0);
    }
    return $n;
}

function cart_add(int $id, int $qty = 1, string $opts = '', float $price = 0): void
{
    $qty = max(1, $qty);
    $key = $id . '|' . $opts;
    if (!isset($_SESSION['cart'][$key])) {
        $_SESSION['cart'][$key] = ['id' => $id, 'qty' => 0, 'opts' => $opts, 'price' => $price];
    }
    $_SESSION['cart'][$key]['qty'] += $qty;
    if ($price > 0) {
        $_SESSION['cart'][$key]['price'] = $price;
    }
}

function cart_set_qty(string $key, int $qty): void
{
    if ($qty <= 0) {
        unset($_SESSION['cart'][$key]);
        return;
    }
    if (isset($_SESSION['cart'][$key])) {
        $_SESSION['cart'][$key]['qty'] = $qty;
    }
}

function cart_items_detailed(): array
{
    $items = [];
    $total = 0.0;
    $regularTotal = 0.0;
    $promotionSavings = 0.0;
    foreach (cart() as $key => $row) {
        $st = db()->prepare('SELECT * FROM products WHERE id = ?');
        $st->execute([(int) $row['id']]);
        $product = $st->fetch();
        if (!$product) {
            unset($_SESSION['cart'][$key]);
            continue;
        }

        $opts = (string) ($row['opts'] ?? '');
        $storage = cart_storage_from_options($product, $opts);
        $pricing = product_purchase_pricing($product, $storage);
        $price = (float) $pricing['price'];
        $qty = (int) $row['qty'];
        $sum = $price * $qty;
        $regularSum = (float) $pricing['regular_price'] * $qty;
        $total += $sum;
        $regularTotal += $regularSum;
        $promotionSavings += max(0, $regularSum - $sum);

        $items[] = [
            'key' => $key,
            'product' => $product,
            'qty' => $qty,
            'opts' => $opts,
            'storage' => $storage,
            'price' => $price,
            'regular_price' => (float) $pricing['regular_price'],
            'promotion_discount' => (float) $pricing['discount'],
            'promotion' => $pricing['promotion'],
            'sum' => $sum,
        ];
    }
    return ['items' => $items, 'total' => $total, 'regular_total' => $regularTotal, 'promotion_savings' => $promotionSavings];
}

function setting(string $key, string $default = ''): string
{
    static $all = null;
    if ($all === null) {
        $all = [];
        try {
            foreach (db()->query('SELECT k, v FROM settings') as $row) {
                $all[$row['k']] = (string) $row['v'];
            }
        } catch (Throwable $e) {
            return $default;
        }
    }
    return $all[$key] ?? $default;
}

function categories(): array
{
    return db()->query('SELECT * FROM categories ORDER BY sort, id')->fetchAll();
}

function json_arr(?string $raw): array
{
    if (!$raw) {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function slugify(string $text): string
{
    $map = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '',
        'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];
    $text = mb_strtolower($text, 'UTF-8');
    $text = strtr($text, $map);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-') ?: 'item';
}

function product_by_slug(string $slug): ?array
{
    $st = db()->prepare('SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM products p JOIN categories c ON c.id = p.category_id WHERE p.slug = ?');
    $st->execute([$slug]);
    return $st->fetch() ?: null;
}

function product_by_id(int $id): ?array
{
    $st = db()->prepare('SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM products p JOIN categories c ON c.id = p.category_id WHERE p.id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function product_url(array $product): string
{
    return url('product.php?id=' . (int) $product['id']);
}

function product_reviews(int $productId): array
{
    $st = db()->prepare('SELECT r.*, u.name FROM reviews r JOIN users u ON u.id = r.user_id WHERE r.product_id = ? ORDER BY r.id DESC');
    $st->execute([$productId]);
    return $st->fetchAll();
}

function review_stats(array $reviews): array
{
    $count = count($reviews);
    if ($count === 0) {
        return ['count' => 0, 'avg' => 0.0];
    }
    $sum = 0;
    foreach ($reviews as $r) {
        $sum += (int) $r['rating'];
    }
    return ['count' => $count, 'avg' => round($sum / $count, 1)];
}

function user_bought_product(int $userId, int $productId): bool
{
    $st = db()->prepare(
        "SELECT 1 FROM orders o
         JOIN order_items oi ON oi.order_id = o.id
         WHERE o.user_id = ? AND oi.product_id = ? AND o.status = 'delivered'
         LIMIT 1"
    );
    $st->execute([$userId, $productId]);
    return (bool) $st->fetchColumn();
}

function user_review(int $userId, int $productId): ?array
{
    $st = db()->prepare('SELECT * FROM reviews WHERE user_id = ? AND product_id = ? LIMIT 1');
    $st->execute([$userId, $productId]);
    $row = $st->fetch();
    return $row ?: null;
}

function review_stats_for_product(int $productId): array
{
    $st = db()->prepare('SELECT COUNT(*) AS cnt, COALESCE(ROUND(AVG(rating), 1), 0) AS avg_rating FROM reviews WHERE product_id = ?');
    $st->execute([$productId]);
    $row = $st->fetch() ?: ['cnt' => 0, 'avg_rating' => 0];
    return ['count' => (int) $row['cnt'], 'avg' => (float) $row['avg_rating']];
}

function review_distribution(int $productId): array
{
    $out = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
    $st = db()->prepare('SELECT rating, COUNT(*) AS cnt FROM reviews WHERE product_id = ? GROUP BY rating');
    $st->execute([$productId]);
    foreach ($st->fetchAll() as $row) {
        $rating = (int) $row['rating'];
        if (isset($out[$rating])) {
            $out[$rating] = (int) $row['cnt'];
        }
    }
    return $out;
}

function clean_review_text(string $text): string
{
    $text = strip_tags($text);
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    return trim($text);
}

function save_review(int $userId, int $productId, int $rating, string $text): array
{
    $existing = user_review($userId, $productId);
    $st = db()->prepare(
        'INSERT INTO reviews (product_id, user_id, rating, text) VALUES (?,?,?,?)
         ON DUPLICATE KEY UPDATE rating = VALUES(rating), text = VALUES(text), created_at = CURRENT_TIMESTAMP'
    );
    $st->execute([$productId, $userId, $rating, $text]);
    $review = user_review($userId, $productId);
    return [
        'action' => $existing ? 'updated' : 'created',
        'review' => $review,
    ];
}

function user_reviews_list(int $userId): array
{
    $st = db()->prepare(
        'SELECT r.*, p.name AS product_name, p.image, p.visual, p.slug, p.short_desc
         FROM reviews r
         JOIN products p ON p.id = r.product_id
         WHERE r.user_id = ?
         ORDER BY r.created_at DESC, r.id DESC'
    );
    $st->execute([$userId]);
    return $st->fetchAll();
}

function stars_html(int $rating, string $class = 'stars'): string
{
    $rating = max(0, min(5, $rating));
    $out = '<span class="' . e($class) . '" aria-label="' . $rating . ' из 5">';
    for ($i = 1; $i <= 5; $i++) {
        $out .= $i <= $rating ? '★' : '☆';
    }
    return $out . '</span>';
}

function review_access(int $productId): array
{
    $user = current_user();
    if (!$user) {
        return [
            'can' => false,
            'msg' => 'Чтобы оставить отзыв, <a href="' . e(url('login.php')) . '">войдите</a> в аккаунт.',
            'existing' => null,
        ];
    }
    if (!user_bought_product((int) $user['id'], $productId)) {
        return [
            'can' => false,
            'msg' => 'Отзыв можно оставить только после получения товара. Дождитесь статуса «Доставлен».',
            'existing' => null,
        ];
    }
    return [
        'can' => true,
        'msg' => '',
        'existing' => user_review((int) $user['id'], $productId),
    ];
}

function order_status_labels(): array
{
    return [
        'collecting' => 'Собирается',
        'on_way' => 'В пути',
        'delivered' => 'Доставлен',
        'cancelled' => 'Отменён',
    ];
}

function order_status_label(string $status): string
{
    $labels = order_status_labels();
    return $labels[$status] ?? $status;
}

function order_status_class(string $status): string
{
    $allowed = ['collecting', 'on_way', 'delivered', 'cancelled'];
    return in_array($status, $allowed, true) ? $status : 'collecting';
}

function in_wishlist(int $productId): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }
    $st = db()->prepare('SELECT 1 FROM wishlist WHERE user_id = ? AND product_id = ?');
    $st->execute([(int) $user['id'], $productId]);
    return (bool) $st->fetchColumn();
}

function discount_percent(array $product): int
{
    $price = (float) $product['price'];
    $old = (float) ($product['old_price'] ?? 0);
    if ($old <= $price || $old <= 0) {
        return 0;
    }
    return (int) round(100 - ($price / $old * 100));
}

function product_visual(array $product, int $w = 320, int $h = 320): string
{
    $type = (string) ($product['visual'] ?? 'phone');
    $colors = json_arr($product['colors'] ?? null);
    $hex = $colors[0]['hex'] ?? '#1d1d1f';
    $fill = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $hex) ? $hex : '#1d1d1f';
    $id = 'pv' . (int) $product['id'] . $w;

    switch ($type) {
        case 'macbook':
            $svg = <<<SVG
<svg viewBox="0 0 360 240" width="{$w}" height="{$h}" role="img" aria-label="">
  <rect x="38" y="18" width="284" height="168" rx="10" fill="#2b2b2f"/>
  <rect x="50" y="30" width="260" height="144" rx="4" fill="{$fill}"/>
  <rect x="20" y="186" width="320" height="14" rx="3" fill="#c8c8cc"/>
  <rect x="140" y="186" width="80" height="6" rx="2" fill="#a8a8ae"/>
</svg>
SVG;
            break;
        case 'imac':
            $svg = <<<SVG
<svg viewBox="0 0 360 260" width="{$w}" height="{$h}" role="img">
  <rect x="30" y="16" width="300" height="190" rx="12" fill="#e8e8ed"/>
  <rect x="42" y="28" width="276" height="166" rx="4" fill="{$fill}"/>
  <rect x="168" y="206" width="24" height="28" fill="#d2d2d7"/>
  <rect x="120" y="234" width="120" height="8" rx="2" fill="#c8c8cc"/>
</svg>
SVG;
            break;
        case 'ipad':
            $svg = <<<SVG
<svg viewBox="0 0 280 360" width="{$w}" height="{$h}" role="img">
  <rect x="40" y="16" width="200" height="328" rx="22" fill="#1d1d1f"/>
  <rect x="52" y="36" width="176" height="288" rx="6" fill="{$fill}"/>
  <circle cx="140" cy="330" r="8" fill="#3a3a3c"/>
</svg>
SVG;
            break;
        case 'watch':
            $svg = <<<SVG
<svg viewBox="0 0 220 280" width="{$w}" height="{$h}" role="img">
  <rect x="78" y="8" width="64" height="52" rx="10" fill="#3a3a3c"/>
  <rect x="48" y="58" width="124" height="154" rx="32" fill="#1d1d1f"/>
  <rect x="60" y="70" width="100" height="130" rx="24" fill="{$fill}"/>
  <rect x="78" y="212" width="64" height="52" rx="10" fill="#3a3a3c"/>
</svg>
SVG;
            break;
        case 'airpods':
            $svg = <<<SVG
<svg viewBox="0 0 280 220" width="{$w}" height="{$h}" role="img">
  <ellipse cx="90" cy="70" rx="36" ry="28" fill="{$fill}"/>
  <rect x="78" y="70" width="16" height="90" rx="8" fill="{$fill}"/>
  <ellipse cx="190" cy="70" rx="36" ry="28" fill="{$fill}"/>
  <rect x="186" y="70" width="16" height="90" rx="8" fill="{$fill}"/>
</svg>
SVG;
            break;
        case 'max':
            $svg = <<<SVG
<svg viewBox="0 0 280 240" width="{$w}" height="{$h}" role="img">
  <path d="M50 110 C50 40 230 40 230 110" fill="none" stroke="{$fill}" stroke-width="18"/>
  <rect x="28" y="100" width="48" height="88" rx="16" fill="{$fill}"/>
  <rect x="204" y="100" width="48" height="88" rx="16" fill="{$fill}"/>
</svg>
SVG;
            break;
        case 'mini':
            $svg = <<<SVG
<svg viewBox="0 0 240 180" width="{$w}" height="{$h}" role="img">
  <rect x="40" y="40" width="160" height="90" rx="10" fill="{$fill}"/>
  <circle cx="68" cy="85" r="6" fill="#28cd41"/>
</svg>
SVG;
            break;
        case 'acc':
            $svg = <<<SVG
<svg viewBox="0 0 240 240" width="{$w}" height="{$h}" role="img">
  <circle cx="120" cy="120" r="70" fill="#e8e8ed"/>
  <circle cx="120" cy="120" r="28" fill="{$fill}"/>
  <circle cx="120" cy="120" r="10" fill="#f5f5f7"/>
</svg>
SVG;
            break;
        default:
            $svg = <<<SVG
<svg viewBox="0 0 220 420" width="{$w}" height="{$h}" role="img">
  <rect x="40" y="12" width="140" height="396" rx="28" fill="#1d1d1f"/>
  <rect x="50" y="28" width="120" height="364" rx="18" fill="{$fill}"/>
  <rect x="88" y="36" width="44" height="14" rx="7" fill="#1d1d1f"/>
</svg>
SVG;
    }

    return '<div class="visual" style="width:' . (int) $w . 'px;max-width:100%">' . $svg . '</div>';
}

function product_image_src(array $p)
{
    $img = trim((string) ($p['image'] ?? ''));
    if ($img === '') {
        $img = product_image_file((string) ($p['slug'] ?? ''), (string) ($p['visual'] ?? 'phone'));
    }
    if (preg_match('#^https?://#i', $img)) {
        return $img;
    }
    return url($img);
}

function product_photo(array $p, $class = 'photo')
{
    $src = product_image_src($p);
    $webp = product_image_webp_src($p);
    $alt = e(product_image_alt($p));
    $title = e(product_image_title($p));
    $w = (int) ($p['image_width'] ?? 0);
    $h = (int) ($p['image_height'] ?? 0);
    $size = ($w > 0 && $h > 0) ? ' width="' . $w . '" height="' . $h . '"' : '';
    $img = '<img src="' . e($src) . '" alt="' . $alt . '" title="' . $title . '"' . $size . ' loading="lazy" decoding="async">';
    if ($webp !== '') $img = '<picture><source srcset="' . e($webp) . '" type="image/webp">' . $img . '</picture>';
    return '<div class="' . e($class) . '">' . $img . '</div>';
}


function promotion_type_labels(): array
{
    return [
        'day' => 'Акция дня',
        'week' => 'Акция недели',
        'month' => 'Акция месяца',
    ];
}

function promotion_type_label(string $type): string
{
    $labels = promotion_type_labels();
    return $labels[$type] ?? 'Акция';
}

function promotion_duration_days(string $type): int
{
    if ($type === 'week') {
        return 7;
    }
    if ($type === 'month') {
        return 30;
    }
    return 1;
}

function promotion_duration_label(string $type): string
{
    $days = promotion_duration_days($type);
    if ($days === 1) {
        return '1 день';
    }
    return $days . ' дней';
}

function promotion_end_date(string $type, string $startDate): string
{
    $start = DateTimeImmutable::createFromFormat('!Y-m-d', $startDate);
    if (!$start || $start->format('Y-m-d') !== $startDate) {
        $start = new DateTimeImmutable('today');
    }
    $days = promotion_duration_days($type);
    if ($days > 1) {
        $start = $start->modify('+' . ($days - 1) . ' days');
    }
    return $start->format('Y-m-d');
}

function promotion_period_days(string $startDate, string $endDate): int
{
    $start = DateTimeImmutable::createFromFormat('!Y-m-d', $startDate);
    $end = DateTimeImmutable::createFromFormat('!Y-m-d', $endDate);
    if (!$start || !$end || $end < $start) {
        return 1;
    }
    return (int) $start->diff($end)->days + 1;
}

function promotion_period_label(array $promotion): string
{
    $days = promotion_period_days((string) ($promotion['start_date'] ?? ''), (string) ($promotion['end_date'] ?? ''));
    $mod10 = $days % 10;
    $mod100 = $days % 100;
    if ($mod10 === 1 && $mod100 !== 11) {
        return $days . ' день';
    }
    if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
        return $days . ' дня';
    }
    return $days . ' дней';
}

function active_promotions(int $limit = 0): array
{
    // Публично выводятся только опубликованные акции, срок которых действует сегодня.
    $sql = "SELECT a.*, p.name AS product_name, p.price AS product_price, p.image AS product_image, p.visual AS product_visual, p.slug AS product_slug
            FROM promotions a
            JOIN products p ON p.id = a.product_id
            WHERE a.published = 1 AND CURDATE() BETWEEN a.start_date AND a.end_date
            ORDER BY FIELD(a.type,'day','week','month'), a.id DESC";
    if ($limit > 0) {
        $sql .= ' LIMIT ' . (int) $limit;
    }
    return db()->query($sql)->fetchAll();
}

function active_promotion_for_product(int $productId): ?array
{
    static $cache = [];
    if (array_key_exists($productId, $cache)) {
        return $cache[$productId];
    }
    try {
        $st = db()->prepare("SELECT a.*, p.price AS product_price
            FROM promotions a
            JOIN products p ON p.id = a.product_id
            WHERE a.product_id = ?
              AND a.published = 1
              AND CURDATE() BETWEEN a.start_date AND a.end_date
              AND a.promo_price IS NOT NULL
              AND a.promo_price > 0
            ORDER BY a.promo_price ASC, FIELD(a.type,'day','week','month'), a.id DESC
            LIMIT 1");
        $st->execute([$productId]);
        $promotion = $st->fetch() ?: null;
        if ($promotion && (float) $promotion['promo_price'] >= (float) $promotion['product_price']) {
            $promotion = null;
        }
        $cache[$productId] = $promotion;
    } catch (Throwable $e) {
        $cache[$productId] = null;
    }
    return $cache[$productId];
}

function product_storage_price(array $product, string $storage = ''): float
{
    $base = (float) ($product['price'] ?? 0);
    if ($storage === '') {
        return $base;
    }
    foreach (json_arr((string) ($product['storage_options'] ?? '')) as $option) {
        if ((string) ($option['label'] ?? '') === $storage) {
            $price = (float) ($option['price'] ?? 0);
            return $price > 0 ? $price : $base;
        }
    }
    return $base;
}

function product_purchase_pricing(array $product, string $storage = '', ?array $promotion = null): array
{
    $regular = product_storage_price($product, $storage);
    if ($promotion === null) {
        $promotion = active_promotion_for_product((int) ($product['id'] ?? 0));
    }

    $discount = 0.0;
    if ($promotion) {
        $base = (float) ($product['price'] ?? 0);
        $promoBase = (float) ($promotion['promo_price'] ?? 0);
        if ($base > 0 && $promoBase > 0 && $promoBase < $base) {
            $discount = $base - $promoBase;
        } else {
            $promotion = null;
        }
    }

    return [
        'regular_price' => $regular,
        'price' => max(0, $regular - $discount),
        'discount' => $discount,
        'promotion' => $promotion,
    ];
}

function cart_storage_from_options(array $product, string $opts): string
{
    if ($opts === '') {
        return '';
    }
    foreach (json_arr((string) ($product['storage_options'] ?? '')) as $option) {
        $label = (string) ($option['label'] ?? '');
        if ($label === '') {
            continue;
        }
        if ($opts === $label || substr($opts, -strlen($label)) === $label) {
            return $label;
        }
    }
    return '';
}

function promotion_image_src(array $promotion): string
{
    $img = trim((string) ($promotion['image'] ?? ''));
    if ($img !== '') {
        return preg_match('#^https?://#i', $img) ? $img : url($img);
    }
    $product = [
        'image' => (string) ($promotion['product_image'] ?? ''),
        'slug' => (string) ($promotion['product_slug'] ?? ''),
        'visual' => (string) ($promotion['product_visual'] ?? 'phone'),
    ];
    return product_image_src($product);
}

function promotion_status(array $promotion): string
{
    if (!(int) ($promotion['published'] ?? 0)) {
        return 'Черновик';
    }
    $today = date('Y-m-d');
    if ($today < (string) $promotion['start_date']) {
        return 'Запланирована';
    }
    if ($today > (string) $promotion['end_date']) {
        return 'Завершена';
    }
    return 'Активна';
}

function handle_promotion_upload(string $old = ''): string
{
    $url = trim((string) ($_POST['promo_image_url'] ?? ''));
    if ($url !== '' && preg_match('#^(https?://|assets/)#i', $url)) {
        $old = $url;
    }
    if (empty($_FILES['promo_image_file']['tmp_name']) || !is_uploaded_file($_FILES['promo_image_file']['tmp_name'])) {
        return $old;
    }
    $ext = strtolower(pathinfo((string) $_FILES['promo_image_file']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg' => 1, 'jpeg' => 1, 'png' => 1, 'webp' => 1, 'gif' => 1];
    if (!isset($allowed[$ext]) || (int) $_FILES['promo_image_file']['size'] > 6 * 1024 * 1024) {
        return $old;
    }
    if ($ext === 'jpeg') {
        $ext = 'jpg';
    }
    $dir = APP_ROOT . '/assets/img/promotions';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $name = 'promo-' . date('YmdHis') . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
    if (!move_uploaded_file($_FILES['promo_image_file']['tmp_name'], $dir . '/' . $name)) {
        return $old;
    }
    return 'assets/img/promotions/' . $name;
}

function handle_product_upload($old = '')
{
    $url = trim((string) ($_POST['image_url'] ?? ''));
    if ($url !== '' && preg_match('#^(https?://|assets/)#i', $url)) {
        $old = $url;
    }
    if (empty($_FILES['image_file']['tmp_name']) || !is_uploaded_file($_FILES['image_file']['tmp_name'])) {
        return $old;
    }
    $ext = strtolower(pathinfo((string) $_FILES['image_file']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg' => 1, 'jpeg' => 1, 'png' => 1, 'webp' => 1, 'gif' => 1];
    if (!isset($allowed[$ext]) || (int) $_FILES['image_file']['size'] > 6 * 1024 * 1024) {
        return $old;
    }
    if ($ext === 'jpeg') {
        $ext = 'jpg';
    }
    $dir = APP_ROOT . '/assets/img/products';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $name = 'p' . date('YmdHis') . bin2hex(random_bytes(3)) . '.' . $ext;
    if (!move_uploaded_file($_FILES['image_file']['tmp_name'], $dir . '/' . $name)) {
        return $old;
    }
    return 'assets/img/products/' . $name;
}

function product_image_webp_src(array $p): string
{
    $webp = trim((string) ($p['image_webp'] ?? ''));
    if ($webp === '') {
        $img = trim((string) ($p['image'] ?? ''));
        if ($img !== '' && !preg_match('#^https?://#i', $img)) {
            $candidate = preg_replace('/\.(jpe?g|png)$/i', '.webp', $img);
            if ($candidate && is_file(APP_ROOT . '/' . ltrim($candidate, '/'))) $webp = $candidate;
        }
    }
    if ($webp === '') return '';
    return preg_match('#^https?://#i', $webp) ? $webp : url($webp);
}

function product_image_alt(array $p): string
{
    $alt = trim((string) ($p['image_alt'] ?? ''));
    return $alt !== '' ? $alt : ('Изображение товара ' . (string) ($p['name'] ?? ''));
}

function product_image_title(array $p): string
{
    $title = trim((string) ($p['image_title'] ?? ''));
    return $title !== '' ? $title : (string) ($p['name'] ?? '');
}

function get_catalog_products(int $limit = 20, int $offset = 0): array
{
    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);
    $sql = "SELECT p.*,
                   COALESCE(rv.review_count,0) AS review_count,
                   COALESCE(rv.avg_rating,0) AS avg_rating,
                   pr.id AS promotion_id, pr.type AS promotion_type,
                   pr.title AS promotion_title, pr.promo_price
            FROM products p
            LEFT JOIN (
                SELECT product_id, COUNT(*) AS review_count, ROUND(AVG(rating),1) AS avg_rating
                FROM reviews GROUP BY product_id
            ) rv ON rv.product_id=p.id
            LEFT JOIN promotions pr ON pr.id=(
                SELECT pr2.id FROM promotions pr2
                WHERE pr2.product_id=p.id AND pr2.published=1
                  AND CURDATE() BETWEEN pr2.start_date AND pr2.end_date
                ORDER BY CASE WHEN pr2.promo_price IS NULL THEN 1 ELSE 0 END, pr2.promo_price ASC, pr2.id DESC
                LIMIT 1
            )
            ORDER BY p.featured DESC, p.created_at DESC, p.id DESC
            LIMIT :lim OFFSET :off";
    $st = db()->prepare($sql);
    $st->bindValue(':lim', $limit, PDO::PARAM_INT);
    $st->bindValue(':off', $offset, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

function backfill_product_seo_fields(): void
{
    $rows = db()->query('SELECT id,name,image,image_alt,image_webp,image_width,image_height,image_title FROM products')->fetchAll();
    $up = db()->prepare('UPDATE products SET image_alt=?,image_webp=?,image_width=?,image_height=?,image_title=? WHERE id=?');
    foreach ($rows as $row) {
        $image = trim((string) $row['image']);
        $alt = trim((string) ($row['image_alt'] ?? '')) ?: ('Изображение товара ' . (string) $row['name']);
        $title = trim((string) ($row['image_title'] ?? '')) ?: (string) $row['name'];
        $webp = trim((string) ($row['image_webp'] ?? ''));
        $width = (int) ($row['image_width'] ?? 0);
        $height = (int) ($row['image_height'] ?? 0);
        if ($image !== '' && !preg_match('#^https?://#i', $image)) {
            $local = APP_ROOT . '/' . ltrim($image, '/');
            if (($width <= 0 || $height <= 0) && is_file($local)) {
                $size = @getimagesize($local);
                if (is_array($size)) { $width=(int)$size[0]; $height=(int)$size[1]; }
            }
            if ($webp === '') {
                $candidate = preg_replace('/\.(jpe?g|png)$/i', '.webp', $image);
                if ($candidate && is_file(APP_ROOT . '/' . ltrim($candidate, '/'))) $webp = $candidate;
            }
        }
        $up->execute([$alt,$webp !== '' ? $webp : null,$width ?: null,$height ?: null,$title,(int)$row['id']]);
    }
}

function apply_2026_catalog_refresh(): void
{
    // One-time catalog refresh. Keeps the existing single-image-per-product model.
    try {
        if (setting('catalog_refresh_2026_v1', '') === '1') return;

        $iphone18 = "https://www.apple.com/newsroom/images/2026/09/apple-debuts-iphone-18-pro-and-iphone-18-pro-max/article/Apple-iPhone-18-Pro-2up-260909_inline.jpg.large.jpg";
        $iphone18Max = "https://www.apple.com/newsroom/images/2026/09/apple-debuts-iphone-18-pro-and-iphone-18-pro-max/article/Apple-iPhone-18-Pro-color-lineup-260909_big.jpg.large.jpg";
        $up = db()->prepare("UPDATE products SET name=?, slug=?, sku=?, short_desc=?, description=?, price=?, old_price=?, image=?, image_webp=NULL, image_alt=?, image_title=?, image_width=NULL, image_height=NULL, specs=?, colors=?, storage_options=? WHERE id=?");
        $up->execute(['iPhone 18 Pro','iphone-18-pro','IP18P-256','A20 Pro. Камера Fusion 48 Мп с переменной диафрагмой.','iPhone 18 Pro: A20 Pro, новая 48MP Fusion Main камера с переменной диафрагмой, обновлённый Dynamic Island и iOS 27.',159990,169990,$iphone18,'iPhone 18 Pro — официальный пресс-рендер Apple','iPhone 18 Pro',json_encode(['Дисплей'=>'6,3" Super Retina XDR','Чип'=>'A20 Pro','Камера'=>'48 Мп Fusion Main, переменная диафрагма','Система'=>'iOS 27'],JSON_UNESCAPED_UNICODE),json_encode([['name'=>'Чёрный','hex'=>'#1d1d1f'],['name'=>'Серебристый','hex'=>'#e6e6e6'],['name'=>'Ледниковый','hex'=>'#dbe8ee'],['name'=>'Бургундский','hex'=>'#6f1d32']],JSON_UNESCAPED_UNICODE),json_encode([['label'=>'256 ГБ','price'=>159990],['label'=>'512 ГБ','price'=>179990],['label'=>'1 ТБ','price'=>209990],['label'=>'2 ТБ','price'=>249990]],JSON_UNESCAPED_UNICODE),1]);
        $up->execute(['iPhone 18 Pro Max','iphone-18-pro-max','IP18PM-256','A20 Pro. 6,9-дюймовый дисплей. Камера Fusion 48 Мп.','iPhone 18 Pro Max: большой Pro-дисплей, A20 Pro, 48MP Fusion Main с переменной диафрагмой и увеличенное время автономной работы.',179990,189990,$iphone18Max,'iPhone 18 Pro Max — линейка официальных цветов Apple','iPhone 18 Pro Max',json_encode(['Дисплей'=>'6,9" Super Retina XDR','Чип'=>'A20 Pro','Камера'=>'48 Мп Fusion Main, переменная диафрагма','Система'=>'iOS 27'],JSON_UNESCAPED_UNICODE),json_encode([['name'=>'Чёрный','hex'=>'#1d1d1f'],['name'=>'Серебристый','hex'=>'#e6e6e6'],['name'=>'Ледниковый','hex'=>'#dbe8ee'],['name'=>'Бургундский','hex'=>'#6f1d32']],JSON_UNESCAPED_UNICODE),json_encode([['label'=>'256 ГБ','price'=>179990],['label'=>'512 ГБ','price'=>199990],['label'=>'1 ТБ','price'=>229990],['label'=>'2 ТБ','price'=>269990]],JSON_UNESCAPED_UNICODE),2]);

    $images = [
        15 => "https://cdsassets.apple.com/live/7WUAS350/images/ipad/ipad/fall-2022-10-gen-ipad.png",
        16 => "https://cdsassets.apple.com/live/7WUAS350/images/ipad/ipad/ipad-mini-2021-colors.png",
        54 => "https://store.storeimages.cdn-apple.com/1/as-images.apple.com/is/MQTP3?.v=MVJhVmI0YmhYQVJ5Y0VDdzF1YWp3MmorYzFkTG5HaE9wejd5WUxYZjRMOHoveDdpQVpwS0ltY2w2UW05aU90TzVtaW9peGdOaitwV1Nxb1VublZoTVE&fmt=jpeg&hei=2000&qlt=90&wid=2000",
        55 => "https://images.kabum.com.br/produtos/fotos/sync_mirakl/401977/xlarge/Bateria-Magsafe-Apple-Mjwy3am-a-Carregador-Para-Iphone-12-13-mini-Pro-Pro-Max-_1749558614.png",
        60 => "https://store.storeimages.cdn-apple.com/8756/as-images.apple.com/is/MYY63?.v=1723236736369&fmt=jpeg&hei=2000&qlt=95&wid=2000",
        27 => "https://cdsassets.apple.com/live/7WUAS350/images/iphone/fall-2023-iphone-colors-iphone-15-plus.png",
        28 => "https://cdsassets.apple.com/live/7WUAS350/images/iphone/fall-2023-iphone-colors-iphone-15-pro.png",
        29 => "https://cdsassets.apple.com/live/7WUAS350/images/iphone/fall-2023-iphone-colors-iphone-15-pro-max.png",
        30 => "https://cdsassets.apple.com/live/7WUAS350/images/iphone/iphone-14-colors-spring-2023.png",
        31 => "https://cdsassets.apple.com/live/7WUAS350/images/iphone/iphone-14-plus-colors-spring-2023.png",
        32 => "https://cdsassets.apple.com/live/7WUAS350/images/iphone/iphone-14-pro-colors.png",
        33 => "https://cdsassets.apple.com/live/7WUAS350/images/iphone/iphone-se-3rd-gen-colors.png",
        34 => "https://cdsassets.apple.com/live/7WUAS350/images/iphone/2022-spring-iphone13-colors.png",
        35 => "https://cdsassets.apple.com/live/7WUAS350/images/iphone/2022-iphone13-mini-colors.png",
        36 => "https://cdsassets.apple.com/live/7WUAS350/images/iphone/2021-iphone12-colors.png",
        37 => "https://cdsassets.apple.com/live/7WUAS350/images/macbook-air/2022-macbook-air-m2-colors.png",
        38 => "https://cdsassets.apple.com/live/7WUAS350/images/macbook-air/2023-macbook-air-15in-m2-colors.png",
        39 => "https://cdsassets.apple.com/live/7WUAS350/images/macbook-pro/macbook-pro-14in-m3-nov-2023-silver-space-gray.png",
        40 => "https://cdsassets.apple.com/live/7WUAS350/images/macbook-pro/macbook-pro-16in-m3-pro-m3-max-nov-2023-silver-space-black.png",
        41 => "https://cdsassets.apple.com/live/7WUAS350/images/mac-mini/mac-mini-2023-m2.png",
        42 => "https://cdsassets.apple.com/live/7WUAS350/images/mac-studio/mac-studio-2023-m2-max.png",
        43 => "https://cdsassets.apple.com/live/7WUAS350/images/imac/imac-24in-2023-four-ports-colors.png",
        44 => "https://cdsassets.apple.com/live/7WUAS350/images/macbook-pro/macbook-pro-13-in-M2-2022.png",
        45 => "https://cdsassets.apple.com/live/7WUAS350/images/ipad/spring-2024-3.png",
        46 => "https://cdsassets.apple.com/live/7WUAS350/images/ipad/ipad/fall-2022-12-9-inch-6gen-ipad-pro.png",
        47 => "https://cdsassets.apple.com/live/7WUAS350/images/ipad/ipad/ipad-2021-colors.png",
        48 => "https://cdsassets.apple.com/live/7WUAS350/images/ipad/ipad/ipad-mini-2021-colors.png",
        49 => "https://cdsassets.apple.com/live/7WUAS350/images/apple-watch/apple-watch-series-9-gps.png",
        50 => "https://cdsassets.apple.com/live/7WUAS350/images/apple-watch/fall-2022-apple-watch-ultra.png",
        51 => "https://cdsassets.apple.com/live/7WUAS350/images/apple-watch/fall-2022-watch-series8-aluminum-gps.png",
        52 => "https://cdsassets.apple.com/live/7WUAS350/images/airpods/airpods-lightning-charging-case-magsafe-charging-case-3rd-generation.png",
        53 => "https://cdsassets.apple.com/live/7WUAS350/images/airpods/airpods-wireless-charging-case.png",
        56 => "https://store.storeimages.cdn-apple.com/4982/as-images.apple.com/is/MXK53?.v=1730508286345&fmt=jpeg&hei=2000&qlt=95&wid=2000",
        57 => "https://estore.ua/media/catalog/product/cache/8/image/650x650/9df78eab33525d08d6e5fb8d27136e95/a/p/apple-magic-trackpad_9_.jpg",
        58 => "https://istyle.rs/cdn/shop/files/mu7v2_av2_geo_emea_1_1_3.jpg?v=1759426771&width=1445",
        59 => "https://m.media-amazon.com/images/I/71grKCk9ntL.jpg",
    ];
    $imageUpdate = db()->prepare('UPDATE products SET image=?, image_webp=NULL, image_width=NULL, image_height=NULL WHERE id=?');
    foreach ($images as $id => $image) $imageUpdate->execute([$image, $id]);

        try {
            db()->prepare("UPDATE promotions SET title='iPhone 18 Pro — предложение дня', description='Актуальное предложение на новое поколение iPhone Pro.' WHERE product_id=1")->execute();
        } catch (Throwable $e) {}
        db()->prepare('INSERT INTO settings (k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)')->execute(['catalog_refresh_2026_v1','1']);
    } catch (Throwable $e) {
        // Store remains usable even when a particular external catalog update fails.
    }
}

function ensure_schema()
{
    try {
        $col = db()->query("SHOW COLUMNS FROM products LIKE 'image'")->fetch();
        if (!$col) {
            db()->exec("ALTER TABLE products ADD COLUMN image VARCHAR(255) NOT NULL DEFAULT '' AFTER visual");
        }
        foreach ([
            'image_alt' => "VARCHAR(255) NULL AFTER image",
            'image_webp' => "VARCHAR(500) NULL AFTER image_alt",
            'image_width' => "SMALLINT UNSIGNED NULL AFTER image_webp",
            'image_height' => "SMALLINT UNSIGNED NULL AFTER image_width",
            'image_title' => "VARCHAR(255) NULL AFTER image_height",
        ] as $seoColumn => $seoDefinition) {
            if (!db()->query("SHOW COLUMNS FROM products LIKE " . db()->quote($seoColumn))->fetch()) {
                db()->exec("ALTER TABLE products ADD COLUMN {$seoColumn} {$seoDefinition}");
            }
        }
        try {
            $idx = db()->query("SHOW INDEX FROM products WHERE Key_name='idx_products_created'")->fetch();
            if (!$idx) db()->exec("ALTER TABLE products ADD INDEX idx_products_created (created_at)");
        } catch (Throwable $e) {}
        $settingUp = db()->prepare('INSERT INTO settings (k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)');
        foreach ([
            'site_logo' => 'uploads/logo.svg',
            'site_logo_alt' => 'Логотип интернет-магазина iTech',
            'site_name' => 'iTech',
            'site_description' => 'Интернет-магазин техники Apple: каталог, акции, отзывы и доставка.',
        ] as $k => $v) $settingUp->execute([$k,$v]);

        $deliveryMethodCol = db()->query("SHOW COLUMNS FROM orders LIKE 'delivery_method'")->fetch();
        if (!$deliveryMethodCol) {
            db()->exec("ALTER TABLE orders ADD COLUMN delivery_method ENUM('delivery','pickup') NOT NULL DEFAULT 'delivery' AFTER address");
        }

        $promotionTable = db()->query("SHOW TABLES LIKE 'promotions'")->fetch();
        if (!$promotionTable) {
            db()->exec("CREATE TABLE promotions (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              product_id INT UNSIGNED NOT NULL,
              type ENUM('day','week','month') NOT NULL,
              title VARCHAR(180) NOT NULL,
              description VARCHAR(500) NOT NULL DEFAULT '',
              image VARCHAR(255) NOT NULL DEFAULT '',
              promo_price DECIMAL(12,2) DEFAULT NULL,
              start_date DATE NOT NULL,
              end_date DATE NOT NULL,
              published TINYINT(1) NOT NULL DEFAULT 1,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              INDEX idx_promo_active (published,start_date,end_date),
              INDEX idx_promo_product (product_id),
              CONSTRAINT fk_promo_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
            ) ENGINE=InnoDB");
            $today = date('Y-m-d');
            $weekEnd = date('Y-m-d', strtotime('+6 days'));
            $monthEnd = date('Y-m-d', strtotime('+29 days'));
            $seed = db()->prepare('INSERT INTO promotions (product_id,type,title,description,promo_price,start_date,end_date,published) VALUES (?,?,?,?,?,?,?,1)');
            $seed->execute([1, 'day', 'iPhone 16 Pro — предложение дня', 'Специальная цена действует только сегодня.', 109990, $today, $today]);
            $seed->execute([6, 'week', 'MacBook Air M3 — акция недели', 'Выгодное предложение на лёгкий MacBook Air для учёбы и работы.', 119990, $today, $weekEnd]);
            $seed->execute([8, 'month', 'MacBook Pro M4 — акция месяца', 'Мощный MacBook Pro по специальной цене в течение месяца.', 189990, $today, $monthEnd]);
        }

        $orderItemRegular = db()->query("SHOW COLUMNS FROM order_items LIKE 'regular_price'")->fetch();
        if (!$orderItemRegular) {
            db()->exec("ALTER TABLE order_items ADD COLUMN regular_price DECIMAL(12,2) DEFAULT NULL AFTER price");
        }
        $orderItemPromotion = db()->query("SHOW COLUMNS FROM order_items LIKE 'promotion_id'")->fetch();
        if (!$orderItemPromotion) {
            db()->exec("ALTER TABLE order_items ADD COLUMN promotion_id INT UNSIGNED DEFAULT NULL AFTER regular_price");
        }
        $orderItemPromotionTitle = db()->query("SHOW COLUMNS FROM order_items LIKE 'promotion_title'")->fetch();
        if (!$orderItemPromotionTitle) {
            db()->exec("ALTER TABLE order_items ADD COLUMN promotion_title VARCHAR(180) NOT NULL DEFAULT '' AFTER promotion_id");
        }
        $orderItemPromotionDiscount = db()->query("SHOW COLUMNS FROM order_items LIKE 'promotion_discount'")->fetch();
        if (!$orderItemPromotionDiscount) {
            db()->exec("ALTER TABLE order_items ADD COLUMN promotion_discount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER promotion_title");
        }

        try {
            $statusColumn = db()->query("SHOW COLUMNS FROM orders LIKE 'status'")->fetch();
            $statusType = strtolower((string) ($statusColumn['Type'] ?? ''));
            if (strpos($statusType, "'collecting'") === false || strpos($statusType, "'delivered'") === false) {
                db()->exec("ALTER TABLE orders MODIFY COLUMN status ENUM('new','paid','shipped','done','collecting','on_way','delivered','cancelled') NOT NULL DEFAULT 'collecting'");
                db()->exec("UPDATE orders SET status='collecting' WHERE status IN ('new','paid')");
                db()->exec("UPDATE orders SET status='on_way' WHERE status='shipped'");
                db()->exec("UPDATE orders SET status='delivered' WHERE status='done'");
                db()->exec("ALTER TABLE orders MODIFY COLUMN status ENUM('collecting','on_way','delivered','cancelled') NOT NULL DEFAULT 'collecting'");
            }
        } catch (Throwable $e) {
        }

        $rows = db()->query("SELECT id, slug, visual, image FROM products")->fetchAll();
        $up = db()->prepare('UPDATE products SET image = ? WHERE id = ?');
        foreach ($rows as $row) {
            if (trim((string) $row['image']) !== '') {
                continue;
            }
            $up->execute([product_image_file((string) $row['slug'], (string) $row['visual']), (int) $row['id']]);
        }

        apply_2026_catalog_refresh();
        backfill_product_seo_fields();

        $rev = db()->query("SHOW TABLES LIKE 'reviews'")->fetch();
        if (!$rev) {
            db()->exec(
                "CREATE TABLE reviews (
                  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  product_id INT UNSIGNED NOT NULL,
                  user_id INT UNSIGNED NOT NULL,
                  rating TINYINT NOT NULL,
                  text TEXT NOT NULL,
                  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  INDEX idx_rev_prod (product_id),
                  INDEX idx_rev_user (user_id),
                  UNIQUE KEY uq_rev_user_product (product_id, user_id),
                  CONSTRAINT chk_rev_rating CHECK (rating BETWEEN 1 AND 5),
                  CONSTRAINT fk_rev_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                  CONSTRAINT fk_rev_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB"
            );
        } else {
            $create = (string) db()->query('SHOW CREATE TABLE reviews')->fetch(PDO::FETCH_NUM)[1];
            if (strpos($create, 'fk_rev_product') === false) {
                try {
                    db()->exec('ALTER TABLE reviews ADD CONSTRAINT fk_rev_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE');
                } catch (Throwable $e) {
                }
            }
            if (strpos($create, 'fk_rev_user') === false) {
                try {
                    db()->exec('ALTER TABLE reviews ADD CONSTRAINT fk_rev_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE');
                } catch (Throwable $e) {
                }
            }
            if (strpos($create, 'chk_rev_rating') === false) {
                try {
                    db()->exec('ALTER TABLE reviews ADD CONSTRAINT chk_rev_rating CHECK (rating BETWEEN 1 AND 5)');
                } catch (Throwable $e) {
                }
            }
            if (strpos($create, 'uq_rev_user_product') === false) {
                try {
                    db()->exec('ALTER TABLE reviews ADD UNIQUE KEY uq_rev_user_product (product_id, user_id)');
                } catch (Throwable $e) {
                }
            }
            if (strpos($create, 'idx_rev_user') === false) {
                try {
                    db()->exec('ALTER TABLE reviews ADD INDEX idx_rev_user (user_id)');
                } catch (Throwable $e) {
                }
            }
        }
    } catch (Throwable $e) {
    }
}

function ensure_installed(): void
{
    if (!db_ready()) {
        exit('Ошибка подключения к базе данных.');
    }

    ensure_schema();
}
