<?php
declare(strict_types=1);
if (!$reviews): ?>
    <div class="empty-reviews">
        <span class="empty-reviews-icon">☆</span>
        <p><strong>Пока нет отзывов.</strong><br><span class="muted">После покупки здесь можно поделиться впечатлениями.</span></p>
    </div>
<?php else: ?>
    <?php foreach ($reviews as $r): ?>
        <article class="review" data-review-id="<?= (int) $r['id'] ?>" itemscope itemtype="https://schema.org/Review">
            <meta itemprop="itemReviewed" content="<?= e($product['name'] ?? '') ?>">
            <div class="review-head">
                <div>
                    <strong itemprop="author" itemscope itemtype="https://schema.org/Person"><span itemprop="name"><?= e($r['name']) ?></span></strong>
                    <span class="verified-badge">Проверенная покупка</span>
                </div>
                <span itemprop="reviewRating" itemscope itemtype="https://schema.org/Rating">
                    <meta itemprop="ratingValue" content="<?= (int) $r['rating'] ?>">
                    <meta itemprop="bestRating" content="5">
                    <meta itemprop="worstRating" content="1">
                    <?= stars_html((int) $r['rating']) ?>
                </span>
            </div>
            <p class="review-text" itemprop="reviewBody"><?= nl2br(e((string) $r['text'])) ?></p>
            <time class="muted review-date" itemprop="datePublished" datetime="<?= e(date('c', strtotime($r['created_at']))) ?>"><?= e(date('d.m.Y', strtotime($r['created_at']))) ?></time>
        </article>
    <?php endforeach; ?>
<?php endif; ?>
