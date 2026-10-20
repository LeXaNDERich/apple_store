<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
ensure_installed();
if (current_user()) redirect('profile.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $st = db()->prepare('SELECT * FROM users WHERE email = ?');
    $st->execute([$email]);
    $user = $st->fetch();
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = (int) $user['id'];
        flash('ok', 'С возвращением, ' . $user['name'] . '!');
        redirect(($user['role'] === 'admin' && !empty($_POST['admin'])) ? 'admin/index.php' : 'index.php');
    }
    flash('error', 'Неверный email или пароль.');
    redirect('login.php');
}
$pageTitle = 'Вход — ' . STORE_NAME;
require __DIR__ . '/includes/header.php';
?>
<section class="auth-shell wrap">
    <div class="auth-art"><div class="auth-art-copy"><p class="page-kicker">Личный кабинет</p><h2>Ваши заказы, избранное и отзывы — в одном месте.</h2><div class="auth-benefits"><span>История заказов</span><span>Статусы доставки</span><span>Избранные товары</span><span>Отзывы о покупках</span></div></div></div>
    <div class="auth-card">
        <div class="auth-card-head"><span class="auth-mark">i</span><div><p>Добро пожаловать</p><h1>Вход в iTech</h1></div></div>
        <form method="post" class="modern-form">
            <?= csrf_field() ?>
            <label>Email<input type="email" name="email" autocomplete="username" placeholder="name@example.com" required></label>
            <label>Пароль<input type="password" name="password" autocomplete="current-password" placeholder="••••••••" required></label>
            <button class="btn wide" type="submit">Войти</button>
        </form>
        <p class="auth-switch">Нет аккаунта? <a href="<?= e(url('register.php')) ?>">Создать</a></p>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
