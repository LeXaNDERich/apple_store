<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$pageTitle = $pageTitle ?? STORE_NAME;
$pageDescription = $pageDescription ?? (STORE_TAGLINE . ' — каталог техники Apple, доставка, гарантия и отзывы покупателей.');
$user = current_user();
$cats = db_ready() ? categories() : [];
$q = trim((string) ($_GET['q'] ?? ''));
$siteName = setting('site_name', setting('store_name', STORE_NAME));
$siteDescription = setting('site_description', $pageDescription);
$logoPath = setting('site_logo', 'uploads/logo.svg');
$logoAlt = setting('site_logo_alt', 'Логотип ' . $siteName);
$logoUrl = absolute_url(url($logoPath));
$currentFile = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
$bodyClass = 'page-' . preg_replace('~[^a-z0-9]+~i', '-', pathinfo($currentFile, PATHINFO_FILENAME));
$bag = '<svg width="18" height="19" viewBox="0 0 17 18" fill="none" aria-hidden="true"><path d="M4.2 6.2V5a4.3 4.3 0 0 1 8.6 0v1.2" stroke="currentColor" stroke-width="1.3"/><path d="M2.4 6.2h12.2l-.7 9.3H3.1L2.4 6.2Z" stroke="currentColor" stroke-width="1.3"/></svg>';
$person = '<svg width="17" height="17" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="6.2" r="3.2" stroke="currentColor" stroke-width="1.5"/><path d="M3.6 17c.4-3.3 2.8-5.1 6.4-5.1s6 1.8 6.4 5.1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <?php if (!empty($pageKeywords)): ?><meta name="keywords" content="<?= e($pageKeywords) ?>"><?php endif; ?>
    <meta name="robots" content="index,follow,max-image-preview:large">
    <meta name="theme-color" content="#f5f5f7">
    <?php if (!empty($canonicalUrl)): ?><link rel="canonical" href="<?= e($canonicalUrl) ?>"><?php endif; ?>
    <meta property="og:locale" content="ru_RU">
    <meta property="og:type" content="<?= e($ogType ?? 'website') ?>">
    <meta property="og:site_name" content="<?= e(STORE_NAME) ?>">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">
    <?php if (!empty($canonicalUrl)): ?><meta property="og:url" content="<?= e($canonicalUrl) ?>"><?php endif; ?>
    <?php if (!empty($ogImage)): ?><meta property="og:image" content="<?= e($ogImage) ?>"><?php endif; ?>
    <link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="preload" href="<?= e(url('assets/css/style.css')) ?>?v=43" as="style">
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>?v=43">
    <script type="application/ld+json">
<?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Organization',
    'name' => $siteName,
    'url' => absolute_url(url('index.php')),
    'logo' => $logoUrl,
    'description' => $siteDescription,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    </script>
</head>
<body class="<?= e($bodyClass) ?>">
<div class="announce"><span>Бесплатная доставка от 0 ₽</span><i></i><span>Обмен 14 дней</span><i></i><span>Гарантия 1 год</span></div>
<header class="top">
    <div class="top-inner">
        <a class="logo" href="<?= e(url('index.php')) ?>" aria-label="<?= e($siteName) ?>">
            <img class="site-logo-img" src="<?= e(url($logoPath)) ?>" alt="<?= e($logoAlt) ?>" width="32" height="32" decoding="async">
            <span><?= e($siteName) ?></span>
        </a>
        <button class="nav-toggle" type="button" aria-label="Меню" aria-expanded="false"><span></span><span></span><span></span></button>
        <nav class="nav" aria-label="Основная навигация">
            <a class="<?= $currentFile === 'index.php' ? 'active' : '' ?>" href="<?= e(url('index.php')) ?>">Главная</a>
            <?php foreach ($cats as $c): ?>
                <a href="<?= e(url('catalog.php?cat=' . urlencode($c['slug']))) ?>"><?= e($c['name']) ?></a>
            <?php endforeach; ?>
            <a class="<?= $currentFile === 'promotions.php' ? 'active' : '' ?>" href="<?= e(url('promotions.php')) ?>">Акции</a>
            <a class="<?= $currentFile === 'catalog.php' && empty($_GET['cat']) ? 'active' : '' ?>" href="<?= e(url('catalog.php')) ?>">Каталог</a>
        </nav>
        <form class="search" action="<?= e(url('catalog.php')) ?>" method="get" role="search">
            <svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="8.5" cy="8.5" r="5.5"></circle><path d="m12.6 12.6 4 4"></path></svg>
            <input type="search" name="q" placeholder="Поиск" value="<?= e($q) ?>" aria-label="Поиск товаров">
        </form>
        <div class="top-actions">
            <?php if ($user): ?>
                <a class="account-link" href="<?= e(url('profile.php')) ?>" title="Личный кабинет"><?= $person ?><span><?= e($user['name']) ?></span></a>
                <?php if (is_admin()): ?><a class="admin-link" href="<?= e(url('admin/index.php')) ?>">Admin</a><?php endif; ?>
            <?php else: ?>
                <a class="account-link" href="<?= e(url('login.php')) ?>"><?= $person ?><span>Войти</span></a>
            <?php endif; ?>
            <a class="icon-btn cart-link" href="<?= e(url('cart.php')) ?>" aria-label="Корзина, товаров: <?= cart_count() ?>"><?= $bag ?><span class="badge"><?= cart_count() ?></span></a>
        </div>
    </div>
</header>
<main>
<?php
$ok = flash('ok');
$err = flash('error');
if ($ok): ?><div class="flash ok"><span>✓</span><?= e($ok) ?></div><?php endif; ?>
<?php if ($err): ?><div class="flash err"><span>!</span><?= e($err) ?></div><?php endif; ?>
