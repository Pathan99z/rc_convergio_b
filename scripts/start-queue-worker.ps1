# Laravel Queue Worker - Auto Restart Script
# Run this script to keep the queue worker running automatically

Write-Host "Starting Laravel Queue Worker..." -ForegroundColor Green
Write-Host "Press Ctrl+C to stop" -ForegroundColor Yellow

while ($true) {
    try {
        Write-Host "Starting queue worker..." -ForegroundColor Cyan
        php artisan queue:work --queue=default --tries=3 --timeout=120 --memory=512
    }
    catch {
        Write-Host "Queue worker stopped. Restarting in 5 seconds..." -ForegroundColor Red
        Start-Sleep -Seconds 5
    }
}
