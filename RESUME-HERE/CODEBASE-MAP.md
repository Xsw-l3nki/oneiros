# Codebase map

## Repository layout

```
/                         GitHub: Xsw-l3nki/oneiros (branch OneirosV2)
├── RESUME-HERE/          project memory — read first (this folder)
├── CLAUDE.md, AGENTS.md  pointers for AI tools → RESUME-HERE
├── README.md             short pointer to the app and to RESUME-HERE
└── Oneiros/              the product: PHP app for Afrihost cPanel (v2.2.0)

(An older Node.js + Supabase backend "v1.0" lived at the root until CP-004. It was deleted at the
owner's request and is still in git history at commit 7068e3a.)
```

## The PHP app (`Oneiros/`)

Plain PHP 8.2+ (host runs 8.5) + MySQL/MariaDB. No Composer, no build step. The app is also
described in `Oneiros/README.md` (features) and `Oneiros/DEPLOY-AFRIHOST.md` (deploying).

| Path | What it is |
|------|-----------|
| `oneiros.php` | the dreamer app (single page), JS in `assets/app.js`, `experience.js`, `lucid.js` |
| `oneiros-console.php` | **Console**: staff control room (v2.2), JS `assets/console.js`, CSS `assets/console.css` |
| `oneiros-admin.php` | Observatory: research charts, revenue, orders, codes (admins) |
| `oneiros-moderation.php` | Care Studio: full moderation workspace (moderators + admins) |
| `api.php` | API front controller; routes `/api/<route>` (or `api.php?_route=` without mod_rewrite) to `api/**.php` |
| `api/console/*.php` | Console API: me, overview, health, settings, users, staff, audit, email |
| `api/admin/*.php` | Observatory/Care Studio API (older; now audit-logged) |
| `api/auth, dreams, connections, payments, research, …` | dreamer-facing API |
| `includes/settings.php` | **all runtime configuration** — layers, Console registry, validation, secret encryption |
| `includes/runtime-config.php` | `return Settings::config();`, which every file uses to read config |
| `includes/staff.php` | Console roles, `Staff::PERMISSIONS`, `Staff::require()`, `Staff::audit()` |
| `includes/migrator.php` | schema check + safe migrations (used by CLI and Console → Health) |
| `includes/db.php` | PDO wrapper `Database::` (`tryPdo()` doesn't exit; `pdo()` responds 500) |
| `includes/auth.php` | JWT auth; `Auth::user()` re-reads the account every request (roles/suspension apply instantly) |
| `includes/helpers.php` | polyfills (mbstring fallbacks), `Helpers::respond()`, `Helpers::requireFeature()` |
| `includes/cors.php` | first include of every endpoint: CORS, **maintenance-mode gate** |
| `includes/premium.php`, `payments.php` | Lucid passes, codes, PayFast/Paystack |
| `includes/mailer.php` | email templates + queue; uses Console email settings |
| `sql/schema.sql` | complete schema (`CREATE TABLE IF NOT EXISTS`) |
| `tools/console.php` | CLI: check / install / migrate / admin / mail / grant / codes (cPanel Terminal) |
| `tools/build-release.py` | builds `dist/oneiros-v<version>-cpanel.zip` from an allowlist |
| `tests/api-smoke.py`, `tests/console-test.py`, `tests/payments-test.py` | integration tests (disposable DB only) |

### Configuration layers (lowest → highest priority)

1. `includes/config.example.php`: shipped defaults; **every key must have one here**
2. `includes/config.php`: server file (never committed): DB login, JWT secrets
3. `includes/config.local.php`: local development only
4. `app_settings` table: values saved in the Console (secrets AES-256-GCM encrypted)
5. `ONEIROS_*` environment variables, which lock a value (the Console shows it as locked)

Only keys in `Settings::definitions()` can be saved from the Console. Each has a **tier**:
`operations` (moderators may edit: maintenance, sign-ups, non-financial feature switches), `core` (admins),
`financial` (admins; hidden from moderators).

### Adding a feature the right way (R5)

1. Add its default to `config.example.php` (for example `feature_x => true`, limits, messages).
2. Register it in `Settings::definitions()` with group, tier, type, label and help.
3. Read it in code via `$cfg[...]` or `Helpers::requireFeature('x', 'message')`.
4. If it can fail or needs setup, add a check in `api/console/health.php`.
5. If staff can act on it, add an endpoint under `api/console/`, a permission in
   `Staff::PERMISSIONS`, and a `Staff::audit()` call for every change.
6. Expose it in `api/capabilities.php` if the dreamer app needs to adapt its UI.
7. Extend `tests/console-test.py`.

### Request flow

`/api/dreams` → `.htaccess` rewrite → `api.php` → `api/dreams/index.php` → `includes/cors.php`
(config, CORS, maintenance gate) → `Auth::require()` → work → `Helpers::respond()` JSON.

### Database tables

`users` (roles: `is_admin`, `is_moderator`; `is_active` = not suspended), `dreams`,
`dream_matches`, `connections`, `messages`, `notifications`, `moderation_flags`,
`recurring_dream_groups`, `research_events`, `refresh_tokens`, `user_badges`, `email_queue`,
`dream_audio`, `referrals`, `premium_orders`, `premium_codes`, `premium_redemptions`,
`payment_events`, **`app_settings`** (v2.2), **`audit_log`** (v2.2).

## Testing (PHP 8.5 + MariaDB in Docker, as used in the Codespace)

```bash
# one-time: database + PHP 8.5 image with pdo_mysql
docker run -d --name oneiros-db -e MARIADB_ROOT_PASSWORD=rootpw -e MARIADB_DATABASE=oneiros_test \
  -e MARIADB_USER=oneiros -e MARIADB_PASSWORD=testpw mariadb:11
printf 'FROM php:8.5-cli\nRUN docker-php-ext-install pdo_mysql\n' | docker build -t oneiros-php85 -
# env for every PHP container (config via environment, so no config file is touched)
ENVS="-e ONEIROS_DB_HOST=127.0.0.1 -e ONEIROS_DB_NAME=oneiros_test -e ONEIROS_DB_USER=oneiros \
  -e ONEIROS_DB_PASS=testpw -e ONEIROS_JWT_SECRET=<64 random chars> -e ONEIROS_JWT_REFRESH_SECRET=<64 other chars> \
  -e ONEIROS_FRONTEND_URL=http://localhost:8085"
# containers share the DB container's network (see LESSONS L8)
docker run --rm --network container:oneiros-db -v $PWD/Oneiros:/app -w /app $ENVS oneiros-php85 php tools/console.php install
docker run -d --name oneiros-web --network container:oneiros-db -v $PWD/Oneiros:/app -w /app $ENVS \
  oneiros-php85 php -S 127.0.0.1:8085 tools/dev-router.php
docker run --rm --network container:oneiros-db -v $PWD/Oneiros:/app -w /app python:3.12-slim \
  python tests/api-smoke.py http://127.0.0.1:8085 --allow-write-tests
# console tests need an admin: register one, then `php tools/console.php admin that@email`, then
#   ONEIROS_TEST_ADMIN_EMAIL=… ONEIROS_TEST_ADMIN_PASSWORD=… python tests/console-test.py http://127.0.0.1:8085 --allow-write-tests
```
