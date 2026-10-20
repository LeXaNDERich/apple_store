<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
ensure_installed();

$pageTitle = 'О магазине — ' . STORE_NAME;
$pageDescription = 'iTech — современный учебный интернет-магазин техники Apple с каталогом, акциями, отзывами и оформлением заказов.';
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero page-hero-about">
    <div class="wrap page-hero-inner">
        <div>
            <p class="page-kicker">iTech · since 2026</p>
            <h1>Техника, которую приятно выбирать.</h1>
            <p class="page-lead">Мы собрали интернет-магазин вокруг простой идеи: меньше визуального шума, больше понятной информации о товаре и аккуратный путь от выбора до заказа.</p>
            <div class="btns page-hero-actions"><a class="btn" href="<?= e(url('catalog.php')) ?>">Открыть каталог</a><a class="btn ghost" href="<?= e(url('contacts.php')) ?>">Связаться</a></div>
        </div>
        <div class="about-orbit" aria-hidden="true"><span class="orbit-core">iTech</span><i></i><i></i><i></i></div>
    </div>
</section>
<section class="section wrap about-metrics">
    <article><b>60+</b><span>товаров в каталоге</span></article>
    <article><b>6</b><span>основных категорий</span></article>
    <article><b>3</b><span>формата акций</span></article>
    <article><b>24/7</b><span>доступ к каталогу</span></article>
</section>
<section class="section wrap about-grid">
    <div class="about-copy">
        <p class="eyebrow-dark">Подход</p>
        <h2>Дизайн без лишнего.</h2>
        <p>В основе интерфейса — крупная типографика, спокойные поверхности, понятные карточки и акцент на самом устройстве. Покупателю не нужно искать цену, наличие или основные характеристики по всей странице.</p>
        <p>Каталог, акции, отзывы, корзина, оформление и личный кабинет собраны в единую визуальную систему — поэтому сайт ощущается цельным на компьютере и телефоне.</p>
    </div>
    <div class="about-principles">
        <article><span>01</span><div><h3>Актуальность</h3><p>Линейка товаров и витрина обновляются по мере выхода новых устройств.</p></div></article>
        <article><span>02</span><div><h3>Прозрачность</h3><p>Цена, скидка, наличие и условия получения видны до оформления заказа.</p></div></article>
        <article><span>03</span><div><h3>Удобство</h3><p>Минимум лишних шагов: фильтрация, корзина и заказ работают последовательно.</p></div></article>
    </div>
</section>
<section class="section wrap about-cta">
    <div><p class="eyebrow-dark">Готовы выбрать устройство?</p><h2>Каталог уже ждёт.</h2></div>
    <a class="btn" href="<?= e(url('catalog.php')) ?>">Смотреть все товары</a>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
