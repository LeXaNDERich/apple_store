<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

$city = trim((string)($_GET['city'] ?? ''));
$query = trim((string)($_GET['q'] ?? ''));

if ($city === '' || mb_strlen($query, 'UTF-8') < 2) {
    echo json_encode(['ok' => true, 'items' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$search = trim($city . ', ' . $query);
$url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
    'format' => 'jsonv2',
    'addressdetails' => 1,
    'countrycodes' => 'ru',
    'limit' => 8,
    'accept-language' => 'ru',
    'q' => $search,
], '', '&', PHP_QUERY_RFC3986);

$body = false;

if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 7,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'User-Agent: AppleStore-Lab4/1.0 (local educational project)'
        ],
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 300) {
        $body = false;
    }
} else {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 7,
            'header' => "Accept: application/json\r\nUser-Agent: AppleStore-Lab4/1.0 (local educational project)\r\n",
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
}

if ($body === false) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'items' => [], 'message' => 'Сервис адресов временно недоступен'], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode($body, true);
if (!is_array($data)) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'items' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$items = [];
$seen = [];
foreach ($data as $row) {
    $a = isset($row['address']) && is_array($row['address']) ? $row['address'] : [];
    $resultCity = (string)($a['city'] ?? $a['town'] ?? $a['village'] ?? $a['municipality'] ?? $city);
    $road = (string)($a['road'] ?? $a['pedestrian'] ?? $a['residential'] ?? $a['footway'] ?? $a['neighbourhood'] ?? $row['name'] ?? '');
    $house = (string)($a['house_number'] ?? '');
    $street = trim($road . ($house !== '' ? ', ' . $house : ''));
    if ($street === '') {
        $street = (string)($row['display_name'] ?? '');
    }
    $label = $street;
    $detailParts = [];
    if ($resultCity !== '') $detailParts[] = $resultCity;
    if (!empty($a['state'])) $detailParts[] = (string)$a['state'];
    $detail = implode(', ', array_values(array_unique($detailParts)));
    $full = trim($resultCity . ($street !== '' ? ', ' . $street : ''));
    if ($label === '' || isset($seen[$full])) continue;
    $seen[$full] = true;
    $items[] = [
        'label' => $label,
        'detail' => $detail,
        'city' => $resultCity,
        'street' => $street,
        'full' => $full,
    ];
}

echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
