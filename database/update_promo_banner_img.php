<?php
require_once __DIR__ . '/../config/app.php';

$db = get_db();
$stmt = $db->prepare("
    UPDATE banners 
    SET image_url = 'uploads/banners/banner_promo_ads_v2.jpg', 
        show_overlay = 0,
        sort_order = 1,
        updated_at = NOW()
    WHERE id = 60069
");
$stmt->execute();
echo "UPDATED: Banner 60069 image_url set to uploads/banners/banner_promo_ads_v2.jpg\n";

