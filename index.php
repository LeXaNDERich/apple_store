<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
ensure_installed();

$featured = db()->query('SELECT * FROM products WHERE featured = 1 ORDER BY id LIMIT 8')->fetchAll();
$latest = db()->query('SELECT * FROM products ORDER BY id DESC LIMIT 8')->fetchAll();
$promotions = active_promotions(3);
$hero = db()->query("SELECT * FROM products WHERE slug = 'iphone-18-pro' LIMIT 1")->fetch() ?: (db()->query("SELECT * FROM products WHERE id = 1 LIMIT 1")->fetch() ?: ['id' => 1, 'name' => 'iPhone 18 Pro', 'slug' => 'iphone-18-pro', 'image' => 'https://www.apple.com/newsroom/images/2026/09/apple-debuts-iphone-18-pro-and-iphone-18-pro-max/article/Apple-iPhone-18-Pro-2up-260909_inline.jpg.large.jpg', 'price' => 159990, 'storage_options' => '[]']);
$heroPricing = product_purchase_pricing($hero);
$mac = ['name' => 'MacBook Air', 'image' => 'assets/img/products/macbook-air.jpg', 'slug' => 'mac'];
$watch = db()->query("SELECT * FROM products WHERE slug = 'apple-watch-ultra-2' LIMIT 1")->fetch() ?: ['name' => 'Watch Ultra 2', 'image' => 'assets/img/products/watch-ultra.jpg', 'slug' => 'apple-watch-ultra-2'];
$pageTitle = STORE_NAME . ' — ' . STORE_TAGLINE;
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <div class="hero-media">
        <img src="<?= e(product_image_src($hero)) ?>" alt="<?= e($hero['name']) ?>" fetchpriority="high" decoding="async">
    </div>
    <div class="hero-copy">
        <p class="eyebrow">Новинка · 2026</p>
        <h1><?= e($hero['name']) ?></h1>
        <p class="lead">A20 Pro. Fusion 48 Мп. Переменная диафрагма.</p>
        <div class="hero-specs"><span>A20 Pro</span><span>48 Мп Fusion</span><span>iOS 27</span><span>до 2 ТБ</span></div>
        <p class="from">От <?= format_price((float) $heroPricing['price']) ?><?php if ($heroPricing['promotion']): ?> <span class="old"><?= format_price((float) $heroPricing['regular_price']) ?></span><?php endif; ?></p>
        <div class="btns">
            <a class="btn" href="<?= e(url('product.php?id=' . (int) ($hero['id'] ?? 1))) ?>">Купить</a>
            <a class="btn ghost" href="<?= e(url('catalog.php?cat=iphone')) ?>">Смотреть линейку</a>
        </div>
    </div>
</section>
<section class="home-trust wrap" aria-label="Преимущества магазина">
    <div><strong>0 ₽</strong><span>доставка по Москве</span></div>
    <div><strong>14 дней</strong><span>на обмен товара</span></div>
    <div><strong>1 год</strong><span>гарантия производителя</span></div>
    <div><strong>60+</strong><span>товаров в каталоге</span></div>
</section>

<?php if ($promotions): ?>
<section class="section wrap promo-home-section">
    <div class="promo-section-head">
        <div>
            <p class="eyebrow-dark">Специальные предложения</p>
            <h2>Акции дня, недели и месяца.</h2>
            <p class="sub muted">Актуальные предложения с ограниченным сроком действия.</p>
        </div>
        <a class="btn ghost promo-all-link" href="<?= e(url('promotions.php')) ?>">Все акции</a>
    </div>
    <div class="promo-grid">
        <?php foreach ($promotions as $promo): ?>
            <?php $promoPercent = ((float) $promo['product_price'] > 0 && (float) $promo['promo_price'] > 0 && (float) $promo['promo_price'] < (float) $promo['product_price']) ? (int) round((1 - ((float) $promo['promo_price'] / (float) $promo['product_price'])) * 100) : 0; ?>
            <article class="promo-card promo-card-<?= e($promo['type']) ?>">
                <a class="promo-card-media" href="<?= e(url('product.php?id=' . (int) $promo['product_id'])) ?>">
                    <img src="<?= e(promotion_image_src($promo)) ?>" alt="<?= e($promo['title']) ?>">
                    <span class="promo-badge"><?= e(promotion_type_label((string) $promo['type'])) ?></span>
                    <?php if ($promoPercent > 0): ?><span class="promo-percent-badge">−<?= $promoPercent ?>%</span><?php endif; ?>
                </a>
                <div class="promo-card-body">
                    <div class="promo-card-meta">
                        <p class="promo-dates"><?= e(date('d.m.Y', strtotime((string) $promo['start_date']))) ?> — <?= e(date('d.m.Y', strtotime((string) $promo['end_date']))) ?></p>
                        <span class="promo-duration-public"><?= e(promotion_period_label($promo)) ?></span>
                    </div>
                    <h3><?= e($promo['title']) ?></h3>
                    <p class="muted"><?= e($promo['description']) ?></p>
                    <div class="promo-price-row">
                        <?php if ((float) $promo['promo_price'] > 0): ?>
                            <strong><?= format_price((float) $promo['promo_price']) ?></strong>
                            <span><?= format_price((float) $promo['product_price']) ?></span>
                        <?php else: ?>
                            <strong><?= format_price((float) $promo['product_price']) ?></strong>
                        <?php endif; ?>
                    </div>
                    <a class="btn" href="<?= e(url('product.php?id=' . (int) $promo['product_id'])) ?>">Посмотреть товар</a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="section wrap">
    <h2>Категории.</h2>
    <p class="sub muted">Соберите свой набор техники Apple.</p>
    <div class="cats">
        <?php
        $catProduct = [
            'iphone' => 'iphone-18-pro',
            'mac' => 'macbook-air-13-m3',
            'ipad' => 'ipad-pro-11-m4',
            'watch' => 'apple-watch-ultra-2',
            'audio' => 'airpods-pro-2',
            'accessories' => 'magic-mouse',
        ];
        foreach (categories() as $c):
            $catSlug = (string) ($catProduct[$c['slug']] ?? 'iphone-18-pro');
            $stCat = db()->prepare('SELECT * FROM products WHERE slug=? LIMIT 1');
            $stCat->execute([$catSlug]);
            $catPreview = $stCat->fetch() ?: $hero;
            $src = product_image_src($catPreview);
            ?>
            <a class="cat" href="<?= e(url('catalog.php?cat=' . urlencode($c['slug']))) ?>">
                <div class="photo"><img src="<?= e($src) ?>" alt="<?= e($c['name']) ?>" loading="lazy" decoding="async"></div>
                <b><?= e($c['name']) ?></b>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<section class="section wrap">
    <h2>Хиты.</h2>
    <p class="sub muted">То, что берут чаще всего.</p>
    <div class="grid">
        <?php foreach ($featured as $p) {
            include __DIR__ . '/includes/card.php';
        } ?>
    </div>
</section>
<section class="section wrap">
    <h2>Новое в магазине.</h2>
    <p class="sub muted">Свежие поступления и аксессуары.</p>
    <div class="grid">
        <?php foreach ($latest as $p) {
            include __DIR__ . '/includes/card.php';
        } ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>