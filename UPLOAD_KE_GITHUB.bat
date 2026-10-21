@echo off
title Upload Kode JASA INHU ke GitHub
color 0B
echo ========================================================
echo        MENGUNGGAH KODE JASA INHU KE GITHUB
echo ========================================================
echo.
echo Sedang mengunggah file proyek ke https://github.com/jasainhu/jasa-inhu...
echo.
echo [CATATAN] Jika muncul jendela login GitHub di browser,
echo           silakan klik "Sign in with your browser" / "Authorize".
echo.

git branch -M main
git push -u origin main

echo.
if %ERRORLEVEL% EQU 0 (
    color 0A
    echo ========================================================
    echo   BERHASIL! Seluruh kode sudah terunggah ke GitHub!
    echo ========================================================
) else (
    color 0C
    echo ========================================================
    echo   Gagal mengunggah. Pastikan login GitHub diizinkan.
    echo ========================================================
)
echo.
pause
