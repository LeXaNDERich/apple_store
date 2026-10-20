<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
ensure_installed();
$pageTitle='Каталог — Skeleton | '.STORE_NAME;
$pageDescription='Карточки товаров с skeleton-заглушками и Intersection Observer.';
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero page-hero-compact"><div class="wrap page-hero-inner single"><div><p class="page-kicker">Лабораторная №7</p><h1>Skeleton loading</h1><p class="page-lead"></p></div></div></section>
<section class="section wrap lazy-catalog-page"><div class="lazy-demo-note"><span>Пример 3</span><b></b></div><div class="lazy-products" id="lazy-products" aria-live="polite"></div></section>
<script>window.CATALOG_API_URL=<?= json_encode(url('api/products.php?limit=20&offset=0'), JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="<?= e(url('assets/js/lazy3.js')) ?>?v=2" defer></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
