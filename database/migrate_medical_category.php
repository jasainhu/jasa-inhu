<?php
require_once __DIR__ . '/../config/database.php';
$db = get_db();
$stmt = $db->prepare("UPDATE service_categories SET icon = 'fa-user-doctor' WHERE slug = 'medical' OR name LIKE '%Medical%'");
$stmt->execute();
echo "Medical icon successfully updated to fa-user-doctor.\n";
