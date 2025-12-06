@echo off
cd /d "C:\xampp\htdocs\suhu-dashboard"

:: Start Server with PHP 8.4
start "Server" cmd /k "C:\php8\php.exe artisan serve --host=192.168.0.16 --port=8000"

:: Start Scheduler (after 3 seconds) with PHP 8.4
timeout /t 3 >nul
start "Scheduler" cmd /k "cd /d C:\xampp\htdocs\suhu-dashboard && C:\php8\php.exe artisan schedule:run"

echo Dashboard started at http://192.168.0.16:8000
echo Scheduler running...
echo.
pause