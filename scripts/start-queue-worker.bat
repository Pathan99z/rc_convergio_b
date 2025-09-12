@echo off
echo Starting Laravel Queue Worker...
echo Press Ctrl+C to stop

:restart
php artisan queue:work --queue=default --tries=3 --timeout=120 --memory=512
echo Queue worker stopped. Restarting in 5 seconds...
timeout /t 5 /nobreak > nul
goto restart
