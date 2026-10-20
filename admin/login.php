<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';
ensure_installed();

if (is_admin()) {
    redirect('admin/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $st = db()->prepare('SELECT * FROM users WHERE email = ?');
    $st->execute([$email]);
    $user = $st->fetch();
    if ($user && $user['role'] === 'admin' && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = (int) $user['id'];
        flash('ok', 'С возвращением, ' . $user['name'] . '!');
        redirect('admin/index.php');
    }
    flash('error', 'Нет доступа. Проверьте логин администратора.');
    redirect('admin/login.php');
}

$pageTitle = 'Админ — вход';
require __DIR__ . '/../includes/header.php';
?>
<section class="auth-shell wrap admin-auth-shell">
    <div class="auth-art admin-auth-art"><div class="auth-art-copy"><p class="page-kicker">iTech Control Center</p><h2>Управляйте магазином из одного интерфейса.</h2><div class="auth-benefits"><span>Товары и категории</span><span>Заказы и статусы</span><span>Акции и отзывы</span><span>Пользователи и сообщения</span></div></div></div>
    <div class="auth-card">
        <div class="auth-card-head"><span class="auth-mark">A</span><div><p>Администрирование</p><h1>Вход в панель</h1></div></div>
        <form method="post" class="modern-form"><?= csrf_field() ?><label>Email<input type="email" name="email" autocomplete="username" placeholder="admin@example.com" required></label><label>Пароль<input type="password" name="password" autocomplete="current-password" placeholder="••••••••" required></label><button class="btn wide" type="submit">Войти в админ-панель</button></form>
        <p class="auth-switch"><a href="<?= e(url('index.php')) ?>">← Вернуться в магазин</a></p>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
