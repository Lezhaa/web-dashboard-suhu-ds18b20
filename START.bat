@echo off
title Laravel Dashboard Auto Starter

set PHP_PATH=C:\xampp\php\php.exe
set PROJECT_PATH=C:\xampp\htdocs\web-dashboard-suhu-ds18b20
set LARAVEL_HOST=192.168.0.74
set LARAVEL_PORT=8000

cd /d "%PROJECT_PATH%"

REM 1. Start Laravel Server
start "Laravel Server" cmd /k "%PHP_PATH% artisan serve --host=%LARAVEL_HOST% --port=%LARAVEL_PORT%"

REM 2. Start Scheduler Worker (INI YANG PENTING)
start "Laravel Scheduler" cmd /k "%PHP_PATH% artisan schedule:work"

REM 3. Log
echo [%time%] Laravel Dashboard started >> "%PROJECT_PATH%\startup.log"

REM Keep open
pause