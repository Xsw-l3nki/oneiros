# Deploying Oneiros 2.1 on Afrihost cPanel (with Terminal)

This guide takes you from the release ZIP to a live site. There are two paths:

- **Path A: Upgrade** an existing Oneiros site (v1.x or 2.0). Your accounts, dreams, messages, uploads and settings are kept.
- **Path B: Fresh install** on a new domain or subfolder.

Then **Selling Lucid passes** (further down) explains how to switch on once-off payments.

Everything below happens in cPanel. Commands are typed in **cPanel → Advanced → Terminal**. Replace the words in `CAPITALS` with your own values.

> **What the ZIP does not contain, on purpose:** `includes/config.php` (your live database password and secrets) and anything inside `uploads/` (your dreamers' images and recordings). Extracting the ZIP over an existing site therefore never overwrites your settings or your users' files.

---

## 0. Before you start (2 minutes)

**PHP 8.2 or newer is required.** In Terminal:

```bash
php -v
php -m | grep -Ei 'pdo_mysql|mbstring|fileinfo|openssl|curl'
```

- If the version is below 8.2, go to **cPanel → Software → Select PHP Version** (or **MultiPHP Manager**), choose **8.2** or **8.3** for your domain, and make sure the extensions `pdo_mysql`, `mbstring`, `fileinfo`, `openssl` and `curl` are ticked.
- If the web version is right but `php -v` in Terminal still shows an older one, use the full path to the newer binary in every command below, for example:
  ```bash
  ls /opt/alt/php8*/usr/bin/php /opt/cpanel/ea-php8*/root/usr/bin/php 2>/dev/null
  # then use e.g. /opt/alt/php82/usr/bin/php instead of php
  ```

**Recommended PHP options** (same screen, "Options" tab): `upload_max_filesize` at least `12M`, `post_max_size` at least `16M`, `memory_limit` at least `128M`.

**HTTPS is required** for voice recording, installing the app on phones, and offline support:
- **cPanel → Security → SSL/TLS Status → Run AutoSSL** (if the domain is not already green).
- **cPanel → Domains → Domains → Force HTTPS Redirect → On.**

---

## Path A: Upgrade an existing Oneiros site

Your site lives in `~/public_html` (adjust the path if Oneiros is in a subfolder or an addon domain folder).

### A1. Back up everything (do not skip)

Your database details are in `~/public_html/includes/config.php` (`db_name`, `db_user`, `db_pass`).

```bash
cd ~
mkdir -p backups
tar -czf backups/public_html-$(date +%F-%H%M).tar.gz public_html
mysqldump --no-tablespaces -u DB_USER -p DB_NAME > backups/oneiros-db-$(date +%F-%H%M).sql
ls -lh backups
```

`mysqldump` asks for the database password. Both backup files must have a size greater than zero before you continue.

### A2. Upload and unpack the release

1. **cPanel → Files → File Manager**, open your home folder (the one that *contains* `public_html`, not `public_html` itself).
2. **Upload** `oneiros-v2.1.0-cpanel.zip`.
3. In Terminal:

```bash
cd ~
rm -rf oneiros-release
unzip -q oneiros-v2.1.0-cpanel.zip -d oneiros-release
ls oneiros-release          # you should see api/, assets/, includes/, oneiros.php …
```

### A3. Put the new files in place

The release brings a new `.htaccess`. If cPanel's **MultiPHP Manager** has written a PHP-version block into your current one, these commands keep it:

```bash
sed -n '/BEGIN cPanel-generated handler/,/END cPanel-generated handler/p' ~/public_html/.htaccess > ~/php-handler.txt
cp -a ~/oneiros-release/. ~/public_html/
[ -s ~/php-handler.txt ] && { echo; cat ~/php-handler.txt; } >> ~/public_html/.htaccess
rm -f ~/public_html/diagnostic.php          # old diagnostics page, no longer used
ls ~/public_html/includes/config.php        # must still exist: your live settings
```

(If you are unsure, open **MultiPHP Manager** afterwards and select PHP 8.2 or newer for the domain again; cPanel rewrites the block.)

### A4. Update the database and check the installation

```bash
cd ~/public_html
php tools/console.php migrate
php tools/console.php check
```

You should see:

```
Schema updated. Existing accounts, dreams and messages were retained.
OK: PHP 8.x, required extensions, database connection, 18 tables, upload permissions, and configuration.
```

`migrate` only adds what is missing, so it is safe to run more than once. If `check` reports a problem, see **Troubleshooting** below.

### A5. Permissions

```bash
cd ~/public_html
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
mkdir -p uploads/dreams uploads/audio uploads/paintings
```

### A6. Open the site

Visit `https://YOURDOMAIN/`. The new version installs itself in visitors' browsers automatically; if you still see the old design, do one hard refresh (Ctrl + Shift + R, or close and reopen the app on a phone).

Then run the **Verification checklist** at the end of this guide.

---

## Path B: Fresh install

### B1. Create the database

1. **cPanel → Databases → Manage My Databases** (also called **MySQL® Databases**).
2. Create a database, for example `oneiros`. cPanel shows the full name with your username in front, e.g. `CPANELUSER_oneiros`. Write it down.
3. Create a database user with a strong password. Write down the full user name and the password.
4. **Add User To Database**, tick **ALL PRIVILEGES**, save.

### B2. Upload and unpack

Upload `oneiros-v2.1.0-cpanel.zip` to your home folder with File Manager, then:

```bash
cd ~
unzip -q oneiros-v2.1.0-cpanel.zip -d oneiros-release
cp -a ~/oneiros-release/. ~/public_html/
```

To install in a subfolder instead (for example `https://YOURDOMAIN/journal`), copy into `~/public_html/journal/` and use that folder in every step below.

### B3. Create your configuration

```bash
cd ~/public_html
cp includes/config.example.php includes/config.php
php -r 'echo bin2hex(random_bytes(48)), PHP_EOL;'   # run twice: two different secrets
nano includes/config.php
```

In the editor, fill in:

| Setting | Value |
|---|---|
| `db_name`, `db_user`, `db_pass` | from step B1 |
| `jwt_secret` | the first random string |
| `jwt_refresh_secret` | the second, different random string |
| `frontend_url` | your full site address, e.g. `https://oneiros.co.za`, **including the subfolder** if you used one (e.g. `https://example.co.za/journal`), with no slash at the end |
| `mail_from` | an address on your domain (only used if you enable email later) |

Save with **Ctrl + O**, **Enter**, then exit with **Ctrl + X**.

### B4. Install the database tables

```bash
php tools/console.php install
php tools/console.php check
```

`install` only works on an empty database, which protects an existing site from being overwritten. Then set permissions exactly as in step **A5**.

### B5. Make yourself the administrator

1. Open your site and register an account normally.
2. In Terminal:
   ```bash
   cd ~/public_html
   php tools/console.php admin YOU@YOURDOMAIN
   ```
3. Sign out and back in. The research console is at `/oneiros-admin.php` and the moderation studio at `/oneiros-moderation.php`.

(Run the same `admin` command after an upgrade if your account is not yet an administrator.)

---

## Optional features

### AI painting (paid, optional)

Every dreamer can already create a **Dream Canvas**, a painting generated for free in their own browser. To also offer AI paintings from OpenAI's image API, add your key to `includes/config.php`:

```php
'image_api_key' => 'sk-...',
'image_model'   => 'gpt-image-1',
```

Each painting is billed to your OpenAI account. Oneiros limits each dreamer to five AI paintings per hour and falls back to the Dream Canvas if the provider is unavailable. Leave the key empty to keep the feature free.

### Email

Email is off by default. To turn it on:

1. Create the sender mailbox in **cPanel → Email → Email Accounts** (for example `noreply@YOURDOMAIN`).
2. In `includes/config.php` set `'mail_from' => 'noreply@YOURDOMAIN'` and `'mail_enabled' => true`.
3. Add a cron job in **cPanel → Advanced → Cron Jobs**, every 10 minutes:
   ```
   cd /home/CPANELUSER/public_html && php tools/console.php mail >/dev/null 2>&1
   ```

### Search engines

`robots.txt` points search engines at `https://oneiros.co.za/sitemap.php`. If your domain is different:

```bash
cd ~/public_html && sed -i 's#https://oneiros.co.za#https://YOURDOMAIN#' robots.txt
```

---

## Selling Lucid passes (once-off payments)

Lucid is Oneiros' paid tier, sold as **once-off passes**: 7, 30, 90 or 365 nights. Nothing is a subscription. A pass simply ends on its date, and buying another adds nights to the time left. Dreamers can also:

- buy a pass **as a gift** (they receive a code to share)
- leave a once-off **"support Oneiros"** contribution
- redeem **gift and promo codes**
- earn **7 free nights for both people** when a friend they invite saves a first dream

Codes, gifts, invitations and manual grants work as soon as you upgrade. Card and EFT payments start when you add a payment gateway.

### What Lucid unlocks

| Free journal (always) | Lucid (once-off pass) |
|---|---|
| Unlimited dreams, Dream Canvas, voice notes up to 3 minutes | Voice notes up to 10 minutes, HD painting downloads |
| The 5 closest resonances for each dream | Every resonance |
| 3 new connection requests a week (accepting is always free) | Unlimited connection requests |
| Insights and 2 symbol reflections per dream | Deep insights (emotional tides, dream signs, word constellations) and every reflection |
| Wind-down page: intention, breathing guide, Night ocean soundscape | All six soundscapes, layering, sleep timer, reality-check reminders |
| Share cards, journal calendar, CSV export | Printable Dream Book, Aurora / Rosé / Eclipse themes, Lucid mark |

The free limits are enforced by the server, so they cannot be bypassed from the browser.

### 1. Your business details and prices

Add these lines to `includes/config.php`, just above the final `];`, and change them to suit you. Prices are in **cents** (R69 = `6900`):

```php
    'business_name'    => 'Oneiros',
    'business_email'   => 'hello@oneiros.co.za',
    'business_details' => 'Your (Pty) Ltd name · Reg number · City',
    'plans' => [
        'lucid-7'   => ['name' => 'Lucid Week',   'days' => 7,   'price_cents' => 2900],
        'lucid-30'  => ['name' => 'Lucid Month',  'days' => 30,  'price_cents' => 6900, 'featured' => true],
        'lucid-90'  => ['name' => 'Lucid Season', 'days' => 90,  'price_cents' => 16900],
        'lucid-365' => ['name' => 'Lucid Year',   'days' => 365, 'price_cents' => 49900],
    ],
    'support_amounts' => [2500, 5000, 10000],
    'referral_reward_days' => 7,
```

Everything is optional: leave a line out and the default shown above is used. Remove a pass from `plans` to stop selling it. Your business details appear at checkout and on every receipt.

> South African online sales fall under the ECT Act and the Consumer Protection Act, which expect your business details, prices, and cancellation and refund policy to be easy to find. The in-app Terms (linked from the landing page footer, the checkout and Profile → Membership) describe once-off passes and refunds in plain language. Have them reviewed by someone qualified before you launch.

### 2. PayFast (recommended for South Africa)

PayFast accepts cards, Instant EFT, SnapScan, Zapper and more.

1. Create and verify a merchant account at **payfast.co.za**.
2. In PayFast, open **Settings → Developer Settings**. Copy your **Merchant ID** and **Merchant Key**, and set a **passphrase** using only letters and numbers.
3. Add to `includes/config.php`:
   ```php
       'payfast_merchant_id'  => '10000000',
       'payfast_merchant_key' => 'your-merchant-key',
       'payfast_passphrase'   => 'YourPassphrase2026',
       'payfast_sandbox'      => false,
   ```
4. Check the setup in Terminal:
   ```bash
   cd ~/public_html && php tools/console.php payments
   ```
   It lists your passes and warns you about anything missing. PayFast is told where to send payment confirmations automatically with every payment, so there is nothing else to paste into PayFast.

**Test before going live.** Create a free sandbox account at **sandbox.payfast.co.za** (PayFast's shared public test merchant no longer accepts signed payments), put its credentials in `config.php` with `'payfast_sandbox' => true`, buy a pass with the sandbox test buyer, and check that it appears under **Revenue & codes** and on your profile. Then switch to your live credentials and `'payfast_sandbox' => false`.

### 3. Paystack (optional second gateway)

If you also want Paystack (cards, Apple Pay):

1. In Paystack, open **Settings → API Keys & Webhooks** and copy the **Secret Key** (`sk_live_…`, or `sk_test_…` for testing).
2. Add `'paystack_secret_key' => 'sk_live_…',` to `includes/config.php`.
3. Run `php tools/console.php payments` and paste the **Webhook URL** it prints into Paystack's Webhook URL field.

With both gateways configured, dreamers choose one at checkout.

### 4. Running Lucid day to day

Everything is in the admin **Observatory** (`/oneiros-admin.php`) → **Revenue & codes**:

- **Sales:** today, 7 and 30 days, all time, active passes, average order, and a 30-day chart.
- **Orders:** every checkout with its reference, buyer and status, and a CSV export for your accountant.
  - **Mark paid** is for a payment you can see in your PayFast or Paystack dashboard, or your bank, whose confirmation never reached the site. It delivers the pass.
  - **Record refund:** first refund the money in the gateway's dashboard, then press this. It removes the nights (or disables the gift code) that the order bought.
- **Grant Lucid:** add nights to anyone by email. Use it for EFT payments, competitions or goodwill.
- **Codes:**
  - *promo* codes give free nights (for example 100 codes worth 30 nights for a launch, or one code 500 people can use)
  - *discount* codes take a percentage off at checkout
  - *gift* codes are single-use

The same tools exist in Terminal:

```bash
php tools/console.php grant dreamer@example.com 30      # add 30 nights
php tools/console.php revoke dreamer@example.com        # end a pass
php tools/console.php codes promo 30 10                 # ten single-use 30-night codes
php tools/console.php codes discount 20 1 100           # one 20%-off code, usable 100 times
```

Dreamers find their purchases and printable receipts under **Profile → Membership**, alongside their invitation link. Receipts and gift codes are also emailed when email is enabled (see Optional features). A reminder appears in the app three days before a pass ends; nothing is ever charged automatically.

## Verification checklist

Run these after either path.

```bash
curl -s https://YOURDOMAIN/api/health
# {"status":"healthy","service":"Oneiros","version":"2.0.0",…}
curl -s -o /dev/null -w "%{http_code}\n" https://YOURDOMAIN/includes/config.php
# 403  (the configuration must never be downloadable)
```

Then in a browser:

- [ ] The landing page shows the moonlit hero with drifting stars.
- [ ] You can sign in (or register), and the dashboard greets you.
- [ ] Save a test dream as **Private**; it appears in **Journal** with its own painting.
- [ ] Open the dream, edit its title, and save.
- [ ] **Insights** shows your emotional palette.
- [ ] **Dream art → Create a dream canvas** paints an image, and saving attaches it.
- [ ] On your phone the bottom navigation appears and voice recording asks for the microphone (HTTPS only).
- [ ] `/oneiros-admin.php` shows the Observatory charts for your admin account.
- [ ] **Revenue & codes** opens. With payments configured, a sandbox (or small real) purchase appears there and on your profile with a receipt.
- [ ] Create a promo code, redeem it from **Profile → Membership**, and see the Lucid mark appear.
- [ ] Delete the test dream when you are done.

---

## Troubleshooting

| What you see | What to do |
|---|---|
| `check` says *Configure a unique random jwt_secret…* | Generate two new secrets (step B3) and put them in `config.php`. Everyone will need to sign in again. |
| `check` says *Database operation failed* | Re-check `db_name`, `db_user`, `db_pass` in `config.php`; make sure the user was added to the database with ALL PRIVILEGES. |
| `check` says *uploads/… must be writable by PHP* | `chmod 755 ~/public_html/uploads ~/public_html/uploads/*` |
| `check` says *PHP 8.2 or later is required* | See step 0: select PHP 8.2+ or use the full path to the newer PHP binary. |
| A toast says *Server error — check cPanel error log* | Look at `~/public_html/error_log` (or **cPanel → Metrics → Errors**). The newest lines explain the problem. |
| Images or recordings do not load | `frontend_url` in `config.php` must match your real address, including any subfolder. |
| Image uploads fail | Raise `upload_max_filesize` to `12M` and `post_max_size` to `16M` (step 0). |
| Voice recording is unavailable | The site must be served over HTTPS (step 0). |
| The old design still shows | Hard refresh once (Ctrl + Shift + R). On an installed phone app, close it fully and reopen. |
| "Online payments are not open yet" | No gateway is configured. Add PayFast or Paystack keys (see Selling Lucid) and run `php tools/console.php payments`. |
| A payment went through but the pass did not open | The confirmation could not reach your site. Check the site is on HTTPS and publicly reachable, and that the PayFast passphrase in `config.php` matches PayFast exactly. Then use **Mark paid** on the order. Every confirmation received is logged in the `payment_events` table. |
| PayFast shows "signature does not match" | The passphrase, merchant ID or key in `config.php` differs from PayFast's settings, or sandbox credentials are being used with `'payfast_sandbox' => false` (or the other way round). |
| 500 error on every page right after uploading | A PHP version below 8.2 is active for the domain. Select PHP 8.2+ again in **MultiPHP Manager** or **Select PHP Version**. |

## Rolling back

If anything goes wrong during an upgrade, restore the backups from step A1:

```bash
cd ~
rm -rf public_html && mkdir public_html
tar -xzf backups/public_html-DATE.tar.gz
mysql -u DB_USER -p DB_NAME < backups/oneiros-db-DATE.sql
```

(Replace `DATE` with the date in the backup file names shown by `ls backups`.)
