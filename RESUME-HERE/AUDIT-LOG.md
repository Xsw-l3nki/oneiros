# Audit log (repository)

Every change to this repository, newest first. One entry per commit: date, commit, who, what, and why.
This log covers **code and docs**. Actions staff take on the live site (settings changes,
suspensions, role changes) are logged automatically in the database and shown in
Console → Audit log.

| Date | Commit | Who | What |
|------|--------|-----|------|
| 2026-09-28 | *(this commit)* | Claude (AI), owner decision | Deleted the Node/Supabase backend (`src/`, `supabase/`, `package*.json`, `tsconfig.json`, `railway.json`, `.env.example`); new root README and `.gitignore`; `Oneiros/dist/` ignored. Moderators may switch the non-financial features (`includes/settings.php` tiers, RULES R6). Tests extended (64 checks). |
| 2026-09-28 | `fceb41a` | Claude (AI), approved by owner | **v2.2.0 Console.** New `oneiros-console.php` + `api/console/*` (overview, health, settings, users, staff, moderation, email, audit). `includes/settings.php` (layered config, Console registry, encrypted secrets), `includes/staff.php` (roles/permissions, `Staff::audit`), `includes/migrator.php`. Tables `app_settings`, `audit_log`. Feature switches wired into register, connections, messages, uploads, voice notes, research, payments, codes, AI painting; maintenance-mode gate in `cors.php`. Observatory/Care Studio writes now audited; Observatory delete now removes files. Mailer uses email settings. PHP 8.4/8.5 deprecations fixed in `api.php`/`db.php`. Version 2.2.0, deploy guide and README updated. Tests: `tests/console-test.py`. Docs: `RESUME-HERE/`, `CLAUDE.md`, `AGENTS.md`, `.github/copilot-instructions.md`. Owner decisions: new separate console; secrets editable in the Console; moderators limited (no core/financial); docs in RESUME-HERE. |
| 2026-09-28 | `4f9acb2` | Claude (AI) | Sign-up fix: mbstring fallbacks in `helpers.php`; `api/health.php` reports missing extensions; untracked `includes/config.php`. |
| 2026-09-28 | `289205f` | Claude (AI), owner request | Imported the owner's `Oneiros/` PHP app (v2.1.0) with a `.gitignore`. **Mistake:** included `includes/config.php` (LESSONS L2). |
| (before) | `7068e3a` | Owner | "OneIros backend v1.0", the Node/Supabase backend at the repo root. |
