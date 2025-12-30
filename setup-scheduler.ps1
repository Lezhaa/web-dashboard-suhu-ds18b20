# Run as Administrator
if (-NOT ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]"Administrator")) {
    Write-Host "Run as Administrator!" -ForegroundColor Red
    pause; exit
}

# Config
$projectPath = "C:\xampp\htdocs\web-dashboard-suhu-ds18b20"
$phpPath = "C:\xampp\php\php.exe"

# 1. Buat batch file
$batch = @"
@echo off
cd /d "$projectPath"
echo [%time%] Running scheduler >> scheduler.log
"$phpPath" artisan suhu:fetch-auto >> scheduler.log 2>&1
echo [%time%] Done >> scheduler.log
"@

$batch | Out-File "$projectPath\run-scheduler.bat" -Encoding ASCII
Write-Host "✅ Batch file created"

# 2. Buat 3 task scheduler
$schedules = @(
    @{Name="Suhu-Pagi"; Hour=8},
    @{Name="Suhu-Siang"; Hour=12},
    @{Name="Suhu-Malam"; Hour=20}
)

foreach ($s in $schedules) {
    $taskName = $s.Name
    $time = "$($s.Hour.ToString('00')):00"
    
    # Hapus lama
    Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue | Unregister-ScheduledTask -Confirm:$false
    
    # Buat baru
    $trigger = New-ScheduledTaskTrigger -Daily -At $time
    $action = New-ScheduledTaskAction -Execute "$projectPath\run-scheduler.bat" -WorkingDirectory $projectPath
    $settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -StartWhenAvailable
    
    Register-ScheduledTask -TaskName $taskName -Trigger $trigger -Action $action -Settings $settings -Description "Ambil data suhu $time" -RunLevel Highest -Force
    
    Write-Host "✅ Task '$taskName' created ($time)"
}

Write-Host "`n🎉 SELESAI! Cek Task Scheduler (taskschd.msc)" -ForegroundColor Green
Write-Host "Test: cd '$projectPath' && php artisan suhu:fetch-auto" -ForegroundColor Yellow
pause