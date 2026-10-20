<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
ensure_installed();

$promotions = active_promotions();
$pageTitle = 'Акции дня, недели и месяца — ' . STORE_NAME;
$pageDescription = 'Актуальные акции магазина: предложения дня, недели и месяца с ограниченным сроком действия.';
$pageKeywords = 'акции, акция дня, акция недели, акция месяца, скидки Apple';
$canonicalUrl = absolute_url(url('promotions.php'));
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero page-hero-compact promo-public-hero"><div class="wrap page-hero-inner single"><div><p class="page-kicker">Limited offers</p><h1>Акции</h1><p class="page-lead">Предложения дня, недели и месяца. На странице остаются только опубликованные акции, которые действуют прямо сейчас.</p></div></div></section>
<section class="section wrap promotions-page">
    <div class="promo-page-toolbar"><div><span>Сейчас активно</span><strong><?= count($promotions) ?> <?= count($promotions) === 1 ? 'предложение' : 'предложений' ?></strong></div><a class="btn ghost" href="<?= e(url('catalog.php')) ?>">Весь каталог</a></div>
    <?php if ($promotions): ?><div class="promo-grid promo-grid-page">
        <?php foreach ($promotions as $promo): ?>
        <?php $promoPercent = ((float)$promo['product_price'] > 0 && (float)$promo['promo_price'] > 0 && (float)$promo['promo_price'] < (float)$promo['product_price']) ? (int) round((1 - ((float)$promo['promo_price']/(float)$promo['product_price']))*100) : 0; ?>
        <article class="promo-card promo-card-<?= e($promo['type']) ?>"><a class="promo-card-media" href="<?= e(url('product.php?id=' . (int)$promo['product_id'])) ?>"><img src="<?= e(promotion_image_src($promo)) ?>" alt="<?= e($promo['title']) ?>"><span class="promo-badge"><?= e(promotion_type_label((string)$promo['type'])) ?></span><?php if ($promoPercent > 0): ?><span class="promo-percent-badge">−<?= $promoPercent ?>%</span><?php endif; ?></a><div class="promo-card-body"><div class="promo-card-meta"><p class="promo-dates"><?= e(date('d.m.Y',strtotime((string)$promo['start_date']))) ?> — <?= e(date('d.m.Y',strtotime((string)$promo['end_date']))) ?></p><span class="promo-duration-public"><?= e(promotion_period_label($promo)) ?></span></div><h2><?= e($promo['title']) ?></h2><p class="muted"><?= e($promo['description']) ?></p><p class="promo-product-name"><?= e($promo['product_name']) ?></p><div class="promo-price-row"><?php if ((float)$promo['promo_price'] > 0): ?><strong><?= format_price((float)$promo['promo_price']) ?></strong><span><?= format_price((float)$promo['product_price']) ?></span><?php else: ?><strong><?= format_price((float)$promo['product_price']) ?></strong><?php endif; ?></div><a class="btn" href="<?= e(url('product.php?id=' . (int)$promo['product_id'])) ?>">Открыть товар</a></div></article>
        <?php endforeach; ?>
    </div><?php else: ?><div class="empty-state"><span class="empty-state-icon">%</span><h2>Сейчас активных акций нет</h2><p>Новые предложения появятся здесь после публикации администратором.</p><a class="btn" href="<?= e(url('catalog.php')) ?>">Перейти в каталог</a></div><?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
