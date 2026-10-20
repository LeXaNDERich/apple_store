<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
ensure_installed();
require_admin();

function seo_slug_file(string $slug): string
{
    $slug = strtolower(trim($slug));
    $slug = preg_replace('~[^a-z0-9-]+~', '-', $slug) ?: 'product';
    return trim($slug, '-') ?: 'product';
}

function seo_http_download(string $url): array
{
    if (!function_exists('curl_init')) {
        return [false, '', 'На сервере недоступен cURL'];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_TIMEOUT => 35,
        CURLOPT_USERAGENT => 'Mozilla/5.0 iTech SEO image migration',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => ['Accept: image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8'],
    ]);
    $body = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ctype = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if (!is_string($body) || $body === '' || $status < 200 || $status >= 300) {
        return [false, '', $error !== '' ? $error : ('HTTP ' . $status)];
    }

    return [true, $body, $ctype];
}

function seo_image_extension_from_bytes(string $bytes, string $contentType = ''): string
{
    $mime = '';
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        if ($fi) {
            $mime = (string) finfo_buffer($fi, $bytes);
            finfo_close($fi);
        }
    }
    if ($mime === '') {
        $mime = strtolower(trim(explode(';', $contentType)[0] ?? ''));
    }

    switch ($mime) {
        case 'image/jpeg': return 'jpg';
        case 'image/png': return 'png';
        case 'image/webp': return 'webp';
        case 'image/gif': return 'gif';
        default: return '';
    }
}

function seo_generate_webp(string $sourceFile, string $targetFile): bool
{
    if (!function_exists('imagecreatefromstring') || !function_exists('imagewebp')) {
        return false;
    }
    $bytes = @file_get_contents($sourceFile);
    if (!is_string($bytes) || $bytes === '') {
        return false;
    }
    $im = @imagecreatefromstring($bytes);
    if (!$im) {
        return false;
    }
    if (function_exists('imagepalettetotruecolor')) {
        @imagepalettetotruecolor($im);
    }
    @imagealphablending($im, true);
    @imagesavealpha($im, true);
    $ok = @imagewebp($im, $targetFile, 84);
    imagedestroy($im);
    return (bool) $ok;
}

function seo_process_product(array $p): array
{
    $id = (int) $p['id'];
    $name = trim((string) $p['name']);
    $slug = seo_slug_file((string) $p['slug']);
    $image = trim((string) $p['image']);
    $productsDir = APP_ROOT . '/assets/img/products';
    if (!is_dir($productsDir) && !mkdir($productsDir, 0775, true) && !is_dir($productsDir)) {
        return ['ok' => false, 'message' => 'Не удалось создать assets/img/products'];
    }

    $localImage = '';
    $relativeImage = $image;
    $downloaded = false;

    if (preg_match('~^https?://~i', $image)) {
        [$ok, $bytes, $ctype] = seo_http_download($image);
        if (!$ok) {
            // Даже если изображение не скачалось, SEO-текст всё равно заполняем.
            db()->prepare('UPDATE products SET image_alt=?, image_title=? WHERE id=?')
                ->execute(['Изображение товара ' . $name, $name, $id]);
            return ['ok' => false, 'message' => 'Не удалось скачать: ' . $ctype];
        }
        $ext = seo_image_extension_from_bytes($bytes, $ctype);
        if ($ext === '') {
            db()->prepare('UPDATE products SET image_alt=?, image_title=? WHERE id=?')
                ->execute(['Изображение товара ' . $name, $name, $id]);
            return ['ok' => false, 'message' => 'Не удалось определить формат изображения'];
        }
        $relativeImage = 'assets/img/products/' . $slug . '.' . $ext;
        $localImage = APP_ROOT . '/' . $relativeImage;
        if (@file_put_contents($localImage, $bytes) === false) {
            return ['ok' => false, 'message' => 'Не удалось сохранить локальный файл'];
        }
        $downloaded = true;
    } else {
        $relativeImage = ltrim($image, '/');
        $localImage = APP_ROOT . '/' . $relativeImage;
        if (!is_file($localImage)) {
            db()->prepare('UPDATE products SET image_alt=?, image_title=? WHERE id=?')
                ->execute(['Изображение товара ' . $name, $name, $id]);
            return ['ok' => false, 'message' => 'Локальный файл не найден: ' . $relativeImage];
        }
    }

    $size = @getimagesize($localImage);
    if (!$size || empty($size[0]) || empty($size[1])) {
        return ['ok' => false, 'message' => 'Не удалось определить размеры изображения'];
    }
    $width = (int) $size[0];
    $height = (int) $size[1];

    $currentExt = strtolower(pathinfo($relativeImage, PATHINFO_EXTENSION));
    if ($currentExt === 'webp') {
        $relativeWebp = $relativeImage;
    } else {
        $relativeWebp = 'assets/img/products/' . $slug . '.webp';
        $webpFile = APP_ROOT . '/' . $relativeWebp;
        if (!is_file($webpFile)) {
            if (!seo_generate_webp($localImage, $webpFile)) {
                $relativeWebp = '';
            }
        }
    }

    $alt = 'Изображение товара ' . $name;
    $title = $name;

    $st = db()->prepare('UPDATE products SET image=?, image_alt=?, image_webp=?, image_width=?, image_height=?, image_title=? WHERE id=?');
    $st->execute([
        $relativeImage,
        $alt,
        $relativeWebp !== '' ? $relativeWebp : null,
        $width,
        $height,
        $title,
        $id,
    ]);

    $details = $width . '×' . $height;
    if ($relativeWebp !== '') $details .= ' · WebP ✓';
    else $details .= ' · WebP не создан (проверьте GD)';
    if ($downloaded) $details .= ' · перенесено локально';

    return ['ok' => true, 'message' => $details];
}

$results = [];
$done = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $products = db()->query('SELECT * FROM products ORDER BY id')->fetchAll();
    foreach ($products as $p) {
        $results[] = [
            'id' => (int) $p['id'],
            'name' => (string) $p['name'],
            'result' => seo_process_product($p),
        ];
    }
    $done = true;
}

$rows = db()->query("SELECT id,name,image,image_alt,image_webp,image_width,image_height,image_title FROM products ORDER BY id")->fetchAll();
$complete = 0;
foreach ($rows as $r) {
    if (trim((string)$r['image_alt']) !== '' && trim((string)$r['image_webp']) !== '' && (int)$r['image_width'] > 0 && (int)$r['image_height'] > 0 && trim((string)$r['image_title']) !== '') {
        $complete++;
    }
}

$adminTitle = 'SEO всех изображений';
require __DIR__ . '/admin/_header.php';
?>
<div class="admin-page-head">
    <div>
        <h1>SEO всех изображений</h1>
        <p class="muted">Полностью заполнено: <?= (int)$complete ?> из <?= count($rows) ?> товаров.</p>
    </div>
    <a class="btn ghost" href="<?= e(url('admin/product_seo.php')) ?>">SEO изображений</a>
</div>

<?php if ($done): ?>
<div class="box" style="margin-bottom:18px">
    <h2>Результат обработки</h2>
    <?php foreach ($results as $row): ?>
        <p style="margin:7px 0;color:<?= $row['result']['ok'] ? '#157347' : '#b42318' ?>">
            <b>#<?= (int)$row['id'] ?> <?= e($row['name']) ?></b> — <?= e($row['result']['message']) ?>
        </p>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="box" style="margin-bottom:18px">
    <p><b>Что делает кнопка:</b> для внешних картинок скачивает файл в <code>assets/img/products/</code>, определяет реальные width/height, создаёт WebP через GD, заполняет alt/title и обновляет поля таблицы products. Для уже локальных файлов определяет размеры и создаёт недостающую WebP-пару.</p>
    <p class="muted">Перед запуском сделайте резервную копию базы и сайта. После успешной обработки этот файл рекомендуется удалить с сервера.</p>
    <form method="post" onsubmit="return confirm('Заполнить SEO и локализовать изображения для всех товаров?');">
        <?= csrf_field() ?>
        <button class="btn" type="submit">Заполнить SEO для всех товаров</button>
    </form>
</div>

<div class="box" style="overflow:auto">
<table style="width:100%;border-collapse:collapse">
    <thead><tr><th>ID</th><th>Товар</th><th>Alt</th><th>WebP</th><th>Размер</th><th>Title</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= e($r['name']) ?></td>
            <td><?= trim((string)$r['image_alt']) !== '' ? '✓' : '—' ?></td>
            <td><?= trim((string)$r['image_webp']) !== '' ? '✓' : '—' ?></td>
            <td><?= ((int)$r['image_width'] > 0 && (int)$r['image_height'] > 0) ? ((int)$r['image_width'] . '×' . (int)$r['image_height']) : '—' ?></td>
            <td><?= trim((string)$r['image_title']) !== '' ? '✓' : '—' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php require __DIR__ . '/admin/_footer.php';
