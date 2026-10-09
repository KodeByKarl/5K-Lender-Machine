@echo off
setlocal enabledelayedexpansion
title Build Desktop Installer
cd /d "%~dp0"

:: Hindi dapat naka-set ito, kundi hindi bubukas ang Electron window
set "ELECTRON_RUN_AS_NODE="

if not exist ".env.desktop" (
    echo [ERROR] Wala ang .env.desktop. Kopyahin ang .env.desktop.example, ilagay ang SETUP_DEFAULT_PASSWORD, at subukan ulit.
    pause
    exit /b 1
)

findstr /r /c:"^SETUP_DEFAULT_PASSWORD=..*" ".env.desktop" >nul 2>&1
if !ERRORLEVEL! neq 0 (
    echo [ERROR] Walang laman ang SETUP_DEFAULT_PASSWORD sa .env.desktop.
    pause
    exit /b 1
)

findstr /r /c:"^APP_KEY=base64:" ".env.desktop" >nul 2>&1
if !ERRORLEVEL! neq 0 (
    echo Gumagawa ng APP_KEY para sa .env.desktop...
    for /f "delims=" %%K in ('php artisan key:generate --show') do set "NEWKEY=%%K"
    powershell -NoProfile -Command "(Get-Content .env.desktop) -replace '^APP_KEY=.*$', ('APP_KEY=' + $env:NEWKEY) | Set-Content .env.desktop"
)

:: Kung bawal gumawa ng symlink (walang Developer Mode/admin), hindi mate-extract ng electron-builder
:: ang winCodeSign. Laktawan ang pag-edit ng exe (icon/metadata); gumagana pa rin ang installer.
set "BUILDER_CFG=vendor\nativephp\desktop\resources\electron\electron-builder.mjs"
powershell -NoProfile -Command "$t=Join-Path $env:TEMP 'symtest.lnk'; Remove-Item $t -Force -ErrorAction SilentlyContinue; try { New-Item -ItemType SymbolicLink -Path $t -Target $env:windir -ErrorAction Stop | Out-Null; Remove-Item $t -Force; exit 0 } catch { exit 1 }"
if !ERRORLEVEL! neq 0 (
    echo [!] Walang symlink permission. Ilalaktaw ang winCodeSign.
    powershell -NoProfile -Command "$f='%BUILDER_CFG%'; $c=Get-Content -Raw $f; if($c -notmatch 'signAndEditExecutable'){ $c=$c -replace 'executableName: fileName,','executableName: fileName, signAndEditExecutable: false,'; Set-Content -NoNewline $f $c }"
)

:: Gamitin ang .env.desktop para sa build, ibalik ang sariling .env pagkatapos
if exist ".env" copy /y ".env" ".env.dev-backup" >nul
copy /y ".env.desktop" ".env" >nul
php artisan config:clear >nul 2>&1

call php artisan native:build win
set "BUILD_RESULT=!ERRORLEVEL!"

if exist ".env.dev-backup" (
    copy /y ".env.dev-backup" ".env" >nul
    del ".env.dev-backup" >nul 2>&1
) else (
    del ".env" >nul 2>&1
)
php artisan config:clear >nul 2>&1

:: Hindi laging nagbabalik ng error code ang build, kaya tingnan kung may installer talaga
dir /b "nativephp\electron\dist\*Setup*.exe" >nul 2>&1
if !ERRORLEVEL! neq 0 set "BUILD_RESULT=1"

if !BUILD_RESULT! neq 0 (
    echo [ERROR] Pumalya ang build. Walang nabuong installer.
    pause
    exit /b 1
)

echo.
echo Tapos na. Ang installer ay nasa: nativephp\electron\dist\
pause
