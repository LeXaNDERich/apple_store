<?php
declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_PORT = '3306';
const DB_NAME = 'r98595b7_apple';
const DB_USER = 'r98595b7_apple';
const DB_PASS = 'Qwertyuiop!';
const DB_CHARSET = 'utf8mb4';

const STORE_NAME = 'iTech';
const STORE_TAGLINE = 'Техника Apple';

$docRoot = rtrim(str_replace('\\', '/', (string) ($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
$appRoot = str_replace('\\', '/', __DIR__);
$base = '';
if ($docRoot !== '' && strpos($appRoot, $docRoot) === 0) {
    $base = substr($appRoot, strlen($docRoot));
}
define('BASE_URL', $base);
define('APP_ROOT', __DIR__);
