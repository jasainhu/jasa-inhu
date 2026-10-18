@echo off
title JASA INHU - Pencadangan Database Mingguan
color 0B
echo ========================================================
echo       JASA INHU - PENCADANGAN DATABASE (BACKUP)
echo ========================================================
echo.
echo Sedang mengambil seluruh data dari TiDB Cloud Serverless...
echo.

php scripts\backup_database.php

echo.
if %ERRORLEVEL% EQU 0 (
    color 0A
    echo ========================================================
    echo   BERHASIL! Data JASA INHU telah berhasil dicadangkan!
    echo   File tersimpan di folder: d:\JASA-INHU\backups\
    echo ========================================================
    echo.
    echo Membuka folder arsip cadangan...
    explorer backups
) else (
    color 0C
    echo ========================================================
    echo   Gagal mencadangkan database. Pastikan koneksi internet aktif.
    echo ========================================================
)
echo.
pause
