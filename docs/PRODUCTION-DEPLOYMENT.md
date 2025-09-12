# Production Deployment Guide

## 🚀 **AUTOMATIC CAMPAIGN AUTOMATION**

**✅ NO MANUAL COMMANDS NEEDED!**

The campaign automation now works automatically without any manual queue worker commands. Here's how:

### **For QA Team & Development:**
```bash
# Just run the normal Laravel server
php artisan serve
```

**That's it!** The queue worker starts automatically in the background.

### **For Production:**
The system has multiple fallback mechanisms to ensure campaigns always work:

1. **Automatic Queue Worker Startup** - Starts when application boots
2. **Inline Execution Fallback** - If queue worker isn't running, campaigns execute immediately
3. **Sync Mode Support** - Works even with `QUEUE_CONNECTION=sync`

## Queue Worker Management

### Linux/Production Server (Recommended)

#### Option 1: Supervisor (Recommended)
1. Install Supervisor:
```bash
sudo apt-get install supervisor
```

2. Copy the configuration:
```bash
sudo cp supervisor/laravel-worker.conf /etc/supervisor/conf.d/
```

3. Update the paths in the config file:
```bash
sudo nano /etc/supervisor/conf.d/laravel-worker.conf
```

4. Reload and start:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

#### Option 2: Systemd Service
Create `/etc/systemd/system/laravel-worker.service`:
```ini
[Unit]
Description=Laravel Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php /path/to/your/project/artisan queue:work --sleep=3 --tries=3 --max-time=3600
WorkingDirectory=/path/to/your/project

[Install]
WantedBy=multi-user.target
```

Enable and start:
```bash
sudo systemctl enable laravel-worker
sudo systemctl start laravel-worker
```

### Windows Development

#### Option 1: PowerShell Script (Recommended)
```powershell
.\scripts\start-queue-worker.ps1
```

#### Option 2: Batch Script
```cmd
.\scripts\start-queue-worker.bat
```

#### Option 3: Windows Service
```cmd
.\scripts\install-queue-service.bat
```

## Monitoring

### Check Queue Status
```bash
php artisan queue:monitor
```

### View Failed Jobs
```bash
php artisan queue:failed
```

### Retry Failed Jobs
```bash
php artisan queue:retry all
```

## Production Checklist

- [ ] Queue worker running automatically
- [ ] Failed job monitoring in place
- [ ] Log rotation configured
- [ ] Memory limits set appropriately
- [ ] Process monitoring (Supervisor/systemd)
- [ ] Email notifications for failures
- [ ] Database connection pooling
- [ ] Redis/database queue driver configured

## Troubleshooting

### Queue Worker Not Processing
1. Check if worker is running: `ps aux | grep queue:work`
2. Check logs: `tail -f storage/logs/laravel.log`
3. Restart worker: `sudo supervisorctl restart laravel-worker:*`

### Memory Issues
- Increase memory limit in worker command
- Use `--memory=512` flag
- Monitor with `php artisan queue:monitor`

### Performance Issues
- Use Redis for queue driver
- Increase number of worker processes
- Optimize database queries in jobs
