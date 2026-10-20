<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
ensure_installed();
require_admin();

if (isset($_GET['delete'])) {
    db()->prepare('DELETE FROM categories WHERE id = ?')->execute([(int) $_GET['delete']]);
    flash('ok', 'Категория удалена (если не было товаров).');
    redirect('admin/categories.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim((string) ($_POST['name'] ?? ''));
    $slug = trim((string) ($_POST['slug'] ?? '')) ?: slugify($name);
    $desc = trim((string) ($_POST['description'] ?? ''));
    $sort = (int) ($_POST['sort'] ?? 0);
    $cid = (int) ($_POST['id'] ?? 0);
    if ($name === '') {
        flash('error', 'Название обязательно.');
        redirect('admin/categories.php');
    }
    try {
        if ($cid) {
            db()->prepare('UPDATE categories SET name=?,slug=?,description=?,sort=? WHERE id=?')->execute([$name, $slug, $desc, $sort, $cid]);
        } else {
            db()->prepare('INSERT INTO categories (name,slug,description,sort) VALUES (?,?,?,?)')->execute([$name, $slug, $desc, $sort]);
        }
        flash('ok', 'Категория сохранена.');
    } catch (Throwable $e) {
        flash('error', 'Не удалось сохранить (возможно, есть товары или slug занят).');
    }
    redirect('admin/categories.php');
}

$list = categories();
$edit = null;
if (!empty($_GET['id'])) {
    $st = db()->prepare('SELECT * FROM categories WHERE id = ?');
    $st->execute([(int) $_GET['id']]);
    $edit = $st->fetch();
}
$adminTitle = 'Категории';
require __DIR__ . '/_header.php';
?>
<div class="admin-page-head premium"><div><p class="admin-kicker">Структура каталога</p><h1>Категории</h1><p class="muted">Управляйте разделами каталога и порядком их отображения.</p></div><span class="admin-count-chip"><?= count($list) ?> разделов</span></div>
<div class="admin-split-layout">
    <section class="admin-panel"><div class="admin-panel-head"><div><span>Список</span><h2>Категории магазина</h2></div></div><div class="admin-table-scroll"><table class="data modern"><thead><tr><th>ID</th><th>Название</th><th>Slug</th><th>Порядок</th><th></th></tr></thead><tbody><?php foreach ($list as $c): ?><tr><td>#<?= (int)$c['id'] ?></td><td><strong><?= e($c['name']) ?></strong><small><?= e($c['description']) ?></small></td><td><span class="table-chip"><?= e($c['slug']) ?></span></td><td><?= (int)$c['sort'] ?></td><td><div class="table-actions"><a href="<?= e(url('admin/categories.php?id=' . (int)$c['id'])) ?>">Править</a><a class="danger-link" data-confirm="Удалить категорию?" href="<?= e(url('admin/categories.php?delete=' . (int)$c['id'])) ?>">Удалить</a></div></td></tr><?php endforeach; ?></tbody></table></div></section>
    <form method="post" class="admin-panel modern-form sticky-panel"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>"><div class="admin-panel-head"><div><span><?= $edit ? 'Редактирование' : 'Новый раздел' ?></span><h2><?= $edit ? e($edit['name']) : 'Добавить категорию' ?></h2></div></div><label>Название<input type="text" name="name" value="<?= e($edit['name'] ?? '') ?>" required></label><label>Slug<input type="text" name="slug" value="<?= e($edit['slug'] ?? '') ?>" placeholder="например, iphone"></label><label>Описание<textarea name="description" rows="3"><?= e($edit['description'] ?? '') ?></textarea></label><label>Сортировка<input type="number" name="sort" value="<?= (int)($edit['sort'] ?? 0) ?>"></label><button class="btn wide" type="submit"><?= $edit ? 'Сохранить изменения' : 'Создать категорию' ?></button><?php if ($edit): ?><a class="form-cancel-link" href="<?= e(url('admin/categories.php')) ?>">Отмена</a><?php endif; ?></form>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
