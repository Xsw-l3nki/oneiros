# Lessons learned

Mistakes and surprises, so nobody repeats them. Newest at the bottom. Each has an id (Ln),
what happened, why, and the rule to follow now.

**L1: A missing PHP extension broke sign-up (v2.x, found 2026-09-28).**
v2 used `mb_substr`/`mb_strlen` in sign-up, dreams and messages. On a PHP build without
`mbstring`, calling them is a fatal error, so new registrations failed with a bare server
error while login (which doesn't use them) worked.
→ `includes/helpers.php` now has fallbacks for every `mb_*` function we use, and `api/health.php`
and Console → Health name missing extensions. **Rule:** before using any extension function,
check it is in the health check list and has a fallback or a clear error. See R9.
*Not yet confirmed on the live server; the owner needs to deploy and test.*

**L2: Live secrets were committed to a public repo (2026-09-28).**
The first import (commit `289205f`) included `Oneiros/includes/config.php`, which holds the live
database password and JWT signing secrets, and the GitHub repo is public. The file was untracked
in `4f9acb2` and gitignored, but it is still in history.
→ The owner must **rotate the DB password and both JWT secrets** and **make the repo private**.
Rewriting history needs a force-push and owner approval (R11); rotation makes the leaked values
useless anyway. **Rule:** read `.gitignore` and the staged file list before the first commit of
any imported folder. See R8.

**L3: Deprecated constants flood the error log on PHP 8.4/8.5.**
`E_STRICT` (api.php) and `PDO::MYSQL_ATTR_INIT_COMMAND` (db.php) are deprecated, which put
~1,000 lines into `error_log`. Fixed in 2.2.0: `Pdo\Mysql::ATTR_INIT_COMMAND` when available,
`E_STRICT` removed. `diagnostic.php` still uses `curl_close()` and the old PDO constant
(harmless, noisy). **Rule:** test on the host's PHP version and read the log after.

**L4: The admin "delete user" left files behind.**
The Observatory delete removed the database row but not the account's uploaded images and audio.
The Console and the Observatory now both call `Media::purgeUser()`.

**L5: Config values that nothing read.**
`mail_from` and `mail_enabled` existed in config but the mailer ignored them (it hard-coded
`noreply@<host>` and always sent). Fixed in 2.2.0. **Rule:** when adding a setting to the
Console, verify the code actually reads it (grep for the key) and add a test that it takes
effect (see `tests/console-test.py`, which checks plans and sign-up switches reach the app).

**L6: `app_version` copied into server config files.**
`config.php` is created by copying the example, so it may carry an old `app_version` that would
override the code's version. `Settings::base()` now always takes the version from the shipped
`config.example.php`.

**L7: Encrypted Console secrets depend on a key file.**
Secrets saved in the Console are encrypted with `includes/.settings-key` (created on the first
save) or `settings_key` in config.php. If that file is lost (for example the folder is replaced
instead of overwritten on upgrade), those secrets can't be decrypted. Console → Health flags
this and the owner re-enters them. **Rule:** upgrades extract over the existing folder
(DEPLOY-AFRIHOST.md); never delete `includes/.settings-key`, and back it up with config.php.

**L8: Docker networking in the Codespace.**
Containers on a user-defined Docker network can't reach each other here (connection timeout).
Run the PHP and test containers with `--network container:oneiros-db` and connect to
`127.0.0.1`. See CODEBASE-MAP → Testing.

**L9: `migrate` adds a second unique index on a fresh install.**
`schema.sql` defines `users.referral_code UNIQUE` (index named `referral_code`) and the migrator
adds `uk_referral_code` too. Harmless but redundant; see ROADMAP.
