@echo off
title JASA INHU - Otomatisasi Broadcast Pagi Jam 08.00 WIB
color 0A
echo ====================================================
echo    JASA INHU - BROADCAST SAPAAN MITRA PAGI OTOMATIS
echo ====================================================
echo.
echo Menjalankan penjadwalan otomatis jam 08:00 WIB...
echo Pastikan laptop tetap hidup dan terkoneksi internet.
echo.
php "d:\JASA-INHU\scripts\broadcast_mitra_morning.php" --at=08:00
pause
