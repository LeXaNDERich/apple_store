<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
ensure_installed();

$id = (int) ($_GET['id'] ?? 0);
$slug = trim((string) ($_GET['slug'] ?? ''));
$product = null;
if ($id > 0) {
    $product = product_by_id($id);
} elseif ($slug !== '') {
    $product = product_by_slug($slug);
    if ($product) {
        redirect('product.php?id=' . (int) $product['id']);
    }
}

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Товар не найден';
    require __DIR__ . '/includes/header.php';
    echo '<section class="section wrap"><h2>Товар не найден</h2><p><a href="' . e(url('catalog.php')) . '">В каталог</a> · <a href="' . e(url('profile.php')) . '">Личный кабинет</a></p></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? 'cart');
    if ($action === 'review') {
        $user = require_login();
        $rating = (int) ($_POST['rating'] ?? 0);
        $text = clean_review_text((string) ($_POST['text'] ?? ''));
        if ($rating < 1 || $rating > 5 || mb_strlen($text, 'UTF-8') < 3 || mb_strlen($text, 'UTF-8') > 2000) {
            flash('error', 'Проверьте оценку (1–5) и текст (от 3 до 2000 символов).');
            redirect('product.php?id=' . (int) $product['id'] . '#reviews');
        }
        if (!user_bought_product((int) $user['id'], (int) $product['id'])) {
            flash('error', 'Отзыв можно оставить только после получения товара. Дождитесь статуса «Доставлен».');
            redirect('product.php?id=' . (int) $product['id'] . '#reviews');
        }
        $saved = save_review((int) $user['id'], (int) $product['id'], $rating, $text);
        metrika_queue_goal('review_submit', [
            'product_id' => (string) $product['id'],
            'rating' => $rating,
        ]);
        flash('ok', $saved['action'] === 'updated' ? 'Отзыв обновлён.' : 'Отзыв опубликован.');
        redirect('product.php?id=' . (int) $product['id'] . '#reviews');
    }
    if ($action === 'wish') {
        $user = require_login();
        if (in_wishlist((int) $product['id'])) {
            db()->prepare('DELETE FROM wishlist WHERE user_id=? AND product_id=?')->execute([(int) $user['id'], (int) $product['id']]);
            flash('ok', 'Удалено из избранного.');
        } else {
            db()->prepare('INSERT INTO wishlist (user_id, product_id) VALUES (?,?)')->execute([(int) $user['id'], (int) $product['id']]);
            flash('ok', 'Добавлено в избранное.');
        }
        redirect('product.php?id=' . (int) $product['id']);
    }

    $qty = max(1, (int) ($_POST['qty'] ?? 1));
    $color = trim((string) ($_POST['color'] ?? ''));
    $storage = trim((string) ($_POST['storage'] ?? ''));
    $storageOptions = json_arr((string) $product['storage_options']);
    if ($storageOptions) {
        $validStorage = false;
        foreach ($storageOptions as $storageOption) {
            if ((string) ($storageOption['label'] ?? '') === $storage) {
                $validStorage = true;
                break;
            }
        }
        if (!$validStorage) {
            $storage = (string) ($storageOptions[0]['label'] ?? '');
        }
    }
    if ((int) $product['stock'] < $qty) {
        flash('error', 'Недостаточно товара на складе.');
        redirect('product.php?id=' . (int) $product['id']);
    }
    $opts = trim($color . ($color && $storage ? ' / ' : '') . $storage, ' /');
    cart_add((int) $product['id'], $qty, $opts);
    metrika_queue_goal('add_to_cart', [
        'product_id' => (string) $product['id'],
        'qty' => $qty,
        'color' => $color,
        'storage' => $storage,
    ]);
    flash('ok', 'Товар добавлен в корзину.');
    redirect('cart.php');
}

$colors = json_arr($product['colors']);
$storage = json_arr($product['storage_options']);
$specs = json_arr($product['specs']);
$purchasePricing = product_purchase_pricing($product);
$activePromotion = $purchasePricing['promotion'];
$displayPrice = (float) $purchasePricing['price'];
$regularPrice = (float) $purchasePricing['regular_price'];
$promotionDiscount = (float) $purchasePricing['discount'];
$promotionPercent = ($activePromotion && $regularPrice > 0 && $displayPrice < $regularPrice)
    ? (int) round((1 - ($displayPrice / $regularPrice)) * 100)
    : 0;
$reviews = product_reviews((int) $product['id']);
$stats = review_stats_for_product((int) $product['id']);
$distribution = review_distribution((int) $product['id']);
$access = review_access((int) $product['id']);
$productId = (int) $product['id'];
$rel = db()->prepare('SELECT * FROM products WHERE category_id = ? AND id != ? LIMIT 4');
$rel->execute([(int) $product['category_id'], (int) $product['id']]);
$related = $rel->fetchAll();
$avg = $stats['avg'];
$reviewCount = $stats['count'];

$pageTitle = $product['name'] . ' — отзывы, характеристики, цена | AppleStore';
$pageDescription = $product['name'] . ': отзывы владельцев, характеристики и цена. Читайте отзывы покупателей в AppleStore — ' . $product['short_desc'];
$pageKeywords = $product['name'] . ', отзывы, отзывы владельцев, характеристики, купить, AppleStore';
$canonicalUrl = absolute_url(product_url($product));
$ogType = 'product';
$ogImage = absolute_url(product_image_src($product));
$imgAlt = 'Фото ' . $product['name'] . ' — отзывы';
require __DIR__ . '/includes/header.php';
?>
<section class="wrap product-page" itemscope itemtype="https://schema.org/Product">
    <div class="crumbs">
        <a href="<?= e(url('catalog.php')) ?>">Каталог</a> /
        <a href="<?= e(url('catalog.php?cat=' . urlencode($product['category_slug']))) ?>"><?= e($product['category_name']) ?></a> /
        <span itemprop="name"><?= e($product['name']) ?></span>
        · <a href="<?= e(url('profile.php')) ?>">Личный кабинет</a>
    </div>
    <meta itemprop="sku" content="<?= e($product['sku']) ?>">
    <div class="product">
        <div class="gallery">
            <div class="photo">
                <img src="<?= e(product_image_src($product)) ?>" alt="<?= e($imgAlt) ?>" itemprop="image">
            </div>
        </div>
        <div class="product-info-panel">
            <p class="product-kicker">Официальная техника Apple</p>
            <?php if ($promotionPercent > 0): ?>
                <span class="tag promo-discount-tag product-promo-discount" data-promo-percent-display>−<?= $promotionPercent ?>%</span>
            <?php endif; ?>
            <h1><?= e($product['name']) ?></h1>
            <p class="muted" itemprop="description"><?= e($product['short_desc']) ?></p>
            <?php if ($reviewCount > 0): ?>
                <div class="agg-rating" itemprop="aggregateRating" itemscope itemtype="https://schema.org/AggregateRating">
                    <?= stars_html((int) round($avg)) ?>
                    <span class="muted">
                        <span itemprop="ratingValue"><?= e(number_format($avg, 1, '.', '')) ?></span>
                        · <span itemprop="reviewCount"><?= (int) $reviewCount ?></span> <?= (int) $reviewCount === 1 ? 'отзыв' : 'отзывов' ?>
                    </span>
                    <meta itemprop="bestRating" content="5">
                    <meta itemprop="worstRating" content="1">
                </div>
            <?php endif; ?>
            <p class="price" id="live-price" data-promo-discount="<?= e((string) $promotionDiscount) ?>" itemprop="offers" itemscope itemtype="https://schema.org/Offer">
                <meta itemprop="priceCurrency" content="RUB">
                <span itemprop="price" content="<?= e(number_format($displayPrice, 2, '.', '')) ?>"><?= format_price($displayPrice) ?></span>
                <?php if ($activePromotion): ?>
                    <span class="old" data-regular-price-display><?= format_price($regularPrice) ?></span>
                <?php endif; ?>
                <link itemprop="availability" href="https://schema.org/<?= (int) $product['stock'] > 0 ? 'InStock' : 'OutOfStock' ?>">
            </p>
            <?php if ($activePromotion): ?>
                <p class="promo-purchase-note"><?= e((string) $activePromotion['title']) ?> · экономия <?= format_price($promotionDiscount) ?></p>
            <?php endif; ?>
            <p class="stock <?= ((int) $product['stock'] <= 0) ? 'out' : '' ?>">
                <?= (int) $product['stock'] > 0 ? 'В наличии: ' . (int) $product['stock'] . ' шт. · арт. ' . e($product['sku']) : 'Нет в наличии' ?>
            </p>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="cart">
                <?php if ($colors): ?>
                    <label class="option-label">Цвет</label>
                    <div class="opts">
                        <?php foreach ($colors as $i => $c): ?>
                            <button type="button" class="swatch <?= $i === 0 ? 'on' : '' ?>" data-pick="color" data-value="<?= e($c['name']) ?>" style="background:<?= e($c['hex']) ?>" title="<?= e($c['name']) ?>"></button>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="color" id="color" value="<?= e($colors[0]['name']) ?>">
                <?php endif; ?>
                <?php if ($storage): ?>
                    <label class="option-label">Конфигурация</label>
                    <div class="opts">
                        <?php foreach ($storage as $i => $s): ?>
                            <button type="button" class="chip <?= $i === 0 ? 'on' : '' ?>" data-pick="storage" data-value="<?= e($s['label']) ?>" data-price="<?= (int) $s['price'] ?>"><?= e($s['label']) ?></button>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="storage" id="storage" value="<?= e($storage[0]['label']) ?>">
                <?php endif; ?>
                <label class="option-label">Количество</label>
                <input class="qty" type="number" name="qty" value="1" min="1" max="<?= (int) $product['stock'] ?>">
                <div class="btns" style="justify-content:flex-start;margin-top:16px">
                    <button class="btn" type="submit" <?= (int) $product['stock'] <= 0 ? 'disabled' : '' ?>>В корзину</button>
                    <a class="btn ghost" href="<?= e(url('catalog.php?cat=' . urlencode($product['category_slug']))) ?>">В каталог</a>
                </div>
            </form>
            <form method="post" class="wishlist-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="wish">
                <button class="btn ghost" type="submit"><?= in_wishlist((int) $product['id']) ? '♥ В избранном' : '♡ В избранное' ?></button>
            </form>
            <div class="product-service-grid">
                <div><span>✓</span><b>Гарантия 1 год</b><small>Официальная гарантия</small></div>
                <div><span>↗</span><b>Быстрое получение</b><small>Доставка или самовывоз</small></div>
                <div><span>↺</span><b>Обмен 14 дней</b><small>По условиям магазина</small></div>
            </div>
            <div class="product-description"><h3>О товаре</h3><p><?= nl2br(e((string) $product['description'])) ?></p></div>
            <?php if ($specs): ?>
                <h3>Характеристики</h3>
                <table class="specs">
                    <?php foreach ($specs as $k => $v): ?>
                        <tr><td><?= e((string) $k) ?></td><td><?= e((string) $v) ?></td></tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <section class="product-extra-band"><div><span>Покупка без лишнего</span><b>Выберите конфигурацию — цена пересчитается автоматически.</b></div><div><span>Есть вопросы?</span><a href="<?= e(url('contacts.php')) ?>">Написать нам →</a></div></section>

    <section class="reviews" id="reviews">
        <div class="reviews-title-row">
            <div>
                <p class="eyebrow-dark">Мнения владельцев</p>
                <h2>Отзывы <span class="reviews-heading-count">(<?= (int) $reviewCount ?>)</span></h2>
            </div>
            <a class="btn ghost" href="#review-form">Оставить отзыв</a>
        </div>
        <?php include __DIR__ . '/includes/review_summary.php'; ?>
        <div class="reviews-list">
            <?php include __DIR__ . '/includes/reviews_list.php'; ?>
        </div>
        <div id="review-form" class="review-form-section">
            <h3>Написать отзыв</h3>
            <?php $reviewFormAction = url('product.php?id=' . (int) $productId . '#reviews'); include __DIR__ . '/includes/review_form.php'; ?>
        </div>
    </section>

    <?php if ($related): ?>
        <h2 style="margin-top:48px">Похожие товары</h2>
        <div class="grid">
            <?php foreach ($related as $p) {
                include __DIR__ . '/includes/card.php';
            } ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
