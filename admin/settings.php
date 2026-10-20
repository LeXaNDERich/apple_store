<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
ensure_installed();
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $keys = ['store_name', 'tagline', 'phone', 'email', 'address', 'shipping'];
    $up = db()->prepare('INSERT INTO settings (k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)');
    foreach ($keys as $k) {
        $up->execute([$k, trim((string) ($_POST[$k] ?? ''))]);
    }
    flash('ok', 'Настройки сохранены.');
    redirect('admin/settings.php');
}

$adminTitle = 'Настройки';
require __DIR__ . '/_header.php';
?>
<div class="admin-page-head premium"><div><p class="admin-kicker">Конфигурация</p><h1>Настройки магазина</h1><p class="muted">Основные контактные данные и параметры доставки, которые используются на публичных страницах.</p></div></div>
<div class="admin-settings-layout">
<form method="post" class="admin-panel modern-form"><?= csrf_field() ?><div class="admin-panel-head"><div><span>Основное</span><h2>Информация о магазине</h2></div></div><div class="form-grid two"><label>Название<input type="text" name="store_name" value="<?= e(setting('store_name',STORE_NAME)) ?>"></label><label>Слоган<input type="text" name="tagline" value="<?= e(setting('tagline',STORE_TAGLINE)) ?>"></label><label>Телефон<input type="text" name="phone" value="<?= e(setting('phone')) ?>"></label><label>Email<input type="text" name="email" value="<?= e(setting('email')) ?>"></label></div><label>Адрес<input type="text" name="address" value="<?= e(setting('address')) ?>"></label><label>Стоимость доставки, ₽<input type="number" name="shipping" value="<?= e(setting('shipping','0')) ?>"><small class="form-help">0 — бесплатная доставка.</small></label><button class="btn" type="submit">Сохранить настройки</button></form>
<aside class="admin-panel settings-preview"><div class="admin-panel-head"><div><span>Предпросмотр</span><h2><?= e(setting('store_name',STORE_NAME)) ?></h2></div></div><div class="settings-preview-card"><span>Телефон</span><b><?= e(setting('phone')) ?></b></div><div class="settings-preview-card"><span>Email</span><b><?= e(setting('email')) ?></b></div><div class="settings-preview-card"><span>Самовывоз</span><b><?= e(setting('address')) ?></b></div><div class="settings-preview-card"><span>Доставка</span><b><?= (float)setting('shipping','0') > 0 ? format_price((float)setting('shipping')) : 'Бесплатно' ?></b></div></aside>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
