<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
ensure_installed();
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim((string) ($_POST['name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));
    if ($name === '') {
        flash('error', 'Имя не может быть пустым.');
        redirect('profile.php');
    }
    db()->prepare('UPDATE users SET name=?, phone=?, address=? WHERE id=?')->execute([$name, $phone, $address, $user['id']]);
    flash('ok', 'Профиль сохранён.');
    redirect('profile.php');
}

$st = db()->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC');
$st->execute([(int) $user['id']]);
$orders = $st->fetchAll();
$wish = db()->prepare('SELECT p.* FROM wishlist w JOIN products p ON p.id = w.product_id WHERE w.user_id = ?');
$wish->execute([(int) $user['id']]);
$wishlist = $wish->fetchAll();
$myReviews = user_reviews_list((int) $user['id']);
$pageTitle = 'Личный кабинет — ' . STORE_NAME;
require __DIR__ . '/includes/header.php';
?>
<section class="profile-hero">
    <div class="wrap profile-hero-inner">
        <div class="profile-avatar"><?= e(mb_strtoupper(mb_substr((string)$user['name'],0,1))) ?></div>
        <div><p class="page-kicker">Личный кабинет</p><h1><?= e($user['name']) ?></h1><p><?= e($user['email']) ?></p></div>
        <div class="profile-hero-actions"><a class="btn ghost" href="<?= e(url('catalog.php')) ?>">В каталог</a><a class="profile-logout" href="<?= e(url('logout.php')) ?>">Выйти</a></div>
    </div>
</section>
<section class="wrap profile-dashboard">
    <aside class="surface-card profile-card">
        <div class="section-mini-head"><span>Аккаунт</span><h2>Контактные данные</h2><p>Они будут подставляться при оформлении заказа.</p></div>
        <form method="post" class="modern-form">
            <?= csrf_field() ?>
            <label>Имя<input type="text" name="name" value="<?= e($user['name']) ?>" required></label>
            <label>Email<input type="email" value="<?= e($user['email']) ?>" disabled></label>
            <label>Телефон<input type="tel" name="phone" class="phone-mask" value="<?= e($user['phone']) ?>" placeholder="+7 (___) ___-__-__"></label>
            <label>Адрес<textarea name="address" rows="3" placeholder="Город, улица, дом"><?= e((string)$user['address']) ?></textarea></label>
            <button class="btn wide" type="submit">Сохранить профиль</button>
        </form>
    </aside>
    <div class="profile-main">
        <section class="surface-card profile-orders">
            <div class="section-mini-head row"><div><span>Покупки</span><h2>Заказы</h2></div><strong><?= count($orders) ?></strong></div>
            <?php if (!$orders): ?><div class="mini-empty"><span>□</span><p>Заказов пока нет.</p><a href="<?= e(url('catalog.php')) ?>">Выбрать товар →</a></div><?php else: ?>
                <div class="profile-order-list">
                    <?php foreach ($orders as $o): ?>
                        <a class="profile-order" href="<?= e(url('order.php?id=' . (int)$o['id'])) ?>">
                            <div><span>Заказ №<?= (int)$o['id'] ?></span><b><?= format_price((float)$o['total']) ?></b><small><?= e(date('d.m.Y H:i',strtotime((string)$o['created_at']))) ?></small></div>
                            <div><span class="order-status <?= e(order_status_class((string)$o['status'])) ?>"><?= e(order_status_label((string)$o['status'])) ?></span><i>→</i></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <section class="profile-quick-grid">
            <article><span>Избранное</span><b><?= count($wishlist) ?></b><small>сохранённых товаров</small></article>
            <article><span>Отзывы</span><b><?= count($myReviews) ?></b><small>ваших оценок</small></article>
            <article><span>Заказы</span><b><?= count($orders) ?></b><small>за всё время</small></article>
        </section>
    </div>
</section>
<?php if ($wishlist): ?>
<section class="section wrap profile-section"><div class="reviews-title-row"><div><p class="eyebrow-dark">Сохранено</p><h2>Избранное</h2></div><a href="<?= e(url('catalog.php')) ?>">Продолжить покупки →</a></div><div class="grid"><?php foreach ($wishlist as $p) include __DIR__ . '/includes/card.php'; ?></div></section>
<?php endif; ?>
<section class="section wrap profile-section" id="my-reviews">
    <div class="reviews-title-row"><div><p class="eyebrow-dark">История оценок</p><h2>Мои отзывы</h2></div><span class="muted"><?= count($myReviews) ?> шт.</span></div>
    <?php if (!$myReviews): ?><div class="empty-state compact"><span class="empty-state-icon">★</span><h3>Пока без отзывов</h3><p>После доставки заказа вы сможете оценить купленный товар.</p></div><?php else: ?>
        <div class="profile-review-grid">
            <?php foreach ($myReviews as $r): ?>
                <article class="profile-review-card"><a class="profile-review-product" href="<?= e(url('product.php?id=' . (int)$r['product_id'] . '#reviews')) ?>"><img src="<?= e(product_image_src($r)) ?>" alt="<?= e('Фото ' . $r['product_name']) ?>"><div><strong><?= e($r['product_name']) ?></strong><span class="muted"><?= e($r['short_desc']) ?></span></div></a><div class="profile-review-meta"><?= stars_html((int)$r['rating']) ?><time class="muted"><?= e(date('d.m.Y',strtotime($r['created_at']))) ?></time></div><p><?= e($r['text']) ?></p><a class="review-edit-link" href="<?= e(url('product.php?id=' . (int)$r['product_id'] . '#review-form')) ?>">Изменить отзыв →</a></article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
