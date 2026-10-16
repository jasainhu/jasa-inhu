<?php
require_once 'd:/JASA-INHU/config/database.php';
$db = get_db();

echo "Updating category ID 16...\n";
$stmt = $db->prepare("
    UPDATE service_categories 
    SET name = 'Pembersih Rumah & ART',
        slug = 'pembersih-rumah-art',
        icon = 'fa-broom',
        description = 'Bersih rumah harian, ART panggilan, sapu pel, cuci piring, setrika, kuras toren & bersih ruko',
        sort_order = 5,
        updated_at = NOW()
    WHERE id = 16
");
$stmt->execute();
echo "Updated rows: " . $stmt->rowCount() . "\n";

$check = $db->query("SELECT * FROM service_categories WHERE id = 16")->fetch(PDO::FETCH_ASSOC);
print_r($check);
