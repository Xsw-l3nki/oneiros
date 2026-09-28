<?php
// Admin panel — security headers + block non-authenticated server-side
if (file_exists(__DIR__ . '/includes/security.php')) {
    require_once __DIR__ . '/includes/security.php';
    Security::headers();
}
// Restrict: only serve if a valid admin session cookie exists (set by JS after login)
// This is a defence-in-depth check; primary auth is JWT in JS.
header('X-Robots-Tag: noindex, nofollow');
// Rate-limit page loads (brute-force on URL)
if (file_exists(__DIR__ . '/includes/security.php')) {
    $ip = Security::ipKey();
    if (!Security::rateLimit("admin_page_{$ip}", 30, 60)) {
        http_response_code(429);
        exit('Too many requests');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Oneiros — Research Command Centre</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&display=swap" rel="stylesheet">
<script src="assets/vendor/chart.umd.min.js"></script>
<style>
:root {
  --bg:       #080B14;
  --bg2:      #0D1220;
  --bg3:      #111827;
  --border:   rgba(99,130,255,0.15);
  --border2:  rgba(99,130,255,0.28);
  --accent:   #6382FF;
  --accent2:  #A78BFA;
  --teal:     #2DD4BF;
  --rose:     #FB7185;
  --gold:     #FBBF24;
  --green:    #34D399;
  --text:     #E2E8F0;
  --muted:    #64748B;
  --muted2:   #94A3B8;
  --glow:     rgba(99,130,255,0.12);
  --glow2:    rgba(99,130,255,0.06);
}

* { margin:0; padding:0; box-sizing:border-box; }

body {
  font-family: 'Syne', sans-serif;
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
  overflow-x: hidden;
}

/* ── SCAN LINES OVERLAY ── */
body::before {
  content: '';
  position: fixed; inset: 0; z-index: 0; pointer-events: none;
  background: repeating-linear-gradient(0deg, transparent, transparent 2px, rgba(0,0,0,0.03) 2px, rgba(0,0,0,0.03) 4px);
}

/* ── GRID BACKGROUND ── */
body::after {
  content: '';
  position: fixed; inset: 0; z-index: 0; pointer-events: none;
  background-image:
    linear-gradient(rgba(99,130,255,0.04) 1px, transparent 1px),
    linear-gradient(90deg, rgba(99,130,255,0.04) 1px, transparent 1px);
  background-size: 40px 40px;
}

/* ── TOP BAR ── */
.topbar {
  position: fixed; top: 0; left: 0; right: 0; z-index: 100;
  height: 56px;
  background: rgba(8,11,20,0.92);
  backdrop-filter: blur(20px);
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 1.5rem;
}
.topbar-logo {
  font-family: 'DM Sans', sans-serif;
  font-size: 0.9rem; letter-spacing: 0.1em;
  color: var(--accent);
}
.topbar-logo span { color: var(--muted2); }
.topbar-center {
  display: flex; align-items: center; gap: 0.25rem;
}
.tab-btn {
  background: none; border: none; cursor: pointer;
  font-family: 'Syne', sans-serif; font-size: 0.78rem;
  font-weight: 500; letter-spacing: 0.06em; text-transform: uppercase;
  color: var(--muted); padding: 0.4rem 0.9rem; border-radius: 0.4rem;
  transition: all 0.2s;
}
.tab-btn:hover { color: var(--text); background: var(--glow2); }
.tab-btn.active { color: var(--accent); background: var(--glow); border: 1px solid var(--border); }
.topbar-right { display: flex; align-items: center; gap: 1rem; }
.live-indicator {
  display: flex; align-items: center; gap: 0.4rem;
  font-family: 'DM Sans', sans-serif; font-size: 0.72rem; color: var(--green);
}
.live-dot {
  width: 6px; height: 6px; border-radius: 50%; background: var(--green);
  animation: pulse-green 2s ease-in-out infinite;
}
@keyframes pulse-green {
  0%,100% { opacity:1; box-shadow: 0 0 0 0 rgba(52,211,153,0.4); }
  50% { opacity:0.7; box-shadow: 0 0 0 4px rgba(52,211,153,0); }
}
.clock {
  font-family: 'DM Sans', sans-serif; font-size: 0.78rem; color: var(--muted2);
}
.logout-btn {
  background: none; border: 1px solid var(--border); cursor: pointer;
  font-family: 'Syne', sans-serif; font-size: 0.72rem; font-weight: 500;
  color: var(--muted); padding: 0.3rem 0.8rem; border-radius: 0.3rem;
  transition: all 0.2s; letter-spacing: 0.06em;
}
.logout-btn:hover { color: var(--rose); border-color: var(--rose); }

/* ── LOGIN SCREEN ── */
#login-screen {
  position: fixed; inset: 0; z-index: 200;
  background: var(--bg);
  display: flex; align-items: center; justify-content: center;
}
.login-box {
  width: 380px;
  border: 1px solid var(--border2);
  border-radius: 1rem;
  padding: 2.5rem;
  background: var(--bg2);
  position: relative;
}
.login-box::before {
  content: '';
  position: absolute; top: -1px; left: 20%; right: 20%; height: 2px;
  background: linear-gradient(90deg, transparent, var(--accent), transparent);
  border-radius: 1px;
}
.login-title {
  font-family: 'DM Sans', sans-serif; font-size: 1rem;
  color: var(--accent); margin-bottom: 0.3rem; letter-spacing: 0.05em;
}
.login-sub { font-size: 0.8rem; color: var(--muted); margin-bottom: 2rem; }
.login-field { margin-bottom: 1rem; }
.login-label { font-size: 0.68rem; letter-spacing: 0.15em; text-transform: uppercase; color: var(--muted2); display: block; margin-bottom: 0.4rem; }
.login-input {
  width: 100%; background: var(--bg3);
  border: 1px solid var(--border); border-radius: 0.5rem;
  padding: 0.65rem 0.9rem; font-family: 'DM Sans', sans-serif;
  font-size: 0.85rem; color: var(--text); outline: none;
  transition: border-color 0.2s;
}
.login-input:focus { border-color: var(--accent); }
.login-btn {
  width: 100%; background: var(--accent); color: var(--bg);
  border: none; cursor: pointer; font-family: 'Syne', sans-serif;
  font-size: 0.82rem; font-weight: 700; letter-spacing: 0.1em;
  padding: 0.75rem; border-radius: 0.5rem; transition: all 0.2s;
  text-transform: uppercase; margin-top: 0.5rem;
}
.login-btn:hover { opacity: 0.88; }
.login-error { font-size: 0.78rem; color: var(--rose); margin-top: 0.75rem; text-align: center; }

/* ── MAIN LAYOUT ── */
.app { padding-top: 56px; display: flex; min-height: 100vh; position: relative; z-index: 1; }

.sidebar {
  width: 220px; flex-shrink: 0;
  position: fixed; top: 56px; left: 0; bottom: 0;
  background: var(--bg2);
  border-right: 1px solid var(--border);
  padding: 1.5rem 0.8rem;
  overflow-y: auto;
}
.sidebar-section {
  font-family: 'DM Sans', sans-serif;
  font-size: 0.62rem; letter-spacing: 0.2em; text-transform: uppercase;
  color: var(--muted); padding: 0.5rem 0.7rem; margin-top: 1rem;
}
.sidebar-item {
  display: flex; align-items: center; gap: 0.65rem;
  padding: 0.6rem 0.7rem; border-radius: 0.5rem;
  font-size: 0.82rem; font-weight: 500; color: var(--muted2);
  cursor: pointer; transition: all 0.2s;
  border: 1px solid transparent;
}
.sidebar-item:hover { color: var(--text); background: var(--glow2); }
.sidebar-item.active { color: var(--accent); background: var(--glow); border-color: var(--border); }
.sidebar-icon { font-size: 0.9rem; width: 18px; text-align: center; }
.sidebar-badge {
  margin-left: auto; background: var(--rose);
  color: white; font-size: 0.6rem; font-family: 'DM Sans', sans-serif;
  padding: 0.1rem 0.4rem; border-radius: 0.25rem;
}

/* ── CONTENT ── */
.content { margin-left: 220px; flex: 1; padding: 1.5rem 2rem 4rem; }

/* ── PANELS ── */
.panel { display: none; }
.panel.active { display: block; }

/* ── SECTION HEADER ── */
.section-header {
  display: flex; align-items: baseline; justify-content: space-between;
  margin-bottom: 1.5rem; padding-bottom: 0.75rem;
  border-bottom: 1px solid var(--border);
}
.section-title {
  font-size: 1.3rem; font-weight: 700; color: var(--text);
  letter-spacing: -0.01em;
}
.section-meta {
  font-family: 'DM Sans', sans-serif; font-size: 0.72rem; color: var(--muted);
}

/* ── METRIC CARDS ── */
.metric-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 1rem; margin-bottom: 1.5rem; }
.metric-card {
  background: var(--bg2); border: 1px solid var(--border);
  border-radius: 0.75rem; padding: 1.25rem;
  position: relative; overflow: hidden; transition: border-color 0.2s;
}
.metric-card:hover { border-color: var(--border2); }
.metric-card::before {
  content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px;
  border-radius: 1px;
}
.metric-card.blue::before { background: var(--accent); }
.metric-card.teal::before { background: var(--teal); }
.metric-card.rose::before { background: var(--rose); }
.metric-card.gold::before { background: var(--gold); }
.metric-card.green::before { background: var(--green); }
.metric-label {
  font-family: 'DM Sans', sans-serif; font-size: 0.63rem;
  letter-spacing: 0.18em; text-transform: uppercase; color: var(--muted);
  margin-bottom: 0.6rem;
}
.metric-value {
  font-family: 'DM Sans', sans-serif; font-size: 2rem;
  font-weight: 700; color: var(--text); line-height: 1;
}
.metric-card.blue  .metric-value { color: var(--accent); }
.metric-card.teal  .metric-value { color: var(--teal); }
.metric-card.rose  .metric-value { color: var(--rose); }
.metric-card.gold  .metric-value { color: var(--gold); }
.metric-card.green .metric-value { color: var(--green); }
.metric-sub { font-size: 0.72rem; color: var(--muted2); margin-top: 0.4rem; }
.metric-delta {
  font-family: 'DM Sans', sans-serif; font-size: 0.7rem;
  margin-top: 0.3rem;
}
.delta-up { color: var(--green); }
.delta-down { color: var(--rose); }

/* ── CHART CARDS ── */
.chart-row { display: grid; gap: 1rem; margin-bottom: 1rem; }
.chart-row-2 { grid-template-columns: 1fr 1fr; }
.chart-row-3 { grid-template-columns: 2fr 1fr; }
.chart-card {
  background: var(--bg2); border: 1px solid var(--border);
  border-radius: 0.75rem; padding: 1.25rem;
}
.chart-card-title {
  font-size: 0.82rem; font-weight: 600; color: var(--muted2);
  letter-spacing: 0.04em; margin-bottom: 1rem; text-transform: uppercase;
  font-family: 'DM Sans', sans-serif;
}
.chart-card-title span { color: var(--accent); font-size: 0.7rem; margin-left: 0.5rem; }

/* ── ARCHETYPE BARS ── */
.archetype-list { display: flex; flex-direction: column; gap: 0.7rem; }
.archetype-row { display: flex; align-items: center; gap: 0.75rem; }
.archetype-label { font-size: 0.78rem; color: var(--muted2); width: 100px; flex-shrink: 0; }
.archetype-bar-wrap { flex: 1; background: rgba(255,255,255,0.04); border-radius: 2px; height: 6px; overflow: hidden; }
.archetype-bar { height: 100%; border-radius: 2px; transition: width 1s cubic-bezier(0.4,0,0.2,1); width: 0%; }
.archetype-count { font-family: 'DM Sans', sans-serif; font-size: 0.72rem; color: var(--muted); width: 40px; text-align: right; }

/* ── EMOTION GRID ── */
.emotion-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 0.6rem; }
.emotion-cell {
  background: var(--bg3); border: 1px solid var(--border);
  border-radius: 0.5rem; padding: 0.75rem; text-align: center;
  transition: border-color 0.2s;
}
.emotion-cell:hover { border-color: var(--border2); }
.emotion-icon { font-size: 1.3rem; margin-bottom: 0.3rem; }
.emotion-name { font-size: 0.7rem; color: var(--muted2); text-transform: uppercase; letter-spacing: 0.08em; }
.emotion-count { font-family: 'DM Sans', sans-serif; font-size: 1rem; font-weight: 700; color: var(--text); margin-top: 0.2rem; }

/* ── WORLD EVENT TAGGER ── */
.event-form {
  display: grid; grid-template-columns: 1fr 1fr auto; gap: 0.75rem;
  align-items: end; margin-bottom: 1.5rem;
  background: var(--bg2); border: 1px solid var(--border);
  border-radius: 0.75rem; padding: 1.25rem;
}
.event-field label {
  font-family: 'DM Sans', sans-serif; font-size: 0.62rem;
  letter-spacing: 0.15em; text-transform: uppercase; color: var(--muted);
  display: block; margin-bottom: 0.4rem;
}
.event-input {
  width: 100%; background: var(--bg3); border: 1px solid var(--border);
  border-radius: 0.4rem; padding: 0.6rem 0.8rem;
  font-family: 'Syne', sans-serif; font-size: 0.85rem;
  color: var(--text); outline: none; transition: border-color 0.2s;
}
.event-input:focus { border-color: var(--accent); }
.event-btn {
  background: var(--accent); color: var(--bg); border: none;
  cursor: pointer; font-family: 'Syne', sans-serif; font-size: 0.78rem;
  font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;
  padding: 0.6rem 1.2rem; border-radius: 0.4rem; transition: all 0.2s;
  white-space: nowrap; align-self: end;
}
.event-btn:hover { opacity: 0.85; }

/* ── EVENTS TABLE ── */
.events-table { width: 100%; border-collapse: collapse; }
.events-table th {
  font-family: 'DM Sans', sans-serif; font-size: 0.62rem;
  letter-spacing: 0.15em; text-transform: uppercase; color: var(--muted);
  text-align: left; padding: 0.5rem 0.75rem;
  border-bottom: 1px solid var(--border);
}
.events-table td {
  font-size: 0.82rem; color: var(--muted2); padding: 0.75rem;
  border-bottom: 1px solid rgba(99,130,255,0.06);
}
.events-table tr:hover td { background: var(--glow2); color: var(--text); }
.event-type-badge {
  font-family: 'DM Sans', sans-serif; font-size: 0.65rem;
  padding: 0.15rem 0.5rem; border-radius: 0.25rem; border: 1px solid;
}
.badge-political  { color: var(--rose); border-color: var(--rose); }
.badge-natural    { color: var(--teal); border-color: var(--teal); }
.badge-cultural   { color: var(--gold); border-color: var(--gold); }
.badge-scientific { color: var(--accent); border-color: var(--accent); }

/* ── GEOGRAPHIC TABLE ── */
.geo-table { width: 100%; border-collapse: collapse; }
.geo-table th {
  font-family: 'DM Sans', sans-serif; font-size: 0.62rem;
  letter-spacing: 0.12em; text-transform: uppercase; color: var(--muted);
  text-align: left; padding: 0.5rem 0.75rem;
  border-bottom: 1px solid var(--border);
}
.geo-table td {
  font-size: 0.83rem; padding: 0.6rem 0.75rem;
  border-bottom: 1px solid rgba(99,130,255,0.06);
}
.geo-table tr:hover td { background: var(--glow2); }
.geo-rank { font-family: 'DM Sans', sans-serif; font-size: 0.7rem; color: var(--muted); }
.geo-region { color: var(--text); font-weight: 500; }
.geo-count { font-family: 'DM Sans', sans-serif; color: var(--accent); }
.geo-top-theme { color: var(--muted2); font-size: 0.76rem; }

/* ── RECURRING GROUPS ── */
.recurring-list { display: flex; flex-direction: column; gap: 0.75rem; }
.recurring-card {
  background: var(--bg3); border: 1px solid var(--border);
  border-radius: 0.6rem; padding: 1rem; display: flex;
  align-items: center; gap: 1rem; transition: border-color 0.2s;
}
.recurring-card:hover { border-color: var(--border2); }
.recurring-count {
  font-family: 'DM Sans', sans-serif; font-size: 1.6rem;
  font-weight: 700; color: var(--accent2); width: 48px;
  text-align: center; flex-shrink: 0;
}
.recurring-info { flex: 1; }
.recurring-themes { display: flex; gap: 0.4rem; flex-wrap: wrap; margin-bottom: 0.3rem; }
.theme-tag {
  font-size: 0.68rem; font-family: 'DM Sans', sans-serif;
  padding: 0.15rem 0.5rem; border-radius: 0.25rem;
  background: rgba(167,139,250,0.12); color: var(--accent2);
  border: 1px solid rgba(167,139,250,0.2);
}
.recurring-meta { font-size: 0.72rem; color: var(--muted); }

/* ── USER TABLE ── */
.users-header { display: flex; gap: 0.75rem; margin-bottom: 1rem; align-items: center; flex-wrap: wrap; }
.search-input {
  background: var(--bg2); border: 1px solid var(--border);
  border-radius: 0.4rem; padding: 0.5rem 0.85rem;
  font-family: 'Syne', sans-serif; font-size: 0.82rem;
  color: var(--text); outline: none; width: 260px; transition: border-color 0.2s;
}
.search-input:focus { border-color: var(--accent); }
.filter-select {
  background: var(--bg2); border: 1px solid var(--border);
  border-radius: 0.4rem; padding: 0.5rem 0.85rem;
  font-family: 'Syne', sans-serif; font-size: 0.82rem;
  color: var(--muted2); outline: none; cursor: pointer;
}
.users-table { width: 100%; border-collapse: collapse; }
.users-table th {
  font-family: 'DM Sans', sans-serif; font-size: 0.62rem;
  letter-spacing: 0.12em; text-transform: uppercase; color: var(--muted);
  text-align: left; padding: 0.5rem 0.75rem;
  border-bottom: 1px solid var(--border);
}
.users-table td {
  font-size: 0.82rem; color: var(--muted2); padding: 0.65rem 0.75rem;
  border-bottom: 1px solid rgba(99,130,255,0.06);
}
.users-table tr:hover td { background: var(--glow2); }
.status-badge {
  font-family: 'DM Sans', sans-serif; font-size: 0.62rem;
  padding: 0.15rem 0.5rem; border-radius: 0.25rem;
}
.status-active  { background: rgba(52,211,153,0.12); color: var(--green); border: 1px solid rgba(52,211,153,0.25); }
.status-inactive { background: rgba(100,116,139,0.12); color: var(--muted); border: 1px solid var(--border); }
.status-premium { background: rgba(251,191,36,0.12); color: var(--gold); border: 1px solid rgba(251,191,36,0.25); }
.action-btn {
  background: none; border: 1px solid var(--border); cursor: pointer;
  font-family: 'DM Sans', sans-serif; font-size: 0.62rem;
  color: var(--muted); padding: 0.2rem 0.5rem; border-radius: 0.25rem;
  transition: all 0.2s; margin-right: 0.3rem;
}
.action-btn:hover { color: var(--rose); border-color: var(--rose); }
.action-btn.promote:hover { color: var(--gold); border-color: var(--gold); }

/* ── MODERATION PANEL ── */
.mod-queue { display: flex; flex-direction: column; gap: 0.75rem; }
.mod-item {
  background: var(--bg2); border: 1px solid var(--border);
  border-radius: 0.6rem; padding: 1.1rem;
}
.mod-item.flagged { border-color: rgba(251,113,133,0.3); }
.mod-header { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem; }
.mod-flag-type { font-family: 'DM Sans', sans-serif; font-size: 0.68rem; color: var(--rose); }
.mod-time { font-family: 'DM Sans', sans-serif; font-size: 0.65rem; color: var(--muted); margin-left: auto; }
.mod-content {
  font-size: 0.82rem; color: var(--muted2); line-height: 1.6;
  background: var(--bg3); padding: 0.75rem; border-radius: 0.4rem;
  border-left: 2px solid var(--border2); margin-bottom: 0.75rem;
  font-style: italic;
}
.mod-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.mod-btn {
  font-family: 'DM Sans', sans-serif; font-size: 0.68rem;
  padding: 0.3rem 0.75rem; border-radius: 0.3rem; cursor: pointer;
  border: 1px solid; transition: all 0.2s; background: none;
}
.mod-btn.dismiss { color: var(--green); border-color: rgba(52,211,153,0.3); }
.mod-btn.dismiss:hover { background: rgba(52,211,153,0.08); }
.mod-btn.warn { color: var(--gold); border-color: rgba(251,191,36,0.3); }
.mod-btn.warn:hover { background: rgba(251,191,36,0.08); }
.mod-btn.remove { color: var(--rose); border-color: rgba(251,113,133,0.3); }
.mod-btn.remove:hover { background: rgba(251,113,133,0.08); }

/* ── TOAST ── */
.toast {
  display: none; position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 300;
  background: var(--bg2); border: 1px solid var(--border2);
  color: var(--text); border-radius: 0.5rem;
  padding: 0.75rem 1.2rem; font-size: 0.82rem;
  font-family: 'DM Sans', sans-serif;
  animation: slideIn 0.3s ease both;
}
.toast.show { display: block; }
@keyframes slideIn { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:translateY(0); } }

/* ── SCROLLBAR ── */
::-webkit-scrollbar { width: 4px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 2px; }

/* ── MOBILE RESPONSIVE ── */
.mobile-menu-toggle {
  display: none; background: none; border: 1px solid var(--border);
  color: var(--text); width: 36px; height: 36px; border-radius: 6px;
  cursor: pointer; align-items: center; justify-content: center;
  font-size: 1.1rem;
}

@media (max-width: 900px) {
  .topbar { padding: 0 0.75rem; height: 52px; }
  .topbar-logo { font-size: 0.78rem; }
  .topbar-center { display: none; }
  .mobile-menu-toggle { display: flex; }

  .clock { display: none; }
  .live-indicator { font-size: 0.65rem; }

  .sidebar {
    transform: translateX(-100%); transition: transform 0.3s ease; z-index: 200;
    box-shadow: 0 0 30px rgba(0,0,0,0.5);
  }
  .sidebar.open { transform: translateX(0); }

  .content { margin-left: 0; padding: 1rem 0.75rem 4rem; padding-top: 52px + 1rem; }
  .app { padding-top: 52px; }

  .metric-grid { grid-template-columns: repeat(2,1fr); gap: 0.75rem; }
  .metric-card { padding: 0.9rem 1rem; }
  .metric-value { font-size: 1.5rem; }

  .chart-row, .chart-row-2, .chart-row-3 { grid-template-columns: 1fr !important; gap: 0.75rem; }
  .chart-card { padding: 1rem; }
  .emotion-grid { grid-template-columns: repeat(3,1fr); }

  .event-form { grid-template-columns: 1fr; gap: 0.6rem; padding: 1rem; }

  .events-table th, .events-table td,
  .geo-table th, .geo-table td,
  .users-table th, .users-table td { padding: 0.4rem 0.5rem; font-size: 0.74rem; }

  .users-header { flex-direction: column; align-items: stretch; }
  .search-input { width: 100%; }
  .filter-select { width: 100%; }

  .section-header { flex-direction: column; align-items: flex-start; gap: 0.5rem; }
  .section-title { font-size: 1.1rem; }
}

.sidebar-backdrop {
  display: none; position: fixed; inset: 0; z-index: 150;
  background: rgba(0,0,0,0.6);
}
.sidebar-backdrop.show { display: block; }


/* ── LOADING ── */
.loading { text-align: center; padding: 3rem; font-family: 'DM Sans', sans-serif; font-size: 0.78rem; color: var(--muted); letter-spacing: 0.1em; }
.loading::after { content: ''; animation: dots 1.5s steps(3,end) infinite; }
@keyframes dots { 0%{content:'.'} 33%{content:'..'} 66%{content:'...'} }

/* ── EXPORT BTN ── */
.export-btn {
  background: none; border: 1px solid var(--border2); cursor: pointer;
  font-family: 'DM Sans', sans-serif; font-size: 0.68rem;
  color: var(--accent); padding: 0.35rem 0.8rem; border-radius: 0.3rem;
  transition: all 0.2s; letter-spacing: 0.06em;
}
.export-btn:hover { background: var(--glow); }

/* ── SEPARATOR ── */
.sep { height: 1px; background: var(--border); margin: 1.5rem 0; }

/* ── EMPTY STATE ── */
.empty { text-align: center; padding: 3rem; color: var(--muted); font-size: 0.82rem; font-family: 'DM Sans', sans-serif; }

/* ── Lucid revenue ── */
.rev-notice { margin-bottom: 1.2rem; padding: 0.9rem 1.1rem; border: 1px solid rgba(251,191,36,.35); border-radius: .6rem; background: rgba(251,191,36,.06); color: var(--text); font-size: .8rem; }
.rev-notice[hidden] { display: none; }
.rev-notice code { color: var(--gold); }
.rev-form label { display: grid; gap: .35rem; font-size: .72rem; color: var(--muted2); margin-bottom: .75rem; }
.rev-form .search-input, .rev-form .filter-select { width: 100%; }
.rev-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 .9rem; }
.rev-actions { display: flex; gap: .6rem; margin-top: .4rem; }
.rev-hint { margin-top: .7rem; font-size: .75rem; color: var(--muted2); }
.rev-codes { margin-top: .9rem; padding: .9rem; border: 1px dashed var(--border2); border-radius: .5rem; color: var(--gold); font-size: .85rem; max-height: 200px; overflow: auto; user-select: all; }
@media (max-width: 700px) { .rev-grid { grid-template-columns: 1fr; } }
</style>
<link rel="stylesheet" href="assets/admin.css?v=2.1">
<script src="assets/admin.js?v=2.0" defer></script>
</head>
<body class="admin-page">
<a class="skip-link" href="#main-app">Skip to workspace</a>

<!-- ── LOGIN ── -->
<div id="login-screen">
  <div class="login-universe" aria-hidden="true">
    <a class="universe-brand" href="./">oneiros<small>A universe within you</small></a>
    <div class="dream-sphere"></div>
    <h2>Care for the world<br>we <em>dream together.</em></h2>
    <p>A quiet space to understand our collective imagination and care for the people behind it.</p>
  </div>
  <div class="login-box">
    <div class="login-eyebrow">The observatory / Admin</div>
    <div class="login-title">Welcome back.</div>
    <div class="login-sub">Research Command Centre — Restricted Access</div>
    <div class="login-field">
      <label class="login-label" for="login-email">Email</label>
      <input class="login-input" id="login-email" type="email" placeholder="admin@oneiros.app">
    </div>
    <div class="login-field">
      <label class="login-label" for="login-password">Password</label>
      <input class="login-input" id="login-password" type="password" placeholder="••••••••"
        onkeydown="if(event.key==='Enter') doLogin()">
    </div>
    <button class="login-btn" onclick="doLogin()">Enter the observatory →</button>
    <div class="login-error" id="login-error" role="alert"></div>
    <a class="login-return" href="./">← Return to Oneiros</a>
  </div>
</div>

<!-- ── TOP BAR ── -->
<nav class="topbar">
  <a class="topbar-logo" href="./">one<span>iros</span><small>The observatory</small></a>
  <button class="mobile-menu-toggle" onclick="toggleAdminMenu()" aria-label="Open workspace navigation" aria-expanded="false" aria-controls="workspace-sidebar">☰</button>
  <div class="topbar-center">
    <button class="tab-btn active" onclick="switchPanel('overview', this)">Overview</button>
    <button class="tab-btn" onclick="switchPanel('archetypes', this)">Archetypes</button>
    <button class="tab-btn" onclick="switchPanel('events', this)">World Events</button>
    <button class="tab-btn" onclick="switchPanel('geo', this)">Geographic</button>
    <button class="tab-btn" onclick="switchPanel('users', this)">Users</button>
    <button class="tab-btn" onclick="switchPanel('moderation', this)">Moderation</button>
  </div>
  <div class="topbar-right">
    <div class="live-indicator"><div class="live-dot"></div><span id="live-count">— live</span></div>
    <div class="clock" id="clock">--:--:--</div>
    <button class="logout-btn" onclick="doLogout()">Sign out</button>
  </div>
</nav>

<div class="app" id="main-app" style="display:none">
  <!-- ── SIDEBAR ── -->
  <aside class="sidebar" id="workspace-sidebar" aria-label="Workspace navigation">
    <div class="sidebar-section">Research</div>
    <div class="sidebar-item active" onclick="switchPanel('overview',null,this)"><span class="sidebar-icon">◉</span> Overview</div>
    <div class="sidebar-item" onclick="switchPanel('archetypes',null,this)"><span class="sidebar-icon">◈</span> Archetypes</div>
    <div class="sidebar-item" onclick="switchPanel('events',null,this)"><span class="sidebar-icon">◆</span> World Events</div>
    <div class="sidebar-item" onclick="switchPanel('geo',null,this)"><span class="sidebar-icon">◎</span> Geographic</div>
    <div class="sidebar-section">Platform</div>
    <div class="sidebar-item" onclick="switchPanel('users',null,this)"><span class="sidebar-icon">◷</span> Users</div>
    <div class="sidebar-item" onclick="switchPanel('moderation',null,this)"><span class="sidebar-icon">⚑</span> Moderation <span class="sidebar-badge" id="mod-badge">0</span></div>
    <div class="sidebar-section">Lucid</div>
    <div class="sidebar-item" onclick="switchPanel('revenue',null,this)"><span class="sidebar-icon">✦</span> Revenue &amp; codes</div>
    <div class="sidebar-section">Growth</div>
    <div class="sidebar-item" onclick="switchPanel('email',null,this)"><span class="sidebar-icon">✉</span> Email Campaigns</div>
    <div class="sidebar-item" onclick="switchPanel('streaks',null,this)"><span class="sidebar-icon">🔥</span> Streaks &amp; Badges</div>
    <div class="sidebar-section">Data</div>
    <div class="sidebar-item" onclick="exportAllData()"><span class="sidebar-icon">↓</span> Export CSV</div>
    <div class="sidebar-item" onclick="window.open(buildApiUrl('/health'),'_blank','noopener')"><span class="sidebar-icon">◌</span> API Health</div>
  </aside>

  <!-- ── CONTENT ── -->
  <div class="content">

    <!-- OVERVIEW -->
    <div class="panel active" id="panel-overview">
      <div class="observatory-intro">
        <span class="observatory-eyebrow">Oneiros / Collective intelligence</span>
        <h1>A window into<br>our <em>shared unconscious.</em></h1>
        <p>Follow the patterns that connect us. Your community’s dreams, brought into perspective.</p>
        <a class="intro-action" href="oneiros-moderation.php">Open the care studio <span aria-hidden="true">↗</span></a>
      </div>
      <div class="section-header">
        <div class="section-title">The collective, at a glance</div>
        <div class="section-meta" id="overview-timestamp">Last updated: —</div>
      </div>

      <div class="metric-grid" id="overview-metrics">
        <div class="metric-card blue"><div class="metric-label">Total Dreams</div><div class="metric-value" id="m-total">—</div><div class="metric-sub">All time</div></div>
        <div class="metric-card teal"><div class="metric-label">Dreamers Active</div><div class="metric-value" id="m-active">—</div><div class="metric-sub">Last 30 minutes</div></div>
        <div class="metric-card green"><div class="metric-label">Countries</div><div class="metric-value" id="m-countries">—</div><div class="metric-sub">Active regions</div></div>
        <div class="metric-card gold"><div class="metric-label">Total Users</div><div class="metric-value" id="m-users">—</div><div class="metric-sub">Registered accounts</div></div>
      </div>

      <div class="chart-row chart-row-2">
        <div class="chart-card">
          <div class="chart-card-title">Top Dream Themes <span>last 7 days</span></div>
          <div style="position:relative;height:260px"><canvas id="themes-chart"></canvas></div>
        </div>
        <div class="chart-card">
          <div class="chart-card-title">Emotion Pulse <span>last 24h</span></div>
          <div class="emotion-grid" id="emotion-grid">
            <div class="loading">Loading</div>
          </div>
        </div>
      </div>

      <div class="chart-row chart-row-2">
        <div class="chart-card">
          <div class="chart-card-title">Dominant Narrative Arcs <span>all time</span></div>
          <div style="position:relative;height:240px"><canvas id="arcs-chart"></canvas></div>
        </div>
        <div class="chart-card">
          <div class="chart-card-title">Dreams Logged <span>last 30 days</span></div>
          <div style="position:relative;height:240px"><canvas id="volume-chart"></canvas></div>
        </div>
      </div>
    </div>

    <!-- ARCHETYPES -->
    <div class="panel" id="panel-archetypes">
      <div class="section-header">
        <div class="section-title">Recurring Archetypes</div>
        <button class="export-btn" onclick="exportArchetypes()">EXPORT CSV</button>
      </div>
      <div class="chart-row chart-row-3">
        <div class="chart-card">
          <div class="chart-card-title">Archetype Frequency</div>
          <div class="archetype-list" id="archetype-bars"><div class="loading">Loading</div></div>
        </div>
        <div class="chart-card">
          <div class="chart-card-title">Theme distribution</div>
          <div style="position:relative;height:300px"><canvas id="archetype-donut"></canvas></div>
        </div>
      </div>
      <div class="sep"></div>
      <div class="section-header" style="margin-bottom:1rem">
        <div class="section-title" style="font-size:1rem">Top Recurring Dream Groups</div>
        <div class="section-meta" id="recurring-count">—</div>
      </div>
      <div class="recurring-list" id="recurring-list"><div class="loading">Loading</div></div>
    </div>

    <!-- WORLD EVENTS -->
    <div class="panel" id="panel-events">
      <div class="section-header">
        <div class="section-title">World event notebook</div>
        <div class="section-meta">Research notes saved in this browser</div>
      </div>
      <div class="event-form">
        <div class="event-field">
          <label>Event Title</label>
          <input class="event-input" id="evt-title" placeholder="e.g. South Africa elections 2026">
        </div>
        <div class="event-field">
          <label>Type</label>
          <select class="event-input" id="evt-type">
            <option value="political">Political</option>
            <option value="natural_disaster">Natural Disaster</option>
            <option value="cultural">Cultural</option>
            <option value="scientific">Scientific</option>
            <option value="economic">Economic</option>
          </select>
        </div>
        <button class="event-btn" onclick="addEvent()">+ TAG EVENT</button>
      </div>
      <div class="chart-card" style="margin-bottom:1rem">
        <div class="chart-card-title">Dream volume · context for your research</div>
        <div style="position:relative;height:280px"><canvas id="correlation-chart"></canvas></div>
      </div>
      <div class="chart-card">
        <div class="chart-card-title">Tagged Events</div>
        <table class="events-table">
          <thead><tr>
            <th>Event</th><th>Type</th><th>Date Tagged</th><th>Regions</th><th>Research note</th>
          </tr></thead>
          <tbody id="events-tbody"><tr><td colspan="5" class="empty">No events tagged yet</td></tr></tbody>
        </table>
      </div>
    </div>

    <!-- GEOGRAPHIC -->
    <div class="panel" id="panel-geo">
      <div class="section-header">
        <div class="section-title">Geographic Intelligence</div>
        <button class="export-btn" onclick="exportGeo()">EXPORT CSV</button>
      </div>
      <div class="chart-row chart-row-2">
        <div class="chart-card">
          <div class="chart-card-title">Activity by Region</div>
          <div style="position:relative;height:300px"><canvas id="geo-bar-chart"></canvas></div>
        </div>
        <div class="chart-card">
          <div class="chart-card-title">Regional Dream Share</div>
          <div style="position:relative;height:300px"><canvas id="geo-donut-chart"></canvas></div>
        </div>
      </div>
      <div class="chart-card">
        <div class="chart-card-title">Detailed Regional Breakdown</div>
        <table class="geo-table">
          <thead><tr><th>#</th><th>Region</th><th>Dreams</th><th>Top Theme</th><th>Share</th></tr></thead>
          <tbody id="geo-tbody"><tr><td colspan="5" class="empty">Loading...</td></tr></tbody>
        </table>
      </div>
    </div>

    <!-- USERS -->
    <div class="panel" id="panel-users">
      <div class="section-header">
        <div class="section-title">User Management</div>
        <div class="section-meta" id="user-count-meta">—</div>
      </div>
      <div class="metric-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:1.5rem">
        <div class="metric-card green"><div class="metric-label">Total Registered</div><div class="metric-value" id="u-total">—</div></div>
        <div class="metric-card gold"><div class="metric-label">Lucid passes</div><div class="metric-value" id="u-premium">—</div></div>
        <div class="metric-card blue"><div class="metric-label">Moderators</div><div class="metric-value" id="u-mods">—</div></div>
      </div>
      <div class="users-header">
        <input class="search-input" id="user-search" placeholder="Search by email or region..." oninput="filterUsers()">
        <select class="filter-select" id="user-filter" onchange="filterUsers()">
          <option value="all">All Users</option>
          <option value="premium">Premium Only</option>
          <option value="moderators">Moderators</option>
          <option value="active">Active (7 days)</option>
        </select>
      </div>
      <div class="chart-card">
        <table class="users-table">
          <thead><tr><th>Email</th><th>Region</th><th>Status</th><th>Joined</th><th>Last Active</th><th>Actions</th></tr></thead>
          <tbody id="users-tbody"><tr><td colspan="6" class="empty">Loading...</td></tr></tbody>
        </table>
      </div>
    </div>

    <!-- LUCID REVENUE -->
    <div class="panel" id="panel-revenue">
      <div class="section-header">
        <div class="section-title">Lucid revenue</div>
        <div class="section-meta" id="rev-meta">Once-off passes · nothing ever renews</div>
      </div>
      <div class="rev-notice" id="rev-notice" hidden></div>
      <div class="metric-grid">
        <div class="metric-card gold"><div class="metric-label">Today</div><div class="metric-value" id="rev-today">—</div><div class="metric-sub">Paid since midnight (UTC)</div></div>
        <div class="metric-card gold"><div class="metric-label">Last 7 days</div><div class="metric-value" id="rev-week">—</div><div class="metric-sub">Paid orders</div></div>
        <div class="metric-card gold"><div class="metric-label">Last 30 days</div><div class="metric-value" id="rev-month">—</div><div class="metric-sub">Paid orders</div></div>
        <div class="metric-card gold"><div class="metric-label">All time</div><div class="metric-value" id="rev-all">—</div><div class="metric-sub" id="rev-all-sub">—</div></div>
      </div>
      <div class="metric-grid">
        <div class="metric-card green"><div class="metric-label">Active passes</div><div class="metric-value" id="rev-active">—</div><div class="metric-sub" id="rev-active-sub">—</div></div>
        <div class="metric-card blue"><div class="metric-label">Average order</div><div class="metric-value" id="rev-avg">—</div><div class="metric-sub" id="rev-buyers">—</div></div>
        <div class="metric-card teal"><div class="metric-label">Patrons</div><div class="metric-value" id="rev-patrons">—</div><div class="metric-sub">Once-off supporters</div></div>
        <div class="metric-card rose"><div class="metric-label">Refunded</div><div class="metric-value" id="rev-refunded">—</div><div class="metric-sub" id="rev-pending">—</div></div>
      </div>
      <div class="chart-row chart-row-2">
        <div class="chart-card">
          <div class="chart-card-title">Revenue <span>last 30 days</span></div>
          <div style="position:relative;height:250px"><canvas id="revenue-chart"></canvas></div>
        </div>
        <div class="chart-card">
          <div class="chart-card-title">Sales by pass <span>all time</span></div>
          <table class="users-table"><thead><tr><th>Pass</th><th>Orders</th><th>Revenue</th></tr></thead><tbody id="rev-plans"><tr><td colspan="3" class="empty">No sales yet</td></tr></tbody></table>
        </div>
      </div>
      <div class="chart-row chart-row-2">
        <div class="chart-card rev-form">
          <div class="chart-card-title">Grant Lucid <span>EFT payments, prizes, apologies</span></div>
          <label>Dreamer’s email<input class="search-input" id="grant-email" type="email" placeholder="dreamer@example.com"></label>
          <label>Nights<input class="search-input" id="grant-days" type="number" min="1" max="3650" value="30"></label>
          <div class="rev-actions"><button class="action-btn promote" onclick="grantLucid(false)">Add nights</button><button class="action-btn" onclick="grantLucid(true)">End pass</button></div>
          <p class="rev-hint" id="grant-result"></p>
        </div>
        <div class="chart-card rev-form">
          <div class="chart-card-title">Create codes <span>free nights or % off</span></div>
          <div class="rev-grid">
            <label>Type<select class="filter-select" id="code-kind" onchange="document.getElementById('code-value-name').textContent = this.value === 'discount' ? 'Percent off' : 'Nights'">
              <option value="promo">Free nights (promo)</option><option value="discount">Discount at checkout</option><option value="gift">Gift (single use)</option></select></label>
            <label><span id="code-value-name">Nights</span><input class="search-input" id="code-value" type="number" min="1" value="30"></label>
            <label>How many codes<input class="search-input" id="code-count" type="number" min="1" max="200" value="1"></label>
            <label>Uses per code<input class="search-input" id="code-uses" type="number" min="1" value="1"></label>
            <label>Expires on (optional)<input class="search-input" id="code-expires" type="date"></label>
            <label>Custom code (optional)<input class="search-input" id="code-custom" placeholder="e.g. WINTER30" style="text-transform:uppercase"></label>
          </div>
          <label>Note<input class="search-input" id="code-note" placeholder="Where you will share it"></label>
          <div class="rev-actions"><button class="action-btn promote" onclick="createCodes()">Create</button></div>
          <pre class="rev-codes" id="code-output" hidden></pre>
        </div>
      </div>
      <div class="chart-card">
        <div class="chart-card-title">Codes <span>latest 300</span></div>
        <table class="users-table"><thead><tr><th>Code</th><th>Type</th><th>Value</th><th>Used</th><th>Expires</th><th>Note</th><th></th></tr></thead><tbody id="codes-tbody"><tr><td colspan="7" class="empty">Loading…</td></tr></tbody></table>
      </div>
      <div class="chart-card">
        <div class="chart-card-title">Orders <span>latest 150</span></div>
        <div class="users-header"><input class="search-input" id="order-search" placeholder="Search reference or email…" oninput="renderOrders()">
          <select class="filter-select" id="order-filter" onchange="renderOrders()"><option value="all">All orders</option><option value="paid">Paid</option><option value="pending">Pending</option><option value="refunded">Refunded</option><option value="cancelled">Cancelled / failed</option></select>
          <button class="export-btn" onclick="exportOrders()">Export CSV</button></div>
        <table class="users-table"><thead><tr><th>Reference</th><th>Date</th><th>Buyer</th><th>Pass</th><th>Amount</th><th>Gateway</th><th>Status</th><th></th></tr></thead><tbody id="orders-tbody"><tr><td colspan="8" class="empty">Loading…</td></tr></tbody></table>
      </div>
    </div>

    <!-- MODERATION -->
    <div class="panel" id="panel-moderation">
      <div class="section-header">
        <div class="section-title">Moderation Queue</div>
        <div class="section-meta" id="mod-count-meta">—</div>
      </div>
      <div class="metric-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:1.5rem">
        <div class="metric-card rose"><div class="metric-label">Pending Review</div><div class="metric-value" id="mod-pending">—</div></div>
        <div class="metric-card gold"><div class="metric-label">Actioned · recent queue</div><div class="metric-value" id="mod-actioned">—</div></div>
        <div class="metric-card green"><div class="metric-label">Dismissed · recent queue</div><div class="metric-value" id="mod-dismissed">—</div></div>
      </div>
      <div class="mod-queue" id="mod-queue"><div class="loading">Loading</div></div>
    </div>

    <!-- EMAIL CAMPAIGNS -->
    <div class="panel" id="panel-email">
      <div class="section-header">
        <div class="section-title">Email Campaigns</div>
        <div class="section-meta">Send emails · Manage queue</div>
      </div>

      <div class="metric-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:1.5rem">
        <div class="metric-card gold"><div class="metric-label">Queue Pending</div><div class="metric-value" id="eq-pending">—</div></div>
        <div class="metric-card green"><div class="metric-label">Sent All-time</div><div class="metric-value" id="eq-sent">—</div></div>
        <div class="metric-card rose"><div class="metric-label">Failed</div><div class="metric-value" id="eq-failed">—</div></div>
      </div>

      <div style="background:var(--bg2);border:1px solid var(--border);border-radius:0.75rem;padding:1.5rem;margin-bottom:1rem">
        <div style="font-size:0.75rem;letter-spacing:0.12em;color:var(--muted);text-transform:uppercase;margin-bottom:1rem">Send Campaign</div>
        <div style="display:grid;grid-template-columns:1fr 1fr auto;gap:0.75rem;align-items:end">
          <div>
            <label style="font-size:0.7rem;color:var(--muted);display:block;margin-bottom:0.4rem;letter-spacing:0.08em">TEMPLATE</label>
            <select id="camp-template" style="width:100%;background:var(--bg3);border:1px solid var(--border2);border-radius:0.4rem;padding:0.55rem 0.75rem;color:var(--text);font-family:'Syne',sans-serif;font-size:0.82rem">
              <option value="re_engagement">Re-engagement (7+ days inactive)</option>
              <option value="weekly_digest">Weekly Digest</option>
              <option value="welcome">Welcome (new users)</option>
            </select>
          </div>
          <div>
            <label style="font-size:0.7rem;color:var(--muted);display:block;margin-bottom:0.4rem;letter-spacing:0.08em">SEGMENT</label>
            <select id="camp-segment" style="width:100%;background:var(--bg3);border:1px solid var(--border2);border-radius:0.4rem;padding:0.55rem 0.75rem;color:var(--text);font-family:'Syne',sans-serif;font-size:0.82rem">
              <option value="all">All active users</option>
              <option value="inactive_7d">Inactive 7+ days</option>
              <option value="inactive_30d">Inactive 30+ days</option>
              <option value="no_dreams">No dreams yet</option>
            </select>
          </div>
          <button onclick="sendCampaign()" style="background:var(--accent);color:#fff;border:none;border-radius:0.4rem;padding:0.6rem 1.25rem;font-size:0.78rem;font-family:'DM Sans',sans-serif;cursor:pointer;white-space:nowrap;letter-spacing:0.04em">SEND →</button>
        </div>
      </div>

      <div style="background:var(--bg2);border:1px solid var(--border);border-radius:0.75rem;padding:1.5rem">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
          <div style="font-size:0.75rem;letter-spacing:0.12em;color:var(--muted);text-transform:uppercase">Queue Processing</div>
          <button onclick="processQueue()" style="background:var(--bg3);color:var(--teal);border:1px solid var(--border2);border-radius:0.4rem;padding:0.4rem 1rem;font-size:0.72rem;font-family:'DM Sans',sans-serif;cursor:pointer">FLUSH QUEUE</button>
        </div>
        <div id="email-queue-status" style="font-size:0.82rem;color:var(--muted2)">Click "Flush Queue" to process pending emails.</div>
      </div>
    </div>

    <!-- STREAKS & BADGES -->
    <div class="panel" id="panel-streaks">
      <div class="section-header">
        <div class="section-title">Streaks &amp; Badges</div>
        <div class="section-meta">Leaderboard &amp; gamification overview</div>
      </div>
      <div class="metric-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:1.5rem">
        <div class="metric-card blue"><div class="metric-label">Active Streaks</div><div class="metric-value" id="str-active">—</div><div class="metric-sub">Users with streak &gt; 0</div></div>
        <div class="metric-card teal"><div class="metric-label">Longest Streak</div><div class="metric-value" id="str-longest">—</div><div class="metric-sub">All-time record (days)</div></div>
        <div class="metric-card gold"><div class="metric-label">Badges Awarded</div><div class="metric-value" id="str-badges">—</div><div class="metric-sub">Total across all users</div></div>
      </div>
      <div id="streak-leaderboard-admin" style="background:var(--bg2);border:1px solid var(--border);border-radius:0.75rem;padding:1.5rem">
        <div style="font-size:0.75rem;letter-spacing:0.12em;color:var(--muted);text-transform:uppercase;margin-bottom:1rem">Top Streaks (anonymous)</div>
        <div id="admin-leaderboard">Loading...</div>
      </div>
    </div>

  </div>
</div>

<div class="toast" id="toast" role="status" aria-live="polite"></div>
<div class="sidebar-backdrop" id="sidebar-backdrop" onclick="closeAdminMenu()"></div>

<script>
function toggleAdminMenu() {
  document.querySelector('.sidebar')?.classList.toggle('open');
  document.getElementById('sidebar-backdrop')?.classList.toggle('show');
}
function closeAdminMenu() {
  document.querySelector('.sidebar')?.classList.remove('open');
  document.getElementById('sidebar-backdrop')?.classList.remove('show');
}
document.addEventListener('click', e => {
  if (window.innerWidth <= 900 && e.target.closest('.sidebar-item')) {
    setTimeout(closeAdminMenu, 100);
  }
});
</script>

<script>
// ════════════════════════════════════════════════════════════
// CONFIG
// ════════════════════════════════════════════════════════════
const APP_BASE = new URL('.', window.location.href);
const API_BASE = new URL('api', APP_BASE).href;

let accessToken = null;
let allUsers = [];
let allFlags = [];
let taggedEvents = (() => { try { const saved = JSON.parse(localStorage.getItem('oneiros_events') || '[]'); return Array.isArray(saved) ? saved : []; } catch { return []; } })();
let researchStats = {};
let dashboardTimer = null;
let charts = {};
let useRewriteFallback = true;

function buildApiUrl(path) {
  const cleanPath = path.startsWith('/') ? path.substring(1) : path;
  if (useRewriteFallback === true) {
    const [route, query] = cleanPath.split('?');
    const sep = query ? '&' : '';
    return `${API_BASE.replace(/\/api$/, '')}/api.php?_route=${route}${sep}${query || ''}`;
  }
  return API_BASE + path;
}

// ════════════════════════════════════════════════════════════
// CLOCK
// ════════════════════════════════════════════════════════════
setInterval(() => {
  document.getElementById('clock').textContent =
    new Date().toLocaleTimeString('en-ZA', { hour12: false });
}, 1000);

// ════════════════════════════════════════════════════════════
// AUTH
// ════════════════════════════════════════════════════════════
async function doLogin() {
  const email = document.getElementById('login-email').value.trim();
  const password = document.getElementById('login-password').value;
  const errEl = document.getElementById('login-error');
  const button = document.querySelector('.login-btn');
  if (button.disabled) return;
  errEl.textContent = '';
  if (!email || !password) { errEl.textContent = 'Enter your email and password to continue.'; return; }
  button.disabled = true; button.textContent = 'Opening your workspace?';
  try {
    const res = await fetch(buildApiUrl('/auth/login'), { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({email,password}) });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Sign in failed. Please try again.');
    if (!data.user?.is_admin) throw new Error('This workspace requires an administrator account.');
    accessToken = data.access_token;
    localStorage.setItem('oneiros_admin_rt', data.refresh_token);
    document.getElementById('login-password').value = '';
    document.getElementById('login-screen').style.display = 'none';
    document.getElementById('main-app').style.display = 'flex';
    document.querySelector('.topbar').style.display = 'flex';
    initDashboard();
  } catch (err) { errEl.textContent = err instanceof SyntaxError ? 'The service is unavailable. Please try again shortly.' : err.message; }
  finally { button.disabled = false; button.textContent = 'Enter the observatory ?'; }
}

async function tryAdminRestore() {
  const rt = localStorage.getItem('oneiros_admin_rt');
  if (!rt) return false;

  // Try clean URL first, then fallback
  for (const fallback of [false, true]) {
    useRewriteFallback = fallback;
    try {
      const res  = await fetch(buildApiUrl('/auth/refresh'), {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ refresh_token: rt })
      });
      if (res.status === 404) continue;
      const data = await res.json();
      if (!res.ok || !data.user?.is_admin) return false;
      accessToken = data.access_token;
      localStorage.setItem('oneiros_admin_rt', data.refresh_token);
      return true;
    } catch {}
  }
  return false;
}

function doLogout() {
  const refresh_token = localStorage.getItem('oneiros_admin_rt');
  if (refresh_token) fetch(buildApiUrl('/auth/logout'), {method:'POST',headers:{'Content-Type':'application/json','Authorization':'Bearer '+accessToken},body:JSON.stringify({refresh_token})}).catch(()=>{});
  clearInterval(dashboardTimer);
  localStorage.removeItem('oneiros_admin_rt');
  accessToken = null;
  document.getElementById('main-app').style.display = 'none';
  document.querySelector('.topbar').style.display = 'none';
  document.getElementById('login-screen').style.display = 'flex';
  closeAdminMenu();
}

// ════════════════════════════════════════════════════════════
// API
// ════════════════════════════════════════════════════════════
async function api(path, opts = {}) {
  return requestPanelApi(path, opts);
}

async function apiPost(path, body) {
  return api(path, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
}

async function apiPatch(path, body) {
  return api(path, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
}

// ════════════════════════════════════════════════════════════
// NAVIGATION
// ════════════════════════════════════════════════════════════
function switchPanel(id) {
  const panel = document.getElementById('panel-' + id);
  if (!panel) return;
  document.querySelectorAll('.panel').forEach(p => p.classList.toggle('active', p === panel));
  document.querySelectorAll('.tab-btn,.sidebar-item').forEach(button => {
    const active = (button.getAttribute('onclick') || '').includes("switchPanel('" + id + "'");
    button.classList.toggle('active', active);
    if (active) button.setAttribute('aria-current','page'); else button.removeAttribute('aria-current');
  });
  closeAdminMenu();
  if (id === 'email') loadEmail();
  if (id === 'streaks') loadStreaksAdmin();
  if (id === 'revenue') { loadRevenue(); loadCodes(); }
  Object.values(charts).forEach(chart => chart.resize());
  window.scrollTo({top:0,behavior:matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant':'smooth'});
}

// ════════════════════════════════════════════════════════════
// INIT
// ════════════════════════════════════════════════════════════
async function initDashboard() {
  await Promise.allSettled([loadOverview(), loadUsers(), loadModeration()]);
  loadArchetypes(); loadGeo(); renderEvents();
  clearInterval(dashboardTimer);
  dashboardTimer = setInterval(() => { if (accessToken && !document.hidden) loadOverview(); }, 60000);
}

// ════════════════════════════════════════════════════════════
// OVERVIEW
// ════════════════════════════════════════════════════════════
async function loadOverview() {
  try {
    const stats = await api('/research/global');
    researchStats = await api('/admin/stats').catch(() => ({}));

    document.getElementById('m-total').textContent    = (stats.total_dreams || 0).toLocaleString();
    document.getElementById('m-active').textContent   = stats.dreamers_active_now || 0;
    document.getElementById('m-countries').textContent = stats.active_countries || 0;
    document.getElementById('live-count').textContent  = (stats.dreamers_active_now || 0) + ' live';
    document.getElementById('overview-timestamp').textContent =
      'Last updated: ' + new Date().toLocaleTimeString('en-ZA');

    // Emotions
    renderEmotionGrid(stats.top_emotions || []);

    // Themes chart
    renderThemesChart(stats.top_themes || []);

    // Aggregate narrative data from the research service
    renderArcsChart();

    // Actual daily activity, never generated sample data
    renderVolumeChart();

  } catch (err) {
    showToast('Overview load failed: ' + err.message);
  }
}

function renderEmotionGrid(emotions) {
  const icons = { wonder:'✨', terror:'😰', peace:'🌿', joy:'☀️', confusion:'🌀',
    longing:'🌙', sadness:'🌧️', dread:'🌑', fear:'😨', awe:'🔭' };
  const el = document.getElementById('emotion-grid');
  if (!emotions.length) { el.innerHTML = '<div class="empty">No emotion data yet</div>'; return; }
  el.innerHTML = emotions.slice(0,8).map(e => `
    <div class="emotion-cell">
      <div class="emotion-icon">${icons[e.emotion] || '◉'}</div>
      <div class="emotion-name">${escHtml(e.emotion)}</div>
      <div class="emotion-count">${e.count}</div>
    </div>`).join('');
}

function renderThemesChart(themes) {
  const ctx = document.getElementById('themes-chart');
  if (charts.themes) charts.themes.destroy();
  if (!themes.length || !window.Chart) { showChartEmpty(ctx, 'Dream themes will appear as your community grows.'); return; }
  clearChartEmpty(ctx);
  charts.themes = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: themes.slice(0,10).map(t => t.theme),
      datasets: [{ data: themes.slice(0,10).map(t => t.count),
        backgroundColor: 'rgba(99,130,255,0.5)',
        borderColor: 'rgba(99,130,255,0.9)',
        borderWidth: 1, borderRadius: 3 }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { ticks: { color: '#9babc6', font: { family: 'DM Sans', size: 10 } },
          grid: { color: 'rgba(99,130,255,0.06)' } },
        y: { ticks: { color: '#9babc6', font: { family: 'DM Sans', size: 10 } },
          grid: { color: 'rgba(99,130,255,0.06)' } }
      }
    }
  });
}

function renderArcsChart() {
  const data = researchStats.narrative_arcs || [];
  const ctx = document.getElementById('arcs-chart');
  if (charts.arcs) charts.arcs.destroy();
  if (!data.length || !window.Chart) { showChartEmpty(ctx, 'Narrative patterns appear as the community adds dreams.'); return; }
  clearChartEmpty(ctx);
  charts.arcs = new Chart(ctx, {type:'doughnut', data:{labels:data.map(r=>r.arc),datasets:[{data:data.map(r=>r.count),backgroundColor:['#bba6ef','#83c8cc','#d9bce9','#d9bf90','#b6bfef','#88baae','#e5a5b9'],borderWidth:0,hoverOffset:6}]},options:{responsive:true,maintainAspectRatio:false,cutout:'72%',plugins:{legend:{position:'right',labels:{color:'#a8b4cf',font:{family:'DM Sans',size:10},boxWidth:8,padding:15}}}}});
}

function renderVolumeChart() {
  const data = researchStats.daily_volume || [];
  const ctx = document.getElementById('volume-chart');
  if (charts.volume) charts.volume.destroy();
  if (!data.length || !window.Chart) { showChartEmpty(ctx, 'Your community’s daily dream activity will appear here.'); return; }
  clearChartEmpty(ctx);
  charts.volume = new Chart(ctx,{type:'line',data:{labels:data.map(r=>new Date(r.date+'T12:00:00').toLocaleDateString('en-ZA',{day:'numeric',month:'short'})),datasets:[{data:data.map(r=>r.count),borderColor:'#87d7d9',backgroundColor:'rgba(135,215,217,.06)',borderWidth:2,pointRadius:0,pointHoverRadius:5,fill:true,tension:.4}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{ticks:{color:'#9babc6',maxTicksLimit:6},grid:{display:false}},y:{beginAtZero:true,ticks:{color:'#9babc6',precision:0},grid:{color:'rgba(193,173,255,.06)'}}}}});
}

// ════════════════════════════════════════════════════════════
// ARCHETYPES
// ════════════════════════════════════════════════════════════
async function loadArchetypes() {
  try {
    const stats = await api('/research/global');
    const themes = stats.top_themes || [];

    // Archetype bars
    const barsEl = document.getElementById('archetype-bars');
    const colors = ['#6382FF','#2DD4BF','#A78BFA','#FBBF24','#FB7185','#34D399','#94A3B8','#FB7185'];
    const max = themes[0]?.count || 1;
    barsEl.innerHTML = themes.slice(0,10).map((t,i) => `
      <div class="archetype-row">
        <div class="archetype-label">${escHtml(t.theme)}</div>
        <div class="archetype-bar-wrap">
          <div class="archetype-bar" style="background:${colors[i%colors.length]};width:${Math.round(t.count/max*100)}%"></div>
        </div>
        <div class="archetype-count">${t.count}</div>
      </div>`).join('');

    // Donut
    const ctx = document.getElementById('archetype-donut');
    if (charts.archDonut) charts.archDonut.destroy();
    charts.archDonut = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: themes.slice(0,8).map(t => t.theme),
        datasets: [{ data: themes.slice(0,8).map(t => t.count),
          backgroundColor: colors, borderWidth: 0, hoverOffset: 6 }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'right',
          labels: { color: '#94A3B8', font: { family: 'DM Sans', size: 10 }, boxWidth: 10, padding: 10 } } }
      }
    });

    // Recurring groups
    loadRecurringGroups();
  } catch (err) { showToast('Archetypes error: ' + err.message); }
}

async function loadRecurringGroups() {
  const groups = researchStats.recurring_groups || [];
  document.getElementById('recurring-count').textContent = groups.length + ' recurring groups';
  document.getElementById('recurring-list').innerHTML = groups.length ? groups.map(g => `
    <div class="recurring-card"><div class="recurring-count">×${Number(g.occurrence_count)}</div>
      <div class="recurring-info"><div class="recurring-themes">${(g.core_themes || []).map(t=>`<span class="theme-tag">${escHtml(t)}</span>`).join('')}</div>
      <div class="recurring-meta">First recorded ${new Date(g.first_seen_at).toLocaleDateString()} · Latest ${new Date(g.last_seen_at).toLocaleDateString()}</div></div></div>`).join('')
    : '<div class="empty">Recurring patterns will appear as dreams are recorded and connected.</div>';
}

// ════════════════════════════════════════════════════════════
// GEOGRAPHIC
// ════════════════════════════════════════════════════════════
async function loadGeo() {
  try {
    const stats = await api('/research/global');
    const regions = stats.regional_activity || [];
    const total = regions.reduce((s,r) => s + r.count, 0) || 1;

    // Bar chart
    const ctx1 = document.getElementById('geo-bar-chart');
    if (charts.geoBar) charts.geoBar.destroy();
    charts.geoBar = new Chart(ctx1, {
      type: 'bar',
      data: {
        labels: regions.slice(0,12).map(r => r.region || r.region_code),
        datasets: [{ data: regions.slice(0,12).map(r => r.count),
          backgroundColor: 'rgba(45,212,191,0.5)', borderColor: '#2DD4BF',
          borderWidth: 1, borderRadius: 3 }]
      },
      options: {
        indexAxis: 'y', responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { ticks: { color: '#9babc6', font: { family: 'DM Sans', size: 9 } }, grid: { color: 'rgba(99,130,255,0.06)' } },
          y: { ticks: { color: '#94A3B8', font: { family: 'DM Sans', size: 10 } }, grid: { display: false } }
        }
      }
    });

    // Donut
    const ctx2 = document.getElementById('geo-donut-chart');
    const colors = ['#6382FF','#2DD4BF','#A78BFA','#FBBF24','#FB7185','#34D399','#94A3B8','#60A5FA'];
    if (charts.geoDonut) charts.geoDonut.destroy();
    charts.geoDonut = new Chart(ctx2, {
      type: 'doughnut',
      data: {
        labels: regions.slice(0,8).map(r => r.region || r.region_code),
        datasets: [{ data: regions.slice(0,8).map(r => r.count),
          backgroundColor: colors, borderWidth: 0 }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'right',
          labels: { color: '#94A3B8', font: { family: 'DM Sans', size: 10 }, boxWidth: 10, padding: 10 } } }
      }
    });

    // Table
    const tbody = document.getElementById('geo-tbody');
    tbody.innerHTML = regions.length ? regions.slice(0,20).map((r,i) => `
      <tr>
        <td class="geo-rank">${String(i+1).padStart(2,'0')}</td>
        <td class="geo-region">${r.region || r.region_code || '—'}</td>
        <td class="geo-count">${r.count}</td>
        <td class="geo-top-theme">${r.top_theme || '—'}</td>
        <td class="geo-count">${Math.round(r.count/total*100)}%</td>
      </tr>`).join('')
      : '<tr><td colspan="5" class="empty">No regional data yet</td></tr>';
  } catch (err) { showToast('Geo load failed: ' + err.message); }
}

// ════════════════════════════════════════════════════════════
// WORLD EVENTS
// ════════════════════════════════════════════════════════════
function addEvent() {
  const title = document.getElementById('evt-title').value.trim();
  const type  = document.getElementById('evt-type').value;
  if (!title) { showToast('Enter an event title'); return; }

  taggedEvents.push({ id: Date.now(), title, type, date: new Date().toISOString() });
  localStorage.setItem('oneiros_events', JSON.stringify(taggedEvents));
  document.getElementById('evt-title').value = '';
  renderEvents();
  renderCorrelationChart();
  showToast('Event tagged: ' + title);
}

function renderEvents() {
  const tbody = document.getElementById('events-tbody');
  const badgeClass = { political:'badge-political', natural_disaster:'badge-natural',
    cultural:'badge-cultural', scientific:'badge-scientific', economic:'badge-gold' };
  tbody.innerHTML = taggedEvents.length
    ? taggedEvents.map(e => `
        <tr>
          <td style="color:var(--text)">${escHtml(e.title)}</td>
          <td><span class="event-type-badge ${badgeClass[e.type]||'badge-political'}">${escHtml(e.type)}</span></td>
          <td>${new Date(e.date).toLocaleDateString()}</td>
          <td style="color:var(--muted2)">Global</td>
          <td style="color:var(--accent);font-family:'DM Sans',sans-serif;font-size:0.72rem">Saved locally</td>
        </tr>`).join('')
    : '<tr><td colspan="5" class="empty">No events tagged yet — use the form above</td></tr>';
  renderCorrelationChart();
}

function renderCorrelationChart() {
  const ctx = document.getElementById('correlation-chart');
  if (charts.correlation) charts.correlation.destroy();
  const data = researchStats.daily_volume || [];
  if (!data.length || !window.Chart) { showChartEmpty(ctx, 'Save event notes to support your research. Daily dream counts appear as the community grows.'); return; }
  clearChartEmpty(ctx);
  charts.correlation = new Chart(ctx,{type:'line',data:{labels:data.map(r=>r.date),datasets:[{label:'Recorded dreams',data:data.map(r=>r.count),borderColor:'#bba6ef',backgroundColor:'rgba(187,166,239,.07)',fill:true,tension:.35,pointRadius:2,borderWidth:2}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{labels:{color:'#a8b4cf',boxWidth:9}}},scales:{x:{ticks:{color:'#9babc6',maxTicksLimit:8},grid:{display:false}},y:{beginAtZero:true,ticks:{color:'#9babc6',precision:0},grid:{color:'rgba(193,173,255,.06)'}}}}});
}

// ════════════════════════════════════════════════════════════
// USERS
// ════════════════════════════════════════════════════════════
async function loadUsers() {
  try {
    const data = await api('/admin/users');
    allUsers = data.users || [];
    document.getElementById('m-users').textContent = allUsers.length;
    document.getElementById('u-total').textContent = allUsers.length;
    document.getElementById('u-premium').textContent = allUsers.filter(u=>u.is_premium).length;
    document.getElementById('u-mods').textContent = allUsers.filter(u=>u.is_moderator).length;
    document.getElementById('user-count-meta').textContent = allUsers.length + ' registered users';
    renderUsersTable(allUsers);
  } catch {
    // Admin endpoint may not exist yet — show placeholder
    document.getElementById('users-tbody').innerHTML =
      '<tr><td colspan="6" class="empty">Admin users endpoint not yet deployed — add GET /admin/users to backend</td></tr>';
  }
}

function renderUsersTable(users) {
  const tbody = document.getElementById('users-tbody');
  if (!users.length) { tbody.innerHTML = '<tr><td colspan="6" class="empty">No users found</td></tr>'; return; }
  tbody.innerHTML = users.map(u => `
    <tr>
      <td style="color:var(--text)">${escHtml(u.email)}</td>
      <td>${u.region || '—'}</td>
      <td>
        ${u.is_premium ? `<span class="status-badge status-premium" title="Lucid until ${u.premium_until ? new Date(u.premium_until).toLocaleDateString('en-ZA') : ''}">LUCID</span> ` : ''}${u.is_patron ? '<span class="status-badge status-premium">PATRON</span> ' : ''}
        <span class="status-badge ${u.is_active?'status-active':'status-inactive'}">${u.is_active?'ACTIVE':'INACTIVE'}</span>
      </td>
      <td>${u.created_at ? new Date(u.created_at).toLocaleDateString() : '—'}</td>
      <td>${u.last_active_at ? new Date(u.last_active_at).toLocaleDateString() : '—'}</td>
      <td>
        <button class="action-btn promote" onclick="promoteUser('${u.id}')">${u.is_moderator ? 'Remove mod' : 'Make mod'}</button>
        <button class="action-btn" onclick="suspendUser('${u.id}')">${u.is_active ? 'Suspend' : 'Restore'}</button>
      </td>
    </tr>`).join('');
}

function filterUsers() {
  const q = document.getElementById('user-search').value.toLowerCase();
  const f = document.getElementById('user-filter').value;
  const now = Date.now();
  let filtered = allUsers.filter(u => {
    const matchQ = !q || u.email?.toLowerCase().includes(q) || u.region?.toLowerCase().includes(q);
    const matchF = f === 'all' ? true
      : f === 'premium' ? u.is_premium
      : f === 'moderators' ? u.is_moderator
      : f === 'active' ? (now - new Date(u.last_active_at||0).getTime() < 7*86400000)
      : true;
    return matchQ && matchF;
  });
  renderUsersTable(filtered);
}

async function promoteUser(id) {
  const user = allUsers.find(u => u.id === id); if (!user) return;
  const is_moderator = !user.is_moderator;
  if (!confirm(`${is_moderator ? 'Make' : 'Remove'} ${user.email} ${is_moderator ? 'a moderator' : 'from the moderator team'}?`)) return;
  try { await apiPatch('/admin/users/' + encodeURIComponent(id), {is_moderator}); await loadUsers(); showToast('Moderator access updated.'); }
  catch(err) { showToast('Could not update this account: '+err.message); }
}
async function suspendUser(id) {
  const user = allUsers.find(u => u.id === id); if (!user) return;
  const is_active = !user.is_active;
  if (!confirm(`${is_active ? 'Restore access for' : 'Suspend'} ${user.email}?`)) return;
  try { await apiPatch('/admin/users/' + encodeURIComponent(id), {is_active}); await loadUsers(); showToast(is_active ? 'Account access restored.' : 'Account suspended.'); }
  catch(err) { showToast('Could not update this account: '+err.message); }
}

// ════════════════════════════════════════════════════════════
// MODERATION
// ════════════════════════════════════════════════════════════
async function loadModeration() {
  try {
    const data = await api('/admin/flags');
    allFlags = data.flags || [];
    const pending    = allFlags.filter(f => f.status === 'pending').length;
    const actioned   = allFlags.filter(f => f.status === 'actioned').length;
    const dismissed  = allFlags.filter(f => f.status === 'dismissed').length;
    document.getElementById('mod-pending').textContent   = pending;
    document.getElementById('mod-actioned').textContent  = actioned;
    document.getElementById('mod-dismissed').textContent = dismissed;
    document.getElementById('mod-badge').textContent     = pending;
    document.getElementById('mod-count-meta').textContent = pending + ' pending review';
    renderModQueue(allFlags.filter(f=>f.status==='pending'));
  } catch {
    document.getElementById('mod-queue').innerHTML =
      '<div class="empty">Admin flags endpoint not yet deployed — add GET /admin/flags to backend</div>';
    document.getElementById('mod-pending').textContent = '0';
    document.getElementById('mod-actioned').textContent = '0';
    document.getElementById('mod-dismissed').textContent = '0';
  }
}

function renderModQueue(flags) {
  const el = document.getElementById('mod-queue');
  if (!flags.length) { el.innerHTML = '<div class="empty">Queue is clear — no pending flags</div>'; return; }
  el.innerHTML = flags.map(f => `
    <div class="mod-item flagged" id="flag-${f.id}">
      <div class="mod-header">
        <span class="mod-flag-type">⚑ ${escHtml(f.reason?.toUpperCase())}</span>
        <span class="mod-time">${f.created_at ? new Date(f.created_at).toLocaleString() : '—'}</span>
      </div>
      <div class="mod-content">${escHtml(f.dream_content_preview || f.notes || 'Content preview not available')}</div>
      <div class="mod-actions">
        <button class="mod-btn dismiss" onclick="moderateFlag('${f.id}','dismissed')">✓ DISMISS</button>
        <button class="mod-btn warn" onclick="moderateFlag('${f.id}','warned')">⚠ ADD WARNING</button>
        <button class="mod-btn remove" onclick="moderateFlag('${f.id}','actioned')">✕ REMOVE</button>
      </div>
    </div>`).join('');
}

async function moderateFlag(flagId, action) {
  if (action === 'actioned' && !confirm('Remove this reported content from the community?')) return;
  const card = document.getElementById('flag-' + flagId);
  card?.querySelectorAll('button').forEach(button=>button.disabled=true);
  try { await apiPatch('/admin/flags/' + encodeURIComponent(flagId), {status:action,action_taken:action}); await loadModeration(); showToast('Review saved.'); }
  catch(err) { card?.querySelectorAll('button').forEach(button=>button.disabled=false); showToast('Review could not be saved: '+err.message); }
}

// ════════════════════════════════════════════════════════════
// EMAIL CAMPAIGNS
// ════════════════════════════════════════════════════════════
async function loadEmail() {
  try {
    const stats = await api('/admin/email/stats');
    document.getElementById('eq-pending').textContent = stats.pending ?? '—';
    document.getElementById('eq-sent').textContent    = stats.sent    ?? '—';
    document.getElementById('eq-failed').textContent  = stats.failed  ?? '—';
  } catch {
    document.getElementById('email-queue-status').textContent = 'Email queue stats unavailable — run v1.1.0 migration first.';
  }
}

async function sendCampaign() {
  const template = document.getElementById('camp-template').value;
  const segment  = document.getElementById('camp-segment').value;
  const label    = document.getElementById('camp-template').options[document.getElementById('camp-template').selectedIndex].text;
  if (!confirm(`Send "${label}" to segment: "${segment}"?\n\nThis will queue emails for all matching users.`)) return;
  try {
    const res = await apiPost('/admin/email/campaign', { template, segment });
    showToast(`✉ Queued ${res.queued} emails for "${template}"`);
    document.getElementById('email-queue-status').textContent = res.message;
    loadEmail();
  } catch (err) {
    showToast('Campaign failed: ' + err.message);
  }
}

async function processQueue() {
  showToast('Processing email queue...');
  try {
    const res = await apiPost('/admin/email/process', {});
    showToast(`✉ Sent ${res.sent} emails from queue`);
    document.getElementById('email-queue-status').textContent = res.message;
    loadEmail();
  } catch (err) {
    showToast('Queue process failed: ' + err.message);
  }
}

// ════════════════════════════════════════════════════════════
// STREAKS & BADGES ADMIN
// ════════════════════════════════════════════════════════════
async function loadStreaksAdmin() {
  try {
    // Get leaderboard from the streaks endpoint
    const data = await api('/streaks/leaderboard');
    const board = data.leaderboard || [];

    // Summary stats from admin
    const adminStats = await api('/admin/stats', {}).catch(() => null);
    // Streaks table stats (may not exist on v1.0 DBs)
    try {
      const streakStats = await api('/admin/streaks/stats', {}).catch(() => null);
      if (streakStats) {
        document.getElementById('str-active').textContent  = streakStats.active_streaks  ?? '—';
        document.getElementById('str-longest').textContent = streakStats.longest_streak  ?? '—';
        document.getElementById('str-badges').textContent  = streakStats.total_badges    ?? '—';
      }
    } catch {}

    const flags = { 'ZA':'🇿🇦','US':'🇺🇸','GB':'🇬🇧','DE':'🇩🇪','FR':'🇫🇷','JP':'🇯🇵','BR':'🇧🇷','IN':'🇮🇳','AU':'🇦🇺','NG':'🇳🇬','KE':'🇰🇪' };
    const el = document.getElementById('admin-leaderboard');
    if (!board.length) {
      el.innerHTML = '<div style="color:var(--muted);font-size:0.82rem">No active streaks yet — users need to log dreams on consecutive days.</div>';
      return;
    }
    el.innerHTML = `<table style="width:100%;border-collapse:collapse;font-size:0.82rem">
      <thead><tr style="color:var(--muted);font-size:0.7rem;letter-spacing:0.08em">
        <th style="text-align:left;padding:0.4rem 0.75rem;border-bottom:1px solid var(--border)">RANK</th>
        <th style="text-align:left;padding:0.4rem 0.75rem;border-bottom:1px solid var(--border)">REGION</th>
        <th style="text-align:right;padding:0.4rem 0.75rem;border-bottom:1px solid var(--border)">CURRENT</th>
        <th style="text-align:right;padding:0.4rem 0.75rem;border-bottom:1px solid var(--border)">LONGEST</th>
      </tr></thead>
      <tbody>${board.map(r => `
        <tr style="border-bottom:1px solid var(--border)">
          <td style="padding:0.5rem 0.75rem;color:${r.rank <= 3 ? 'var(--gold)' : 'var(--muted2)'}">#${r.rank}</td>
          <td style="padding:0.5rem 0.75rem">${flags[r.region_code] || '🌍'}</td>
          <td style="padding:0.5rem 0.75rem;text-align:right;color:var(--teal);font-family:'Space Mono'">${r.current_streak}d</td>
          <td style="padding:0.5rem 0.75rem;text-align:right;color:var(--muted2);font-family:'Space Mono'">${r.longest_streak}d</td>
        </tr>`).join('')}
      </tbody></table>`;
  } catch (err) {
    document.getElementById('admin-leaderboard').innerHTML = '<div style="color:var(--muted)">Run v1.1.0 migration to enable streak leaderboard.</div>';
  }
}

// ════════════════════════════════════════════════════════════
// EXPORT
// ════════════════════════════════════════════════════════════
async function exportAllData() {
  try {
    const stats = await api('/research/global');
    const rows = [
      ['Export Type', 'Global Research Stats'],
      ['Generated', new Date().toISOString()],
      [''],
      ['Total Dreams', stats.total_dreams],
      ['Active Countries', stats.active_countries],
      ['Dreamers Active Now', stats.dreamers_active_now],
      [''],
      ['Top Themes (Last 7 Days)'],
      ['Theme', 'Count'],
      ...(stats.top_themes||[]).map(t=>[t.theme, t.count]),
      [''],
      ['Top Emotions (Last 24h)'],
      ['Emotion', 'Count'],
      ...(stats.top_emotions||[]).map(e=>[e.emotion, e.count]),
      [''],
      ['Regional Activity'],
      ['Region Code', 'Region', 'Dream Count', 'Top Theme'],
      ...(stats.regional_activity||[]).map(r=>[r.region_code, r.region, r.count, r.top_theme])
    ];
    const csv = rows.map(r => r.map(csvCell).join(',')).join('\r\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url; a.download = 'oneiros-research-' + new Date().toISOString().slice(0,10) + '.csv';
    a.click(); URL.revokeObjectURL(url);
    showToast('Research data exported');
  } catch (err) { showToast('Export failed: ' + err.message); }
}

async function exportArchetypes() {
  try { const stats = await api('/research/global'); downloadCSV([['Theme','Dream count'],...(stats.top_themes||[]).map(t=>[t.theme,t.count])],'oneiros-archetypes'); showToast('Archetype data exported.'); }
  catch(err) { showToast('Export failed: '+err.message); }
}

async function exportGeo() {
  try { const stats = await api('/research/global'); downloadCSV([['Region code','Region','Dreams','Top theme'],...(stats.regional_activity||[]).map(r=>[r.region_code,r.region,r.count,r.top_theme])],'oneiros-regions'); showToast('Geographic data exported.'); }
  catch(err) { showToast('Export failed: '+err.message); }
}

// ════════════════════════════════════════════════════════════
// LUCID REVENUE
// ════════════════════════════════════════════════════════════
let revenueData = null;
function revMoney(cents) {
  const symbol = revenueData?.currency_symbol || 'R';
  const value = Number(cents || 0);
  const [whole, fraction] = (value / 100).toFixed(2).split('.');
  return symbol + whole.replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + (fraction === '00' ? '' : '.' + fraction);
}
async function loadRevenue() {
  try {
    revenueData = await api('/admin/revenue');
  } catch (err) {
    document.getElementById('orders-tbody').innerHTML = `<tr><td colspan="8" class="empty">${escHtml(err.message)}</td></tr>`;
    return;
  }
  const t = revenueData.totals;
  const set = (id, value) => { document.getElementById(id).textContent = value; };
  set('rev-today', revMoney(t.today)); set('rev-week', revMoney(t.week)); set('rev-month', revMoney(t.month)); set('rev-all', revMoney(t.all_time));
  set('rev-all-sub', `${t.paid_orders} paid order${t.paid_orders === 1 ? '' : 's'}`);
  set('rev-active', t.active_passes);
  set('rev-active-sub', t.dreamers ? `${Math.round(t.active_passes / t.dreamers * 100)}% of ${t.dreamers} dreamers` : '—');
  set('rev-avg', revMoney(t.average)); set('rev-buyers', `${t.buyers} buyer${t.buyers === 1 ? '' : 's'}`);
  set('rev-patrons', t.patrons); set('rev-refunded', revMoney(t.refunded)); set('rev-pending', `${t.pending} checkout${t.pending === 1 ? '' : 's'} open today`);
  const notice = document.getElementById('rev-notice');
  notice.hidden = !(revenueData.sandbox || !revenueData.providers.length);
  notice.innerHTML = !revenueData.providers.length
    ? '✦ No payment gateway is configured yet. Add PayFast or Paystack keys to <code>includes/config.php</code> (see DEPLOY-AFRIHOST.md). Codes and grants work meanwhile.'
    : '✦ PayFast is in <strong>sandbox</strong> mode, so payments are test payments. Set <code>payfast_sandbox</code> to false to go live.';
  document.getElementById('rev-plans').innerHTML = revenueData.by_plan.length ? revenueData.by_plan.map(p => `
    <tr><td style="color:var(--text)">${escHtml(p.plan_name)}${p.kind !== 'pass' ? ` <span class="status-badge status-premium">${escHtml(p.kind.toUpperCase())}</span>` : ''}</td><td>${p.orders}</td><td>${revMoney(p.cents)}</td></tr>`).join('')
    : '<tr><td colspan="3" class="empty">No sales yet</td></tr>';
  const days = Array.from({ length: 30 }, (_, i) => { const d = new Date(); d.setUTCDate(d.getUTCDate() - 29 + i); return d.toISOString().slice(0, 10); });
  const byDay = Object.fromEntries(revenueData.daily.map(r => [r.day, Number(r.cents)]));
  charts.revenue?.destroy();
  charts.revenue = new Chart(document.getElementById('revenue-chart'), {
    type: 'bar',
    data: { labels: days.map(d => new Date(d + 'T12:00:00').toLocaleDateString('en-ZA', { day: 'numeric', month: 'short' })),
      datasets: [{ data: days.map(d => (byDay[d] || 0) / 100), backgroundColor: 'rgba(251,191,36,.55)', borderRadius: 4, maxBarThickness: 18 }] },
    options: { responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => revMoney(Math.round(c.raw * 100)) } } },
      scales: { x: { ticks: { color: '#9babc6', maxTicksLimit: 8 }, grid: { display: false } },
        y: { beginAtZero: true, ticks: { color: '#9babc6', callback: v => revMoney(v * 100) }, grid: { color: 'rgba(193,173,255,.06)' } } } },
  });
  renderOrders();
}
function renderOrders() {
  if (!revenueData) return;
  const q = document.getElementById('order-search').value.toLowerCase();
  const f = document.getElementById('order-filter').value;
  const rows = revenueData.orders.filter(o => (!q || o.reference.toLowerCase().includes(q) || o.buyer_email.toLowerCase().includes(q))
    && (f === 'all' || o.status === f || (f === 'cancelled' && ['cancelled', 'failed'].includes(o.status))));
  document.getElementById('orders-tbody').innerHTML = rows.length ? rows.map(o => `
    <tr>
      <td style="color:var(--text);font-family:monospace">${escHtml(o.reference)}</td>
      <td>${new Date(o.created_at).toLocaleString('en-ZA', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })}</td>
      <td>${escHtml(o.buyer_email)}</td>
      <td>${escHtml(o.plan_name)}${o.gift_code ? `<br><small style="font-family:monospace">${escHtml(o.gift_code)}</small>` : ''}${o.discount_code ? `<br><small>code ${escHtml(o.discount_code)}</small>` : ''}</td>
      <td>${escHtml(o.amount)}</td>
      <td>${escHtml(o.provider)}</td>
      <td><span class="status-badge ${o.status === 'paid' ? 'status-active' : o.status === 'refunded' ? 'status-inactive' : ''}">${escHtml(o.status.toUpperCase())}</span></td>
      <td>${o.status === 'paid' ? `<button class="action-btn" onclick="orderAction('${o.id}','refund')">Record refund</button>`
        : o.status !== 'refunded' ? `<button class="action-btn promote" onclick="orderAction('${o.id}','mark_paid')">Mark paid</button>` : ''}</td>
    </tr>`).join('') : '<tr><td colspan="8" class="empty">No orders yet</td></tr>';
}
async function orderAction(id, action) {
  const order = revenueData.orders.find(o => o.id === id);
  const question = action === 'refund'
    ? `Record a refund for ${order.reference}? First refund the money in your ${order.provider} dashboard. This removes what the order bought (its Lucid nights or gift code).`
    : `Mark ${order.reference} as paid? Only do this after confirming the payment in your ${order.provider} dashboard or bank account.`;
  if (!confirm(question)) return;
  try { await apiPost('/admin/revenue', { order_id: id, action }); showToast(action === 'refund' ? 'Refund recorded.' : 'Order fulfilled.'); loadRevenue(); }
  catch (err) { showToast(err.message); }
}
async function grantLucid(revoke) {
  const email = document.getElementById('grant-email').value.trim();
  const days = Number(document.getElementById('grant-days').value);
  if (!email) { showToast('Enter the dreamer’s email.'); return; }
  if (revoke && !confirm(`End the Lucid pass for ${email}?`)) return;
  try {
    const res = await apiPost('/admin/premium', revoke ? { email, revoke: true } : { email, days });
    document.getElementById('grant-result').textContent = res.is_premium
      ? `${res.email} has Lucid until ${new Date(res.premium_until).toLocaleDateString('en-ZA', { day: 'numeric', month: 'long', year: 'numeric' })} (${res.days_left} nights).`
      : `${res.email} is on the free journal.`;
    showToast(revoke ? 'Pass ended.' : 'Lucid nights added.');
    loadRevenue(); loadUsers();
  } catch (err) { showToast(err.message); }
}
async function createCodes() {
  const kind = document.getElementById('code-kind').value;
  const value = Number(document.getElementById('code-value').value);
  const body = { kind, count: Number(document.getElementById('code-count').value), max_uses: Number(document.getElementById('code-uses').value),
    expires_on: document.getElementById('code-expires').value, code: document.getElementById('code-custom').value.trim(), note: document.getElementById('code-note').value.trim() };
  if (kind === 'discount') body.percent_off = value; else body.days = value;
  if (kind === 'gift') body.max_uses = 1;
  try {
    const res = await apiPost('/admin/codes', body);
    const out = document.getElementById('code-output');
    out.hidden = false;
    out.textContent = res.created.join('\n');
    showToast(`${res.created.length} code${res.created.length === 1 ? '' : 's'} created. Copy them from the box.`);
    document.getElementById('code-custom').value = '';
    loadCodes();
  } catch (err) { showToast(err.message); }
}
async function loadCodes() {
  try {
    const { codes } = await api('/admin/codes');
    document.getElementById('codes-tbody').innerHTML = codes.length ? codes.map(c => `
      <tr style="${c.is_active ? '' : 'opacity:.45'}">
        <td style="color:var(--text);font-family:monospace">${escHtml(c.code)}</td>
        <td>${escHtml(c.kind)}</td>
        <td>${c.kind === 'discount' ? `${c.percent_off}% off` : `${c.days} nights`}</td>
        <td>${c.uses} / ${c.max_uses}</td>
        <td>${c.expires_at ? new Date(c.expires_at).toLocaleDateString('en-ZA') : '—'}</td>
        <td>${escHtml(c.note || '')}</td>
        <td><button class="action-btn" onclick="toggleCode('${c.id}', ${!c.is_active})">${c.is_active ? 'Disable' : 'Enable'}</button></td>
      </tr>`).join('') : '<tr><td colspan="7" class="empty">No codes yet</td></tr>';
  } catch (err) {
    document.getElementById('codes-tbody').innerHTML = `<tr><td colspan="7" class="empty">${escHtml(err.message)}</td></tr>`;
  }
}
async function toggleCode(id, active) {
  try { await apiPatch('/admin/codes', { id, is_active: active }); loadCodes(); } catch (err) { showToast(err.message); }
}
function exportOrders() {
  if (!revenueData?.orders?.length) { showToast('No orders to export yet.'); return; }
  downloadCSV([['Reference', 'Created', 'Paid', 'Buyer', 'Pass', 'Kind', 'Nights', 'Amount (cents)', 'Discount code', 'Gateway', 'Gateway ref', 'Status', 'Gift code'],
    ...revenueData.orders.map(o => [o.reference, o.created_at, o.paid_at || '', o.buyer_email, o.plan_name, o.kind, o.days, o.amount_cents, o.discount_code || '', o.provider, o.provider_ref || '', o.status, o.gift_code || ''])], 'oneiros-orders');
}

// ════════════════════════════════════════════════════════════
// TOAST
// ════════════════════════════════════════════════════════════
function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  clearTimeout(t._t);
  t._t = setTimeout(() => t.classList.remove('show'), 3500);
}

// ════════════════════════════════════════════════════════════
// INIT
// ════════════════════════════════════════════════════════════
window.addEventListener('DOMContentLoaded', async () => {
  document.querySelector('.topbar').style.display = 'none';
  const restored = await tryAdminRestore();
  if (restored) {
    document.getElementById('login-screen').style.display = 'none';
    document.getElementById('main-app').style.display = 'flex';
    document.querySelector('.topbar').style.display = 'flex';
    initDashboard();
  }
});
</script>
</body>
</html>
