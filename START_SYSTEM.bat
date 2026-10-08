@echo off
setlocal enabledelayedexpansion
title ES8 Micro Merchandising - Lending Management System
color 0B

echo ============================================================
echo         ES8 MICRO MERCHANDISING - LENDING SYSTEM            
echo ============================================================
echo.

cd /d "%~dp0"

:: 1. Hanapin ang PHP executable
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

echo [ERROR] Hindi makita ang PHP sa computer na ito!
echo Pakisiguradong may XAMPP sa C:\xampp\php o naka-install ang PHP.
echo.
pause
exit /b 1

:php_found
echo [1/4] PHP detected: "%PHP_BIN%"

:: 2. Siguraduhing may .env file
if not exist ".env" (
    echo [2/4] Ginagawa ang .env configuration file...
    copy ".env.example" ".env" >nul
    "%PHP_BIN%" artisan key:generate --force >nul 2>&1
) else (
    echo [2/4] .env configuration file is ready.
)

:: 3. Siguraduhing may database at tables
if not exist "database\database.sqlite" (
    echo [3/4] Ginagawa ang bagong database.sqlite...
    type nul > "database\database.sqlite"
    echo [3/4] Ginagawa ang mga database tables at default accounts...
    "%PHP_BIN%" artisan migrate --force --seed
) else (
    echo [3/4] Updating database tables...
    "%PHP_BIN%" artisan migrate --force >nul 2>&1
)

:: 4. Linisin ang cache para sariwa ang UI
"%PHP_BIN%" artisan optimize:clear >nul 2>&1

:: 5. Buksan ang browser papunta sa Admin login
echo [4/4] Binubuksan ang browser...
start http://127.0.0.1:8000/admin

echo.
echo ============================================================
echo   SYSTEM IS NOW RUNNING!
echo ============================================================
echo   URL:        http://127.0.0.1:8000/admin
echo.
echo   Accounts:
echo   - Administrator: admin@lender.test   (password: password)
echo   - Area 1 Staff:  staff1@lender.test  (password: password)
echo   - Area 2 Staff:  staff2@lender.test  (password: password)
echo   - Area 3 Staff:  staff3@lender.test  (password: password)
echo ============================================================
echo   PAALALA: Huwag isasara ang black window na ito habang
echo   ginagamit ang Lending System sa browser.
echo ============================================================
echo.

"%PHP_BIN%" artisan serve --port=8000
pause
