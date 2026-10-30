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
# APP_URL is optional — omit it and the domain is auto-detected per request.
# APP_URL=https://yourdomain.com
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

- `APP_URL` — **optional**. When omitted, the app derives the origin from each
  request (`HTTP_HOST` plus `HTTPS` / `X-Forwarded-Proto`), so links, the
  Telegram webhook URL and the WebApp button all follow whatever domain the
  site is served from. Set it only if detection is wrong — for example when the
  app lives in a subdirectory (`https://example.com/app`) or a proxy rewrites
  the host. **No trailing slash** when you do set it.
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
(nginx does). See [§10 nginx](#10-if-you-are-on-nginx) below.

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
| `bot_token` | The token from @BotFather. **Required** — without it the mini app cannot verify the Telegram identity and every page shows the "Open inside Telegram" screen. |
| `bot_username` | Your bot's username **without** the `@` |
| `bot_web_app_url` | **Leave blank** — it follows the current domain automatically. Set it only to pin a specific URL. |
| `webhook_secret` | Any long random string (letters + digits) |

### How the mini app authenticates

Telegram passes the signed `initData` payload in the URL **fragment**
(`#tgWebAppData=...`), which browsers never send to the server. So on first
load the page posts it to `webapp-auth.php`, which verifies the HMAC signature
against `bot_token` and stores it in the session. Every later request resolves
the user from that session value.

If `bot_token` is blank or wrong, the signature check fails and the app falls
back to the "Open inside Telegram" screen — that is the symptom to look for.

The same applies to `withdrawal_api_url`: it is an optional external payout hook.
Leave it blank unless you are forwarding payouts to your own payout service — see
[First withdrawal rule](#first-withdrawal-rule) below.

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

## 9. First withdrawal rule

A user's **first** withdrawal is treated differently from every later one:

| | First withdrawal | Later withdrawals |
|---|---|---|
| Cap | `min_withdrawal_t` (default 2,000 T) | `max_withdrawal_t` (default 50,000 T) |
| Minimum | none — any amount from 1 T up to the cap | `min_withdrawal_t` |
| Referral requirement | skipped | `min_referrals_withdrawal` |
| Payout | sent to `withdrawal_api_url` immediately | sent to `withdrawal_api_url` when an admin approves it |

The cap is deliberately tied to `min_withdrawal_t` so there is a single number
to tune: raising the minimum also raises the first-withdrawal ceiling.

A request only counts as "used" once it is neither `cancelled` nor `rejected`,
so a user whose first attempt was rejected still gets the first-withdrawal
treatment on their next try.

### Payout API contract

`withdrawal_api_url` receives a `POST` with a JSON body:

```json
{ "amt": 200.00, "upi": "user@okaxis", "txid": "WD42" }
```

`amt` is the **rupee** value, not the T-coin amount. The withdrawal row stores
T coins, so the amount is converted with `coin_to_inr_rate` before the call:
2,000 T at the default rate of `0.10` is sent as `200.00`. It is always
formatted to two decimals.

The same values are substituted into the URL if it contains `{amt}`, `{upi}` or
`{txid}` placeholders.

A **2xx** response marks the withdrawal `completed`. The transaction ID is read
from the first of `transaction_id`, `txid`, `txn_id`, `utr`, `reference`,
`reference_id` or `id` present in the response, and the whole response is stored
in `withdrawals.api_response`.

The payout is treated as **failed** when the response is non-2xx, when it
contains `"success": false`, or when `status` is one of `failed`, `failure`,
`error`, `rejected`, `declined`.

The API is called from **two** places:

| Trigger | On failure |
|---|---|
| A user's first withdrawal, immediately after the request is saved | The withdrawal stays `pending` with the reason in `admin_note`, so an admin can approve or reject it from **Admin → Withdrawals** as usual. |
| An admin approving a withdrawal in **Admin → Withdrawals** | The admin's decision stands: the withdrawal stays `completed` and the reason is recorded in `admin_note` for follow-up. |

The user's balance is never restored automatically — only an explicit
rejection refunds it.

Leave `withdrawal_api_url` blank to keep every withdrawal in the admin queue.

---

## 10. Broadcast delivery

Broadcasts are sent **synchronously** from the admin composer — there is no
queue, no cron job and no worker process to install. When you press **Send**,
`adm/broadcast_send.php` saves the broadcast, then calls the Telegram Bot API
once per recipient before the page redirects.

### Requirements

| Requirement | Why |
|---|---|
| `bot_token` set in **Admin → Settings** | Without it the broadcast is saved as a draft and the send is refused with "Bot token is not configured." |
| Recipients have started the bot | Only users with `bot_started_at` set are messaged. Telegram forbids bots from opening a chat. |
| `uploads/` writable | Media broadcasts are uploaded from disk. The directory is created automatically on first use. |

### Audience filters

| Audience | Who receives it |
|---|---|
| `all` | Every user who has started the bot and is not `blocked` |
| `active` | The above, last active within 30 days |
| `inactive` | The above, last active more than 30 days ago (or never) |

Users with `status = 'blocked'` are always excluded.

### What happens during a send

1. The broadcast row is committed first, so a delivery failure never loses the draft.
2. `status` moves to `sending` and `total_recipients` is filled in.
3. Each recipient is attempted in turn, with a 50 ms pause between sends to stay
   under Telegram's ~30 messages/second limit.
4. A `429 Too Many Requests` response is retried once after the `retry_after`
   delay Telegram asks for (clamped to 1–30 seconds).
5. A `400` response complaining about *entities* is retried once with
   `parse_mode` removed, so a stray `*` or `_` in the message does not fail the
   whole broadcast.
6. For media broadcasts the file is uploaded **once**; the returned `file_id` is
   reused for every remaining recipient instead of re-uploading the file.
7. `delivered_count` / `failed_count` are written every 25 sends, so a timeout
   still leaves usable numbers.
8. `status` ends as `sent` when at least one message got through, otherwise
   `failed`, and `sent_at` is stamped.

The admin sees a toast with the outcome, e.g.
`Broadcast #12 sent to 184 of 190 users. 6 failed.`

### Media and formatting

- Images (JPG, PNG, GIF, WebP) up to 5 MB, video (MP4, MOV) up to 50 MB, and
  documents (PDF, DOC, DOCX, XLS, XLSX, ZIP) up to 20 MB.
- Files are stored under `uploads/broadcasts/` with a random name and are
  **never served over HTTP** — `.htaccess` returns 404 for `/uploads/`.
- The composer's Markdown (`*bold*`, `_italic_`, `__underline__`, `~strike~`,
  `||spoiler||`, `` `code` ``, `[text](url)`, `> quote`) is converted to
  Telegram **HTML** server-side and sent with `parse_mode = 'HTML'`. Legacy
  Markdown does not support underline, strike, spoiler or quotes, and
  MarkdownV2 would require escaping every `.`/`!`/`-`.
- Captions on media broadcasts are limited to **1024** characters; text
  broadcasts allow 4096. A text broadcast must have text — buttons alone are
  rejected, because `sendMessage` refuses an empty message.
- GIFs are sent with `sendAnimation` so they stay animated.
- Up to 8 inline URL buttons per broadcast, one per row. Only `http(s)://` and
  `tg://` links are accepted — Telegram rejects `mailto:` with
  `BUTTON_URL_INVALID`. A button with a label but no link is an error rather
  than being silently dropped.
- File type detection uses `finfo` first and falls back to the original
  filename extension, because legacy `.doc`/`.xls` report as
  `application/CDFV2` and modern `.docx`/`.xlsx` report as `application/zip`.

> **Upload limits.** PHP's `upload_max_filesize` and `post_max_size` must be at
> least as large as the biggest file you intend to send. The defaults in
> `php.ini` are often 2 MB, which will reject images before the app's own size
> check runs. Raise both to `50M` if you plan to send video. When
> `post_max_size` is exceeded PHP empties `$_POST` and `$_FILES`; the handler
> detects this and reports the real cause instead of "session expired".

### Long sends

Because delivery is synchronous, a broadcast to a very large audience keeps the
admin's browser waiting. With the 50 ms pause, roughly 1,200 recipients take
about a minute. If you expect audiences in the tens of thousands, raise
`max_execution_time` or split the audience into batches.

---

## 11. If you are on nginx

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

## 12. Post-launch checklist

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

## 13. Backups

Nightly `mysqldump` via cron:

```bash
0 3 * * * mysqldump -u myuser -p'PASSWORD' myuser_refer_earn \
  | gzip > /home/myuser/backups/refer_earn_$(date +\%F).sql.gz
```

Keep at least 14 days. The `wallet_transactions` and `withdrawals` tables are
financial records — never prune them.

---

## 14. Updating the site

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
| "Open inside Telegram" screen inside Telegram | `bot_token` is blank or wrong, so `webapp-auth.php` cannot verify `initData`. Set it in Admin → Settings. |
| Webhook returns 401 | `webhook_secret` in Settings does not match the `secret_token` Telegram was registered with. Press **Rotate** then **Set** again. |
| Lock screen never goes away | `require_bot_start` is on and the user has not sent `/start`, or the channel check is failing in strict mode. |
| Referral never credited | The referral is applied on the user's **first app load** after `/start CODE`. If they never open the app, it stays pending by design. |
