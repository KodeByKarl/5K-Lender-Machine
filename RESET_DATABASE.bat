@echo off
setlocal enabledelayedexpansion
title ES8 Micro Merchandising - Reset Database
color 0C

echo ============================================================
echo      RESET DATABASE - ES8 MICRO MERCHANDISING               
echo ============================================================
echo.
echo BABALA: Buburahin nito ang lahat ng borrowers, loans,
echo at payments para magsimula sa malinis na database!
echo.
set /p CONFIRM="Sigurado ka bang buburahin ang lahat ng data? (Y/N): "
if /i not "%CONFIRM%"=="Y" (
    echo Kinansela ang pag-reset. Walang nabago.
    pause
    exit /b 0
)

cd /d "%~dp0"

:: Hanapin ang PHP
set "PHP_BIN="
where php.exe >nul 2>&1
if %ERRORLEVEL% equ 0 (
    set "PHP_BIN=php"
    goto :php_found
)
if exist "C:\xampp\php\php.exe" (
    set "PHP_BIN=C:\xampp\php\php.exe"
    goto :php_found
)
if exist "C:\laragon\bin\php" (
    for /f "delims=" %%I in ('dir /b /s "C:\laragon\bin\php\php.exe" 2^>nul') do (
        set "PHP_BIN=%%I"
        goto :php_found
    )
)
if exist "C:\php\php.exe" (
    set "PHP_BIN=C:\php\php.exe"
    goto :php_found
)

echo [ERROR] Hindi makita ang PHP.
pause
exit /b 1

:php_found
echo.
echo [INFO] Binubura at muling inihahanda ang malinis na database...
"%PHP_BIN%" artisan migrate:fresh --force --seed
"%PHP_BIN%" artisan optimize:clear >nul 2>&1

echo.
echo ============================================================
echo   TAPOS NA! MALINIS NA ANG DATABASE.
echo   Maaari mo nang gamitin ang START_SYSTEM.bat.
echo ============================================================
echo.
pause
