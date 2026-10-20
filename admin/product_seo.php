<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
ensure_installed();
require_admin();
$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    $list = db()->query('SELECT id,name,image,image_alt,image_webp,image_width,image_height,image_title FROM products ORDER BY id DESC')->fetchAll();
    $adminTitle = 'SEO изображений'; require __DIR__ . '/_header.php'; ?>
    <div class="admin-page-head"><div><h1>SEO изображений</h1><p class="muted">Alt-текст, title, WebP и размеры изображений товаров.</p></div></div>
    <div class="seo-admin-list"><?php foreach ($list as $p): ?><a class="seo-admin-row" href="<?= e(url('admin/product_seo.php?id=' . (int)$p['id'])) ?>"><img src="<?= e(product_image_src($p)) ?>" alt="" width="64" height="64"><span><b><?= e($p['name']) ?></b><small><?= e($p['image_alt'] ?: 'Alt не заполнен') ?></small></span><em><?= !empty($p['image_webp']) ? 'WebP ✓' : 'WebP —' ?></em></a><?php endforeach; ?></div>
    <?php require __DIR__ . '/_footer.php'; exit;
}
$st=db()->prepare('SELECT * FROM products WHERE id=?'); $st->execute([$id]); $p=$st->fetch();
if(!$p){http_response_code(404);exit('Товар не найден');}
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check(); $alt=trim((string)($_POST['image_alt']??'')); $title=trim((string)($_POST['image_title']??'')); $webp=trim((string)($_POST['image_webp']??'')); $w=max(0,(int)($_POST['image_width']??0)); $h=max(0,(int)($_POST['image_height']??0));
    db()->prepare('UPDATE products SET image_alt=?,image_title=?,image_webp=?,image_width=?,image_height=? WHERE id=?')->execute([$alt,$title,$webp!==''?$webp:null,$w?:null,$h?:null,$id]);
    flash('ok','SEO-настройки изображения сохранены.'); redirect('admin/product_seo.php?id='.$id);
}
$adminTitle='SEO изображения'; require __DIR__ . '/_header.php'; ?>
<div class="admin-page-head"><div><h1>SEO изображения</h1><p class="muted"><?= e($p['name']) ?></p></div><a class="btn ghost" href="<?= e(url('admin/product_seo.php')) ?>">Все товары</a></div>
<div class="seo-editor-grid"><div class="box seo-preview-box"><picture><?php if(product_image_webp_src($p)!==''): ?><source srcset="<?= e(product_image_webp_src($p)) ?>" type="image/webp"><?php endif; ?><img src="<?= e(product_image_src($p)) ?>" alt="<?= e(product_image_alt($p)) ?>"></picture><p class="muted">Основное изображение: <?= e((string)$p['image']) ?></p></div>
<form method="post" class="box seo-editor-form"><?= csrf_field() ?><label>Alt-текст</label><input type="text" name="image_alt" maxlength="255" value="<?= e((string)($p['image_alt']??'')) ?>" placeholder="Например: iPhone 16 Pro, вид спереди"><label>Title изображения</label><input type="text" name="image_title" maxlength="255" value="<?= e((string)($p['image_title']??'')) ?>" placeholder="<?= e($p['name']) ?>"><label>Путь к WebP</label><input type="text" name="image_webp" value="<?= e((string)($p['image_webp']??'')) ?>" placeholder="assets/img/products/iphone-pro.webp"><div class="seo-dimensions"><label>Ширина, px<input type="number" name="image_width" min="0" value="<?= (int)($p['image_width']??0) ?>"></label><label>Высота, px<input type="number" name="image_height" min="0" value="<?= (int)($p['image_height']??0) ?>"></label></div><button class="btn" type="submit">Сохранить SEO</button></form></div>
<?php require __DIR__ . '/_footer.php';
