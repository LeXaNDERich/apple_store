<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
ensure_installed();
$adminUser = require_admin();
$adminTitle = $adminTitle ?? 'Админка';
$currentAdmin = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
$activeAdmin = $currentAdmin === 'product-edit.php' ? 'products.php' : $currentAdmin;
$adminNav = [
    ['index.php','⌂','Обзор'],
    ['products.php','◇','Товары'],
    ['categories.php','▦','Категории'],
    ['product_seo.php','◉','SEO изображений'],
    ['promotions.php','%','Акции'],
    ['orders.php','□','Заказы'],
    ['statuses.php','↻','Статусы'],
    ['users.php','◎','Пользователи'],
    ['messages.php','✉','Сообщения'],
    ['reviews.php','★','Отзывы'],
    ['settings.php','⚙','Настройки'],
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($adminTitle) ?> — iTech Admin</title>
    <meta name="theme-color" content="#111318">
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>?v=40">
</head>
<body class="admin-body">
<div class="admin-shell">
    <aside class="admin-sidebar">
        <a class="admin-sidebar-brand" href="<?= e(url('admin/index.php')) ?>"><span class="admin-brand-mark">i</span><span><b>iTech</b><small>Control Center</small></span></a>
        <nav class="admin-side-nav" aria-label="Разделы админ-панели">
            <?php foreach ($adminNav as $item): ?><a class="<?= $activeAdmin === $item[0] ? 'active' : '' ?>" href="<?= e(url('admin/' . $item[0])) ?>"><span><?= $item[1] ?></span><b><?= e($item[2]) ?></b></a><?php endforeach; ?>
        </nav>
        <div class="admin-sidebar-bottom">
            <a class="admin-back-store" href="<?= e(url('index.php')) ?>">← В магазин</a>
            <a class="admin-side-logout" href="<?= e(url('logout.php')) ?>">Выйти</a>
        </div>
    </aside>
    <div class="admin-workspace">
        <header class="admin-toolbar">
            <button class="admin-menu-toggle" type="button" aria-label="Открыть меню">☰</button>
            <div><span>iTech Admin</span><strong><?= e($adminTitle) ?></strong></div>
            <div class="admin-toolbar-user"><span class="admin-avatar"><?= e(mb_strtoupper(mb_substr((string)$adminUser['name'],0,1))) ?></span><div><small>Администратор</small><b><?= e($adminUser['name']) ?></b></div></div>
        </header>
        <main class="admin-main">
<?php
$ok = flash('ok');
$err = flash('error');
if ($ok) echo '<div class="flash ok"><span>✓</span>' . e($ok) . '</div>';
if ($err) echo '<div class="flash err"><span>!</span>' . e($err) . '</div>';
?>
