<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
ensure_installed();
require_admin();

$labels = order_status_labels();
$id = (int) ($_GET['id'] ?? 0);
$filter = (string) ($_GET['status'] ?? 'all');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $orderId = (int) ($_POST['id'] ?? 0);
    $status = (string) ($_POST['status'] ?? '');
    if ($orderId <= 0 || !isset($labels[$status])) {
        flash('error', 'Некорректный статус заказа.');
    } else {
        $st = db()->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $st->execute([$status, $orderId]);
        flash('ok', 'Статус заказа №' . $orderId . ' изменён на «' . $labels[$status] . '».');
    }
    redirect('admin/statuses.php' . ($id > 0 ? '?id=' . $id : ''));
}

$counts = [];
foreach ($labels as $key => $label) {
    $st = db()->prepare('SELECT COUNT(*) FROM orders WHERE status = ?');
    $st->execute([$key]);
    $counts[$key] = (int) $st->fetchColumn();
}

$params = [];
$sql = 'SELECT * FROM orders';
if ($filter !== 'all' && isset($labels[$filter])) {
    $sql .= ' WHERE status = ?';
    $params[] = $filter;
}
$sql .= ' ORDER BY id DESC';
$st = db()->prepare($sql);
$st->execute($params);
$orders = $st->fetchAll();

$adminTitle = 'Статусы заказов';
require __DIR__ . '/_header.php';
?>
<div class="admin-heading-row">
    <div>
        <h1>Статусы заказов</h1>
        <p class="muted">Управляйте этапом выполнения заказа. Отзыв становится доступен покупателю только после статуса «Доставлен».</p>
    </div>
</div>

<div class="order-status-stats">
    <?php foreach ($labels as $key => $label): ?>
        <a class="status-stat <?= $filter === $key ? 'active' : '' ?>" href="<?= e(url('admin/statuses.php?status=' . $key)) ?>">
            <span class="order-status <?= e(order_status_class($key)) ?>"><?= e($label) ?></span>
            <strong><?= (int) $counts[$key] ?></strong>
        </a>
    <?php endforeach; ?>
    <a class="status-stat <?= $filter === 'all' ? 'active' : '' ?>" href="<?= e(url('admin/statuses.php')) ?>">
        <span>Все заказы</span><strong><?= array_sum($counts) ?></strong>
    </a>
</div>

<div class="box admin-table-scroll">
    <table class="data status-orders-table">
        <thead><tr><th>№</th><th>Дата</th><th>Клиент</th><th>Получение</th><th>Сумма</th><th>Текущий статус</th><th>Изменить</th></tr></thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
            <tr<?= $id === (int) $o['id'] ? ' class="status-row-focus"' : '' ?>>
                <td><a href="<?= e(url('admin/orders.php?id=' . (int) $o['id'])) ?>">№<?= (int) $o['id'] ?></a></td>
                <td><?= e(date('d.m.Y H:i', strtotime((string) $o['created_at']))) ?></td>
                <td><b><?= e($o['customer_name']) ?></b><br><span class="muted"><?= e($o['phone']) ?></span></td>
                <td><?= (($o['delivery_method'] ?? 'delivery') === 'pickup') ? 'Самовывоз' : 'Доставка' ?></td>
                <td><?= format_price((float) $o['total']) ?></td>
                <td><span class="order-status <?= e(order_status_class((string) $o['status'])) ?>"><?= e(order_status_label((string) $o['status'])) ?></span></td>
                <td>
                    <form method="post" class="status-change-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                        <select name="status" aria-label="Статус заказа №<?= (int) $o['id'] ?>">
                            <?php foreach ($labels as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= $o['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn admin-small-btn" type="submit">Сохранить</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$orders): ?><tr><td colspan="7" class="muted">Заказов с таким статусом нет.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
