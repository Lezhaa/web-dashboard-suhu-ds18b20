@echo off
cd /d "C:\xampp\htdocs\web-dashboard-suhu-ds18b20"
echo [%time%] Running scheduler >> scheduler.log
"C:\xampp\php\php.exe" artisan suhu:fetch-auto >> scheduler.log 2>&1
echo [%time%] Done >> scheduler.log
