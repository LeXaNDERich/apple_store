<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
ensure_installed();
if (current_user()) redirect('profile.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $phone = trim((string) ($_POST['phone'] ?? ''));
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
        flash('error', 'Имя, корректный email и пароль от 6 символов обязательны.');
        redirect('register.php');
    }
    try {
        $st = db()->prepare('INSERT INTO users (name,email,password,phone,role) VALUES (?,?,?,?,?)');
        $st->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $phone, 'user']);
        $_SESSION['user_id'] = (int) db()->lastInsertId();
        flash('ok', 'Аккаунт создан. Добро пожаловать!');
        redirect('profile.php');
    } catch (PDOException $e) {
        flash('error', 'Этот email уже зарегистрирован.');
        redirect('register.php');
    }
}
$pageTitle = 'Регистрация — ' . STORE_NAME;
require __DIR__ . '/includes/header.php';
?>
<section class="auth-shell wrap auth-shell-register">
    <div class="auth-art"><div class="auth-art-copy"><p class="page-kicker">Новый аккаунт</p><h2>Сохраняйте понравившееся и следите за заказами.</h2><div class="auth-benefits"><span>Быстрое оформление</span><span>Избранное</span><span>История покупок</span><span>Отзывы после доставки</span></div></div></div>
    <div class="auth-card">
        <div class="auth-card-head"><span class="auth-mark">i</span><div><p>Регистрация</p><h1>Создать аккаунт</h1></div></div>
        <form method="post" class="modern-form">
            <?= csrf_field() ?>
            <label>Имя<input type="text" name="name" placeholder="Как к вам обращаться" required></label>
            <label>Email<input type="email" name="email" placeholder="name@example.com" required></label>
            <label>Телефон<input type="tel" name="phone" class="phone-mask" placeholder="+7 (___) ___-__-__"></label>
            <label>Пароль<input type="password" name="password" minlength="6" placeholder="Минимум 6 символов" required></label>
            <button class="btn wide" type="submit">Создать аккаунт</button>
        </form>
        <p class="auth-switch">Уже есть аккаунт? <a href="<?= e(url('login.php')) ?>">Войти</a></p>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
