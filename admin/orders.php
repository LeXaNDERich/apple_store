<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
ensure_installed();
require_admin();

$id = (int)($_GET['id'] ?? 0);
$adminTitle = 'Заказы';
require __DIR__ . '/_header.php';

if ($id) {
    $st = db()->prepare('SELECT * FROM orders WHERE id = ?'); $st->execute([$id]); $order = $st->fetch();
    if (!$order) { echo '<div class="admin-panel admin-empty"><h2>Заказ не найден</h2></div>'; require __DIR__ . '/_footer.php'; exit; }
    $items = db()->prepare('SELECT * FROM order_items WHERE order_id = ?'); $items->execute([$id]); $items = $items->fetchAll();
    ?>
    <div class="admin-page-head premium"><div><p class="admin-kicker">Заказ #<?= $id ?></p><h1><?= e($order['customer_name']) ?></h1><p class="muted"><?= e(date('d.m.Y H:i',strtotime((string)$order['created_at']))) ?> · <?= format_price((float)$order['total']) ?></p></div><div class="admin-head-actions"><span class="order-status <?= e(order_status_class((string)$order['status'])) ?>"><?= e(order_status_label((string)$order['status'])) ?></span><a class="btn" href="<?= e(url('admin/statuses.php?id=' . $id)) ?>">Изменить статус</a></div></div>
    <div class="admin-order-layout">
        <section class="admin-panel"><div class="admin-panel-head"><div><span>Клиент</span><h2>Контактные данные</h2></div></div><dl class="admin-detail-list"><div><dt>Имя</dt><dd><?= e($order['customer_name']) ?></dd></div><div><dt>Email</dt><dd><?= e($order['email']) ?></dd></div><div><dt>Телефон</dt><dd><?= e($order['phone']) ?></dd></div><div><dt>Получение</dt><dd><?= (($order['delivery_method'] ?? 'delivery') === 'pickup') ? 'Самовывоз' : 'Доставка' ?></dd></div><div class="wide"><dt>Адрес</dt><dd><?= e($order['address']) ?></dd></div><?php if ($order['comment']): ?><div class="wide"><dt>Комментарий</dt><dd><?= e($order['comment']) ?></dd></div><?php endif; ?></dl></section>
        <aside class="admin-panel admin-order-total"><span>Итого</span><strong><?= format_price((float)$order['total']) ?></strong><div><span>Скидка</span><b><?= format_price((float)$order['discount']) ?></b></div><div><span>Доставка</span><b><?= (float)$order['shipping'] > 0 ? format_price((float)$order['shipping']) : 'Бесплатно' ?></b></div></aside>
    </div>
    <section class="admin-panel"><div class="admin-panel-head"><div><span>Состав заказа</span><h2>Товары · <?= count($items) ?></h2></div></div><div class="admin-table-scroll"><table class="data modern"><thead><tr><th>Товар</th><th>Опции</th><th>Количество</th><th>Цена</th><th>Акция</th></tr></thead><tbody><?php foreach ($items as $it): ?><tr><td><strong><?= e($it['product_name']) ?></strong></td><td><?= e($it['options']) ?: '—' ?></td><td><?= (int)$it['qty'] ?></td><td><strong><?= format_price((float)$it['price']) ?></strong><?php if (!empty($it['promotion_title'])): ?><small class="old"><?= format_price((float)($it['regular_price'] ?: $it['price'])) ?></small><?php endif; ?></td><td><?= !empty($it['promotion_title']) ? e($it['promotion_title']) : '—' ?></td></tr><?php endforeach; ?></tbody></table></div></section>
    <?php require __DIR__ . '/_footer.php'; exit;
}

$list = db()->query('SELECT * FROM orders ORDER BY id DESC')->fetchAll();
$totalRevenue = 0.0; foreach ($list as $o) if (($o['status'] ?? '') !== 'cancelled') $totalRevenue += (float)$o['total'];
?>
<div class="admin-page-head premium"><div><p class="admin-kicker">Продажи</p><h1>Заказы</h1><p class="muted">История оформленных заказов и текущие этапы выполнения.</p></div><div class="admin-head-actions"><span class="admin-count-chip"><?= count($list) ?> заказов</span><span class="admin-count-chip success"><?= format_price($totalRevenue) ?></span></div></div>
<section class="admin-panel"><div class="admin-table-scroll"><table class="data modern orders-admin-table"><thead><tr><th>Заказ</th><th>Клиент</th><th>Получение</th><th>Сумма</th><th>Статус</th><th></th></tr></thead><tbody><?php foreach ($list as $o): ?><tr><td><a class="order-id-link" href="<?= e(url('admin/orders.php?id=' . (int)$o['id'])) ?>">#<?= (int)$o['id'] ?></a><small><?= e(date('d.m.Y H:i',strtotime((string)$o['created_at']))) ?></small></td><td><strong><?= e($o['customer_name']) ?></strong><small><?= e($o['phone']) ?></small></td><td><?= (($o['delivery_method'] ?? 'delivery') === 'pickup') ? 'Самовывоз' : 'Доставка' ?></td><td><strong><?= format_price((float)$o['total']) ?></strong></td><td><span class="order-status <?= e(order_status_class((string)$o['status'])) ?>"><?= e(order_status_label((string)$o['status'])) ?></span></td><td><a class="table-arrow" href="<?= e(url('admin/orders.php?id=' . (int)$o['id'])) ?>">→</a></td></tr><?php endforeach; ?><?php if (!$list): ?><tr><td colspan="6" class="muted">Заказов пока нет.</td></tr><?php endif; ?></tbody></table></div></section>
<?php require __DIR__ . '/_footer.php'; ?>
