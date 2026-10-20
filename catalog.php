<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
ensure_installed();

$catSlug = trim((string) ($_GET['cat'] ?? ''));
$q = trim((string) ($_GET['q'] ?? ''));
$sort = (string) ($_GET['sort'] ?? 'featured');
$min = max(0, (int) ($_GET['min'] ?? 0));
$max = max(0, (int) ($_GET['max'] ?? 0));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;

$where = ' WHERE 1=1';
$args = [];
$title = 'Каталог';
$currentCat = null;

if ($catSlug !== '') {
    $st = db()->prepare('SELECT * FROM categories WHERE slug = ?');
    $st->execute([$catSlug]);
    $currentCat = $st->fetch() ?: null;
    if ($currentCat) {
        $where .= ' AND p.category_id = ?';
        $args[] = (int) $currentCat['id'];
        $title = (string) $currentCat['name'];
    }
}
if ($q !== '') {
    $where .= ' AND (p.name LIKE ? OR p.short_desc LIKE ? OR p.sku LIKE ?)';
    $like = '%' . $q . '%';
    array_push($args, $like, $like, $like);
    $title = 'Поиск: ' . $q;
}
if ($min > 0) {
    $where .= ' AND p.price >= ?';
    $args[] = $min;
}
if ($max > 0) {
    $where .= ' AND p.price <= ?';
    $args[] = $max;
}

$countSt = db()->prepare('SELECT COUNT(*) FROM products p JOIN categories c ON c.id = p.category_id' . $where);
$countSt->execute($args);
$total = (int) $countSt->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

switch ($sort) {
    case 'price_asc':
        $order = 'p.price ASC, p.id DESC';
        break;
    case 'price_desc':
        $order = 'p.price DESC, p.id DESC';
        break;
    case 'name':
        $order = 'p.name ASC, p.id DESC';
        break;
    default:
        $order = 'p.featured DESC, p.id DESC';
        break;
}

$sql = 'SELECT p.*, c.name AS category_name, c.slug AS category_slug,
               COALESCE(rv.review_count, 0) AS review_count,
               COALESCE(rv.avg_rating, 0) AS avg_rating
        FROM products p
        JOIN categories c ON c.id = p.category_id
        LEFT JOIN (
            SELECT product_id, COUNT(*) AS review_count, ROUND(AVG(rating), 1) AS avg_rating
            FROM reviews GROUP BY product_id
        ) rv ON rv.product_id = p.id' . $where . " ORDER BY $order LIMIT $perPage OFFSET $offset";
$st = db()->prepare($sql);
$st->execute($args);
$products = $st->fetchAll();

$pageTitle = $title . ($page > 1 ? ' — страница ' . $page : '') . ' | ' . STORE_NAME;
$pageDescription = $currentCat
    ? $currentCat['name'] . ' в ' . STORE_NAME . ': цены, характеристики и отзывы владельцев. Удобный каталог с быстрым просмотром и рейтингами.'
    : 'Каталог ' . STORE_NAME . ': техника Apple, характеристики, цены, отзывы владельцев и рейтинги покупателей.';
$pageKeywords = trim(($currentCat['name'] ?? 'Apple') . ', каталог, отзывы, цены, характеристики');
$catalogCategories = categories();
require __DIR__ . '/includes/header.php';

$queryBase = $_GET;
unset($queryBase['page']);
function catalog_page_url(array $base, int $page): string {
    if ($page > 1) $base['page'] = $page;
    return url('catalog.php' . ($base ? '?' . http_build_query($base) : ''));
}
?>
<section class="catalog-hero">
    <div class="wrap catalog-hero-inner">
        <div><p class="page-kicker"><?= $currentCat ? e($currentCat['name']) : 'iTech Catalog' ?></p><h1><?= e($title) ?></h1><p><?= $currentCat ? e($currentCat['description']) : 'iPhone, Mac, iPad, Apple Watch, аудио и аксессуары — в одной аккуратной витрине.' ?></p></div>
        <div class="catalog-count"><strong><?= $total ?></strong><span><?= $total === 1 ? 'товар' : 'товаров' ?></span></div>
    </div>
</section>
<section class="wrap catalog-shell">
    <nav class="category-pills" aria-label="Категории каталога">
        <a class="<?= $catSlug === '' ? 'active' : '' ?>" href="<?= e(url('catalog.php')) ?>">Все</a>
        <?php foreach ($catalogCategories as $category): ?><a class="<?= $catSlug === $category['slug'] ? 'active' : '' ?>" href="<?= e(url('catalog.php?cat=' . urlencode($category['slug']))) ?>"><?= e($category['name']) ?></a><?php endforeach; ?>
    </nav>
    <form class="catalog-filters" method="get">
        <?php if ($catSlug !== ''): ?><input type="hidden" name="cat" value="<?= e($catSlug) ?>"><?php endif; ?>
        <label class="filter-search"><span>Поиск</span><input type="search" name="q" value="<?= e($q) ?>" placeholder="Название или артикул"></label>
        <label><span>Цена от</span><input type="number" name="min" value="<?= $min ?: '' ?>" placeholder="0" min="0"></label>
        <label><span>Цена до</span><input type="number" name="max" value="<?= $max ?: '' ?>" placeholder="Без лимита" min="0"></label>
        <label><span>Сортировка</span><select name="sort"><option value="featured" <?= $sort === 'featured' ? 'selected' : '' ?>>Сначала хиты</option><option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Сначала дешевле</option><option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Сначала дороже</option><option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>По имени</option></select></label>
        <button class="btn" type="submit">Применить</button>
        <?php if ($q !== '' || $min || $max || $sort !== 'featured'): ?><a class="filter-reset" href="<?= e(url('catalog.php' . ($catSlug ? '?cat=' . urlencode($catSlug) : ''))) ?>">Сбросить</a><?php endif; ?>
    </form>

    <div class="catalog-result-head"><div><span>Результат</span><strong><?= $total ?> <?= $total === 1 ? 'позиция' : 'позиций' ?></strong></div><?php if ($pages > 1): ?><small>Страница <?= $page ?> из <?= $pages ?></small><?php endif; ?></div>

    <?php if (!$products): ?>
        <div class="empty-state"><span class="empty-state-icon">⌕</span><h2>Ничего не нашли</h2><p>Попробуйте изменить поисковый запрос или диапазон цены.</p><a class="btn" href="<?= e(url('catalog.php')) ?>">Показать весь каталог</a></div>
    <?php else: ?>
        <div class="grid catalog-grid"><?php foreach ($products as $p) include __DIR__ . '/includes/card.php'; ?></div>
        <?php if ($pages > 1): ?>
            <nav class="pagination" aria-label="Страницы каталога">
                <?php if ($page > 1): ?><a href="<?= e(catalog_page_url($queryBase, $page - 1)) ?>" aria-label="Предыдущая страница">←</a><?php endif; ?>
                <?php for ($i = 1; $i <= $pages; $i++): ?><?php if ($i === $page): ?><span class="current" aria-current="page"><?= $i ?></span><?php else: ?><a href="<?= e(catalog_page_url($queryBase, $i)) ?>"><?= $i ?></a><?php endif; ?><?php endfor; ?>
                <?php if ($page < $pages): ?><a href="<?= e(catalog_page_url($queryBase, $page + 1)) ?>" aria-label="Следующая страница">→</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
