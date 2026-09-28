# Rules

Standing rules set by the project owner. Every AI assistant and developer follows them in
every session. Refer to them by id (for example "per R1"). To change a rule, the owner must say
so explicitly. Record the change in AUDIT-LOG.md with the date.

## Working with the owner

**R1: Ask before building.** Before building any feature, redesign or behaviour change, ask
clarifying questions to confirm alignment, give a recommendation, and wait for the answers.
Agreed bug fixes and urgent security hygiene may proceed. Say what you are doing.
*(Owner, 2026-09-28: "always ask clarity questions before build to ensure alignment".)*

**R2: Keep RESUME-HERE current.** Every session reads this folder first and updates it before
ending: checkpoints, audit log, lessons learned, roadmap. Any AI must be able to pick up and
continue with no other context. *(Owner, 2026-09-28.)*

**R3: Every change is logged.** Each commit gets a line in AUDIT-LOG.md. Staff actions on
the live site are logged automatically in the database `audit_log` table (shown in Console →
Audit log); never add a code path that changes data from the Console or Observatory without
calling `Staff::audit()`.

**R4: Checkpoints.** After each meaningful milestone (a release, a fix deployed, a decision
made) add a numbered checkpoint (CP-nnn) to CHECKPOINTS.md describing the state, so work can
resume from it.

## The Console (admin panel)

**R5: Everything is configurable from the Console.** Every feature and capability must have
its settings in the Console (`includes/settings.php` registry): on/off switches, limits,
prices, messages and keys. Every health check and status must show in Console → Health or
Overview, and every user-management capability must be in Console → Users/Staff. When you add
a feature, add its setting, switch, health check and audit logging in the same change.
*(Owner, 2026-09-28: "admin panel must have all settings for every feature and capability
configurable, including health checks, statuses, user management capabilities, everything.")*

**R6: Role limits.** There are two staff roles:
- **Admin** can do everything.
- **Moderator** gets enough to run the site day to day, without changing core or financial
  settings. Moderators can view health and status, manage users (suspend, reactivate, sign
  out), moderate reports, process email and change the *Operations* and *Features* settings only. They never
  see financial settings, revenue, orders or payment keys, and cannot manage staff.
- Moderators may also switch the **non-financial features** on and off (connections, image uploads,
  voice notes, public research) so they can pause one during an incident. *(Owner, 2026-09-28.)*
  Payments, codes and AI painting are financial, so only admins can switch them.

The permission table in `includes/staff.php` (`Staff::PERMISSIONS`) and the setting tiers in
`includes/settings.php` are the only place these limits live. Admins add moderators from
Console → Staff. *(Owner, 2026-09-28.)*

**R7: Secrets.** Payment and API keys are editable in the Console (owner decision,
2026-09-28). They are encrypted at rest, and the API never sends them to a browser (only
"set/not set" and the last 4 characters). They never appear in the audit log. Bootstrap
secrets stay in `includes/config.php`: the database login and the JWT signing secrets.

## Code and repository

**R8: Never commit secrets or private data.** Never commit `includes/config.php`,
`includes/config.local.php`, `includes/.settings-key`, `error_log` files, `uploads/`, `.env`, or
release `.zip` files. Check `git status` and the staged file list before every commit. The
GitHub repo is currently public (see LESSONS L2).

**R9: Match the host.** The live server runs PHP 8.5 on Afrihost cPanel with MySQL/MariaDB and
no Composer, Node or build step. Code must run there. Test on PHP 8.5 (see CODEBASE-MAP →
Testing). Never assume an extension exists (LESSONS L1). Use fallbacks, and a health check
that names what is missing.

**R10: Don't break data.** Schema changes go in `sql/schema.sql` (`CREATE TABLE IF NOT
EXISTS`) plus `includes/migrator.php` for new columns on existing tables, so migrations are
safe to run repeatedly and never drop data.

**R11: Git.** Work on a branch (currently `OneirosV2`), not `main`. Never force-push or rewrite
history without the owner's explicit say-so. Commit messages say what changed and why.

**R12: Match the surrounding code.** Plain PHP classes in `includes/`, one endpoint file per
route in `api/`, JSON responses through `Helpers::respond()`, and no frameworks. Comments
explain *why*, sparingly.

**R13: Report honestly.** If tests fail, say so with the output. If something was not tested
(for example on the live server), say so. Never claim a fix is confirmed until it is.
