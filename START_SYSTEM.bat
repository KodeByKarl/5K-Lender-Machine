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

:: 1.1 Kailangan ng PHP 8.2 pataas at ang intl extension
"%PHP_BIN%" -r "exit(version_compare(PHP_VERSION,'8.2.0','>=')?0:1);"
if !ERRORLEVEL! neq 0 (
    echo [ERROR] Luma ang PHP version nito. Kailangan ang PHP 8.2 o mas bago.
    echo Mag-install ng XAMPP na may PHP 8.2+ at patakbuhin ulit ito.
    "%PHP_BIN%" -v
    pause
    exit /b 1
)
for %%E in (intl zip) do (
    "%PHP_BIN%" -r "exit(extension_loaded('%%E')?0:1);"
    if !ERRORLEVEL! neq 0 (
        echo [!] Ina-enable ang PHP %%E extension sa php.ini...
        set "PHP_INI="
        "%PHP_BIN%" -r "file_put_contents('php_ini_path.tmp', (string)php_ini_loaded_file());"
        set /p PHP_INI=<php_ini_path.tmp
        del php_ini_path.tmp >nul 2>&1
        if defined PHP_INI if exist "!PHP_INI!" (
            if not exist "!PHP_INI!.bak" copy "!PHP_INI!" "!PHP_INI!.bak" >nul
            powershell -NoProfile -Command "(Get-Content -LiteralPath $env:PHP_INI) -replace '^\s*;\s*extension\s*=\s*%%E\s*$','extension=%%E' | Set-Content -LiteralPath $env:PHP_INI"
        )
    )
    "%PHP_BIN%" -r "exit(extension_loaded('%%E')?0:1);"
    if !ERRORLEVEL! neq 0 (
        echo [ERROR] Hindi ma-enable ang PHP %%E extension.
        echo Buksan ang php.ini ^(C:\xampp\php\php.ini^), hanapin ang ";extension=%%E",
        echo tanggalin ang ";" sa unahan, i-save, at patakbuhin ulit ito.
        pause
        exit /b 1
    )
)

:: 1.5 Siguraduhing naka-install ang vendor (Composer dependencies)
if not exist "vendor\autoload.php" (
    echo [!] Wala pa ang vendor folder. Kailangan ng internet para sa unang setup...
    set "COMPOSER_CMD="
    where composer >nul 2>&1
    if !ERRORLEVEL! equ 0 (
        set "COMPOSER_CMD=composer"
    ) else (
        if not exist "composer.phar" (
            echo [!] Dina-download ang Composer...
            "%PHP_BIN%" -r "copy('https://getcomposer.org/download/latest-stable/composer.phar', 'composer.phar');"
        )
        if exist "composer.phar" set "COMPOSER_CMD=%PHP_BIN% composer.phar"
    )
    if not defined COMPOSER_CMD (
        echo [ERROR] Hindi ma-download ang Composer. Tingnan ang internet connection.
        pause
        exit /b 1
    )
    echo [!] Ini-install ang dependencies, maghintay po...
    call !COMPOSER_CMD! install --no-dev --optimize-autoloader --no-interaction
    if not exist "vendor\autoload.php" (
        echo [ERROR] Hindi na-install ang dependencies. Ipakita ang error sa itaas sa developer.
        pause
        exit /b 1
    )
)

:: 2. Siguraduhing may .env file
if not exist ".env" (
    echo [2/4] Ginagawa ang .env configuration file...
    copy ".env.example" ".env" >nul
) else (
    echo [2/4] .env configuration file is ready.
)

:: Siguraduhing may APP_KEY (kung wala o blangko, gagawa ng bago)
findstr /r /c:"^APP_KEY=base64:" ".env" >nul 2>&1
if !ERRORLEVEL! neq 0 (
    echo [2/4] Ginagawa ang APP_KEY...
    "%PHP_BIN%" artisan key:generate --force
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
    rem Ligtas ulit-ulitin: hindi binubura ang data at hindi nire-reset ang password
    "%PHP_BIN%" artisan db:seed --force >nul 2>&1
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
