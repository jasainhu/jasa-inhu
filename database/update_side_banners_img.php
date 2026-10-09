<?php
require_once __DIR__ . '/../config/database.php';
$db = get_db();
$db->query("UPDATE banners SET image_url='uploads/banners/banner_side_emergency.jpg', show_overlay=0 WHERE position='side_top'");
$db->query("UPDATE banners SET image_url='uploads/banners/banner_side_partner.jpg', show_overlay=0 WHERE position='side_bottom'");
echo "Banners updated successfully!\n";
