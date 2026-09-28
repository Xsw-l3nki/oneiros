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
4. Owner reviews the Console. Adjust the moderator's Operations tier if they should be able to change more or less.
5. Merge `OneirosV2` into `main` once the owner is happy.

## Ideas not yet agreed (ask per R1 before building)

- Password-reset-by-email flow. There is none yet, so the Console can't send reset links.
- Console access to revenue, orders and codes. These live in the Observatory for now; the Console links there.
- Moving the Observatory's "grant Lucid" and email campaign features fully into the Console.
- Tidy `diagnostic.php` deprecations (L3) and the duplicate referral index (L9).
- Uptime monitor on `/api/health` (external service, owner's choice).
- Two-factor sign-in for staff accounts.

## Open questions for the owner

- **Q1:** Is the Node/Supabase backend at the repo root (`src/`, v1.0) still used anywhere, or can it be
  archived? Keep it untouched until answered.
- **Q2:** Should moderators be able to switch individual features (connections, uploads) off during an
  incident, or should that stay admin-only? Currently admin-only (core tier).
