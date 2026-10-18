<?php
/**
 * Helper Pencadangan Database (SQL Dump) JASA INHU
 * Menghasilkan file cadangan SQL lengkap (Struktur & Isi Data)
 */

require_once __DIR__ . '/../config/database.php';

function generate_database_sql_dump(): string {
    $db = get_db();
    $db_name = getenv('DB_NAME') ?: 'jasa_inhu';
    
    $output = "-- ========================================================\n";
    $output .= "-- JASA INHU - CADANGAN DATABASE RESMI (SQL DUMP)\n";
    $output .= "-- Tanggal Ekspor : " . date('Y-m-d H:i:s') . " WIB\n";
    $output .= "-- Database Target: " . $db_name . "\n";
    $output .= "-- Sistem Cloud   : TiDB Cloud Serverless / MySQL Compatible\n";
    $output .= "-- ========================================================\n\n";
    $output .= "SET FOREIGN_KEY_CHECKS = 0;\n";
    $output .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
    $output .= "SET time_zone = '+07:00';\n\n";

    // Ambil daftar semua tabel
    $tables = [];
    $stmtTables = $db->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
    while ($row = $stmtTables->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    foreach ($tables as $table) {
        $output .= "-- --------------------------------------------------------\n";
        $output .= "-- Struktur Tabel: `$table`\n";
        $output .= "-- --------------------------------------------------------\n";
        $output .= "DROP TABLE IF EXISTS `$table`;\n";

        // Create table schema
        $stmtCreate = $db->query("SHOW CREATE TABLE `$table`");
        $createRow = $stmtCreate->fetch(PDO::FETCH_NUM);
        $output .= $createRow[1] . ";\n\n";

        // Export data
        $stmtData = $db->query("SELECT * FROM `$table`");
        $rowCount = $stmtData->rowCount();
        
        $rows = $stmtData->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            $output .= "-- Data Tabel: `$table` (" . count($rows) . " baris)\n";
            $columns = array_keys($rows[0]);
            $colList = implode('`, `', $columns);

            $chunks = array_chunk($rows, 100);
            foreach ($chunks as $chunk) {
                $valStrings = [];
                foreach ($chunk as $r) {
                    $rowVals = [];
                    foreach ($r as $val) {
                        if ($val === null) {
                            $rowVals[] = 'NULL';
                        } elseif (is_numeric($val) && !str_starts_with((string)$val, '0')) {
                            $rowVals[] = $val;
                        } else {
                            $rowVals[] = $db->quote((string)$val);
                        }
                    }
                    $valStrings[] = "(" . implode(', ', $rowVals) . ")";
                }
                $output .= "INSERT INTO `$table` (`$colList`) VALUES\n" . implode(",\n", $valStrings) . ";\n";
            }
            $output .= "\n";
        }
    }

    $output .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    $output .= "-- Selesai pada: " . date('Y-m-d H:i:s') . " WIB\n";

    return $output;
}

function download_database_backup(): void {
    $sql = generate_database_sql_dump();
    $filename = 'backup_jasa_inhu_' . date('Y-m-d_His') . '.sql';

    header('Content-Description: File Transfer');
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . strlen($sql));

    echo $sql;
    exit;
}
