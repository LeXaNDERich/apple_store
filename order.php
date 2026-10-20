<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
ensure_installed();
$user = require_login();

$orderId = (int) ($_GET['id'] ?? 0);
if ($orderId <= 0) {
    flash('error', 'Неверный ID заказа.');
    redirect('profile.php');
}

$st = db()->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ?');
$st->execute([$orderId, (int) $user['id']]);
$order = $st->fetch();

if (!$order) {
    flash('error', 'Заказ не найден.');
    redirect('profile.php');
}

$st = db()->prepare('SELECT * FROM order_items WHERE order_id = ?');
$st->execute([$orderId]);
$items = $st->fetchAll();
$orderPromotionSavings = 0.0;
foreach ($items as $orderItem) {
    $orderPromotionSavings += (float) ($orderItem['promotion_discount'] ?? 0) * (int) ($orderItem['qty'] ?? 0);
}

$statusLabels = order_status_labels();
$statusFlow = ['collecting','on_way','delivered'];
$currentStep = array_search((string)$order['status'], $statusFlow, true);
if ($currentStep === false) $currentStep = -1;

$pageTitle = 'Заказ №' . $orderId . ' — ' . STORE_NAME;
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero page-hero-compact order-hero">
    <div class="wrap page-hero-inner single"><div><p class="page-kicker">Заказ №<?= (int)$order['id'] ?></p><h1><?= e(order_status_label((string)$order['status'])) ?></h1><p class="page-lead">Создан <?= e(date('d.m.Y в H:i',strtotime((string)$order['created_at']))) ?> · сумма <?= format_price((float)$order['total']) ?></p></div></div>
</section>
<section class="wrap order-page-shell">
    <a class="back-link" href="<?= e(url('profile.php')) ?>">← Вернуться в личный кабинет</a>
    <?php if ($order['status'] !== 'cancelled'): ?>
        <div class="order-progress surface-card">
            <?php foreach ([['collecting','Собираем','Заказ принят'],['on_way','В пути','Передан в доставку'],['delivered','Доставлен','Можно оставить отзыв']] as $idx => $step): ?>
                <div class="order-step <?= $currentStep >= $idx ? 'done' : '' ?> <?= (string)$order['status'] === $step[0] ? 'current' : '' ?>"><span><?= $currentStep > $idx ? '✓' : $idx + 1 ?></span><div><b><?= $step[1] ?></b><small><?= $step[2] ?></small></div></div>
            <?php endforeach; ?>
        </div>
    <?php else: ?><div class="order-cancelled surface-card"><span>×</span><div><b>Заказ отменён</b><p>Если это произошло по ошибке, свяжитесь с магазином.</p></div></div><?php endif; ?>

    <div class="order-detail-layout">
        <section class="surface-card order-info-card">
            <div class="section-mini-head"><span>Детали</span><h2>Информация о заказе</h2></div>
            <dl class="order-info-list">
                <div><dt>Статус</dt><dd><span class="order-status <?= e(order_status_class((string)$order['status'])) ?>"><?= e(order_status_label((string)$order['status'])) ?></span></dd></div>
                <div><dt>Получатель</dt><dd><?= e($order['customer_name']) ?></dd></div>
                <div><dt>Телефон</dt><dd><?= e($order['phone']) ?></dd></div>
                <div><dt>Email</dt><dd><?= e($order['email']) ?></dd></div>
                <div><dt>Получение</dt><dd><?= (($order['delivery_method'] ?? 'delivery') === 'pickup') ? 'Самовывоз' : 'Доставка' ?></dd></div>
                <div><dt><?= (($order['delivery_method'] ?? 'delivery') === 'pickup') ? 'Пункт выдачи' : 'Адрес' ?></dt><dd><?= e($order['address']) ?></dd></div>
                <?php if ($order['promo']): ?><div><dt>Промокод</dt><dd><?= e($order['promo']) ?></dd></div><?php endif; ?>
                <?php if ($order['comment']): ?><div class="wide"><dt>Комментарий</dt><dd><?= e($order['comment']) ?></dd></div><?php endif; ?>
            </dl>
        </section>
        <aside class="surface-card order-total-card">
            <span>Итого по заказу</span><strong><?= format_price((float)$order['total']) ?></strong>
            <?php if ($orderPromotionSavings > 0): ?><div><span>Акции</span><b>−<?= format_price($orderPromotionSavings) ?></b></div><?php endif; ?>
            <?php if ($order['discount'] > 0): ?><div><span>Промокод</span><b>−<?= format_price((float)$order['discount']) ?></b></div><?php endif; ?>
            <div><span>Доставка</span><b><?= (float)$order['shipping'] > 0 ? format_price((float)$order['shipping']) : 'Бесплатно' ?></b></div>
        </aside>
    </div>

    <section class="section order-items-section">
        <div class="reviews-title-row"><div><p class="eyebrow-dark">Состав заказа</p><h2>Товары</h2></div><span class="muted"><?= count($items) ?> поз.</span></div>
        <?php if (!$items): ?><div class="empty-state compact"><p>Товары не найдены.</p></div><?php else: ?>
            <div class="order-product-list modern">
            <?php foreach ($items as $item): ?>
                <article class="order-product-card"><div class="order-product-main"><h4><?= e($item['product_name']) ?></h4><?php if ($item['options']): ?><p class="muted order-product-options"><?= e($item['options']) ?></p><?php endif; ?></div><div class="order-product-meta"><div><span>Количество</span><strong><?= (int)$item['qty'] ?></strong></div><div><span>Цена</span><strong><?= format_price((float)$item['price']) ?></strong><?php if (!empty($item['promotion_title'])): ?><small class="order-promo-old"><?= format_price((float)($item['regular_price'] ?: $item['price'])) ?></small><?php endif; ?></div><div><span>Сумма</span><strong><?= format_price((float)$item['price'] * (int)$item['qty']) ?></strong></div></div><?php if (!empty($item['promotion_title'])): ?><div class="order-promo-note"><b>Акция:</b> <?= e($item['promotion_title']) ?> · скидка <?= format_price((float)$item['promotion_discount']) ?> за единицу</div><?php endif; ?><div class="order-product-review"><?php if ($order['status'] === 'delivered'): ?><a class="btn ghost admin-small-btn" href="<?= e(url('product.php?id=' . (int)$item['product_id'] . '#review-form')) ?>">Оставить отзыв</a><?php else: ?><span class="muted">Отзыв доступен после доставки</span><?php endif; ?></div></article>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
