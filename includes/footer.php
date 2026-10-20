</main>
<section class="service-strip">
    <div class="wrap service-strip-grid">
        <div><span class="service-icon">↗</span><div><b>Быстрая доставка</b><small>Курьером или самовывоз</small></div></div>
        <div><span class="service-icon">✓</span><div><b>Официальная гарантия</b><small>12 месяцев на технику</small></div></div>
        <div><span class="service-icon">↺</span><div><b>14 дней на обмен</b><small>Спокойно выбирайте своё</small></div></div>
        <div><span class="service-icon">✦</span><div><b>Поддержка</b><small>Поможем с выбором и заказом</small></div></div>
    </div>
</section>
<footer class="foot">
    <div class="wrap foot-grid">
        <div class="foot-brand">
            <a class="foot-logo" href="<?= e(url('index.php')) ?>"><img src="<?= e(url(setting('site_logo', 'uploads/logo.svg'))) ?>" alt="" width="38" height="38"><span><?= e(STORE_NAME) ?></span></a>
            <p>Современный магазин техники Apple: актуальные устройства, честные цены, акции и удобное оформление заказа.</p>
            <div class="foot-pills"><span>Apple</span><span>2026</span><span>iTech</span></div>
        </div>
        <div>
            <h4>Магазин</h4>
            <a href="<?= e(url('catalog.php')) ?>">Каталог</a>
            <a href="<?= e(url('promotions.php')) ?>">Акции</a>
            <a href="<?= e(url('about.php')) ?>">О магазине</a>
            <a href="<?= e(url('contacts.php')) ?>">Контакты</a>
        </div>
        <div>
            <h4>Покупателю</h4>
            <a href="<?= e(url('profile.php')) ?>">Личный кабинет</a>
            <a href="<?= e(url('cart.php')) ?>">Корзина</a>
            <a href="<?= e(url('catalog.php?cat=accessories')) ?>">Аксессуары</a>
            <span>Ежедневно 10:00–22:00</span>
        </div>
        <div class="foot-contact">
            <h4>Связь</h4>
            <a href="tel:<?= e(preg_replace('~[^+0-9]~', '', setting('phone', '+7 (495) 000-16-00'))) ?>"><?= e(setting('phone', '+7 (495) 000-16-00')) ?></a>
            <a href="mailto:<?= e(setting('email', 'hello@itech.local')) ?>"><?= e(setting('email', 'hello@itech.local')) ?></a>
            <span><?= e(setting('address', 'Москва, Тверская, 7')) ?></span>
        </div>
    </div>
    <div class="wrap copy"><span>© <?= date('Y') ?> <?= e(STORE_NAME) ?></span><span>Учебный интернет-магазин · Колледж</span></div>
</footer>
<?php $metrikaGoals = metrika_pull_goals(); ?>
<script>window.__METRIKA_SERVER_GOALS__ = <?= json_encode($metrikaGoals, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="<?= e(url('assets/js/app.js')) ?>?v=40" defer></script>
<script type="text/javascript">
    (function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};m[i].l=1*new Date();for(var j=0;j<document.scripts.length;j++){if(document.scripts[j].src===r){return;}}k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})(window,document,'script','https://mc.yandex.ru/metrika/tag.js?id=113557563','ym');
    ym(113557563,'init',{ssr:true,webvisor:true,clickmap:true,ecommerce:"dataLayer",referrer:document.referrer,url:location.href,accurateTrackBounce:true,trackLinks:true});
</script>
<noscript><div><img src="https://mc.yandex.ru/watch/113557563" style="position:absolute; left:-9999px;" alt=""></div></noscript>
</body>
</html>
