<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}

$src = __DIR__;
$dst = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'apple-store-opt';

function copy_tree(string $src, string $dst, array $skip): void
{
    if (!is_dir($dst) && !mkdir($dst, 0777, true) && !is_dir($dst)) {
        throw new RuntimeException('Cannot create ' . $dst);
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $file) {
        $rel = substr($file->getPathname(), strlen($src) + 1);
        if ($rel === false) {
            continue;
        }
        $relNorm = str_replace('\\', '/', $rel);
        if (in_array(basename($relNorm), $skip, true) || strpos($relNorm, '.git') === 0) {
            continue;
        }
        $target = $dst . DIRECTORY_SEPARATOR . $rel;
        if ($file->isDir()) {
            if (!is_dir($target)) {
                mkdir($target, 0777, true);
            }
            continue;
        }
        $dir = dirname($target);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        copy($file->getPathname(), $target);
    }
}

function minify_css(string $css): string
{
    $css = preg_replace('!/\*.*?\*/!s', '', $css) ?? $css;
    $css = preg_replace('/\s+/', ' ', $css) ?? $css;
    $css = str_replace([' {', '{ ', ' }', '} ', ': ', '; ', ', '], ['{', '{', '}', '}', ':', ';', ','], $css);
    return trim($css);
}

function minify_js(string $js): string
{
    $js = preg_replace('!/\*.*?\*/!s', '', $js) ?? $js;
    $js = preg_replace('/(?:^|\n)\s*\/\/.*?(?=\n|$)/', '', $js) ?? $js;
    $js = preg_replace('/\s+/', ' ', $js) ?? $js;
    return trim($js);
}

function compact_php(string $code): string
{
    $tokens = token_get_all($code);
    $out = '';
    $prev = '';
    foreach ($tokens as $token) {
        if (is_array($token)) {
            [$id, $text] = $token;
            if ($id === T_COMMENT || $id === T_DOC_COMMENT) {
                continue;
            }
            if ($id === T_WHITESPACE) {
                $need = preg_match('/[a-zA-Z0-9_]$/', $prev) && preg_match('/^[a-zA-Z0-9_]/', ltrim($text) . 'x');
                $nextChar = '';
                continue;
            }
            if ($id === T_WHITESPACE) {
                continue;
            }
            if ($id === T_WHITESPACE) {
                $text = ' ';
            }
            $out .= $text;
            $prev = $text;
        } else {
            $out .= $token;
            $prev = $token;
        }
    }
    return $out;
}

function compact_php_safe(string $code): string
{
    $tokens = token_get_all($code);
    $out = '';
    $prevWasWord = false;
    foreach ($tokens as $token) {
        if (is_array($token)) {
            [$id, $text] = $token;
            if ($id === T_COMMENT || $id === T_DOC_COMMENT) {
                continue;
            }
            if ($id === T_WHITESPACE) {
                if ($prevWasWord) {
                    $out .= ' ';
                    $prevWasWord = false;
                }
                continue;
            }
            if ($id === T_INLINE_HTML || $id === T_OPEN_TAG || $id === T_OPEN_TAG_WITH_ECHO || $id === T_CLOSE_TAG) {
                $out .= $text;
                $prevWasWord = false;
                continue;
            }
            if ($prevWasWord && preg_match('/^[a-zA-Z_\\\\$]/', $text)) {
                $out .= ' ';
            }
            $out .= $text;
            $prevWasWord = (bool) preg_match('/[a-zA-Z0-9_]$/', $text);
        } else {
            $out .= $token;
            $prevWasWord = false;
        }
    }
    return $out;
}

copy_tree($src, $dst, ['mkopt.php', '_dbtest.php', '_runinstall.php', '_smoketest.php', '_fetch_images.php', '_migrate.php', '.git']);

$config = file_get_contents($dst . '/config.php');
$config = str_replace("const DB_NAME = 'apple_store';", "const DB_NAME = 'apple_store_opt';", $config);
file_put_contents($dst . '/config.php', $config);

$sql = file_get_contents($dst . '/database.sql');
$sql = str_replace('apple_store', 'apple_store_opt', $sql);
file_put_contents($dst . '/database.sql', $sql);

$css = file_get_contents($dst . '/assets/css/style.css');
file_put_contents($dst . '/assets/css/style.css', minify_css($css));

$js = file_get_contents($dst . '/assets/js/app.js');
file_put_contents($dst . '/assets/js/app.js', minify_js($js));

$footer = file_get_contents($dst . '/includes/footer.php');
$footer = str_replace(
    '<script src="<?= e(url(\'assets/js/app.js\')) ?>"></script>',
    '<script src="<?= e(url(\'assets/js/app.js\')) ?>" defer></script>',
    $footer
);
file_put_contents($dst . '/includes/footer.php', $footer);

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dst, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $code = file_get_contents($file->getPathname());
    if (strpos($code, '<<<') !== false) {
        continue;
    }
    file_put_contents($file->getPathname(), compact_php_safe($code));
}

$ht = <<<HT
DirectoryIndex index.php
AddDefaultCharset UTF-8
<IfModule mod_deflate.c>
AddOutputFilterByType DEFLATE text/html text/css text/plain application/javascript application/json image/svg+xml
</IfModule>
<IfModule mod_expires.c>
ExpiresActive On
ExpiresByType text/css "access plus 30 days"
ExpiresByType application/javascript "access plus 30 days"
ExpiresByType image/svg+xml "access plus 30 days"
</IfModule>
<IfModule mod_headers.c>
<FilesMatch "\\.(css|js|svg)$">
Header set Cache-Control "public, max-age=2592000"
</FilesMatch>
</IfModule>
HT;
file_put_contents($dst . '/.htaccess', $ht);

$header = file_get_contents($dst . '/includes/header.php');
$header = str_replace(
    '<script src="<?= e(url(\'assets/js/app.js\')) ?>"></script>',
    '',
    $header
);
file_put_contents($dst . '/includes/header.php', $header);

$footer = file_get_contents($dst . '/includes/footer.php');
$footer = str_replace(
    '<script src="<?= e(url(\'assets/js/app.js\')) ?>"></script>',
    '<script src="<?= e(url(\'assets/js/app.js\')) ?>" defer></script>',
    $footer
);
file_put_contents($dst . '/includes/footer.php', $footer);

echo "OK " . $dst . PHP_EOL;
