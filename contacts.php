<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
ensure_installed();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $text = trim((string) ($_POST['text'] ?? ''));
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $text === '') {
        flash('error', 'Заполните все поля корректно.');
        redirect('contacts.php');
    }
    db()->prepare('INSERT INTO messages (name,email,text) VALUES (?,?,?)')->execute([$name, $email, $text]);
    flash('ok', 'Сообщение отправлено. Ответим на почту.');
    redirect('contacts.php');
}

$pageTitle = 'Контакты — ' . STORE_NAME;
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero page-hero-compact">
    <div class="wrap page-hero-inner single">
        <div><p class="page-kicker">Связаться с iTech</p><h1>Мы на связи.</h1><p class="page-lead">Вопрос по товару, доставке или заказу? Напишите нам — сообщение попадёт прямо в админ-панель.</p></div>
    </div>
</section>
<section class="section wrap contact-layout">
    <form method="post" class="surface-card contact-form-card">
        <?= csrf_field() ?>
        <div class="section-mini-head"><span>Сообщение</span><h2>Напишите нам</h2><p>Обычно для учебной демонстрации достаточно проверить, что сообщение появляется в разделе «Сообщения» админки.</p></div>
        <div class="form-grid two">
            <label>Имя<input type="text" name="name" placeholder="Ваше имя" required></label>
            <label>Email<input type="email" name="email" placeholder="name@example.com" required></label>
        </div>
        <label>Сообщение<textarea name="text" rows="6" placeholder="Опишите вопрос или пожелание" required></textarea></label>
        <button class="btn" type="submit">Отправить сообщение</button>
    </form>
    <aside class="contact-aside">
        <article class="contact-info-card"><span>Телефон</span><a href="tel:<?= e(preg_replace('~[^+0-9]~', '', setting('phone'))) ?>"><?= e(setting('phone')) ?></a><small>Ежедневно с 10:00 до 22:00</small></article>
        <article class="contact-info-card"><span>Email</span><a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a><small>Для общих вопросов</small></article>
        <article class="contact-info-card"><span>Самовывоз</span><strong><?= e(setting('address')) ?></strong><small>Адрес пункта выдачи</small></article>
        <div class="contact-visual"><span>iTech</span><p>Сервис начинается ещё до покупки.</p></div>
    </aside>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
