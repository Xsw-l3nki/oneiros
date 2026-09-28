<?php
// Oneiros Console — staff control room. Every action is checked again by the API (includes/staff.php).
require_once __DIR__ . '/includes/security.php';
Security::headers();
header('X-Robots-Tag: noindex, nofollow');
if (!Security::rateLimit('console_page_' . Security::ipKey(), 30, 60)) {
    http_response_code(429);
    exit('Too many requests');
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Oneiros Console</title>
<link rel="icon" href="assets/oneiros-icon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/console.css?v=2.2.0">
<script src="assets/console.js?v=2.2.0" defer></script>
</head>
<body>

<main id="login-screen" class="login" hidden>
  <form class="login-card" id="login-form" novalidate>
    <h1>Oneiros Console</h1>
    <p class="muted">For admins and moderators. Sign in with your usual Oneiros account.</p>
    <label for="login-email">Email</label>
    <input id="login-email" type="email" autocomplete="username" required>
    <label for="login-password">Password</label>
    <input id="login-password" type="password" autocomplete="current-password" required>
    <p id="login-error" class="error" role="alert"></p>
    <button type="submit" class="btn primary">Sign in</button>
  </form>
</main>

<div id="app" hidden>
  <header class="topbar">
    <button class="menu-btn" id="menu-btn" aria-label="Open menu" aria-expanded="false">☰</button>
    <strong class="brand">Oneiros Console</strong>
    <span id="env-badge" class="badge"></span>
    <span class="spacer"></span>
    <span id="who" class="muted small"></span>
    <button class="btn ghost small" id="logout-btn">Sign out</button>
  </header>
  <nav class="sidebar" id="sidebar" aria-label="Console sections">
    <a href="#overview" data-section="overview">Overview</a>
    <a href="#health" data-section="health">Health</a>
    <a href="#settings" data-section="settings">Settings</a>
    <a href="#users" data-section="users">Users</a>
    <a href="#staff" data-section="staff" data-perm="staff.manage">Staff</a>
    <a href="#moderation" data-section="moderation" data-perm="moderation.manage">Moderation</a>
    <a href="#email" data-section="email" data-perm="email.process">Email</a>
    <a href="#audit" data-section="audit">Audit log</a>
    <hr>
    <a href="oneiros-admin.php" data-perm="finance.view" target="_blank" rel="noopener">Observatory (research, revenue, codes) ↗</a>
    <a href="oneiros-moderation.php" target="_blank" rel="noopener">Care Studio ↗</a>
    <a href="oneiros.php" target="_blank" rel="noopener">Open the app ↗</a>
  </nav>
  <main class="content" id="content" tabindex="-1">
    <section id="section-overview" hidden></section>
    <section id="section-health" hidden></section>
    <section id="section-settings" hidden></section>
    <section id="section-users" hidden></section>
    <section id="section-staff" hidden></section>
    <section id="section-moderation" hidden></section>
    <section id="section-email" hidden></section>
    <section id="section-audit" hidden></section>
  </main>
</div>

<dialog id="dialog" aria-labelledby="dialog-title">
  <form method="dialog" class="dialog-body" id="dialog-form">
    <h2 id="dialog-title"></h2>
    <div id="dialog-content"></div>
    <p id="dialog-error" class="error" role="alert"></p>
    <div class="dialog-actions" id="dialog-actions"></div>
  </form>
</dialog>
<div id="toast" class="toast" role="status" aria-live="polite" hidden></div>
</body>
</html>
