<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
ensure_installed();
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        db()->prepare('DELETE FROM reviews WHERE id = ?')->execute([$id]);
        flash('ok', 'Отзыв удалён.');
    }
    redirect('admin/reviews.php');
}

$q = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT r.*, u.name AS user_name, u.email, p.name AS product_name, p.id AS product_id
        FROM reviews r
        JOIN users u ON u.id = r.user_id
        JOIN products p ON p.id = r.product_id';
$args = [];
if ($q !== '') {
    $sql .= ' WHERE u.name LIKE ? OR u.email LIKE ? OR p.name LIKE ? OR r.text LIKE ?';
    $like = '%' . $q . '%';
    $args = [$like, $like, $like, $like];
}
$sql .= ' ORDER BY r.created_at DESC, r.id DESC';
$st = db()->prepare($sql);
$st->execute($args);
$rows = $st->fetchAll();

$adminTitle = 'Отзывы';
require __DIR__ . '/_header.php';
?>
<div class="admin-heading-row">
    <div>
        <h1>Отзывы</h1>
        <p class="muted">Все отзывы покупателей. Удаляйте только спам или некорректный контент.</p>
    </div>
    <div class="stat mini-stat"><span class="muted">Всего</span><b><?= count($rows) ?></b></div>
</div>
<form class="filters" method="get">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Товар, покупатель или текст">
    <button class="btn" type="submit">Найти</button>
    <?php if ($q !== ''): ?><a class="btn ghost" href="<?= e(url('admin/reviews.php')) ?>">Сбросить</a><?php endif; ?>
</form>
<div class="box admin-table-scroll">
    <table class="data">
        <tr><th>Товар</th><th>Покупатель</th><th>Оценка</th><th>Отзыв</th><th>Дата</th><th></th></tr>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><a href="<?= e(url('product.php?id=' . (int) $r['product_id'] . '#reviews')) ?>"><?= e($r['product_name']) ?></a></td>
                <td><?= e($r['user_name']) ?><br><span class="muted"><?= e($r['email']) ?></span></td>
                <td><?= stars_html((int) $r['rating']) ?></td>
                <td class="admin-review-text"><?= e($r['text']) ?></td>
                <td><?= e(date('d.m.Y H:i', strtotime($r['created_at']))) ?></td>
                <td>
                    <form method="post" onsubmit="return confirm('Удалить этот отзыв?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <button class="btn danger admin-small-btn" type="submit">Удалить</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6" class="muted">Отзывы не найдены.</td></tr><?php endif; ?>
    </table>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
