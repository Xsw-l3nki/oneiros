# Checkpoints

A checkpoint records a known state you can resume from. Newest at the bottom. Format:
id, date, branch and commit, state, how to verify, and what's next.

## CP-001: PHP app imported (2026-09-28)
- Branch `OneirosV2`, commit `289205f`.
- The owner's local `Oneiros/` folder (v2.1.0, live on Afrihost) was added to the repo next to the older Node backend.
- Problem found: `includes/config.php` (live secrets) was committed (LESSONS L2).

## CP-002: Sign-up fix (2026-09-28)
- Branch `OneirosV2`, commit `4f9acb2`.
- mbstring fallbacks; `api/health.php` reports missing extensions; `config.php` untracked.
- Verify: `GET /api/health` → `"status":"healthy"` and empty `missing_required`.

## CP-003: v2.2.0 Console (2026-09-28)
- Branch `OneirosV2`, commit `fceb41a`.
- Adds the Console (`/oneiros-console.php`), the settings engine (`includes/settings.php`), roles
  and permissions (`includes/staff.php`), the audit log, feature switches, maintenance mode, the migrator,
  the mailer settings fix and the RESUME-HERE docs.
- Verified locally on PHP 8.5.11 + MariaDB 11.8: `tests/api-smoke.py` (33 checks) and
  `tests/console-test.py` (60 checks) pass, and the Console UI was clicked through in headless Chromium.
- **Not yet deployed or verified on the live server.**
- Next: ROADMAP → Next steps 1–3.

## CP-004: Node backend removed; moderators can switch features (2026-09-28)
- Branch `OneirosV2`. The commit is listed in AUDIT-LOG.md.
- Deleted the unused Node/Supabase backend at the repo root (owner answer to ROADMAP Q1).
- Moderators can now switch connections, image uploads, voice notes and public research on and off (Q2, RULES R6).
- Verified: `tests/console-test.py` 64/64 on PHP 8.5.11 + MariaDB.
- Still **not deployed**. Next: ROADMAP → Next steps 1–3.
