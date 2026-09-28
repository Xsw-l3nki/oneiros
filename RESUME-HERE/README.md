# RESUME HERE — read this first

This folder is the memory of the Oneiros project. Any AI assistant or developer starting a
new session reads it **before touching code**, and updates it **before ending** the session.
It exists so a new chat never loses context, never repeats a solved mistake, and follows the
owner's rules.

## Reading order

| # | File | What it gives you |
|---|------|-------------------|
| 1 | [RULES.md](RULES.md) | The owner's standing rules (R1, R2, …). Non-negotiable. Cite them by id. |
| 2 | [CHECKPOINTS.md](CHECKPOINTS.md) | Where we are: the newest checkpoint is the current state. |
| 3 | [ROADMAP.md](ROADMAP.md) | Where we are going, what is next, and open questions for the owner. |
| 4 | [LESSONS-LEARNED.md](LESSONS-LEARNED.md) | Mistakes already made (L1, L2, …) and how to avoid them. |
| 5 | [CODEBASE-MAP.md](CODEBASE-MAP.md) | How the code is organised and how a request flows. |
| 6 | [AUDIT-LOG.md](AUDIT-LOG.md) | Every change made to the repo, newest first, with commit ids. |

## Current state in one paragraph

*(Keep this paragraph current. Last updated 2026-09-28, checkpoint CP-003.)*

Oneiros is a dream-journal web app. The **live product is the PHP app in `Oneiros/`**, hosted
on Afrihost cPanel (PHP 8.5, MySQL/MariaDB). Version **2.2.0** adds the staff **Console**
(`/oneiros-console.php`): health checks, statuses, every feature setting, user management,
staff roles and an audit log. Admins can do everything. Moderators can run the site day to day
but can't change core or financial settings. Work happens on branch `OneirosV2` of
`github.com/Xsw-l3nki/oneiros`. The Node/Supabase backend at the repo root is an older v1.0;
whether it is still needed is an open question (see ROADMAP Q1). **Security to-do for the
owner:** rotate the database password and signing secrets leaked in commit `289205f` and make
the repository private (see LESSONS L2).

## Session protocol (see RULES R1–R4)

**Start of session**
1. Read the files above in order.
2. Tell the owner, in a few lines, the current state and what you understand the next step to be.
3. Ask clarifying questions before building anything new (R1). Wait for answers.

**End of session (or after any meaningful change)**
1. Add a checkpoint to CHECKPOINTS.md if the state changed.
2. Add entries to AUDIT-LOG.md for every commit.
3. Add any new mistake or surprise to LESSONS-LEARNED.md.
4. Update ROADMAP.md (done / next / open questions) and the paragraph above.
5. Commit the docs with the code they describe.

## Where rules are also referenced

`CLAUDE.md`, `AGENTS.md` and `.github/copilot-instructions.md` at the repo root point AI tools
here automatically. If you add another tool's instruction file, point it here too. Don't copy the
rules into it, because copies drift out of date.
