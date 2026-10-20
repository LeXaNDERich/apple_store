<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
ensure_installed();
require_admin();

if (isset($_GET['delete'])) {
    db()->prepare('DELETE FROM messages WHERE id = ?')->execute([(int) $_GET['delete']]);
    redirect('admin/messages.php');
}

$list = db()->query('SELECT * FROM messages ORDER BY id DESC')->fetchAll();
$adminTitle = 'Сообщения';
require __DIR__ . '/_header.php';
?>
<div class="admin-page-head premium"><div><p class="admin-kicker">Обратная связь</p><h1>Сообщения</h1><p class="muted">Обращения из формы «Контакты» на публичной части сайта.</p></div><span class="admin-count-chip"><?= count($list) ?> сообщений</span></div>
<?php if (!$list): ?><div class="admin-panel admin-empty"><span>✉</span><h2>Пока пусто</h2><p>Новые сообщения появятся здесь.</p></div><?php else: ?><div class="message-admin-grid"><?php foreach ($list as $m): ?><article class="admin-message-card"><div class="message-card-head"><span><?= e(mb_strtoupper(mb_substr((string)$m['name'],0,1))) ?></span><div><b><?= e($m['name']) ?></b><a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a></div><time><?= e(date('d.m.Y H:i',strtotime((string)$m['created_at']))) ?></time></div><p><?= nl2br(e($m['text'])) ?></p><div class="message-card-actions"><a href="mailto:<?= e($m['email']) ?>">Ответить</a><a class="danger-link" data-confirm="Удалить сообщение?" href="<?= e(url('admin/messages.php?delete=' . (int)$m['id'])) ?>">Удалить</a></div></article><?php endforeach; ?></div><?php endif; ?>
<?php require __DIR__ . '/_footer.php'; ?>
