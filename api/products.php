<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
ensure_installed();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');
try {
    $limit=max(1,min(100,(int)($_GET['limit']??20))); $offset=max(0,(int)($_GET['offset']??0)); $items=get_catalog_products($limit,$offset);
    foreach($items as &$item){$regular=(float)$item['price'];$promo=isset($item['promo_price'])?(float)$item['promo_price']:0.0;$actual=($promo>0&&$promo<$regular)?$promo:$regular;$item['image_url']=product_image_webp_src($item)?:product_image_src($item);$item['fallback_image_url']=product_image_src($item);$item['alt_text']=product_image_alt($item);$item['image_title_text']=product_image_title($item);$item['width']=(int)($item['image_width']?:400);$item['height']=(int)($item['image_height']?:300);$item['product_url']=product_url($item);$item['actual_price']=$actual;$item['promo_percent']=($actual<$regular&&$regular>0)?(int)round((1-$actual/$regular)*100):0;} unset($item);
    echo json_encode(['ok'=>true,'count'=>count($items),'items'=>$items],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'error'=>'Не удалось загрузить товары'],JSON_UNESCAPED_UNICODE);}
