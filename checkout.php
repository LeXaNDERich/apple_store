<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
ensure_installed();

$data = cart_items_detailed();
if (!$data['items']) {
    flash('error', 'Корзина пуста.');
    redirect('cart.php');
}

$user = current_user();
$promo = $_SESSION['promo'] ?? null;
$discount = $promo ? round($data['total'] * ((int) $promo['discount_percent'] / 100), 2) : 0.0;
$deliveryShipping = (float) setting('shipping', '0');
$shipping = $deliveryShipping;
$grand = max(0, $data['total'] - $discount + $shipping);
$pickupAddress = setting('address', 'Москва, Тверская, 7');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $deliveryMethod = (string) ($_POST['delivery_method'] ?? 'delivery');
    if (!in_array($deliveryMethod, ['delivery', 'pickup'], true)) {
        $deliveryMethod = 'delivery';
    }
    $city = trim((string) ($_POST['city'] ?? ''));
    $street = trim((string) ($_POST['street'] ?? ''));
    $house = trim((string) ($_POST['house'] ?? ''));
    $apartment = trim((string) ($_POST['apartment'] ?? ''));
    $comment = trim((string) ($_POST['comment'] ?? ''));
    if ($deliveryMethod === 'pickup') {
        $address = 'Самовывоз: ' . $pickupAddress;
        $shipping = 0.0;
    } else {
        $addressParts = array_filter([
            $city,
            $street !== '' ? 'ул. ' . $street : '',
            $house !== '' ? 'д. ' . $house : '',
            $apartment !== '' ? 'кв. ' . $apartment : '',
        ]);
        $address = implode(', ', $addressParts);
        $shipping = $deliveryShipping;
    }
    $grand = max(0, $data['total'] - $discount + $shipping);
    if ($name === '' || $email === '' || $phone === '' || ($deliveryMethod === 'delivery' && ($city === '' || $street === '' || $house === ''))) {
        flash('error', 'Заполните имя, email, телефон, город, улицу и дом.');
        redirect('checkout.php');
    }
    db()->beginTransaction();
    try {
        $st = db()->prepare('INSERT INTO orders (user_id,customer_name,email,phone,address,delivery_method,comment,promo,discount,shipping,total,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
        $st->execute([
            $user ? (int) $user['id'] : null,
            $name,
            $email,
            $phone,
            $address,
            $deliveryMethod,
            $comment,
            $promo['code'] ?? null,
            $discount,
            $shipping,
            $grand,
            'collecting',
        ]);
        $oid = (int) db()->lastInsertId();
        $ins = db()->prepare('INSERT INTO order_items (order_id,product_id,product_name,qty,price,regular_price,promotion_id,promotion_title,promotion_discount,options) VALUES (?,?,?,?,?,?,?,?,?,?)');
        $upd = db()->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
        foreach ($data['items'] as $item) {
            $ok = $upd->execute([$item['qty'], $item['product']['id'], $item['qty']]);
            if ($upd->rowCount() === 0) {
                throw new RuntimeException('Товара «' . $item['product']['name'] . '» больше нет в нужном количестве.');
            }
            $itemPromotion = $item['promotion'];
            $ins->execute([
                $oid,
                $item['product']['id'],
                $item['product']['name'],
                $item['qty'],
                $item['price'],
                $item['regular_price'],
                $itemPromotion ? (int) $itemPromotion['id'] : null,
                $itemPromotion ? (string) $itemPromotion['title'] : '',
                $item['promotion_discount'],
                $item['opts'],
            ]);
        }
        db()->commit();
        $_SESSION['cart'] = [];
        unset($_SESSION['promo']);
        metrika_queue_goal('order_complete', [
            'order_id' => (string) $oid,
            'order_price' => (float) $grand,
            'currency' => 'RUB',
        ]);
        flash('ok', 'Заказ №' . $oid . ' оформлен. Мы свяжемся с вами.');
        redirect($user ? 'profile.php' : 'index.php');
    } catch (Throwable $e) {
        db()->rollBack();
        flash('error', $e->getMessage());
        redirect('checkout.php');
    }
}

$pageTitle = 'Оформление заказа — ' . STORE_NAME;
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero page-hero-compact checkout-hero">
    <div class="wrap page-hero-inner single"><div><p class="page-kicker">Финальный шаг</p><h1>Оформление заказа</h1><p class="page-lead">Укажите контакты и выберите удобный способ получения. Перед отправкой ещё раз проверьте итоговую сумму.</p></div></div>
</section>
<section class="wrap checkout-shell">
    <form method="post" class="checkout-form">
        <?= csrf_field() ?>
        <section class="checkout-section surface-card">
            <div class="checkout-section-head"><span>01</span><div><h2>Контактные данные</h2><p>Используем их только для связи по заказу.</p></div></div>
            <div class="form-grid two">
                <label>Имя<input type="text" name="name" required value="<?= e($user['name'] ?? '') ?>" placeholder="Ваше имя"></label>
                <label>Email<input type="email" name="email" required value="<?= e($user['email'] ?? '') ?>" placeholder="name@example.com"></label>
            </div>
            <label>Телефон<input type="tel" name="phone" id="phone" class="phone-mask" required placeholder="+7 (___) ___-__-__" value="<?= e($user['phone'] ?? '') ?>"></label>
        </section>

        <section class="checkout-section surface-card">
            <div class="checkout-section-head"><span>02</span><div><h2>Способ получения</h2><p>Можно выбрать доставку или забрать заказ самостоятельно.</p></div></div>
            <fieldset class="delivery-choice" data-delivery-choice>
                <div class="delivery-options">
                    <label class="delivery-option"><input type="radio" name="delivery_method" value="delivery" checked><span class="delivery-option-icon" aria-hidden="true">↗</span><span class="delivery-option-copy"><b>Доставка</b><small>Курьером по вашему адресу</small></span><span class="delivery-option-check" aria-hidden="true">✓</span></label>
                    <label class="delivery-option"><input type="radio" name="delivery_method" value="pickup"><span class="delivery-option-icon" aria-hidden="true">⌂</span><span class="delivery-option-copy"><b>Самовывоз</b><small><?= e($pickupAddress) ?></small></span><span class="delivery-option-check" aria-hidden="true">✓</span></label>
                </div>
            </fieldset>
            <?php
                $savedAddress = trim((string) ($user['address'] ?? ''));
                $savedCity = 'Москва'; $savedStreet = ''; $savedHouse = ''; $savedApartment = '';
                if ($savedAddress !== '') {
                    $parts = array_map('trim', explode(',', $savedAddress));
                    if (!empty($parts[0])) $savedCity = $parts[0];
                    if (!empty($parts[1])) $savedStreet = preg_replace('/^(ул\.|улица)\s*/ui', '', $parts[1]);
                    if (!empty($parts[2])) $savedHouse = preg_replace('/^(д\.|дом)\s*/ui', '', $parts[2]);
                    if (!empty($parts[3])) $savedApartment = preg_replace('/^(кв\.|квартира)\s*/ui', '', $parts[3]);
                }
            ?>
            <div class="delivery-address-card" data-delivery-address>
                <div class="delivery-address-head"><div><h3>Адрес доставки</h3><p>Заполните обязательные поля вручную.</p></div></div>
                <div class="address-form-grid">
                    <div class="address-field address-field-city"><label for="checkout-city">Город <span>*</span></label><input id="checkout-city" name="city" type="text" value="<?= e($savedCity) ?>" autocomplete="address-level2" placeholder="Москва" data-address-field></div>
                    <div class="address-field address-field-street"><label for="checkout-street">Улица <span>*</span></label><input id="checkout-street" name="street" type="text" value="<?= e($savedStreet) ?>" autocomplete="address-line1" placeholder="Тверская" data-address-field></div>
                    <div class="address-field"><label for="checkout-house">Дом <span>*</span></label><input id="checkout-house" name="house" type="text" value="<?= e($savedHouse) ?>" placeholder="7" data-address-field></div>
                    <div class="address-field"><label for="checkout-apartment">Квартира</label><input id="checkout-apartment" name="apartment" type="text" value="<?= e($savedApartment) ?>" placeholder="42" data-address-field></div>
                </div>
            </div>
        </section>

        <section class="checkout-section surface-card">
            <div class="checkout-section-head"><span>03</span><div><h2>Комментарий</h2><p>Необязательно. Можно указать домофон или удобное время.</p></div></div>
            <textarea name="comment" rows="3" placeholder="Пожелания к заказу"></textarea>
        </section>

        <button class="btn checkout-submit" type="submit">Подтвердить заказ · <?= format_price($grand) ?></button>
    </form>

    <aside class="checkout-summary surface-card">
        <div class="summary-head"><span>Ваш заказ</span><strong><?= count($data['items']) ?> поз.</strong></div>
        <div class="checkout-products">
            <?php foreach ($data['items'] as $item): ?>
                <div class="checkout-product-row"><img src="<?= e(product_image_src($item['product'])) ?>" alt=""><div><b><?= e($item['product']['name']) ?></b><small><?= (int)$item['qty'] ?> × <?= format_price($item['price']) ?><?php if ($item['opts']): ?> · <?= e($item['opts']) ?><?php endif; ?></small><?php if ($item['promotion']): ?><span><?= e(promotion_type_label((string)$item['promotion']['type'])) ?></span><?php endif; ?></div><strong><?= format_price($item['sum']) ?></strong></div>
            <?php endforeach; ?>
        </div>
        <div class="summary-lines">
            <?php if ($data['promotion_savings'] > 0): ?><div class="saving"><span>Скидка по акциям</span><b>−<?= format_price($data['promotion_savings']) ?></b></div><?php endif; ?>
            <div><span>Промокод</span><b><?= $discount > 0 ? '−' . format_price($discount) : '—' ?></b></div>
            <div data-shipping-row><span>Доставка</span><b data-shipping-price><?= format_price($deliveryShipping) ?></b></div>
        </div>
        <div class="summary-total"><span>Итого</span><strong data-checkout-total data-base-total="<?= e((string) max(0, $data['total'] - $discount)) ?>" data-delivery-shipping="<?= e((string)$deliveryShipping) ?>"><?= format_price($grand) ?></strong></div>
        <div class="checkout-security"><span>✓</span><p><b>Проверка заказа</b><small>Заказ создаётся только после успешного сохранения в базе данных.</small></p></div>
    </aside>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
