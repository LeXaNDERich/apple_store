<?php
declare(strict_types=1);
$adminTitle = 'Обзор';
require __DIR__ . '/_header.php';

$counts = [
    'Товары' => (int) db()->query('SELECT COUNT(*) FROM products')->fetchColumn(),
    'Заказы' => (int) db()->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
    'Акции' => (int) db()->query("SELECT COUNT(*) FROM promotions WHERE published=1 AND CURDATE() BETWEEN start_date AND end_date")->fetchColumn(),
    'Пользователи' => (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn(),
];
$revenue = (float) db()->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status != 'cancelled'")->fetchColumn();
$recent = db()->query('SELECT * FROM orders ORDER BY id DESC LIMIT 8')->fetchAll();
$low = db()->query('SELECT * FROM products WHERE stock <= 5 ORDER BY stock ASC LIMIT 6')->fetchAll();
$statusCounts = [];
foreach (order_status_labels() as $key => $label) {
    $st = db()->prepare('SELECT COUNT(*) FROM orders WHERE status=?'); $st->execute([$key]); $statusCounts[$key] = (int)$st->fetchColumn();
}
?>
<div class="admin-page-head premium"><div><p class="admin-kicker">Control Center</p><h1>Обзор магазина</h1><p class="muted">Ключевые показатели, последние заказы и остатки — без переходов между разделами.</p></div><div class="admin-head-actions"><a class="btn ghost" href="<?= e(url('admin/products.php')) ?>">Товары</a><a class="btn" href="<?= e(url('admin/promotions.php')) ?>">Новая акция</a></div></div>
<div class="admin-metric-grid">
    <article class="admin-metric primary"><span>Выручка</span><strong><?= format_price($revenue) ?></strong><small>без отменённых заказов</small></article>
    <article><span>Товары</span><strong><?= $counts['Товары'] ?></strong><small>позиций в каталоге</small></article>
    <article><span>Заказы</span><strong><?= $counts['Заказы'] ?></strong><small>всего оформлено</small></article>
    <article><span>Акции</span><strong><?= $counts['Акции'] ?></strong><small>активно сейчас</small></article>
    <article><span>Пользователи</span><strong><?= $counts['Пользователи'] ?></strong><small>зарегистрировано</small></article>
</div>
<div class="admin-dashboard-grid">
    <section class="admin-panel wide">
        <div class="admin-panel-head"><div><span>Последние операции</span><h2>Новые заказы</h2></div><a href="<?= e(url('admin/orders.php')) ?>">Все заказы →</a></div>
        <div class="admin-table-scroll"><table class="data modern"><thead><tr><th>№</th><th>Клиент</th><th>Сумма</th><th>Статус</th><th></th></tr></thead><tbody>
        <?php foreach ($recent as $o): ?><tr><td><b>#<?= (int)$o['id'] ?></b><small><?= e(date('d.m H:i',strtotime((string)$o['created_at']))) ?></small></td><td><?= e($o['customer_name']) ?><small><?= e($o['phone']) ?></small></td><td><strong><?= format_price((float)$o['total']) ?></strong></td><td><span class="order-status <?= e(order_status_class((string)$o['status'])) ?>"><?= e(order_status_label((string)$o['status'])) ?></span></td><td><a class="table-arrow" href="<?= e(url('admin/orders.php?id=' . (int)$o['id'])) ?>">→</a></td></tr><?php endforeach; ?>
        <?php if (!$recent): ?><tr><td colspan="5" class="muted">Заказов пока нет.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>
    <aside class="admin-panel">
        <div class="admin-panel-head"><div><span>Логистика</span><h2>Статусы</h2></div></div>
        <div class="admin-status-mini"><?php foreach (order_status_labels() as $key=>$label): ?><a href="<?= e(url('admin/statuses.php?status=' . $key)) ?>"><span class="order-status <?= e(order_status_class($key)) ?>"><?= e($label) ?></span><b><?= (int)($statusCounts[$key] ?? 0) ?></b></a><?php endforeach; ?></div>
    </aside>
    <aside class="admin-panel">
        <div class="admin-panel-head"><div><span>Склад</span><h2>Заканчиваются</h2></div><a href="<?= e(url('admin/products.php')) ?>">Каталог →</a></div>
        <div class="low-stock-list"><?php foreach ($low as $p): ?><a href="<?= e(url('admin/product-edit.php?id=' . (int)$p['id'])) ?>"><span><?= e($p['name']) ?></span><b><?= (int)$p['stock'] ?> шт.</b></a><?php endforeach; ?><?php if (!$low): ?><p class="muted">Все позиции в норме.</p><?php endif; ?></div>
    </aside>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
