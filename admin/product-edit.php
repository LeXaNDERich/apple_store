<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
ensure_installed();
require_admin();

$id = (int) ($_GET['id'] ?? 0);
$row = [
    'name' => '', 'slug' => '', 'sku' => '', 'category_id' => 0, 'short_desc' => '', 'description' => '',
    'price' => 0, 'old_price' => '', 'stock' => 0, 'visual' => 'phone', 'image' => '', 'featured' => 0,
    'specs' => '{}', 'colors' => '[]', 'storage_options' => '[]',
];
if ($id) {
    $st = db()->prepare('SELECT * FROM products WHERE id = ?');
    $st->execute([$id]);
    $found = $st->fetch();
    if ($found) {
        $row = $found;
    } else {
        $id = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim((string) ($_POST['name'] ?? ''));
    $slug = trim((string) ($_POST['slug'] ?? ''));
    if ($slug === '') {
        $slug = slugify($name);
    }
    $image = handle_product_upload((string) ($row['image'] ?? ''));
    $data = [
        (int) ($_POST['category_id'] ?? 0),
        $name,
        $slug,
        trim((string) ($_POST['sku'] ?? '')),
        trim((string) ($_POST['short_desc'] ?? '')),
        trim((string) ($_POST['description'] ?? '')),
        (float) ($_POST['price'] ?? 0),
        $_POST['old_price'] === '' ? null : (float) $_POST['old_price'],
        (int) ($_POST['stock'] ?? 0),
        (string) ($_POST['visual'] ?? 'phone'),
        $image,
        (string) ($_POST['specs'] ?? '{}'),
        (string) ($_POST['colors'] ?? '[]'),
        (string) ($_POST['storage_options'] ?? '[]'),
        isset($_POST['featured']) ? 1 : 0,
    ];
    if ($name === '' || $data[0] <= 0) {
        flash('error', 'Укажите название и категорию.');
        redirect('admin/product-edit.php' . ($id ? '?id=' . $id : ''));
    }
    if ($id) {
        $data[] = $id;
        db()->prepare('UPDATE products SET category_id=?,name=?,slug=?,sku=?,short_desc=?,description=?,price=?,old_price=?,stock=?,visual=?,image=?,specs=?,colors=?,storage_options=?,featured=? WHERE id=?')->execute($data);
        flash('ok', 'Товар сохранён.');
        redirect('admin/product-edit.php?id=' . $id);
    }
    db()->prepare('INSERT INTO products (category_id,name,slug,sku,short_desc,description,price,old_price,stock,visual,image,specs,colors,storage_options,featured) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute($data);
    flash('ok', 'Товар создан.');
    redirect('admin/products.php');
}

$cats = categories();
$adminTitle = $id ? 'Редактирование' : 'Новый товар';
require __DIR__ . '/_header.php';
?>
<div class="admin-page-head premium"><div><p class="admin-kicker">Каталог</p><h1><?= $id ? 'Редактировать товар' : 'Новый товар' ?></h1><p class="muted"><?= $id ? e($row['name']) : 'Создайте новую карточку товара и заполните основные параметры.' ?></p></div><div class="admin-head-actions"><a class="btn ghost" href="<?= e(url('admin/products.php')) ?>">← К товарам</a><?php if ($id): ?><a class="btn ghost" href="<?= e(url('product.php?id=' . $id)) ?>" target="_blank">Открыть на сайте</a><?php endif; ?></div></div>
<form method="post" class="admin-product-editor" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <section class="admin-panel editor-main">
        <div class="admin-panel-head"><div><span>Основное</span><h2>Карточка товара</h2></div></div>
        <div class="form-grid two">
            <label>Название<input type="text" name="name" value="<?= e($row['name']) ?>" required></label>
            <label>Slug<input type="text" name="slug" value="<?= e($row['slug']) ?>" placeholder="создастся автоматически"></label>
            <label>Артикул<input type="text" name="sku" value="<?= e($row['sku']) ?>"></label>
            <label>Категория<select name="category_id" required><?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$row['category_id']===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></label>
        </div>
        <label>Краткое описание<input type="text" name="short_desc" value="<?= e($row['short_desc']) ?>" placeholder="Одна строка для карточки товара"></label>
        <label>Полное описание<textarea name="description" rows="5"><?= e((string)$row['description']) ?></textarea></label>
    </section>
    <aside class="admin-panel editor-side product-media-editor">
        <div class="admin-panel-head"><div><span>Медиа</span><h2>Изображение</h2></div></div>
        <?php if (!empty($row['image'])): ?><div class="editor-image-preview"><img src="<?= e(product_image_src($row)) ?>" alt=""></div><?php endif; ?>
        <label>Загрузить файл<input type="file" name="image_file" accept="image/jpeg,image/png,image/webp,image/gif"></label>
        <label>Или URL / путь<input type="text" name="image_url" value="<?= e((string)($row['image'] ?? '')) ?>" placeholder="https://… или assets/img/products/file.jpg"></label>
        <p class="form-help">JPG, PNG, WEBP или GIF до 6 МБ.</p>
    </aside>
    <section class="admin-panel editor-pricing">
        <div class="admin-panel-head"><div><span>Продажи</span><h2>Цена и наличие</h2></div></div>
        <div class="form-grid three"><label>Цена<input type="number" name="price" value="<?= e((string)$row['price']) ?>" step="1"></label><label>Старая цена<input type="number" name="old_price" value="<?= e((string)($row['old_price'] ?? '')) ?>" step="1"></label><label>Склад<input type="number" name="stock" value="<?= (int)$row['stock'] ?>"></label></div>
        <div class="form-grid two"><label>Тип визуала<select name="visual"><?php foreach (['phone','macbook','imac','mini','ipad','watch','airpods','max','acc'] as $v): ?><option <?= $row['visual']===$v?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select></label><label class="toggle-label"><span>Хит продаж</span><input type="checkbox" name="featured" <?= !empty($row['featured'])?'checked':'' ?>><i></i></label></div>
    </section>
    <section class="admin-panel editor-json">
        <div class="admin-panel-head"><div><span>Конфигурация</span><h2>Данные товара</h2></div><small>JSON</small></div>
        <label>Характеристики<textarea name="specs" rows="6"><?= e(is_string($row['specs']) ? $row['specs'] : json_encode($row['specs'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></textarea></label>
        <div class="form-grid two"><label>Цвета<textarea name="colors" rows="6"><?= e($row['colors']) ?></textarea></label><label>Конфигурации / память<textarea name="storage_options" rows="6"><?= e($row['storage_options']) ?></textarea></label></div>
    </section>
    <div class="editor-save-bar"><a href="<?= e(url('admin/products.php')) ?>">Отмена</a><button class="btn" type="submit">Сохранить товар</button></div>
</form>
<?php require __DIR__ . '/_footer.php'; ?>
