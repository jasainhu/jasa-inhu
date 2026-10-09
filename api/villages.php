<?php
/**
 * API: Daftar Desa / Kelurahan berdasarkan Kecamatan
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$district_id = (int)($_GET['district_id'] ?? 0);

if ($district_id <= 0) {
    echo json_encode([
        'status' => 'success',
        'data'   => []
    ]);
    exit;
}

try {
    $db = get_db();
    $stmt = $db->prepare("SELECT id, name, postal_code FROM villages WHERE district_id = ? ORDER BY name ASC");
    $stmt->execute([$district_id]);
    $villages = $stmt->fetchAll();

    echo json_encode([
        'status' => 'success',
        'data'   => $villages
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Gagal memuat desa/kelurahan: ' . $e->getMessage()
    ]);
}
