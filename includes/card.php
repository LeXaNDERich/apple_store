<?php
declare(strict_types=1);
$pid = (int) $p['id'];
$cardPricing = product_purchase_pricing($p);
$cardPromotion = $cardPricing['promotion'];
$cardPrice = (float) $cardPricing['price'];
$cardRegularPrice = (float) $cardPricing['regular_price'];
$cardPromotionPercent = ($cardPromotion && $cardRegularPrice > 0 && $cardPrice < $cardRegularPrice)
    ? (int) round((1 - ($cardPrice / $cardRegularPrice)) * 100)
    : 0;
if (array_key_exists('review_count', $p)) {
    $revCount = (int) $p['review_count'];
    $revAvg = (float) ($p['avg_rating'] ?? 0);
} else {
    $cardStats = review_stats_for_product($pid);
    $revCount = (int) $cardStats['count'];
    $revAvg = (float) $cardStats['avg'];
}
?>
<div class="card-wrap">
    <a class="card" href="<?= e(product_url($p)) ?>">
        <?php if ($cardPromotionPercent > 0): ?>
            <span class="tag promo-discount-tag">−<?= $cardPromotionPercent ?>%</span>
        <?php endif; ?>
        <?= product_photo($p) ?>
        <div class="card-body">
            <h3><?= e($p['name']) ?></h3>
            <p class="muted"><?= e($p['short_desc']) ?></p>
            <div class="card-revs muted">
                <?php if ($revCount): ?>
                    <span class="card-rating"><?= stars_html((int) round($revAvg)) ?><span><?= e(number_format($revAvg, 1, '.', '')) ?> · <?= $revCount ?></span></span>
                <?php else: ?>
                    <span>Пока без отзывов</span>
                <?php endif; ?>
            </div>
            <div class="price">
                <?= format_price($cardPrice) ?>
                <?php if ($cardPromotion): ?>
                    <span class="old"><?= format_price($cardRegularPrice) ?></span>
                <?php endif; ?>
            </div>
            <div class="card-footer-line">
                <div class="stock <?= ((int) $p['stock'] <= 0) ? 'out' : '' ?>"><?= (int) $p['stock'] > 0 ? 'В наличии' : 'Нет в наличии' ?></div>
                <span>Подробнее →</span>
            </div>
        </div>
    </a>
</div>
