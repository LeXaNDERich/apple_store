<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
$_SESSION = [];
session_destroy();
header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/index.php');
exit;
