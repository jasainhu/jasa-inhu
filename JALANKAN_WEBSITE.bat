@echo off
title Server JASA INHU - Aktif
color 0A
echo ========================================================
echo         MENJALANKAN SERVER APLIKASI JASA INHU
echo ========================================================
echo.
echo Website dapat diakses di:
echo    http://127.0.0.1:8000
echo    atau http://localhost:8000
echo.
echo [INFO] Jangan tutup jendela command prompt ini selama memakai website.
echo [INFO] Membuka browser otomatis dalam 2 detik...
echo.

start http://127.0.0.1:8000
php -S 127.0.0.1:8000
pause
