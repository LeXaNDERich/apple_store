<?php
declare(strict_types=1);
$existing = $access['existing'] ?? null;
$curRating = $existing ? (int) $existing['rating'] : 0;
$curText = $existing ? (string) $existing['text'] : '';
$fallbackAction = $reviewFormAction ?? url('product.php?id=' . (int) $productId . '#reviews');
$apiAction = url('api/submit_review.php');
?>
<div class="review-form-wrap" data-product-id="<?= (int) $productId ?>">
<?php if (!$access['can']): ?>
    <div class="review-note box compact-note"><?= $access['msg'] ?></div>
<?php else: ?>
    <form class="review-form box" method="post" action="<?= e($fallbackAction) ?>" data-ajax-review data-api-url="<?= e($apiAction) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="review">
        <input type="hidden" name="product_id" value="<?= (int) $productId ?>">
        <input type="hidden" name="rating" class="rating-input" value="<?= $curRating ?>" required>

        <div class="review-form-head">
            <div>
                <strong><?= $existing ? 'Изменить свой отзыв' : 'Оцените товар' ?></strong>
                <p class="muted">Оценка от 1 до 5 и текст минимум 3 символа.</p>
            </div>
            <?php if ($existing): ?><span class="verified-badge">Покупка подтверждена</span><?php endif; ?>
        </div>

        <label>Ваша оценка</label>
        <div class="star-input" role="radiogroup" aria-label="Оценка от 1 до 5">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <button
                    type="button"
                    class="star-btn<?= $i <= $curRating ? ' on' : '' ?>"
                    data-star="<?= $i ?>"
                    role="radio"
                    aria-checked="<?= $i === $curRating ? 'true' : 'false' ?>"
                    aria-label="<?= $i ?> из 5"
                    title="<?= $i ?> из 5"
                >★</button>
            <?php endfor; ?>
        </div>

        <label for="review-text-<?= (int) $productId ?>">Текст отзыва</label>
        <textarea id="review-text-<?= (int) $productId ?>" name="text" rows="4" minlength="3" maxlength="2000" required placeholder="Расскажите, что понравилось и как товар показал себя в использовании"><?= e($curText) ?></textarea>
        <div class="review-form-actions">
            <button class="btn" type="submit"><?= $existing ? 'Обновить отзыв' : 'Опубликовать отзыв' ?></button>
            <span class="review-form-status muted" aria-live="polite"></span>
        </div>
    </form>
<?php endif; ?>
</div>
