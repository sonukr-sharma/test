# Deploying Refer & Earn to the web

A step-by-step guide to take this project from XAMPP on your laptop to a live
HTTPS site that Telegram can talk to.

---

## 0. What you need before you start

| Requirement | Why |
|---|---|
| **PHP 8.1 or newer** | The code uses `declare(strict_types=1)`, typed properties, and `match`. |
| **PHP extensions:** `pdo_mysql`, `curl`, `openssl`, `mbstring`, `json`, `bcmath`, `fileinfo` | Database, Telegram API, HMAC signature checks, UTF-8 names, money maths, uploads. |
| **MySQL 5.7+ / MariaDB 10.2+** | Schema uses `JSON` columns (5.7.8+ / 10.2.7+). No window functions are used, so 5.7 works. |
| **HTTPS with a valid certificate** | **Telegram refuses non-HTTPS webhook URLs.** This is not optional. |
| **A Telegram bot token** | From [@BotFather](https://t.me/BotFather). |
| **Write access to one folder outside the web root** | For PHP error logs. |

> **Shared hosting (cPanel) works fine.** You only need PHP + MySQL + HTTPS.
> No Composer, no Node, no build step.

---

## 1. Create the database

In cPanel → **MySQL Databases** (or your DB admin tool):

1. Create a database, e.g. `myuser_refer_earn`.
2. Create a user with a **strong password** and grant it **ALL** privileges on
   that database only.
3. Note down: host (`localhost`), database name, user, password.

Then import the schema — either through **phpMyAdmin → Import → `schema.sql`**,
or on the command line:

```bash
mysql -u myuser -p myuser_refer_earn < schema.sql
```

This creates **14 tables, 3 views**, the seed admin account, and **19 settings**.

Verify:

```sql
SELECT COUNT(*) FROM information_schema.tables
WHERE table_schema = 'myuser_refer_earn';   -- expect 17 (14 tables + 3 views)

SELECT COUNT(*) FROM settings;              -- expect 20
```

---

## 2. Upload the files

Upload everything **except** these to your web root
(usually `public_html/`):

```
.env.example      ← keep locally, or upload then delete
.gitignore
.htaccess         ← DO upload this one
schema.sql        ← upload only if you import via phpMyAdmin, then delete
```

Recommended layout:

```
public_html/
├── .htaccess
├── .env                  ← created in step 3, never committed
├── config.php
├── index.php
├── withdraw.php
├── activity.php
├── refer-earn.php
├── leaderboard.php
├── asset/
├── adm/
│   ├── auth/index.php
│   ├── withdrawal.php
│   ├── withdrawal_action.php
│   ├── settings.php
│   ├── broadcast.php
│   ├── broadcast_send.php
│   ├── channels.php
│   └── logout.php
└── webhook/
    ├── index.php
    └── setup.php
```

> **Better (VPS only):** put `config.php` and `.env` **above** `public_html/`
> and point the site's document root at a `public/` folder. On shared hosting
> the `.htaccess` rules already block direct access to both.

---

## 3. Create `.env`

Copy `.env.example` to `.env` in the web root and fill in real values:

```ini
APP_ENV=production
APP_DEBUG=0
APP_URL=https://yourdomain.com
APP_TIMEZONE=Asia/Kolkata

DB_HOST=localhost
DB_PORT=3306
DB_NAME=myuser_refer_earn
DB_USER=myuser_refer
DB_PASS=the-strong-password-you-just-set

SESSION_NAME=refer_earn_sid
SESSION_LIFETIME=28800

LOG_DIR=/home/myuser/logs
```

Rules:

- `APP_URL` — **no trailing slash**, must be the exact public HTTPS origin.
- `APP_DEBUG=0` — always in production. `1` prints stack traces to visitors.
- `LOG_DIR` — an absolute path to a writable folder **outside** `public_html`.
  Leave empty to use the host's default error log.
- File permissions: `chmod 600 .env` (owner read/write only).

If your host does not allow `.env` files, set the same keys as real environment
variables instead (cPanel → **MultiPHP INI Editor** or an Apache `SetEnv`
block). Real environment variables always win over `.env`.

---

## 4. Check the install

Open these in a browser:

| URL | Expected |
|---|---|
| `https://yourdomain.com/` | The mini app, or the "Start the bot" lock screen |
| `https://yourdomain.com/adm/auth/` | Admin login page |
| `https://yourdomain.com/.env` | **403 Forbidden** |
| `https://yourdomain.com/schema.sql` | **403 Forbidden** |
| `https://yourdomain.com/config.php` | **403 Forbidden** |

If `.env` or `schema.sql` returns content, your host is ignoring `.htaccess`
(nginx does). See [§9 nginx](#9-if-you-are-on-nginx) below.

---

## 5. Secure the admin account

1. Log in at `/adm/auth/` with the seeded credentials:
   - **Username:** `admin`
   - **Password:** `admin123`
2. **Change the password immediately.** Until you do, anyone who reads this
   guide can log in.

To change it, generate a bcrypt hash and update the row:

```bash
php -r "echo password_hash('YourNewStrongPassword', PASSWORD_DEFAULT), PHP_EOL;"
```

```sql
UPDATE admins
SET password_hash = '$2y$10$...the-hash-you-just-generated...'
WHERE username = 'admin';
```

Also change the seeded email `admin@referearn.com` to a real address.

---

## 6. Configure the bot

Go to **Admin → Settings** and fill in:

| Setting | Value |
|---|---|
| `bot_token` | The token from @BotFather |
| `bot_username` | Your bot's username **without** the `@` |
| `bot_web_app_url` | `https://yourdomain.com` — the same as `APP_URL` |
| `webhook_secret` | Any long random string (letters + digits) |

Generate a secret:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Then, in @BotFather:

1. `/mybots` → your bot → **Bot Settings → Menu Button → Configure menu button**
   → set the URL to `https://yourdomain.com`.
2. `/mybots` → your bot → **Bot Settings → Domain** → add your domain
   (required for Web Apps to open).

---

## 7. Register the webhook

Open **`https://yourdomain.com/webhook/setup.php`** (admin login required).

1. The status panel shows the current webhook state.
2. Press **Set Webhook**.
3. Press **Test** — Telegram should reply `ok: true`.
4. Send `/start` to your bot from a real Telegram account. The update should
   appear in the "Recent updates" table within a second or two.

If **Set Webhook** fails:

| Error | Fix |
|---|---|
| `bad webhook: HTTPS url must be provided` | You are on HTTP. Install a certificate. |
| `SSL error` / `certificate verify failed` | The certificate chain is incomplete. Fix it at your host. |
| `Wrong response from the webhook: 403` | `.htaccess` or a WAF is blocking `webhook/index.php`. |
| `Wrong response from the webhook: 500` | Check the PHP error log (`LOG_DIR`). |
| Timeout | Your host blocks outbound requests, or the site is behind a login wall. |

---

## 8. Configure the access gate

**Admin → Channels** (`/adm/channels.php`):

1. Add each channel the user must join:
   - **Chat ID** — e.g. `@mychannel` or `-1001234567890`
   - **Title** — shown on the lock screen
   - **Invite URL** — e.g. `https://t.me/mychannel`
2. **The bot must be an administrator of every channel** you list, otherwise
   `getChatMember` fails and the check is skipped.
3. Toggle **Require channel join** on when you are ready.

**Admin → Settings → Access Gate**:

| Setting | Meaning |
|---|---|
| `require_bot_start` | User must send `/start` before the app opens. Keep **on**. |
| `require_channel_join` | User must join the channels above. |
| `channel_gate_strict` | **Off (default):** if the membership check errors, let the user in. **On:** block them. Turn on only after you have confirmed the bot is a channel admin. |
| `channel_gate_message` | Text shown on the lock screen. |

Test with a fresh Telegram account before announcing the app.

---

## 9. If you are on nginx

`.htaccess` is Apache-only. Add the equivalent to your server block:

```nginx
server {
    listen 443 ssl http2;
    server_name yourdomain.com;
    root /var/www/refer-earn/public_html;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;

    # Block secrets and the schema dump
    location ~ /\.            { deny all; }
    location ~* \.(sql|md|example|log|ini|bak|old|sh)$ { deny all; }
    location = /config.php    { deny all; }

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        include        fastcgi_params;
        fastcgi_pass   unix:/run/php/php8.2-fpm.sock;
        fastcgi_param  SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    add_header X-Content-Type-Options nosniff always;
    add_header X-Frame-Options SAMEORIGIN always;
    add_header Referrer-Policy strict-origin-when-cross-origin always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
}
```

---

## 10. Post-launch checklist

- [ ] `APP_DEBUG=0` and `APP_ENV=production` in `.env`
- [ ] `.env` returns **403** in a browser
- [ ] `schema.sql` deleted from the server (or returns 403)
- [ ] Admin password changed from `admin123`
- [ ] Admin email changed from `admin@referearn.com`
- [ ] `bot_token` and `webhook_secret` set in Settings
- [ ] Webhook **Set** and **Test** both pass
- [ ] `/start` from a real account logs an update
- [ ] Bot is an **admin** of every required channel
- [ ] Gate tested with a fresh account
- [ ] `LOG_DIR` is writable and outside the web root
- [ ] Database backed up (see below)

---

## 11. Backups

Nightly `mysqldump` via cron:

```bash
0 3 * * * mysqldump -u myuser -p'PASSWORD' myuser_refer_earn \
  | gzip > /home/myuser/backups/refer_earn_$(date +\%F).sql.gz
```

Keep at least 14 days. The `wallet_transactions` and `withdrawals` tables are
financial records — never prune them.

---

## 12. Updating the site

```bash
git pull origin main
```

Then, only if `schema.sql` changed, apply the new statements manually (never
re-import the whole file over a live database — it drops tables).

---

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| `#1064 ... near '(ORDER BY'` while importing `schema.sql` | Your server is older than MySQL 8.0 / MariaDB 10.2 and the schema still contains a window function. The shipped `v_leaderboard` uses a correlated subquery instead, so re-download `schema.sql`. |
| `#1064` on `CHECK (...)` | MySQL 5.7 parses but ignores `CHECK`; only 8.0.16+ / MariaDB 10.2.1+ enforce it. Harmless. |
| Blank white page | `APP_DEBUG=0` is hiding a fatal error. Set it to `1` temporarily, reload, then set it back. |
| "Database connection failed" | Wrong `DB_*` values in `.env`, or the DB user lacks privileges. |
| Login says "Invalid credentials" | The `admins` row was not seeded, or the password hash was overwritten. |
| Mini app opens but shows no data | `telegram_id` mismatch — the user row was created by a different bot. |
| Webhook returns 401 | `webhook_secret` in Settings does not match the `secret_token` Telegram was registered with. Press **Rotate** then **Set** again. |
| Lock screen never goes away | `require_bot_start` is on and the user has not sent `/start`, or the channel check is failing in strict mode. |
| Referral never credited | The referral is applied on the user's **first app load** after `/start CODE`. If they never open the app, it stays pending by design. |
