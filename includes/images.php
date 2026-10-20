<?php
declare(strict_types=1);

function product_image_file($slug, $visual = 'phone')
{
    $map = [
        'iphone-16-pro' => 'iphone-pro.jpg',
        'iphone-16-pro-max' => 'iphone-max.jpg',
        'iphone-16' => 'iphone-16.jpg',
        'iphone-16-plus' => 'iphone-plus.jpg',
        'iphone-15' => 'iphone-15.jpg',
        'macbook-air-13-m3' => 'macbook-air.jpg',
        'macbook-air-15-m3' => 'macbook-air15.jpg',
        'macbook-pro-14-m4' => 'macbook-pro.jpg',
        'macbook-pro-16-m4-pro' => 'macbook-pro16.jpg',
        'imac-24-m4' => 'imac.jpg',
        'mac-mini-m4' => 'mac-mini.jpg',
        'ipad-pro-11-m4' => 'ipad-pro.jpg',
        'ipad-pro-13-m4' => 'ipad-pro13.jpg',
        'ipad-air-11-m2' => 'ipad-air.jpg',
        'ipad-10-9' => 'ipad.jpg',
        'ipad-mini-8-3' => 'ipad-mini.jpg',
        'apple-watch-ultra-2' => 'watch-ultra.jpg',
        'apple-watch-series-10' => 'watch.jpg',
        'apple-watch-se' => 'watch-se.jpg',
        'airpods-pro-2' => 'airpods-pro.jpg',
        'airpods-4' => 'airpods.jpg',
        'airpods-max' => 'airpods-max.jpg',
        'magsafe-charger' => 'magsafe.jpg',
        'magic-keyboard-ipad-pro' => 'keyboard.jpg',
        'apple-pencil-pro' => 'pencil.jpg',
        'studio-display' => 'display.jpg',
    ];
    if (isset($map[$slug])) {
        return 'assets/img/products/' . $map[$slug];
    }
    $byType = [
        'phone' => 'iphone-pro.jpg',
        'macbook' => 'macbook-air.jpg',
        'imac' => 'imac.jpg',
        'mini' => 'mac-mini.jpg',
        'ipad' => 'ipad-pro.jpg',
        'watch' => 'watch.jpg',
        'airpods' => 'airpods.jpg',
        'max' => 'airpods-max.jpg',
        'acc' => 'magsafe.jpg',
    ];
    $file = isset($byType[$visual]) ? $byType[$visual] : 'iphone-pro.jpg';
    return 'assets/img/products/' . $file;
}
