# Shoplisto server inventory and migration guide

Read from the Raspberry Pi (`raspberrypi5.local`) on 9 November 2026, read-only. No passwords, keys or tokens are in this
file. Where a value is secret, only its name is listed.

## 1. What runs today

```
Internet -> Cloudflare Tunnel (cloudflared, run by pm2)
              tomjielis.com     -> nginx :80   -> Nuxt on 127.0.0.1:3000 (pm2, also the Socket.io relay)
              api.tomjielis.com -> nginx :8000 -> PHP-FPM 8.4 -> Laravel /var/www/groceryListApi/public
Laravel -> MariaDB 127.0.0.1:3306, database groceryList
Queue worker: systemd grocerylist-queue    Scheduler: cron of www-data, every minute
```

There are no TLS certificates on the Pi: HTTPS ends at Cloudflare. Certbot is installed but has no certificates.

| Part | Pi today | Installed from |
|---|---|---|
| OS | Debian 13 (trixie), arm64, Raspberry Pi 5, 8 GB, 117 GB SD card (9.4 GB used) | |
| Timezone | Europe/Amsterdam, NTP on | |
| nginx | 1.26.3 | Debian |
| PHP CLI and FPM | 8.4.27 | Debian (`php8.4-*`) |
| Composer | 2.8.8 | Debian |
| Node.js | 20.19.5 | NodeSource `node_20.x` |
| npm | 10.8.2 | with Node |
| pm2 | 6.0.13 | global npm |
| MariaDB | 11.8.3 | Debian |
| cloudflared | 2026.10.4 | `.deb` from Cloudflare, not an apt source |
| git | 2.47.3 | Debian |

**PHP extensions in use:** bcmath, curl, gd, intl, mbstring, mysql (pdo_mysql, mysqli), opcache, readline, xml, zip. The
rest (ctype, exif, fileinfo, pcntl, sodium, tokenizer and others) come with `php8.4-common` and `php8.4-cli`.

**Not needed on the new server:** the Raspberry Pi desktop (`rpd-*`, lightdm), cups, bluetooth, ModemManager, rpcbind,
avahi, certbot (while Cloudflare handles HTTPS), ffmpeg, mkvtoolnix, Ghostscript.

## 2. Fix before or during the move

1. **There are no backups.** No cron job, timer or script makes a database dump, and the database and photos are on one
   SD card. Add backups on the new server, and consider a dump on the Pi now.
2. **`/var/www/groceryListApi/public/info.php` is in the web folder on production** and calls `phpinfo()`, so by the nginx
   config it can be opened at `https://api.tomjielis.com/info.php` and shows server details. Delete it, along with the
   stray file `/var/www/groceryListApi/tail`. Don't copy either to the new server; they aren't in git.
3. **No firewall is active** (`nftables` has no rules). Port 8000 (API) and 111 (rpcbind) are open on the local network.
   On the new server, allow only SSH from outside: the tunnel connects outward, so nginx needs no open ports.
4. **Website uploads are capped at nginx's default 1 MB** on port 80, because that site has no `client_max_body_size`.
   The API site allows 12 MB. Photo uploads through the website proxy can fail above 1 MB; add the setting on the new server.
5. **PHP-FPM has only 5 workers** (`pm.max_children = 5`) and MariaDB's InnoDB buffer is 128 MB. Both are fine on the Pi;
   raise them to suit a bigger server.
6. **`date.timezone` isn't set** for PHP (UTC). Laravel sets its own timezone, so copy this as is unless you change it on purpose.
7. **Old leftovers:** the empty database `laravel_db`, its user `laravel_user`, and a grant to a missing `grocery_db`.
   Don't recreate these.
8. **New CPU type:** if the new server is x86 (amd64), run `npm ci` and `composer install` again there and don't copy
   `node_modules` or `vendor`. The Android APK and the app builds aren't affected.

## 3. Install on the new server (Debian 13 or similar)

```bash
sudo apt update
sudo apt install -y nginx git curl unzip zip mariadb-server \
  php8.4-fpm php8.4-cli php8.4-bcmath php8.4-curl php8.4-gd php8.4-intl \
  php8.4-mbstring php8.4-mysql php8.4-opcache php8.4-readline php8.4-xml php8.4-zip composer

# Node 20 (the version production runs now)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
sudo npm install -g pm2

# cloudflared: use the package for your CPU from Cloudflare's downloads page
sudo timedatectl set-timezone Europe/Amsterdam
```

Check that the new server has PHP 8.4 or later, because `composer.lock` was resolved for it.

## 4. Database

Run on the Pi to export, without stopping anything:

```bash
sudo mariadb-dump --single-transaction --routines --triggers groceryList | gzip > groceryList.sql.gz
```

On the new server, with your own password in place of the placeholder:

```sql
CREATE DATABASE groceryList CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci;
CREATE USER 'grocery_list_user'@'localhost' IDENTIFIED BY '<new password>';
GRANT ALL PRIVILEGES ON groceryList.* TO 'grocery_list_user'@'localhost';
FLUSH PRIVILEGES;
```

```bash
gunzip -c groceryList.sql.gz | sudo mariadb groceryList
```

The database is small: 31 tables, about 6 MB. Pi settings: `bind-address = 127.0.0.1`, `sql_mode` default for MariaDB
11.8 (`STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION`), `max_connections = 151`.

## 5. Apps

Both are in `/var/www`, on branch `main`, owned by `admin:admin` with mode 775.

```bash
sudo mkdir -p /var/www && sudo chown $USER:$USER /var/www && cd /var/www
git clone git@github.com:TomJielis/groceryListApi.git
git clone git@github.com:TomJielis/groceryList.git

cd /var/www/groceryListApi
composer install --no-dev --optimize-autoloader
# copy .env (section 6) first
php artisan migrate --force
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

cd /var/www/groceryList
npm ci
npm run build
```

**Copy these from the Pi; they aren't in git:**

| What | Where | Size |
|---|---|---|
| Recipe photos and other private files | `/var/www/groceryListApi/storage/app/private` | 18 MB |
| Imports | `/var/www/groceryListApi/storage/app/imports` | 412 KB |
| Maintenance page | `/var/www/grocerylist-fallback/maintenance.html` | small |

`public/storage` is not linked on the Pi, and photos are served through the API, so `php artisan storage:link` isn't needed.
The Android APK (`downloads/Shoplisto.apk`) and `Shoplisto.json` come with the website's git repository.

Deploying today: `php release.php` for the API (git pull, composer install, migrate, db:seed, clear caches, restart queue), and
`release.sh` for the website (git pull, npm install, build, pm2 restart). `release.php` pulls the code itself.

## 6. Settings to copy (`.env`)

Copy both `.env` files securely and don't paste them into chat. `APP_KEY` must stay the same, or logins and encrypted
reset and activation links stop working.

**API, non-secret values:** `APP_ENV=prod`, `APP_DEBUG=false`, `APP_URL=https://tomjielis.com`,
`API_URL=https://api.tomjielis.com`, `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_DATABASE=groceryList`,
`SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`, `FILESYSTEM_DISK=local`, `MAIL_MAILER=smtp`,
`LOG_CHANNEL=stack`, `MIN_APP_BUILD_IOS=69`, `MIN_APP_BUILD_ANDROID=69`.

**API, secrets to carry over:** `APP_KEY`, `DB_USERNAME`, `DB_PASSWORD` (or your new one), `MAIL_USERNAME`, `MAIL_PASSWORD`,
`SUPPORT_IMAP_PASSWORD`, and `REDIS_PASSWORD`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` if they are set.

**Website:** `NUXT_API_URL=http://localhost:8000`, `NUXT_API_BASE_PATH=/api`, `NUXT_PUBLIC_APP_NAME="Shoplisto"`,
`NUXT_ANDROID_APK_PATH=/var/www/groceryList/downloads/Shoplisto.apk`, plus `NUXT_APP_LINKS_APPLE_TEAM_ID` and
`NUXT_PUBLIC_TEST_FLIGHT_URL`.

## 7. nginx

`/etc/nginx/sites-available/grocerylist-api`, linked from `sites-enabled`:

```nginx
server {
    listen 8000;
    server_name tomjielis.com api.tomjielis.com;
    root /var/www/groceryListApi/public;
    index index.php;

    set_real_ip_from 127.0.0.1;
    set_real_ip_from ::1;
    real_ip_header CF-Connecting-IP;

    location / {
        try_files $uri /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    access_log /var/log/nginx/grocerylist-api.access.log;
    error_log  /var/log/nginx/grocerylist-api.error.log;
    client_max_body_size 12M;
}
```

`/etc/nginx/sites-available/grocerylist-frontend`. The `client_max_body_size` line is new, see section 2:

```nginx
server {
    listen 80;
    server_name tomjielis.com;
    root /var/www/groceryList/.output/public;
    index index.html;
    client_max_body_size 12M;

    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        error_page 502 503 504 /maintenance.html;
    }

    location = /maintenance.html {
        root /var/www/grocerylist-fallback;
        internal;
    }

    access_log /var/log/nginx/nuxt-frontend.access.log;
    error_log  /var/log/nginx/nuxt-frontend.error.log;
}
```

The Pi's `server_name` lines also contain its local address, `192.168.68.114`; leave that out on the new server.
The `Upgrade` and `Connection` headers are required for live updates. Remove the default site, then:

```bash
sudo ln -s /etc/nginx/sites-available/grocerylist-api /etc/nginx/sites-available/grocerylist-frontend /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

## 8. PHP-FPM

Pool `/etc/php/8.4/fpm/pool.d/www.conf`: `user`, `group`, `listen.owner` and `listen.group` are `www-data`,
`listen = /run/php/php8.4-fpm.sock`, `pm = dynamic`, `pm.max_children = 5`, `pm.start_servers = 2`,
`pm.min_spare_servers = 1`, `pm.max_spare_servers = 3`.

Changed in `/etc/php/8.4/fpm/php.ini`: `upload_max_filesize = 16M`, `post_max_size = 16M`. Other values are defaults:
`memory_limit = 128M`, `max_execution_time = 30`.

Set the same upload limits on the new server, or photos and cards will be rejected.

## 9. Background jobs

`/etc/systemd/system/grocerylist-queue.service`:

```ini
[Unit]
Description=GroceryList API queue worker
After=network.target mariadb.service

[Service]
User=www-data
WorkingDirectory=/var/www/groceryListApi
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload && sudo systemctl enable --now grocerylist-queue
```

Scheduler, in the crontab of `www-data` (`sudo crontab -u www-data -e`):

```cron
* * * * * cd /var/www/groceryListApi && php artisan schedule:run >> /dev/null 2>&1
```

## 10. pm2 and the tunnel

pm2 runs as `admin` and starts at boot through the `pm2-admin` service.

| pm2 name | Folder | Command |
|---|---|---|
| `groceryList` | `/var/www/groceryList` | `npm run start` (`nuxt start`, port 3000) |
| `groceryList-tunnel` | `/home/admin` | `cloudflared --config ~/.cloudflared/config.yml tunnel run nuxt-tunnel` |

```bash
cd /var/www/groceryList && pm2 start npm --name groceryList -- run start
pm2 start /usr/local/bin/cloudflared --name groceryList-tunnel -- --config /home/$USER/.cloudflared/config.yml tunnel run nuxt-tunnel
pm2 save
pm2 startup   # run the command it prints
```

Tunnel config `~/.cloudflared/config.yml`:

```yaml
tunnel: <tunnel id>
credentials-file: /home/<user>/.cloudflared/<tunnel id>.json
ingress:
  - hostname: tomjielis.com
    service: http://localhost:80
  - hostname: api.tomjielis.com
    service: http://localhost:8000
  - service: http_status:404
```

Copy `cert.pem` and `<tunnel id>.json` from `~/.cloudflared` securely. With the same tunnel, Cloudflare's DNS doesn't need changing.

## 11. Switch-over

1. Test the new server fully with the tunnel still running on the Pi, using a separate test tunnel or hostname.
2. Put the Pi in maintenance mode so nothing changes: `php artisan down`, and stop the queue worker.
3. Make a final database dump and copy `storage/app`.
4. Import both on the new server and run `php artisan migrate --force`.
5. Stop the tunnel on the Pi, then start it on the new server. Two running at once share the traffic between both servers.
6. Keep the Pi unchanged for a few days in case you need to go back.
