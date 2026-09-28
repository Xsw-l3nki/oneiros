# Oneiros 2.1 · Nocturne + Lucid

A home for your dreams. Dreamers keep a private journal, discover people around the world who dreamed something similar, and see the patterns in their own nights. PHP 8.2 + MySQL, built for shared cPanel hosting. No build step, no Node, no Composer.

**To deploy, follow [DEPLOY-AFRIHOST.md](DEPLOY-AFRIHOST.md).**

## What's new in 2.1: Lucid

- **Once-off Lucid passes** (7, 30, 90, 365 nights, never recurring) through **PayFast** (card, Instant EFT, SnapScan, Zapper) and optionally **Paystack**. Passes stack, show a reminder three days before ending, and come with printable and emailed receipts.
- **More ways to earn**: gift passes (the buyer receives a shareable code), a once-off "support Oneiros" tip jar with a Patron mark, promo codes (free nights), discount codes (% off at checkout), and invite rewards (both friends receive 7 nights after the friend's first dream).
- **Free vs Lucid**, enforced on the server: free dreamers see their 5 closest resonances (with a "3 more resonated with you" teaser), send 3 connection requests a week, and get a daily AI painting when that is configured.
- **Lucid features**:
  - deep insights: emotional tides, dream signs, word constellation, most resonant dreams
  - every symbol reflection
  - the full wind-down library with layering and a sleep timer
  - reality-check reminders
  - a printable Dream Book and HD painting downloads
  - Aurora, Rosé and Eclipse themes
  - 10-minute voice notes
- **New for everyone**:
  - a Wind-down page (tonight's intention, a 4-7-8 or box breathing guide, generated soundscapes)
  - guided recall prompts
  - symbol reflections
  - a journal calendar view
  - shareable dream cards
  - last night's intention check-in on Home
- **Admin**: an Observatory → *Revenue & codes* panel (sales, 30-day chart, orders with mark-paid/refund, CSV export, grants, code creation), plus console commands (`payments`, `grant`, `revoke`, `codes`).

## What's in 2.0

- **Nocturne design system**: a midnight, motion-rich interface with a cinematic moonlit hero, drifting star and light particles, pointer and scroll parallax, scroll-revealed storytelling, and a persistent *Pause motion* control that also honours the operating system's reduced-motion setting.
- **Dream Canvas**: every dream becomes a painting generated in the browser from its words, emotions and themes (moons, oceans, forests, cities, doorways, embers). Dreamers can attach one when saving, and journal entries without an image get their own painted cover. Optional server-side AI painting is available when an image API key is configured.
- **Journal**: search, filter by visibility or recurring dreams, and open any dream to edit its title, text and visibility, add or replace an image, see its resonances, or delete it (including stored files).
- **Insights**: emotional palette, returning themes, rhythm by weekday, symbols, and how your themes compare with the anonymous collective.
- **Connections that work end to end**: incoming requests appear anonymously (country and resonance score only) with Accept and Decline, conversations begin only after both agree, email addresses are never shared, and dreamers can block each other.
- **Honest notifications**: badges clear when the panel is opened, and optional browser alerts fire while Oneiros is open in a background tab.
- **Quality of life**: drafts are kept on the device, Ctrl/Cmd + Enter saves, dialogs are keyboard accessible, the layout works from 360px phones to wide desktops, and the app installs on phones (PWA).
- **Admin**: the research *Observatory* (`/oneiros-admin.php`) and moderation *Care Studio* (`/oneiros-moderation.php`) share the Nocturne theme, with Chart.js served locally.
- **Operations**: `tools/console.php` checks, installs and migrates the database from cPanel Terminal, and grants admin rights.

## Project layout

```
oneiros.php              the dreamer app (single page)
oneiros-admin.php        research observatory      oneiros-moderation.php   moderation studio
api.php                  API front controller (works with or without mod_rewrite)
api/                     endpoint handlers         includes/                shared PHP libraries + config
assets/base.css          structural styles         assets/dream.css         Nocturne theme
                                                   assets/lucid.css         Lucid styles (loads last)
assets/app.js            data flows and pages      assets/experience.js     motion, Dream Canvas, dialogs, insights
assets/lucid.js          Lucid passes and rituals  includes/premium.php     passes, codes, gifts, referrals
includes/payments.php    PayFast + Paystack        api/payments/            checkout, notifications, redeem
sql/schema.sql           complete schema           tools/console.php        check / install / migrate / admin / mail
```

Configuration lives in `includes/config.php` (copy `includes/config.example.php`). Values can also come from environment variables named `ONEIROS_DB_HOST`, `ONEIROS_DB_NAME`, `ONEIROS_JWT_SECRET` and so on. An optional `includes/config.local.php` overrides both for local development and must never be uploaded to the server.

## Local development

```bash
# PHP 8.2+ and a local MySQL/MariaDB database
cp includes/config.example.php includes/config.local.php   # point it at a disposable database
php tools/console.php install
php -S 127.0.0.1:8080 tools/dev-router.php
# open http://127.0.0.1:8080/oneiros.php
```

API integration checks (they create and delete test accounts, so use a disposable database only):

```bash
python tests/api-smoke.py http://127.0.0.1:8080 --allow-write-tests
```

Build the deployable ZIP (it leaves out configuration files, uploads, logs and development tools):

```bash
python tools/build-release.py        # writes dist/oneiros-v2.1.0-cpanel.zip
```

Payments integration checks run against a local server started with test PayFast credentials; see the header of `tests/payments-test.py`.
