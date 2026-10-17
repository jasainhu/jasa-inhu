<?php
/**
 * Kwitansi Elektronik Resmi (Alias Redirect to invoice.php)
 * JASA INHU - Memastikan tautan kwitansi selalu terbuka dengan lancar
 */

require_once __DIR__ . '/config/app.php';

$id = (int)($_GET['id'] ?? $_GET['request_id'] ?? 0);

if ($id > 0) {
    header("Location: " . BASE_URL . "/invoice.php?id=" . $id);
    exit;
}

header("Location: " . BASE_URL . "/");
exit;
