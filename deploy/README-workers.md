Persistent workers (systemd / supervisor)

This project uses Laravel queue workers to process `nametag` jobs. To ensure workers are always running in production, install one of the following service definitions and enable it.

1) Systemd (recommended on modern Linux)

- Copy the template to systemd and enable/start:

```bash
sudo cp deploy/nametag-worker.service /etc/systemd/system/nametag-worker.service
sudo systemctl daemon-reload
sudo systemctl enable --now nametag-worker.service
sudo journalctl -u nametag-worker -f
```

- Notes:
  - Ensure the `User` in the unit (`deploy`) exists and has permission to run `php` and access the app dir.
  - Adjust `ExecStart` if PHP binary is in a different path.
  - If your `.env` contains environment variables required by the worker, either create a small `/etc/default/nametag-worker` with `KEY=VALUE` pairs and uncomment `EnvironmentFile` in the unit, or export necessary vars in the unit file.

2) Supervisor (alternative)

- Copy `deploy/supervisor-nametag-worker.conf` to `/etc/supervisor/conf.d/nametag-worker.conf` and reload supervisor:

```bash
sudo cp deploy/supervisor-nametag-worker.conf /etc/supervisor/conf.d/nametag-worker.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start nametag-worker
sudo tail -f /var/log/nametag-worker.log
```

3) Important operational notes

- Queue driver: The templates above run `queue:work database`. If you use Redis as queue driver, change the command to `queue:work redis` and ensure the PHP Redis extension (`php-redis`) or `predis/predis` is installed. The logs previously showed `Class "Redis" not found` — fix that before switching to Redis.

- Cache store parity: Ensure the worker process sees the same cache store as the web process. If using Redis for cache, workers must have the same `CACHE_DRIVER`/`REDIS_CLIENT` set via environment.

- Supervisor vs systemd: use whichever your infra already uses. systemd is native and preferred for simple setups.

- Monitoring: consider adding process monitors or alerts for worker restarts and failures.

4) Quick test (manual)

```bash
# process pending jobs once (as deploy user):
sudo -u deploy php artisan queue:work database --queue=nametag --once --tries=1 -vvv
```

If you want, I can: 
- attempt to enable and start the systemd unit now (requires root), or
- install a Supervisor config and start it (requires sudo), or
- adjust the worker command to use `redis` if you prefer Redis and fix the missing PHP Redis extension.
