<?php
/**
 * API: Daftar Kecamatan di Inhu
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

try {
    $db = get_db();
    $stmt = $db->query("SELECT id, name, code FROM districts ORDER BY name ASC");
    $districts = $stmt->fetchAll();

    echo json_encode([
        'status' => 'success',
        'data'   => $districts
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Gagal memuat data kecamatan: ' . $e->getMessage()
    ]);
}
