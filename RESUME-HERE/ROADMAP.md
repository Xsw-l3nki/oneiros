# Roadmap

## Where we are (2026-09-28)

- v2.1.0 was live on Afrihost. New registrations were failing (LESSONS L1).
- v2.2.0 is built and tested locally on PHP 8.5 + MariaDB (branch `OneirosV2`), **not yet deployed**:
  sign-up fix, Console, settings engine, staff roles, audit log, feature switches, deprecation cleanup.

## Next steps (in order)

1. **Owner: security.** Rotate the database password and both JWT secrets, and make the GitHub repo
   private (LESSONS L2).
2. **Owner: deploy v2.2.0.** Build the zip, upload, run `php tools/console.php migrate` (or Console →
   Health → Run database migrations), then sign in at `/oneiros-console.php`.
3. **Confirm the sign-up fix on the live server** (register a test account; check Console → Health).
   Close L1 once confirmed.
4. Owner reviews the Console and says if moderators should be able to change more or less.
5. Merge `OneirosV2` into `main` once the owner is happy.

## Ideas not yet agreed (ask per R1 before building)

- Password-reset-by-email flow. There is none yet, so the Console can't send reset links.
- Console access to revenue, orders and codes. These live in the Observatory for now; the Console links there.
- Moving the Observatory's "grant Lucid" and email campaign features fully into the Console.
- Tidy `diagnostic.php` deprecations (L3) and the duplicate referral index (L9).
- Uptime monitor on `/api/health` (external service, owner's choice).
- Two-factor sign-in for staff accounts.

## Open questions for the owner

None open.

## Answered

- **Q1 (2026-09-28):** The Node/Supabase backend at the repo root was unused. Owner: delete it. It was
  removed in CP-004 and is still in git history (commit `7068e3a`) if ever needed.
- **Q2 (2026-09-28):** Should moderators switch features off during an incident? Owner: yes. The four
  non-financial switches moved to the `operations` tier (RULES R6).
