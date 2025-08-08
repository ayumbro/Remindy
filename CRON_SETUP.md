# Remindy Queue & Cron Setup Guide

## Overview
Remindy uses Laravel's queue system to process background jobs and scheduled tasks for sending subscription reminders. This guide will help you set up the necessary cron jobs and queue workers.

## Prerequisites
- PHP 8.1 or higher installed
- Access to system crontab or task scheduler
- Supervisor or systemd (for production environments)

## Components

### 1. Laravel Scheduler (Cron Job)
The Laravel scheduler runs every minute and triggers scheduled commands.

#### Add Cron Entry
```bash
# Open crontab editor
crontab -e

# Add this line (replace path with your actual Remindy installation path)
* * * * * cd /home/admin/laraval/Remindy && php artisan schedule:run >> /dev/null 2>&1
```

#### What This Runs
- **Hourly Tasks:**
  - `reminders:send-scheduled` - Sends reminders at users' preferred notification times
  - `reminders:create-schedules` - Creates reminder schedules for subscriptions
  
- **Every Minute:**
  - `reminders:process-pending` - Processes pending reminder notifications
  
- **Every 15 Minutes:**
  - `reminders:process-failed` - Retries failed notification attempts
  
- **Daily Tasks:**
  - `queue:prune-batches` - Cleans up old batch jobs (at 2:00 AM)
  - `queue:prune-failed` - Removes week-old failed jobs (at 2:30 AM)

### 2. Queue Worker
The queue worker processes background jobs immediately as they're dispatched.

## Installation Methods

### Option A: Development Setup (Manual)

#### Terminal 1 - Queue Worker
```bash
cd /home/admin/laraval/Remindy
php artisan queue:work --sleep=3 --tries=3
```

#### Terminal 2 - Test Scheduler
```bash
# Run scheduler once
php artisan schedule:run

# Or test specific commands
php artisan reminders:send-scheduled
php artisan reminders:process-pending
```

### Option B: Production Setup with Supervisor (Recommended)

#### 1. Install Supervisor
```bash
# Ubuntu/Debian
sudo apt-get install supervisor

# CentOS/RHEL
sudo yum install supervisor
```

#### 2. Create Supervisor Configuration
Create file: `/etc/supervisor/conf.d/remindy-worker.conf`

```ini
[program:remindy-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /home/admin/laraval/Remindy/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=admin
numprocs=1
redirect_stderr=true
stdout_logfile=/home/admin/laraval/Remindy/storage/logs/worker.log
stopwaitsecs=3600
```

#### 3. Start Supervisor
```bash
# Reload supervisor configuration
sudo supervisorctl reread
sudo supervisorctl update

# Start the worker
sudo supervisorctl start remindy-worker:*

# Check status
sudo supervisorctl status
```

### Option C: Production Setup with systemd

#### 1. Create systemd Service
Create file: `/etc/systemd/system/remindy-worker.service`

```ini
[Unit]
Description=Remindy Queue Worker
After=network.target

[Service]
User=admin
Group=admin
Restart=always
RestartSec=5
ExecStart=/usr/bin/php /home/admin/laraval/Remindy/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
StandardOutput=append:/home/admin/laraval/Remindy/storage/logs/worker.log
StandardError=append:/home/admin/laraval/Remindy/storage/logs/worker.log

[Install]
WantedBy=multi-user.target
```

#### 2. Enable and Start Service
```bash
# Reload systemd
sudo systemctl daemon-reload

# Enable service to start on boot
sudo systemctl enable remindy-worker

# Start the service
sudo systemctl start remindy-worker

# Check status
sudo systemctl status remindy-worker

# View logs
sudo journalctl -u remindy-worker -f
```

## Monitoring & Troubleshooting

### Check Queue Status
```bash
# View pending jobs in queue
php artisan queue:monitor

# List failed jobs
php artisan queue:failed

# Retry all failed jobs
php artisan queue:retry all

# Retry specific failed job
php artisan queue:retry {job-id}

# Clear all failed jobs
php artisan queue:flush
```

### Check Scheduler Status
```bash
# List scheduled tasks
php artisan schedule:list

# Run scheduler manually (for testing)
php artisan schedule:run

# Run specific command manually
php artisan reminders:send-scheduled
```

### View Logs
```bash
# Scheduler logs
tail -f storage/logs/scheduled-reminders.log
tail -f storage/logs/reminders.log
tail -f storage/logs/reminder-schedules.log
tail -f storage/logs/failed-notifications.log

# Queue worker logs
tail -f storage/logs/worker.log

# Laravel application logs
tail -f storage/logs/laravel.log
```

## Important Notes

1. **Time Zones**: Remindy stores all times in UTC. Users' notification preferences are converted to UTC for scheduling.

2. **Email Configuration**: Ensure users have configured their SMTP settings in `/settings/notifications` for email reminders to work.

3. **Database Queue Driver**: Remindy uses the database queue driver. Failed jobs are stored in the `failed_jobs` table.

4. **Memory Management**: The `--max-time=3600` parameter restarts the worker every hour to prevent memory leaks.

5. **Multiple Workers**: For high-volume installations, you can run multiple workers by changing `numprocs` in the Supervisor configuration.

## Verification Checklist

- [ ] Cron job is installed (`crontab -l` shows the Laravel scheduler entry)
- [ ] Queue worker is running (`ps aux | grep queue:work`)
- [ ] Test email can be sent from `/settings/notifications`
- [ ] Scheduled reminders are being created (check `notification_schedules` table)
- [ ] Log files are being written to `storage/logs/`

## Common Issues

### Jobs Not Processing
- Check if queue worker is running
- Verify `QUEUE_CONNECTION=database` in `.env`
- Check for failed jobs: `php artisan queue:failed`

### Scheduler Not Running
- Verify cron service is running: `sudo service cron status`
- Check cron logs: `grep CRON /var/log/syslog`
- Test scheduler manually: `php artisan schedule:run`

### Email Not Sending
- Verify SMTP settings in user's notification settings
- Check `storage/logs/laravel.log` for SMTP errors
- Test with: `php artisan reminders:send-scheduled --force`

## Support

For issues or questions:
1. Check the logs in `storage/logs/`
2. Review failed jobs with `php artisan queue:failed`
3. Ensure all services are running with proper permissions

---

Last updated: 2025-08-08