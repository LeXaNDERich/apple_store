<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
ensure_installed();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'update') {
        foreach (($_POST['qty'] ?? []) as $key => $qty) {
            cart_set_qty((string) $key, (int) $qty);
        }
        flash('ok', 'Корзина обновлена.');
        redirect('cart.php');
    }
    if ($action === 'remove') {
        cart_set_qty((string) ($_POST['key'] ?? ''), 0);
        flash('ok', 'Товар удалён.');
        redirect('cart.php');
    }
    if ($action === 'promo') {
        $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
        $st = db()->prepare('SELECT * FROM promo_codes WHERE code = ? AND active = 1');
        $st->execute([$code]);
        $promo = $st->fetch();
        if ($promo) {
            $_SESSION['promo'] = $promo;
            flash('ok', 'Промокод применён: −' . (int) $promo['discount_percent'] . '%');
        } else {
            unset($_SESSION['promo']);
            flash('error', 'Промокод не найден.');
        }
        redirect('cart.php');
    }
}

$data = cart_items_detailed();
$promo = $_SESSION['promo'] ?? null;
$discount = 0.0;
if ($promo) {
    $discount = round($data['total'] * ((int) $promo['discount_percent'] / 100), 2);
}
$shipping = (float) setting('shipping', '0');
$grand = max(0, $data['total'] - $discount + $shipping);
$pageTitle = 'Корзина — ' . STORE_NAME;
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero page-hero-compact cart-hero">
    <div class="wrap page-hero-inner single"><div><p class="page-kicker">Ваш выбор</p><h1>Корзина</h1><p class="page-lead"><?= $data['items'] ? 'Проверьте комплектацию и переходите к оформлению.' : 'Здесь появятся товары, которые вы выберете в каталоге.' ?></p></div></div>
</section>
<section class="wrap cart-shell">
    <?php if (!$data['items']): ?>
        <div class="empty-state cart-empty"><span class="empty-state-icon">□</span><h2>Корзина пока пустая</h2><p>Добавьте iPhone, Mac, iPad или аксессуары — всё выбранное сохранится здесь.</p><a class="btn" href="<?= e(url('catalog.php')) ?>">Перейти в каталог</a></div>
    <?php else: ?>
        <div class="cart-layout">
            <div>
                <form method="post" class="cart-update-form">
                    <?= csrf_field() ?><input type="hidden" name="action" value="update">
                    <div class="cart-list">
                        <?php foreach ($data['items'] as $item): ?>
                            <article class="cart-item">
                                <a class="cart-item-media" href="<?= e(product_url($item['product'])) ?>"><img src="<?= e(product_image_src($item['product'])) ?>" alt="<?= e($item['product']['name']) ?>"></a>
                                <div class="cart-item-main">
                                    <div class="cart-item-title"><div><span class="cart-item-category">Apple</span><h3><a href="<?= e(product_url($item['product'])) ?>"><?= e($item['product']['name']) ?></a></h3><?php if ($item['opts']): ?><p><?= e($item['opts']) ?></p><?php endif; ?></div>
                                    <button class="cart-remove" form="rm" name="key" value="<?= e($item['key']) ?>" type="submit" aria-label="Удалить товар">×</button></div>
                                    <div class="cart-item-bottom">
                                        <label class="cart-qty">Количество<input type="number" name="qty[<?= e($item['key']) ?>]" value="<?= (int) $item['qty'] ?>" min="1"></label>
                                        <div class="cart-item-price">
                                            <?php if ($item['promotion']): ?><span class="cart-promo-label"><?= e(promotion_type_label((string)$item['promotion']['type'])) ?></span><small class="old"><?= format_price($item['regular_price']) ?></small><?php endif; ?>
                                            <strong><?= format_price($item['sum']) ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <div class="cart-update-note"><span>Количество обновляется автоматически после изменения.</span><button class="btn ghost" type="submit">Обновить корзину</button></div>
                </form>
                <form id="rm" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="remove"></form>
            </div>
            <aside class="cart-summary surface-card">
                <div class="summary-head"><span>Заказ</span><strong><?= count($data['items']) ?> поз.</strong></div>
                <div class="summary-lines">
                    <div><span>Товары</span><b><?= format_price($data['total']) ?></b></div>
                    <?php if ($data['promotion_savings'] > 0): ?><div class="saving"><span>Скидка по акциям</span><b>−<?= format_price($data['promotion_savings']) ?></b></div><?php endif; ?>
                    <div><span>Промокод</span><b><?= $discount > 0 ? '−' . format_price($discount) : '—' ?></b></div>
                    <div><span>Доставка</span><b><?= $shipping > 0 ? format_price($shipping) : 'Бесплатно' ?></b></div>
                </div>
                <div class="summary-total"><span>Итого</span><strong><?= format_price($grand) ?></strong></div>
                <form method="post" class="promo-code-form">
                    <?= csrf_field() ?><input type="hidden" name="action" value="promo">
                    <label>Промокод<div><input type="text" name="code" placeholder="Например, APPLE10" value="<?= e((string)($promo['code'] ?? '')) ?>"><button class="btn ghost" type="submit">OK</button></div></label>
                </form>
                <a class="btn wide checkout-btn" href="<?= e(url('checkout.php')) ?>">Перейти к оформлению</a>
                <p class="summary-safe">✓ Заказ можно проверить перед подтверждением</p>
            </aside>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
