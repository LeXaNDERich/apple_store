<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
ensure_installed();
require_admin();

if (isset($_GET['delete'])) {
    db()->prepare('DELETE FROM products WHERE id = ?')->execute([(int) $_GET['delete']]);
    flash('ok', 'Товар удалён.');
    redirect('admin/products.php');
}
$list = db()->query('SELECT p.*, c.name AS cat FROM products p JOIN categories c ON c.id = p.category_id ORDER BY p.id DESC')->fetchAll();
$adminTitle = 'Товары';
require __DIR__ . '/_header.php';
?>
<div class="admin-page-head premium"><div><p class="admin-kicker">Каталог</p><h1>Товары</h1><p class="muted">Редактируйте карточки, цены, остатки и SEO-параметры изображений.</p></div><div class="admin-head-actions"><span class="admin-count-chip"><?= count($list) ?> поз.</span><a class="btn" href="<?= e(url('admin/product-edit.php')) ?>">+ Добавить товар</a></div></div>
<section class="admin-panel">
<div class="admin-table-scroll"><table class="data modern products-admin-table"><thead><tr><th>Товар</th><th>Категория</th><th>Цена</th><th>Склад</th><th>Статус</th><th>Действия</th></tr></thead><tbody>
<?php foreach ($list as $p): ?>
<tr>
<td><div class="admin-product-cell"><img class="thumb" src="<?= e(product_image_src($p)) ?>" alt=""><div><b><?= e($p['name']) ?></b><small>#<?= (int)$p['id'] ?> · <?= e($p['sku']) ?></small></div></div></td>
<td><span class="table-chip"><?= e($p['cat']) ?></span></td>
<td><strong><?= format_price((float)$p['price']) ?></strong></td>
<td><span class="stock-number <?= (int)$p['stock'] <= 5 ? 'low' : '' ?>"><?= (int)$p['stock'] ?> шт.</span></td>
<td><?= $p['featured'] ? '<span class="table-chip accent">Хит</span>' : '<span class="muted">Обычный</span>' ?></td>
<td><div class="table-actions"><a href="<?= e(url('admin/product-edit.php?id=' . (int)$p['id'])) ?>">Редактировать</a><a href="<?= e(url('admin/product_seo.php?id=' . (int)$p['id'])) ?>">SEO</a><a class="danger-link" data-confirm="Удалить товар?" href="<?= e(url('admin/products.php?delete=' . (int)$p['id'])) ?>">Удалить</a></div></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
