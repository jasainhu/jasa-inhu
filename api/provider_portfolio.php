<?php
/**
 * API: Portofolio Hasil Kerja Mitra (On-Demand Loading)
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$provider_id = (int)($_GET['provider_id'] ?? 0);

if ($provider_id <= 0) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Provider ID tidak valid'
    ]);
    exit;
}

try {
    $db = get_db();
    
    // Ambil info provider
    $stmtProv = $db->prepare("
        SELECT sp.id, sp.business_name, sc.name as category_name
        FROM service_providers sp
        JOIN service_categories sc ON sp.primary_category_id = sc.id
        WHERE sp.id = ?
        LIMIT 1
    ");
    $stmtProv->execute([$provider_id]);
    $provider = $stmtProv->fetch();

    if (!$provider) {
        echo json_encode([
            'status'   => 'success',
            'data'     => [],
            'provider' => null
        ]);
        exit;
    }

    $stmtPort = $db->prepare("
        SELECT id, provider_id, title, description, image_before, image_after, service_date, created_at
        FROM provider_portfolios
        WHERE provider_id = ?
        ORDER BY id DESC
    ");
    $stmtPort->execute([$provider_id]);
    $portfolios = $stmtPort->fetchAll();

    echo json_encode([
        'status'   => 'success',
        'provider' => $provider,
        'data'     => $portfolios
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Gagal memuat portofolio: ' . $e->getMessage()
    ]);
}
