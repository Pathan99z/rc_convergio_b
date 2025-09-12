@echo off
echo Installing Laravel Queue Worker as Windows Service...

REM Create a service that runs the queue worker
sc create "LaravelQueueWorker" binPath= "C:\xampp\php\php.exe C:\xampp\htdocs\rc_convergio_s\artisan queue:work --queue=default --tries=3 --timeout=120" start= auto

echo Service created. Starting service...
sc start "LaravelQueueWorker"

echo Service started. Check status with: sc query LaravelQueueWorker
pause
