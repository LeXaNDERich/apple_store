<?php
declare(strict_types=1);
$count = (int) ($stats['count'] ?? 0);
$avg = (float) ($stats['avg'] ?? 0);
?>
<div class="rating-summary" data-rating-summary>
    <div class="rating-score">
        <strong data-rating-avg><?= $count ? e(number_format($avg, 1, '.', '')) : '—' ?></strong>
        <div><?= stars_html((int) round($avg)) ?></div>
        <span class="muted"><span data-rating-count><?= $count ?></span> <?= $count === 1 ? 'отзыв' : 'отзывов' ?></span>
    </div>
    <div class="rating-bars" aria-label="Распределение оценок">
        <?php for ($i = 5; $i >= 1; $i--):
            $n = (int) ($distribution[$i] ?? 0);
            $pct = $count > 0 ? (int) round($n / $count * 100) : 0;
        ?>
            <div class="rating-row" data-rating-row="<?= $i ?>">
                <span><?= $i ?> ★</span>
                <div class="rating-track"><i style="width:<?= $pct ?>%"></i></div>
                <span data-rating-row-count><?= $n ?></span>
            </div>
        <?php endfor; ?>
    </div>
</div>
