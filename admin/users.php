<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
ensure_installed();
require_admin();

if (isset($_GET['delete'])) {
    $did = (int) $_GET['delete'];
    if ($did !== (int) current_user()['id']) {
        db()->prepare('DELETE FROM users WHERE id = ?')->execute([$did]);
        flash('ok', 'Пользователь удалён.');
    }
    redirect('admin/users.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['role'], $_POST['id'])) {
    csrf_check();
    db()->prepare('UPDATE users SET role=? WHERE id=?')->execute([(string) $_POST['role'], (int) $_POST['id']]);
    flash('ok', 'Роль обновлена.');
    redirect('admin/users.php');
}

$list = db()->query('SELECT id,name,email,phone,role,created_at FROM users ORDER BY id DESC')->fetchAll();
$adminTitle = 'Пользователи';
require __DIR__ . '/_header.php';
?>
<div class="admin-page-head premium"><div><p class="admin-kicker">Клиенты</p><h1>Пользователи</h1><p class="muted">Аккаунты покупателей и права доступа к админ-панели.</p></div><span class="admin-count-chip"><?= count($list) ?> аккаунтов</span></div>
<section class="admin-panel"><div class="admin-table-scroll"><table class="data modern"><thead><tr><th>Пользователь</th><th>Контакты</th><th>Роль</th><th>Дата</th><th></th></tr></thead><tbody>
<?php foreach ($list as $u): ?><tr><td><div class="admin-user-cell"><span><?= e(mb_strtoupper(mb_substr((string)$u['name'],0,1))) ?></span><div><b><?= e($u['name']) ?></b><small>#<?= (int)$u['id'] ?></small></div></div></td><td><strong><?= e($u['email']) ?></strong><small><?= e($u['phone']) ?></small></td><td><form method="post" class="inline-role-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><select name="role"><option value="user" <?= $u['role']==='user'?'selected':'' ?>>Пользователь</option><option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>Администратор</option></select><button class="btn ghost" type="submit">Сохранить</button></form></td><td><?= !empty($u['created_at']) ? e(date('d.m.Y',strtotime((string)$u['created_at']))) : '—' ?></td><td><?php if ((int)$u['id'] !== (int)current_user()['id']): ?><a class="danger-link" data-confirm="Удалить пользователя?" href="<?= e(url('admin/users.php?delete=' . (int)$u['id'])) ?>">Удалить</a><?php else: ?><span class="table-chip accent">Вы</span><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php require __DIR__ . '/_footer.php'; ?>
