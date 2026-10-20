<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
ensure_installed();

$productCount = (int) db()->query('SELECT COUNT(*) FROM products')->fetchColumn();
$reviewCount = (int) db()->query('SELECT COUNT(*) FROM reviews')->fetchColumn();
$demoProductId = (int) db()->query('SELECT id FROM products ORDER BY featured DESC, id ASC LIMIT 1')->fetchColumn();
$pageTitle = 'Лабораторная работа №4 — отзывы и SEO | ' . STORE_NAME;
$pageDescription = 'Лабораторная работа №4: реализация отзывов интернет-магазина, звёздного рейтинга, проверок покупателя, AJAX и SEO-оптимизации.';
$pageKeywords = 'лабораторная работа 4, отзывы, SEO, PHP, MySQL, Schema.org';
require __DIR__ . '/includes/header.php';
?>
<section class="section wrap lab-page">
    <div class="crumbs"><a href="<?= e(url('index.php')) ?>">Главная</a> / Лабораторная работа №4</div>
    <div class="lab-hero box">
        <p class="eyebrow-dark">Web-разработка · 4 курс</p>
        <h1>Лабораторная работа №4</h1>
        <p class="lab-lead">Страницы отзывов интернет-магазина <?= e(STORE_NAME) ?> + SEO-оптимизация.</p>
        <div class="lab-kpis">
            <div><strong><?= $productCount ?></strong><span>товаров</span></div>
            <div><strong><?= $reviewCount ?></strong><span>отзывов в БД</span></div>
            <div><strong>1–5</strong><span>звёзд рейтинга</span></div>
            <div><strong>AJAX</strong><span>без перезагрузки</span></div>
        </div>
        <div class="btns lab-actions">
            <a class="btn" href="<?= e(url('product.php?id=' . $demoProductId . '#reviews')) ?>">Открыть отзывы товара</a>
            <a class="btn ghost" href="<?= e(url('catalog.php')) ?>">Открыть каталог</a>
        </div>
    </div>

    <section class="lab-section">
        <h2>1. Цель работы</h2>
        <p>Создать функциональный модуль отзывов для интернет-магазина: привязать отзыв к товару и пользователю, разрешить публикацию только покупателям, реализовать рейтинг 1–5, AJAX-отправку, адаптивную вёрстку и SEO-разметку.</p>
    </section>

    <section class="lab-section">
        <h2>2. Что реализовано</h2>
        <div class="lab-check-grid">
            <article class="lab-check"><b>✓ Отзывы товара</b><span>Имя автора, звёзды, текст, дата, Schema.org Review.</span></article>
            <article class="lab-check"><b>✓ Только покупатели</b><span>Проверка orders + order_items, доступ к отзыву появляется только при статусе «Доставлен».</span></article>
            <article class="lab-check"><b>✓ Один отзыв</b><span>UNIQUE по user_id/product_id и обновление существующего отзыва.</span></article>
            <article class="lab-check"><b>✓ Страница товара</b><span>Отзывы, рейтинг и форма публикации доступны непосредственно на странице конкретного товара.</span></article>
            <article class="lab-check"><b>✓ AJAX + fallback</b><span>Отправка без перезагрузки; без JS форма работает через product.php.</span></article>
            <article class="lab-check"><b>✓ Защита</b><span>CSRF, prepared statements, диапазон рейтинга, длина текста, экранирование XSS.</span></article>
            <article class="lab-check"><b>✓ SEO</b><span>Уникальные title/description/keywords, h1/h2/h3, alt, Product/Review/AggregateRating.</span></article>
            <article class="lab-check"><b>✓ Адаптивность</b><span>2 колонки до 900 px, 1 колонка до 600 px, крупные звёзды для touch.</span></article>
            <article class="lab-check"><b>✓ Производительность</b><span>Один CSS, один JS, системные шрифты, пагинация каталога, gzip/cache через .htaccess.</span></article>
            <article class="lab-check"><b>✓ SEO-инфраструктура</b><span>sitemap.xml, robots.txt, canonical и Open Graph для товара.</span></article>
        </div>
    </section>

    <section class="lab-section">
        <h2>3. Таблица reviews</h2>
        <pre class="code-block"><code>CREATE TABLE reviews (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
  text TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_rev_prod (product_id),
  INDEX idx_rev_user (user_id),
  UNIQUE KEY uq_rev_user_product (product_id, user_id),
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;</code></pre>
    </section>

    <section class="lab-section">
        <h2>4. Проверка факта покупки</h2>
        <pre class="code-block"><code>SELECT 1
FROM orders o
JOIN order_items oi ON oi.order_id = o.id
WHERE o.user_id = ?
  AND oi.product_id = ?
  AND o.status = 'delivered'
LIMIT 1;</code></pre>
        <p>Если запрос возвращает строку — пользователь имеет право оставить или изменить отзыв. Для гостя показывается предложение войти, если заказ ещё не доставлен — сообщение о необходимости дождаться статуса «Доставлен».</p>
    </section>

    <section class="lab-section">
        <h2>5. Тестовые сценарии</h2>
        <div class="table-scroll">
            <table class="data lab-table">
                <tr><th>Сценарий</th><th>Действие</th><th>Ожидаемый результат</th></tr>
                <tr><td>Гость</td><td>Открыть товар и вкладку отзывов</td><td>Отзывы видны, форма предлагает войти.</td></tr>
                <tr><td>Не покупатель</td><td>Войти и открыть товар без заказа</td><td>Публикация заблокирована сообщением о покупке.</td></tr>
                <tr><td>Покупатель</td><td>Поставить 1–5 звёзд и отправить текст ≥ 3 символов</td><td>Отзыв появляется без перезагрузки, рейтинг пересчитывается.</td></tr>
                <tr><td>Повторный отзыв</td><td>Изменить оценку или текст</td><td>Существующая строка обновляется, дубль не создаётся.</td></tr>
                <tr><td>Короткий текст</td><td>Отправить 1–2 символа</td><td>Клиент и сервер отклоняют отправку.</td></tr>
                <tr><td>XSS</td><td>Отправить HTML/JS в тексте</td><td>Теги удаляются при сохранении, вывод экранируется.</td></tr>
            </table>
        </div>
    </section>

    <section class="lab-section">
        <h2>6. Кроссбраузерность</h2>
        <p class="muted">Код написан на стандартных HTML/CSS/JS API без тяжёлых фреймворков. Итоговую ручную проверку следует выполнить на компьютере, где сдаётся работа.</p>
        <div class="table-scroll">
            <table class="data lab-table">
                <tr><th>Браузер</th><th>Что проверить</th><th>Статус в проекте</th></tr>
                <tr><td>Chrome</td><td>Табы, звёзды, AJAX, маска телефона</td><td>Реализовано; требуется ручной прогон</td></tr>
                <tr><td>Firefox</td><td>Табы, звёзды, AJAX, маска телефона</td><td>Реализовано; требуется ручной прогон</td></tr>
                <tr><td>Safari</td><td>Табы, звёзды, AJAX, touch на 360 px</td><td>Реализовано; требуется ручной прогон</td></tr>
                <tr><td>Edge</td><td>Табы, звёзды, AJAX, маска телефона</td><td>Реализовано; требуется ручной прогон</td></tr>
            </table>
        </div>
    </section>

    <section class="lab-section">
        <h2>7. SEO-оптимизация</h2>
        <ul class="lab-list">
            <li>У каждого товара формируется уникальный title: «Название — отзывы, характеристики, цена».</li>
            <li>Description содержит название товара, «отзывы владельцев», характеристики и краткое описание.</li>
            <li>На странице используются h1 для названия, h2 для отзывов и h3 для характеристик/формы.</li>
            <li>Отзывы размечены Schema.org Review, средний рейтинг — AggregateRating, товар — Product.</li>
            <li>Изображения товара имеют осмысленный alt «Фото Название — отзывы».</li>
            <li>Добавлены sitemap, robots, canonical, Open Graph и внутренняя перелинковка каталог ↔ товар ↔ личный кабинет.</li>
        </ul>
    </section>

    <section class="lab-section">
        <h2>8. Ответы на контрольные вопросы</h2>
        <div class="lab-qa">
            <details open><summary>1. Почему отзыв только после покупки?</summary><p>Это снижает риск спама, накрутки рейтинга и отзывов от людей, которые не пользовались товаром. В проекте право подтверждается по заказам.</p></details>
            <details><summary>2. Зачем внешние ключи и ON DELETE CASCADE?</summary><p>product_id связывает отзыв с товаром, user_id — с автором. Внешние ключи не дают создать «висячий» отзыв, а CASCADE автоматически удаляет связанные отзывы при удалении товара или пользователя.</p></details>
            <details><summary>3. Как проверить, что заказ уже доставлен?</summary><p>Сервер ищет конкретный товар в заказах пользователя со статусом delivered. Пока заказ собирается, находится в пути или отменён, форма отзыва недоступна.</p></details>
            <details><summary>4. Как убрать дубли отзывов?</summary><p>На пару product_id + user_id действует UNIQUE. Повторная отправка выполняет upsert и обновляет прежнюю запись.</p></details>
            <details><summary>5. Какие проверки обязательны?</summary><p>Рейтинг 1–5, текст 3–2000 символов, авторизация, подтверждение покупки, CSRF. HTML-теги удаляются, а функция e() использует htmlspecialchars при выводе.</p></details>
            <details><summary>6. Какие SEO-метатеги нужны?</summary><p>Title, description и keywords. Description должен быть уникальным, содержать название товара и естественно описывать пользу страницы, а не повторять одинаковый шаблон без контекста.</p></details>
            <details><summary>7. Зачем Review и AggregateRating?</summary><p>Микроразметка объясняет поисковой системе, где находится отзыв и агрегированный рейтинг. Поисковик может использовать эти данные при формировании расширенного представления страницы, если она соответствует его правилам.</p></details>
            <details><summary>8. Зачем один CSS/JS, системные шрифты и пагинация?</summary><p>Меньше запросов и загрузок, быстрее первый рендер и меньше данных на странице. Дополнительно помогают сжатие, кэширование статических файлов и оптимизированные изображения.</p></details>
            <details><summary>9. Что нужно для 360 px?</summary><p>Одна колонка, модалка не шире экрана, крупные touch-цели и звёзды не меньше 26 px. В проекте звёзды имеют 32 px и увеличенную область нажатия.</p></details>
            <details><summary>10. Три приёмочных сценария?</summary><p>Гость видит отзывы, но не публикует; покупатель публикует валидный отзыв; повторная отправка того же покупателя обновляет запись, а не создаёт дубль.</p></details>
        </div>
    </section>

    <section class="lab-section lab-conclusion box">
        <h2>Вывод</h2>
        <p>Отзывы создают регулярно обновляемый пользовательский контент, повышают доверие к карточке товара и дают поисковым системам структурированные данные о рейтинге. Реализация разделяет серверные проверки и интерфейс: даже при отключённом JavaScript основные действия остаются доступны на странице товара.</p>
    </section>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
