# Telegram Server Monitor

**English** | [Русский](README.md)

> Self-hosted Telegram bot for Linux server monitoring: RAM, CPU, disk, Nginx, PM2, fail2ban, site checks. PHP, no cloud.

Lightweight Linux server monitoring via a Telegram bot: RAM, CPU, disk, Nginx, PM2, site checks, fail2ban bans — all from inline buttons. Self-hosted, no cloud required.

**Audience:** admins and developers with VPS/dedicated servers who want to check status and run simple actions from their phone.

---

## Features

| Action | Description |
|--------|-------------|
| **RAM** | Memory summary + "Show processes" (top by RAM). "Restart"/"Stop" buttons — enter PID; enabled in settings. System processes (init, systemd, etc.) are blocked. |
| **CPU** | Load average, core count, uptime + "Show processes" (top by CPU) and same PID buttons. |
| **Disk** | Used/free for `/` + "Details" (per mount: space and inodes). |
| **Nginx** | Process status + "Details" (version, `nginx -t` result). |
| **PM2** | Process list and "Manage" → pick process → status and Start / Stop / Restart. |
| **Sites** | Domain availability check (HTTPS/HTTP). List in web UI or `config.php` (`sites_domains`). |
| **Banned IPs** | List of IPs banned by fail2ban (jail nginx-limit-req), with pagination and "Unban" (enter list number). Requires `bot-fail2ban.sh` and sudoers. |
| **All** | Summary: RAM + CPU + disk + Nginx. |
| **Reboot** | Server reboot with confirmation (requires `bot-reboot.sh` and sudoers). |

---

## Settings web interface

Settings (bot token, webhook secret, allowed username, site list for "Sites", **enabled modules**) are editable in the browser. `index.php` is the form and saves to `config.php`. **"Detect server technologies"** runs `scripts/detect-technologies.php`: it checks for Nginx, PM2, PHP-FPM, MySQL/MariaDB, Docker and suggests modules (RAM, CPU, Disk are always suggested).

The **"Cron / disk"** tab sets the **root disk usage threshold (%)** for **`server-monitor.sh`** (server cron). On save, **`monitor-threshold.env`** is written next to `config.php` with `DISK_ALERT_THRESHOLD_PERCENT`. If that file exists, the monitor script sources it after `/etc/server-monitor.conf`. Telegram alerts: first when the threshold is first reached, then only when usage **increases** (e.g. after 90%, the next alert at 91% or higher).

**Important:** protect the settings UI with a password. Use a separate (sub)domain (e.g. `tg.example.com`) and Nginx `auth_basic`.

### Password protection (Nginx)

1. **Create password file** (once):

```bash
sudo htpasswd -c /etc/nginx/.htpasswd_telegram_monitor admin
```

For more users: `sudo htpasswd /etc/nginx/.htpasswd_telegram_monitor second_user` (without `-c`).

2. **In the `server` block** add:

```nginx
auth_basic "Settings";
auth_basic_user_file /etc/nginx/.htpasswd_telegram_monitor;
```

See **`nginx-settings.example.conf`** for a full example.

---

## Requirements

- **PHP** 7.4+ (json extension; no extra modules required)
- **Nginx** (or another web server with PHP-FPM)
- **HTTPS** for webhook (required by Telegram)
- **Telegram Bot Token** — from [@BotFather](https://t.me/BotFather)

Optional for full features:

- **Node.js + PM2** — for "PM2" section
- **sudo** — for service/PM2 control (helper scripts and sudoers)

---

## Installation

### 1. Clone and place files

```bash
sudo mkdir -p /var/www/telegram-bot
sudo cp -r . /var/www/telegram-bot/
sudo chown -R apache:apache /var/www/telegram-bot
```

(Replace `apache` with your PHP-FPM user: `nginx`, `www-data`, etc.)

### 2. Configuration

```bash
cd /var/www/telegram-bot
cp config.example.php config.php
nano config.php
```

Set:

- **bot_token** — from @BotFather  
- **webhook_secret** — long random string (e.g. `openssl rand -hex 32`)  
- **allowed_username** — Telegram username without `@` (only this user can use the bot; leave `''` to allow everyone)

`config.php` is in `.gitignore` and must not be committed.

### 3. Modules and site list

- **Modules** — in the web UI, choose which buttons to show. Stored in `config.php` as `modules`. Use "Detect server technologies" to auto-suggest by installed services.
- **Sites** — in the web UI or `config.php` key `sites_domains` (array of domain strings).

### 4. Nginx

- **Webhook:** use `nginx.example.conf` for the domain that will receive Telegram webhook (e.g. `bot.example.com`). Replace `YOUR_DOMAIN`, app path, and SSL paths.
- **Settings UI:** use `nginx-settings.example.conf` for a separate domain (e.g. `tg.example.com`) with `auth_basic` as above.

```bash
sudo nginx -t && sudo systemctl reload nginx
```

### 5. SSL (Let's Encrypt)

```bash
sudo certbot certonly --webroot -w /usr/share/nginx/html -d YOUR_DOMAIN
# or: sudo systemctl stop nginx && sudo certbot certonly --standalone -d YOUR_DOMAIN && sudo systemctl start nginx
```

### 6. Webhook URL

The web UI shows the webhook URL from the current host. Use the URL where your webhook is actually reachable (often a subdomain like `bot.example.com`).

### 7. Register webhook with Telegram

```bash
curl -s -X POST "https://api.telegram.org/bot<YOUR_BOT_TOKEN>/setWebhook" \
  -H "Content-Type: application/json" \
  -d "{\"url\":\"https://YOUR_DOMAIN/webhook.php\",\"secret_token\":\"<YOUR_WEBHOOK_SECRET>\"}"
```

Use values from `config.php`. Response should include `"ok":true`.

### 8. Process settings (RAM/CPU)

In the web UI, "Modules" tab, **Processes (RAM/CPU)**:

- **Process list size** (1–25) — how many rows in "Show processes". Default 10.
- **Allow process stop by PID** — "Stop" button; bot asks for PID.
- **Allow process restart by PID** — "Restart"; only nginx, php-fpm, mysqld, mariadbd via systemctl. PID 1, 2 and init, systemd, kthreadd, sshd are forbidden.

### 9. Helper scripts (optional)

For PM2 and Restart/Stop by PID, copy scripts and set sudoers:

```bash
sudo cp scripts/bot-pm2-list.sh scripts/bot-pm2-restart.sh scripts/bot-pm2-stop.sh scripts/bot-pm2-start.sh scripts/bot-restart-service.sh scripts/bot-process-pid.sh scripts/bot-reboot.sh scripts/bot-fail2ban.sh /usr/local/bin/
sudo chmod +x /usr/local/bin/bot-pm2-*.sh /usr/local/bin/bot-restart-service.sh /usr/local/bin/bot-process-pid.sh /usr/local/bin/bot-reboot.sh /usr/local/bin/bot-fail2ban.sh
```

```bash
sudo cp sudoers.example /etc/sudoers.d/bot-telegram
sudo visudo -f /etc/sudoers.d/bot-telegram
# Set your PHP-FPM user instead of 'apache'
sudo chmod 440 /etc/sudoers.d/bot-telegram
```

Check: `sudo visudo -c -f /etc/sudoers.d/bot-telegram`

---

## Usage

1. Open the bot in Telegram and send `/start` or "status".  
2. Menu with buttons: RAM, CPU, Disk, Nginx, PM2, Sites, All, Reboot.  
3. Use buttons to get data or run actions (restart service/PM2).

If `allowed_username` is set, only that user gets replies; others are ignored.

---

## Security

- **secret_token** — set in `config.php` and in `setWebhook`. Telegram sends this header; without it webhook returns 403.  
- **allowed_username** — restrict the bot to your Telegram username.  
- **HTTPS** — required for webhook.  
- **sudoers** — only specific scripts and args (nginx, php-fpm, mysqld, mariadb, pm2), not arbitrary commands.

---

## Changing webhook secret or URL

1. Update `webhook_secret` in `config.php`.  
2. Call `setWebhook` with the new `secret_token` and optionally new `url`.  
Otherwise Telegram will keep sending the old secret and the script will return 403.

---

## Banned IPs module (fail2ban)

When **banned_ips** is enabled, the bot shows "Banned IPs": numbered list from jail `nginx-limit-req` with pagination. "Unban" asks for the list number and unbans that IP.

Required:

- Script **`/usr/local/bin/bot-fail2ban.sh`** (in `scripts/`). Commands: `list [jail]`, `unban <jail> <ip>`.
- In sudoers for PHP-FPM user: `bot-fail2ban.sh *`

---

## Before publishing to a public repo

- [ ] Ensure **config.php** is not committed (it's in `.gitignore`).
- [ ] **config.example.php** and **config.test.php** contain only placeholders (`YOUR_BOT_TOKEN`, `test`, etc.), no real secrets.
- [ ] No personal domains, IPs, server names or Telegram usernames in code or README (use examples like `example.com`).
- [ ] **LICENSE** file present (MIT in this project).
- [ ] README documents installation, requirements and optional modules.

After cloning, users copy `config.example.php` to `config.php` and fill in their data.

---

## Code structure

- **webhook.php** — entry: secret check, username auth, callback and command handling, pending state. Requires `helpers.php`.
- **helpers.php** — metric/data functions: `getServerStat`, `getRamProcessesList`, `getCpuProcessesList`, `getPm2ListRaw` / `getPm2ListSimple`, `getBannedIpsList`, `getSitesCheckResult`. No config or request dependency.
- **index.php** — settings web UI (modules, domains, webhook URL).
- Constants `BANNED_IPS_JAIL` and `BANNED_IPS_PER_PAGE` are in webhook.php; can be moved to config if needed.

---

## License

MIT.
