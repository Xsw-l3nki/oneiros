<?php
// Moderation panel — security headers
if (file_exists(__DIR__ . '/includes/security.php')) {
    require_once __DIR__ . '/includes/security.php';
    Security::headers();
}
header('X-Robots-Tag: noindex, nofollow');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Oneiros — Moderation Queue</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root {
  --cream:     #FAF7F2;
  --surface:   #FFFFFF;
  --lavender:  #E2D9F3;
  --blush:     #F2E8F0;
  --indigo:    #2D1B4E;
  --indigo-mid:#4A3270;
  --indigo-light:#7B68AA;
  --border:    rgba(167,139,202,0.2);
  --border2:   rgba(167,139,202,0.35);
  --glass:     rgba(255,255,255,0.5);
  --rose:      #C9849A;
  --rose-deep: #9B3A5A;
  --gold:      #C8A97A;
  --gold-deep: #8B6A2E;
  --green:     #5B9E7A;
  --green-deep:#2D6B4A;
  --danger-bg: rgba(201,132,154,0.08);
  --warn-bg:   rgba(200,169,122,0.08);
  --ok-bg:     rgba(91,158,122,0.08);
  --muted:     #94A0B8;
  --text:      #2D1B4E;
}

* { margin:0; padding:0; box-sizing:border-box; }

body {
  font-family: 'Jost', sans-serif;
  background: var(--cream);
  color: var(--text);
  min-height: 100vh;
}

/* ── BACKGROUND ── */
.bg-orbs { position:fixed; inset:0; pointer-events:none; z-index:0; overflow:hidden; }
.orb { position:absolute; border-radius:50%; filter:blur(90px); opacity:0.2; }
.orb1 { width:600px;height:600px;background:radial-gradient(circle,#D9C8F0,transparent);top:-200px;left:-200px; }
.orb2 { width:400px;height:400px;background:radial-gradient(circle,#F0D9E8,transparent);bottom:-100px;right:-100px; }

/* ── NAV ── */
nav {
  position: sticky; top:0; z-index:100;
  background: rgba(250,247,242,0.88);
  backdrop-filter: blur(20px);
  border-bottom: 1px solid var(--border);
  padding: 0 2rem; height: 60px;
  display: flex; align-items:center; justify-content:space-between;
}
.nav-brand { display:flex; align-items:center; gap:0.75rem; }
.nav-logo {
  font-family: 'Cormorant Garamond', serif;
  font-size: 1.4rem; font-weight:600; color:var(--indigo);
}
.nav-logo span { color:var(--indigo-light); }
.nav-divider { width:1px; height:20px; background:var(--border); }
.nav-role {
  font-size:0.72rem; letter-spacing:0.15em; text-transform:uppercase;
  color:var(--indigo-light); font-weight:500;
}
.nav-right { display:flex; align-items:center; gap:1rem; }
.nav-stat {
  display:flex; align-items:center; gap:0.4rem;
  font-size:0.8rem; color:var(--indigo-mid);
}
.nav-stat-num {
  font-family:'Cormorant Garamond',serif; font-size:1.1rem;
  font-weight:600; color:var(--indigo);
}
.nav-user-chip {
  display:flex; align-items:center; gap:0.5rem;
  padding:0.3rem 0.8rem; border-radius:2rem;
  border:1px solid var(--border); background:var(--glass);
  font-size:0.78rem; color:var(--indigo-mid);
}
.nav-avatar {
  width:26px; height:26px; border-radius:50%;
  background:linear-gradient(135deg,var(--lavender),var(--blush));
  display:flex; align-items:center; justify-content:center;
  font-size:0.65rem; font-weight:600; color:var(--indigo-mid);
}
.btn-logout {
  background:none; border:1px solid var(--border); border-radius:2rem;
  padding:0.35rem 0.9rem; font-family:'Jost',sans-serif; font-size:0.75rem;
  color:var(--indigo-light); cursor:pointer; transition:all 0.2s;
}
.btn-logout:hover { color:var(--rose); border-color:var(--rose); }

/* ── LOGIN ── */
#login-screen {
  position:fixed; inset:0; z-index:200;
  background:var(--cream);
  display:flex; align-items:center; justify-content:center;
  flex-direction:column; gap:0;
}
.login-card {
  width:400px; background:var(--surface);
  border:1px solid var(--border2); border-radius:1.5rem;
  padding:2.5rem; box-shadow:0 20px 60px rgba(45,27,78,0.08);
  position:relative;
}
.login-card::before {
  content:''; position:absolute; top:-1px; left:25%; right:25%;
  height:2px; border-radius:1px;
  background:linear-gradient(90deg,transparent,var(--indigo-light),transparent);
}
.login-eyebrow {
  font-size:0.68rem; letter-spacing:0.2em; text-transform:uppercase;
  color:var(--indigo-light); margin-bottom:0.5rem;
}
.login-title {
  font-family:'Cormorant Garamond',serif; font-size:2rem;
  font-weight:400; color:var(--indigo); margin-bottom:0.3rem;
}
.login-sub { font-size:0.82rem; color:var(--muted); margin-bottom:2rem; line-height:1.6; }
.form-field { margin-bottom:1.1rem; }
.form-label {
  display:block; font-size:0.7rem; letter-spacing:0.12em;
  text-transform:uppercase; color:var(--indigo-mid); margin-bottom:0.4rem;
}
.form-input {
  width:100%; background:rgba(250,247,242,0.8);
  border:1.5px solid var(--border); border-radius:0.75rem;
  padding:0.7rem 1rem; font-family:'Jost',sans-serif;
  font-size:0.88rem; color:var(--text); outline:none;
  transition:border-color 0.2s;
}
.form-input:focus { border-color:rgba(167,139,202,0.5); background:#fff; }
.login-btn {
  width:100%; background:var(--indigo); color:#fff; border:none;
  cursor:pointer; font-family:'Jost',sans-serif; font-size:0.82rem;
  font-weight:600; letter-spacing:0.1em; text-transform:uppercase;
  padding:0.8rem; border-radius:2rem; transition:all 0.2s; margin-top:0.5rem;
}
.login-btn:hover { background:var(--indigo-mid); transform:translateY(-1px); }
.login-error {
  font-size:0.78rem; color:var(--rose-deep); margin-top:0.75rem;
  text-align:center; min-height:1.2rem;
}
.login-notice {
  margin-top:1.5rem; padding:0.9rem 1rem;
  background:rgba(226,217,243,0.3); border:1px solid var(--border);
  border-radius:0.75rem; font-size:0.75rem; color:var(--indigo-mid); line-height:1.6;
}
.login-notice strong { color:var(--indigo); }

/* ── MAIN LAYOUT ── */
.app { position:relative; z-index:1; max-width:1100px; margin:0 auto; padding:2rem 1.5rem 6rem; }

/* ── STATS BAR ── */
.stats-bar {
  display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; margin-bottom:2rem;
}
.stat-card {
  background:var(--glass); backdrop-filter:blur(10px);
  border:1px solid var(--border); border-radius:1rem; padding:1.1rem 1.2rem;
  position:relative; overflow:hidden;
}
.stat-card::before {
  content:''; position:absolute; top:0; left:0; right:0; height:2px;
}
.stat-card.pending::before  { background:var(--rose); }
.stat-card.reviewed::before { background:var(--green); }
.stat-card.today::before    { background:var(--gold); }
.stat-card.streak::before   { background:var(--indigo-light); }
.stat-card-label {
  font-size:0.68rem; letter-spacing:0.15em; text-transform:uppercase;
  color:var(--muted); margin-bottom:0.5rem;
}
.stat-card-value {
  font-family:'Cormorant Garamond',serif; font-size:2rem;
  font-weight:600; line-height:1;
}
.stat-card.pending  .stat-card-value { color:var(--rose-deep); }
.stat-card.reviewed .stat-card-value { color:var(--green-deep); }
.stat-card.today    .stat-card-value { color:var(--gold-deep); }
.stat-card.streak   .stat-card-value { color:var(--indigo); }
.stat-card-sub { font-size:0.72rem; color:var(--muted); margin-top:0.3rem; }

/* ── FILTER BAR ── */
.filter-bar {
  display:flex; align-items:center; gap:0.75rem; margin-bottom:1.5rem;
  flex-wrap:wrap;
}
.filter-title {
  font-family:'Cormorant Garamond',serif; font-size:1.4rem;
  font-weight:600; color:var(--indigo); flex:1;
}
.filter-pills { display:flex; gap:0.4rem; flex-wrap:wrap; }
.pill {
  background:rgba(255,255,255,0.5); border:1.5px solid var(--border);
  border-radius:2rem; padding:0.35rem 0.9rem; cursor:pointer;
  font-size:0.75rem; font-family:'Jost',sans-serif; color:var(--indigo-mid);
  transition:all 0.2s; font-weight:500;
}
.pill:hover { background:var(--lavender); }
.pill.active { background:var(--indigo); color:#fff; border-color:var(--indigo); }
.pill-count {
  display:inline-block; background:rgba(255,255,255,0.25);
  border-radius:1rem; padding:0 0.4rem; font-size:0.65rem; margin-left:0.2rem;
}
.pill.active .pill-count { background:rgba(255,255,255,0.2); }

/* ── QUEUE ── */
.queue { display:flex; flex-direction:column; gap:1.2rem; }

/* ── FLAG CARD ── */
.flag-card {
  background:var(--surface);
  border:1px solid var(--border); border-radius:1.25rem;
  overflow:hidden; transition:all 0.3s;
  animation:cardIn 0.35s cubic-bezier(0.34,1.4,0.64,1) both;
}
@keyframes cardIn {
  from { opacity:0; transform:translateY(12px); }
  to   { opacity:1; transform:translateY(0); }
}
.flag-card.removing {
  opacity:0; transform:translateX(20px) scale(0.98);
  transition:all 0.3s ease;
}
.flag-card.resolved { border-color:rgba(91,158,122,0.3); background:rgba(91,158,122,0.02); }

/* Card header */
.flag-header {
  display:flex; align-items:center; gap:0.9rem;
  padding:1rem 1.3rem; border-bottom:1px solid var(--border);
  background:rgba(250,247,242,0.5);
}
.flag-type-badge {
  display:flex; align-items:center; gap:0.4rem;
  padding:0.3rem 0.8rem; border-radius:2rem;
  font-size:0.72rem; font-weight:600; letter-spacing:0.06em;
  text-transform:uppercase; flex-shrink:0;
}
.badge-inappropriate { background:var(--danger-bg); color:var(--rose-deep); border:1px solid rgba(201,132,154,0.3); }
.badge-harmful       { background:rgba(180,60,60,0.08); color:#8B2020; border:1px solid rgba(180,60,60,0.25); }
.badge-spam          { background:var(--warn-bg); color:var(--gold-deep); border:1px solid rgba(200,169,122,0.3); }
.badge-personal_info { background:rgba(99,130,255,0.08); color:#3040A0; border:1px solid rgba(99,130,255,0.25); }
.badge-other         { background:rgba(147,139,202,0.1); color:var(--indigo-mid); border:1px solid var(--border); }
.flag-meta { flex:1; min-width:0; }
.flag-meta-top {
  display:flex; align-items:center; gap:0.6rem; flex-wrap:wrap;
}
.flag-content-type {
  font-size:0.72rem; color:var(--indigo-mid); font-weight:500;
}
.flag-id {
  font-size:0.68rem; color:var(--muted); font-family:'Jost',monospace;
}
.flag-time { font-size:0.72rem; color:var(--muted); }
.flag-reporter { font-size:0.72rem; color:var(--muted); margin-top:0.15rem; }
.flag-count-chip {
  background:var(--danger-bg); color:var(--rose-deep);
  font-size:0.68rem; padding:0.15rem 0.5rem; border-radius:1rem;
  font-weight:600; flex-shrink:0;
}
.expand-btn {
  background:none; border:none; cursor:pointer; color:var(--muted);
  font-size:1rem; padding:0.25rem; transition:all 0.2s; flex-shrink:0;
}
.expand-btn:hover { color:var(--indigo); }

/* Card body */
.flag-body { padding:1.3rem; }

.content-section { margin-bottom:1.2rem; }
.content-section-label {
  font-size:0.68rem; letter-spacing:0.15em; text-transform:uppercase;
  color:var(--muted); margin-bottom:0.5rem;
}
.dream-title {
  font-family:'Cormorant Garamond',serif; font-size:1.15rem;
  font-weight:600; color:var(--indigo); margin-bottom:0.4rem;
}
.dream-content {
  font-size:0.85rem; color:var(--indigo-mid); line-height:1.75;
  padding:1rem 1.1rem; border-radius:0.75rem;
  background:rgba(250,247,242,0.7);
  border-left:3px solid var(--border2);
}
.dream-content.truncated { display:-webkit-box; -webkit-line-clamp:4; -webkit-box-orient:vertical; overflow:hidden; }
.dream-tags { display:flex; gap:0.4rem; flex-wrap:wrap; margin-top:0.75rem; }
.dream-tag {
  font-size:0.7rem; background:var(--lavender); color:var(--indigo-mid);
  padding:0.2rem 0.6rem; border-radius:1rem;
}

/* Flag reporter notes */
.reporter-notes {
  font-size:0.8rem; color:var(--indigo-mid); font-style:italic;
  padding:0.75rem 1rem; border-radius:0.6rem;
  background:rgba(200,169,122,0.06); border:1px solid rgba(200,169,122,0.2);
}

/* Privacy bar */
.privacy-bar {
  display:flex; align-items:center; gap:0.75rem;
  font-size:0.75rem; color:var(--muted);
  padding:0.6rem 0;
  border-top:1px solid var(--border); margin-top:1rem;
}
.privacy-pill {
  background:rgba(226,217,243,0.4); color:var(--indigo-mid);
  padding:0.15rem 0.6rem; border-radius:1rem; font-size:0.7rem;
}

/* Card footer */
.flag-footer {
  padding:1rem 1.3rem;
  border-top:1px solid var(--border);
  background:rgba(250,247,242,0.4);
  display:flex; align-items:center; justify-content:space-between;
  gap:1rem; flex-wrap:wrap;
}
.flag-guidelines {
  font-size:0.72rem; color:var(--muted); flex:1;
  line-height:1.5;
}
.action-group { display:flex; gap:0.5rem; flex-wrap:wrap; }

/* Action buttons */
.action-btn {
  display:flex; align-items:center; gap:0.4rem;
  padding:0.55rem 1.1rem; border-radius:2rem; cursor:pointer;
  font-family:'Jost',sans-serif; font-size:0.78rem; font-weight:600;
  letter-spacing:0.04em; border:1.5px solid; transition:all 0.2s;
  white-space:nowrap;
}
.action-btn:hover { transform:translateY(-1px); }
.action-btn:active { transform:translateY(0) scale(0.98); }
.action-btn:disabled { opacity:0.5; cursor:not-allowed; transform:none; }

.btn-dismiss {
  background:var(--ok-bg); color:var(--green-deep);
  border-color:rgba(91,158,122,0.35);
}
.btn-dismiss:hover { background:rgba(91,158,122,0.14); }

.btn-warn {
  background:var(--warn-bg); color:var(--gold-deep);
  border-color:rgba(200,169,122,0.35);
}
.btn-warn:hover { background:rgba(200,169,122,0.14); }

.btn-hide {
  background:rgba(74,50,112,0.06); color:var(--indigo-mid);
  border-color:var(--border2);
}
.btn-hide:hover { background:rgba(74,50,112,0.12); }

.btn-remove {
  background:var(--danger-bg); color:var(--rose-deep);
  border-color:rgba(201,132,154,0.35);
}
.btn-remove:hover { background:rgba(201,132,154,0.16); }

.btn-escalate {
  background:rgba(139,32,32,0.06); color:#8B2020;
  border-color:rgba(139,32,32,0.2);
}
.btn-escalate:hover { background:rgba(139,32,32,0.12); }

/* ── RESOLVED CARD ── */
.resolved-bar {
  display:flex; align-items:center; gap:0.6rem;
  padding:0.9rem 1.3rem;
  font-size:0.8rem; color:var(--green-deep);
  background:rgba(91,158,122,0.05);
}
.resolved-icon { font-size:1.1rem; }

/* ── EMPTY STATE ── */
.empty-state {
  text-align:center; padding:5rem 2rem;
  display:flex; flex-direction:column; align-items:center; gap:1rem;
}
.empty-icon { font-size:3rem; opacity:0.25; }
.empty-title {
  font-family:'Cormorant Garamond',serif; font-size:1.8rem;
  font-style:italic; color:var(--indigo-light);
}
.empty-sub { font-size:0.85rem; color:var(--muted); max-width:360px; line-height:1.7; }

/* ── CONFIRM MODAL ── */
.modal-overlay {
  display:none; position:fixed; inset:0; z-index:300;
  background:rgba(45,27,78,0.35); backdrop-filter:blur(6px);
  align-items:center; justify-content:center;
}
.modal-overlay.open { display:flex; }
.modal {
  background:var(--surface); border:1px solid var(--border2);
  border-radius:1.5rem; padding:2rem; width:100%; max-width:420px;
  animation:cardIn 0.25s cubic-bezier(0.34,1.4,0.64,1) both;
}
.modal-icon { font-size:2rem; margin-bottom:0.75rem; }
.modal-title {
  font-family:'Cormorant Garamond',serif; font-size:1.6rem;
  font-weight:600; color:var(--indigo); margin-bottom:0.4rem;
}
.modal-body { font-size:0.85rem; color:var(--indigo-mid); line-height:1.7; margin-bottom:1.5rem; }
.modal-note-label {
  font-size:0.7rem; letter-spacing:0.12em; text-transform:uppercase;
  color:var(--muted); display:block; margin-bottom:0.4rem;
}
.modal-textarea {
  width:100%; background:rgba(250,247,242,0.8);
  border:1.5px solid var(--border); border-radius:0.75rem;
  padding:0.7rem 0.9rem; font-family:'Jost',sans-serif;
  font-size:0.85rem; color:var(--text); resize:none;
  outline:none; margin-bottom:1.2rem; line-height:1.6;
}
.modal-textarea:focus { border-color:rgba(167,139,202,0.5); }
.modal-actions { display:flex; gap:0.6rem; justify-content:flex-end; }
.modal-cancel {
  background:none; border:1.5px solid var(--border); border-radius:2rem;
  padding:0.55rem 1.2rem; font-family:'Jost',sans-serif; font-size:0.78rem;
  color:var(--indigo-mid); cursor:pointer; transition:all 0.2s;
}
.modal-cancel:hover { background:var(--lavender); }
.modal-confirm {
  border:none; border-radius:2rem; padding:0.55rem 1.4rem;
  font-family:'Jost',sans-serif; font-size:0.78rem; font-weight:600;
  cursor:pointer; transition:all 0.2s;
}
.modal-confirm.danger { background:var(--rose-deep); color:#fff; }
.modal-confirm.warn   { background:var(--gold-deep); color:#fff; }
.modal-confirm.ok     { background:var(--green-deep); color:#fff; }
.modal-confirm:hover  { opacity:0.88; }

/* ── TOAST ── */
.toast {
  display:none; position:fixed; bottom:2rem; left:50%;
  transform:translateX(-50%);
  background:var(--indigo); color:#fff; border-radius:2rem;
  padding:0.7rem 1.6rem; font-size:0.82rem; z-index:400;
  box-shadow:0 8px 30px rgba(45,27,78,0.25);
  animation:toastIn 0.3s cubic-bezier(0.34,1.4,0.64,1) both;
  white-space:nowrap;
}
.toast.show { display:block; }
.toast.success { background:var(--green-deep); }
.toast.warn    { background:var(--gold-deep); }
.toast.error   { background:var(--rose-deep); }
@keyframes toastIn { from{opacity:0;transform:translateX(-50%) translateY(8px);}to{opacity:1;transform:translateX(-50%) translateY(0);} }

/* ── LOADING ── */
.loading-state { text-align:center; padding:4rem; color:var(--muted); }
.loading-state::after { content:' '; animation:ellipsis 1.4s steps(4,end) infinite; }
@keyframes ellipsis { 0%{content:'.'}33%{content:'..'}66%{content:'...'}100%{content:''} }

/* ── GUIDELINES PANEL ── */
.guidelines-panel {
  background:var(--surface); border:1px solid var(--border);
  border-radius:1.25rem; padding:1.5rem; margin-bottom:1.5rem;
  display:none;
}
.guidelines-panel.open { display:block; }
.guidelines-toggle {
  display:flex; align-items:center; justify-content:space-between;
  cursor:pointer; padding:0 0 0.5rem;
}
.guidelines-toggle-title {
  font-size:0.8rem; font-weight:600; color:var(--indigo-mid);
  letter-spacing:0.06em; text-transform:uppercase;
}
.guidelines-content { display:none; }
.guidelines-panel.open .guidelines-content { display:block; margin-top:1rem; }
.guideline-row {
  display:flex; gap:0.75rem; align-items:flex-start;
  padding:0.5rem 0; border-bottom:1px solid var(--border); font-size:0.8rem;
}
.guideline-row:last-child { border:none; }
.guideline-action {
  width:100px; flex-shrink:0; font-weight:600; font-size:0.72rem;
  text-transform:uppercase; letter-spacing:0.06em;
}
.guideline-action.dismiss { color:var(--green-deep); }
.guideline-action.warn    { color:var(--gold-deep); }
.guideline-action.remove  { color:var(--rose-deep); }
.guideline-action.escalate { color:#8B2020; }
.guideline-desc { color:var(--indigo-mid); line-height:1.6; }

/* ── SCROLLBAR ── */
::-webkit-scrollbar { width:5px; }
::-webkit-scrollbar-track { background:transparent; }
::-webkit-scrollbar-thumb { background:var(--border2); border-radius:3px; }

/* ── MOBILE RESPONSIVE ── */
@media (max-width: 900px) {
  nav { padding: 0 1rem; height: 56px; flex-wrap: wrap; }
  .nav-brand { gap: 0.5rem; }
  .nav-logo { font-size: 1.1rem; }
  .nav-divider { display: none; }
  .nav-role { font-size: 0.62rem; }
  .nav-right { gap: 0.5rem; }
  .nav-stat:nth-of-type(2) { display: none; }
  .nav-user-chip { padding: 0.25rem 0.6rem; font-size: 0.7rem; }

  .app { padding: 1rem 0.75rem 5rem; }

  .stats-bar { grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
  .stat-card { padding: 0.9rem 1rem; }
  .stat-card-value { font-size: 1.4rem; }

  .filter-bar { flex-direction: column; align-items: stretch; }
  .filter-pills { width: 100%; }

  .flag-header { flex-wrap: wrap; padding: 0.85rem 1rem; }
  .flag-meta-top { font-size: 0.7rem; }
  .flag-time { font-size: 0.7rem; margin-left: auto; }

  .flag-body { padding: 1rem; }
  .flag-footer { padding: 0.85rem 1rem; }
  .action-group { width: 100%; gap: 0.4rem; }
  .action-btn { font-size: 0.72rem; padding: 0.5rem 0.85rem; flex: 1; min-width: 0; justify-content: center; }
  .flag-guidelines { font-size: 0.7rem; }

  .login-card { width: calc(100vw - 2rem); padding: 2rem 1.5rem; }
}
</style>
<link rel="stylesheet" href="assets/admin.css?v=2.0">
<script src="assets/admin.js?v=2.0" defer></script>
</head>
<body class="moderation-page">
<a class="skip-link" href="#main-app">Skip to workspace</a>

<div class="bg-orbs">
  <div class="orb orb1"></div>
  <div class="orb orb2"></div>
</div>

<!-- ── LOGIN ── -->
<div id="login-screen">
  <div class="login-universe" aria-hidden="true">
    <a class="universe-brand" href="./">oneiros<small>A universe within you</small></a>
    <div class="dream-sphere"></div>
    <h2>Care for the world<br>we <em>dream together.</em></h2>
    <p>A quiet space to understand our collective imagination and care for the people behind it.</p>
  </div>
  <div class="login-card">
    <div class="login-eyebrow">The care studio / Moderation</div>
    <div class="login-title">A little care.<br>A better dreamspace.</div>
    <div class="login-sub">Sign in to help keep our collective dreamspace thoughtful, safe, and open.</div>
    <div class="form-field">
      <label class="form-label">Email</label>
      <input class="form-input" id="login-email" type="email" placeholder="moderator@example.com">
    </div>
    <div class="form-field">
      <label class="form-label">Password</label>
      <input class="form-input" id="login-password" type="password" placeholder="••••••••"
        onkeydown="if(event.key==='Enter') doLogin()">
    </div>
    <button class="login-btn" onclick="doLogin()">Enter the care studio →</button>
    <div class="login-error" id="login-error" role="alert"></div>
    <a class="login-return" href="./">← Return to Oneiros</a>
    <div class="login-notice">
      <strong>Confidentiality reminder:</strong> All content you review is private dream data. You are bound by your moderator agreement. Do not share, screenshot, or discuss flagged content outside this platform.
    </div>
  </div>
</div>

<!-- ── NAV ── -->
<nav id="main-nav" style="display:none">
  <div class="nav-brand">
    <a class="nav-logo" href="./">one<span>iros</span></a>
    <div class="nav-divider"></div>
    <div class="nav-role">The care studio</div>
  </div>
  <div class="nav-right">
    <div class="nav-stat">
      <span class="nav-stat-num" id="nav-pending">—</span>
      <span>pending</span>
    </div>
    <div class="nav-stat">
      <span class="nav-stat-num" id="nav-resolved-today">—</span>
      <span>this session</span>
    </div>
    <div class="nav-user-chip">
      <div class="nav-avatar" id="nav-avatar">✧</div>
      <span id="nav-mod-name">Moderator</span>
    </div>
    <button class="btn-logout" onclick="doLogout()">Sign out</button>
  </div>
</nav>

<!-- ── CONFIRM MODAL ── -->
<div class="modal-overlay" id="modal-overlay">
  <div class="modal">
    <div class="modal-icon" id="modal-icon">⚠️</div>
    <div class="modal-title" id="modal-title">Confirm action</div>
    <div class="modal-body" id="modal-body"></div>
    <label class="modal-note-label" id="modal-note-label" style="display:none">Moderator note (optional)</label>
    <textarea class="modal-textarea" id="modal-note" rows="3" placeholder="Add a note for the record..." style="display:none"></textarea>
    <div class="modal-actions">
      <button class="modal-cancel" onclick="closeModal()">Cancel</button>
      <button class="modal-confirm" id="modal-confirm-btn" onclick="executeAction()">Confirm</button>
    </div>
  </div>
</div>

<!-- ── TOAST ── */
<div class="toast" id="toast" role="status" aria-live="polite"></div>

<!-- ── MAIN APP ── -->
<div class="app" id="main-app" style="display:none">
  <div class="observatory-intro">
    <span class="observatory-eyebrow">Oneiros / Community care</span>
    <h1>A softer place<br>to <em>share our dreams.</em></h1>
    <p>Behind every dream is a person. Review with curiosity, kindness, and a little room for the unexpected.</p>
    <div class="review-actions"><button class="refresh-queue" onclick="loadQueue()">↻ Refresh queue</button><a class="intro-action" href="./">Return to your dreamspace ↗</a></div>
  </div>

  <!-- STATS BAR -->
  <div class="stats-bar">
    <div class="stat-card pending">
      <div class="stat-card-label">Pending Review</div>
      <div class="stat-card-value" id="stat-pending">—</div>
      <div class="stat-card-sub">Awaiting your decision</div>
    </div>
    <div class="stat-card reviewed">
      <div class="stat-card-label">Resolved this session</div>
      <div class="stat-card-value" id="stat-today">0</div>
      <div class="stat-card-sub">In this session</div>
    </div>
    <div class="stat-card today">
      <div class="stat-card-label">Avg Review Time</div>
      <div class="stat-card-value" id="stat-avg">—</div>
      <div class="stat-card-sub">Minutes per item</div>
    </div>
    <div class="stat-card streak">
      <div class="stat-card-label">Resolved in queue</div>
      <div class="stat-card-value" id="stat-total">—</div>
      <div class="stat-card-sub">In the current queue</div>
    </div>
  </div>

  <!-- GUIDELINES (collapsible) -->
  <div class="guidelines-panel open" id="guidelines-panel">
    <div class="guidelines-toggle" aria-expanded="true" onclick="toggleGuidelines()">
      <div class="guidelines-toggle-title">📋 Moderation Guidelines — click to collapse</div>
    </div>
    <div class="guidelines-content">
      <div class="guideline-row">
        <div class="guideline-action dismiss">Dismiss</div>
        <div class="guideline-desc">Content does not violate any rule. Flag was raised in error or is a matter of personal taste. Dream remains visible and in the matching pool.</div>
      </div>
      <div class="guideline-row">
        <div class="guideline-action warn">Add Warning</div>
        <div class="guideline-desc">Content is disturbing but not prohibited — e.g. vivid nightmares, violent imagery that is clearly dream content. Adds a content warning label before the entry. Dream remains in matching pool.</div>
      </div>
      <div class="guideline-row">
        <div class="guideline-action remove">Hide from Matching</div>
        <div class="guideline-desc">Content violates guidelines but is not severe enough for full removal. Dream is removed from the public matching pool but remains in user's private journal. User receives a warning notification.</div>
      </div>
      <div class="guideline-row">
        <div class="guideline-action remove">Remove</div>
        <div class="guideline-desc">Content clearly violates the Terms of Service — hate speech, targeted harassment, self-harm instructions, or detailed harmful content. Entry is fully removed. The action is recorded for administrator review.</div>
      </div>
      <div class="guideline-row">
        <div class="guideline-action escalate">Escalate</div>
        <div class="guideline-desc">Content may constitute CSAM or contains a credible threat to a real person. Do NOT action this yourself — escalate to admin immediately. Content is automatically hidden pending admin review.</div>
      </div>
    </div>
  </div>

  <!-- FILTER BAR -->
  <div class="filter-bar">
    <div class="filter-title">Review Queue</div>
    <div class="filter-pills">
      <div class="pill active" onclick="setFilter('pending',this)">
        Pending <span class="pill-count" id="pill-pending">—</span>
      </div>
      <div class="pill" onclick="setFilter('all',this)">All</div>
      <div class="pill" onclick="setFilter('inappropriate',this)">Inappropriate</div>
      <div class="pill" onclick="setFilter('harmful',this)">Harmful</div>
      <div class="pill" onclick="setFilter('spam',this)">Spam</div>
      <div class="pill" onclick="setFilter('personal_info',this)">Personal Info</div>
      <div class="pill" onclick="setFilter('dismissed',this)">Dismissed</div>
      <div class="pill" onclick="setFilter('actioned',this)">Actioned</div>
    </div>
  </div>

  <!-- QUEUE -->
  <div class="queue" id="queue-container">
    <div class="loading-state">Loading queue</div>
  </div>

</div>

<script>
// ════════════════════════════════════════════════════════════
// CONFIG
// ════════════════════════════════════════════════════════════
const APP_BASE = new URL('.', window.location.href);
const API_BASE = new URL('api', APP_BASE).href;

// ── STATE ──
let accessToken    = null;
let currentUser    = null;
let allFlags       = [];
let currentFilter  = 'pending';
let sessionResolved = 0;
let sessionTimes   = [];
let sessionStart   = null;
let pendingAction  = null;  // { flagId, action, dreamId }
let reviewStarts   = {};    // flagId → timestamp when card was rendered
let useRewriteFallback = true;
let queueTimer = null;

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
    const res = await fetch(buildApiUrl('/auth/login'), {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({email,password})});
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Sign in failed. Please try again.');
    if (!data.user?.is_admin && !data.user?.is_moderator) throw new Error('This workspace requires a moderator account.');
    accessToken = data.access_token; currentUser = data.user;
    localStorage.setItem('oneiros_mod_rt', data.refresh_token);
    document.getElementById('login-password').value = '';
    onLoginSuccess();
  } catch(err) { errEl.textContent = err instanceof SyntaxError ? 'The service is unavailable. Please try again shortly.' : err.message; }
  finally { button.disabled = false; button.textContent = 'Enter the care studio ?'; }
}

async function tryRestore() {
  const rt = localStorage.getItem('oneiros_mod_rt');
  if (!rt) return false;
  for (const fallback of [false, true]) {
    useRewriteFallback = fallback;
    try {
      const res  = await fetch(buildApiUrl('/auth/refresh'), {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ refresh_token: rt })
      });
      if (res.status === 404) continue;
      const data = await res.json();
      if (!res.ok || (!data.user?.is_moderator && !data.user?.is_admin)) return false;
      accessToken = data.access_token;
      currentUser = data.user;
      localStorage.setItem('oneiros_mod_rt', data.refresh_token);
      return true;
    } catch {}
  }
  return false;
}

function onLoginSuccess() {
  document.getElementById('login-screen').style.display = 'none';
  document.getElementById('main-nav').style.display = 'flex';
  document.getElementById('main-app').style.display = 'block';

  // Set nav info
  const name = currentUser.display_name || currentUser.email.split('@')[0];
  document.getElementById('nav-mod-name').textContent = name;
  document.getElementById('nav-avatar').textContent = name.slice(0,2).toUpperCase();

  sessionStart = Date.now();
  loadQueue();
  clearInterval(queueTimer);
  queueTimer = setInterval(refreshPending, 30000);
}

function doLogout() {
  const refresh_token = localStorage.getItem('oneiros_mod_rt');
  if (refresh_token) fetch(buildApiUrl('/auth/logout'), {method:'POST',headers:{'Content-Type':'application/json','Authorization':'Bearer '+accessToken},body:JSON.stringify({refresh_token})}).catch(()=>{});
  clearInterval(queueTimer);
  localStorage.removeItem('oneiros_mod_rt');
  accessToken = null; currentUser = null;
  sessionResolved = 0; sessionTimes = []; reviewStarts = {};
  document.getElementById('main-nav').style.display = 'none';
  document.getElementById('main-app').style.display = 'none';
  document.getElementById('login-screen').style.display = 'flex';
  closeModal();
}

// ════════════════════════════════════════════════════════════
// QUEUE LOADING
// ════════════════════════════════════════════════════════════
async function loadQueue() {
  const button = document.querySelector('.refresh-queue');
  if (button) { button.disabled = true; button.textContent = 'Refreshing?'; }
  try { const data = await apiGet('/admin/flags'); allFlags = data.flags || []; updateStats(); renderQueue(); }
  catch(err) {
    document.getElementById('queue-container').innerHTML = '<div class="empty-state"><div class="empty-icon">☾</div><div class="empty-title">A moment of quiet</div><div class="empty-sub">The review queue could not be loaded. Check your connection and try again.</div><button class="refresh-queue" onclick="loadQueue()">Try again</button></div>';
    showToast('Queue unavailable: ' + err.message, 'error');
  } finally { if (button) { button.disabled = false; button.textContent = '↻ Refresh queue'; } }
}

async function refreshPending() {
  if (!accessToken || document.hidden || pendingAction) return;
  try { const data = await apiGet('/admin/flags'); allFlags = data.flags || []; updateStats(); if (currentFilter === 'pending') renderQueue(); } catch {}
}

function updateStats() {
  const pending = allFlags.filter(f => f.status === 'pending').length;
  const resolved = allFlags.filter(f => f.status !== 'pending').length;
  document.getElementById('stat-pending').textContent = pending;
  document.getElementById('stat-today').textContent = sessionResolved;
  document.getElementById('stat-total').textContent = resolved;
  document.getElementById('nav-pending').textContent = pending;
  document.getElementById('nav-resolved-today').textContent = sessionResolved;
  document.getElementById('pill-pending').textContent = pending;
  document.getElementById('stat-avg').textContent = sessionTimes.length ? (sessionTimes.reduce((a,b)=>a+b,0) / sessionTimes.length / 60000).toFixed(1) : '—';
}

// ════════════════════════════════════════════════════════════
// RENDER QUEUE
// ════════════════════════════════════════════════════════════
function renderQueue() {
  const container = document.getElementById('queue-container');
  let filtered = allFlags;

  if (currentFilter === 'pending')      filtered = allFlags.filter(f => f.status === 'pending');
  else if (currentFilter === 'dismissed') filtered = allFlags.filter(f => f.status === 'dismissed');
  else if (currentFilter === 'actioned') filtered = allFlags.filter(f => ['actioned','warned','hidden','escalated','reviewed'].includes(f.status));
  else if (currentFilter !== 'all')       filtered = allFlags.filter(f => f.reason === currentFilter);

  if (!filtered.length) {
    container.innerHTML = `
      <div class="empty-state">
        <div class="empty-icon">${currentFilter === 'pending' ? '✨' : '◎'}</div>
        <div class="empty-title">${currentFilter === 'pending' ? 'Queue is clear' : 'Nothing here'}</div>
        <div class="empty-sub">${currentFilter === 'pending'
          ? 'No items pending review. The dreams flow freely. Come back later or check other categories.'
          : 'No items in this category yet.'}</div>
      </div>`;
    return;
  }

  container.innerHTML = filtered.map(flag => renderFlagCard(flag)).join('');

  // Track when each card was shown
  filtered.forEach(f => { if (!reviewStarts[f.id]) reviewStarts[f.id] = Date.now(); });
}

function renderFlagCard(flag) {
  const badgeClass = {
    inappropriate:'badge-inappropriate', harmful:'badge-harmful',
    spam:'badge-spam', personal_info:'badge-personal_info', other:'badge-other'
  }[flag.reason] || 'badge-other';

  const badgeLabel = {
    inappropriate:'Inappropriate', harmful:'Harmful Content',
    spam:'Spam', personal_info:'Personal Info', other:'Other'
  }[flag.reason] || 'Other';

  const timeAgo = flag.created_at ? getTimeAgo(flag.created_at) : '—';
  const preview = flag.dream_content_preview || '';
  const title   = extractTitle(preview);
  const content = preview.replace(/^[^—]*— ?/,'') || 'No preview available';
  const isResolved = flag.status !== 'pending';

  const guidelinesText = {
    inappropriate: 'Does this dream contain graphic content beyond what a typical nightmare would include?',
    harmful: 'Does this content include instructions, encouragement, or detailed descriptions of harm to self or others?',
    spam: 'Is this clearly commercial content or automated spam unrelated to actual dream journaling?',
    personal_info: 'Does this reveal personally identifiable information about a real individual who could be identified?',
    other: 'Consider whether this content could cause distress to a reasonable reader and whether it violates our Terms of Service.'
  }[flag.reason] || 'Review carefully against our Terms of Service before taking action.';

  return `
    <div class="flag-card ${isResolved ? 'resolved' : ''}" id="flag-${flag.id}" data-flag-id="${flag.id}">
      <div class="flag-header">
        <div class="flag-type-badge ${badgeClass}">⚑ ${badgeLabel}</div>
        <div class="flag-meta">
          <div class="flag-meta-top">
            <span class="flag-content-type">${flag.dream_id ? 'Dream Entry' : 'Chat Message'}</span>
            <span class="flag-id">#${String(flag.id).slice(-6).toUpperCase()}</span>
            ${flag.flag_count > 1 ? `<span class="flag-count-chip">${flag.flag_count} flags</span>` : ''}
          </div>
          <div class="flag-reporter">Flagged ${timeAgo}${flag.reporter?.email ? ' · ' + escHtml(maskEmail(flag.reporter.email)) : ''}</div>
        </div>
        <div class="flag-time">${flag.created_at ? new Date(flag.created_at).toLocaleDateString() : '—'}</div>
        <button class="expand-btn" onclick="toggleContent('${flag.id}')" title="Expand/collapse content">⤢</button>
      </div>

      <div class="flag-body" id="flag-body-${flag.id}">
        <div class="content-section">
          <div class="content-section-label">Dream Content</div>
          ${title ? `<div class="dream-title">${escHtml(title)}</div>` : ''}
          <div class="dream-content truncated" id="dream-content-${flag.id}">${escHtml(content)}</div>
          ${flag.emotions?.length || flag.themes?.length ? `
            <div class="dream-tags">
              ${(flag.emotions||[]).map(e=>`<span class="dream-tag">🌙 ${escHtml(e)}</span>`).join('')}
              ${(flag.themes||[]).map(t=>`<span class="dream-tag">◈ ${escHtml(t)}</span>`).join('')}
            </div>` : ''}
        </div>

        ${flag.notes ? `
          <div class="content-section">
            <div class="content-section-label">Reporter's Notes</div>
            <div class="reporter-notes">"${escHtml(flag.notes)}"</div>
          </div>` : ''}

        <div class="privacy-bar">
          <span class="privacy-pill">Anonymous dreamer</span>
          <span>·</span>
          <span>Reporter cannot be identified to the content owner</span>
        </div>
      </div>

      ${isResolved
        ? `<div class="resolved-bar">
            <div class="resolved-icon">${flag.status === 'dismissed' ? '✓' : '✕'}</div>
            <span>Resolved — <strong>${escHtml(flag.status)}</strong>${flag.action_taken ? ': ' + escHtml(flag.action_taken) : ''}${flag.reviewer?.email ? ' by ' + escHtml(maskEmail(flag.reviewer.email)) : ''}</span>
          </div>`
        : `<div class="flag-footer">
            <div class="flag-guidelines">⚡ ${guidelinesText}</div>
            <div class="action-group">
              <button class="action-btn btn-dismiss"   onclick="confirmAction('${flag.id}','dismiss',  '${flag.dream_id||''}')">✓ Dismiss</button>
              <button class="action-btn btn-warn"      onclick="confirmAction('${flag.id}','warn',     '${flag.dream_id||''}')">⚠ Warn</button>
              <button class="action-btn btn-hide"      onclick="confirmAction('${flag.id}','hide',     '${flag.dream_id||''}')">◎ Hide</button>
              <button class="action-btn btn-remove"    onclick="confirmAction('${flag.id}','remove',   '${flag.dream_id||''}')">✕ Remove</button>
              <button class="action-btn btn-escalate"  onclick="confirmAction('${flag.id}','escalate', '${flag.dream_id||''}')">↑ Escalate</button>
            </div>
          </div>`}
    </div>`;
}

function extractTitle(preview) {
  if (!preview) return null;
  const match = preview.match(/^([^—\n]{3,60})(?:\s*—|$)/);
  return match ? match[1].trim() : null;
}

function toggleContent(flagId) {
  const el = document.getElementById('dream-content-' + flagId);
  if (el) el.classList.toggle('truncated');
}

// ════════════════════════════════════════════════════════════
// ACTIONS
// ════════════════════════════════════════════════════════════
const ACTION_CONFIG = {
  dismiss:  { icon:'✓', title:'Dismiss flag', body:'The content does not violate our guidelines. The dream will remain visible and in the matching pool.', confirmClass:'ok',   confirmLabel:'Dismiss',     note:false },
  warn:     { icon:'⚠️', title:'Add content warning', body:'A content warning label will be added before this dream. It will remain in the matching pool but readers will be alerted.', confirmClass:'warn',  confirmLabel:'Add Warning', note:true  },
  hide:     { icon:'◎', title:'Hide from matching', body:'This dream will be removed from the public matching pool but will remain in the user\'s private journal. The user will receive a notification.', confirmClass:'warn',  confirmLabel:'Hide Dream',  note:true  },
  remove:   { icon:'✕', title:'Remove content', body:'This dream will be removed from the community. The action will be recorded for administrator review.', confirmClass:'danger', confirmLabel:'Remove',    note:true  },
  escalate: { icon:'🚨', title:'Escalate to Admin', body:'This item will be hidden immediately and escalated to admin for urgent review. Only use this for CSAM or credible real-world threats.', confirmClass:'danger', confirmLabel:'Escalate',   note:true  }
};

function confirmAction(flagId, action, dreamId) {
  const cfg = ACTION_CONFIG[action];
  pendingAction = { flagId, action, dreamId };

  document.getElementById('modal-icon').textContent        = cfg.icon;
  document.getElementById('modal-title').textContent       = cfg.title;
  document.getElementById('modal-body').textContent        = cfg.body;
  document.getElementById('modal-note-label').style.display = cfg.note ? 'block' : 'none';
  document.getElementById('modal-note').style.display   = cfg.note ? 'block' : 'none';
  document.getElementById('modal-note').value           = '';

  const confirmBtn = document.getElementById('modal-confirm-btn');
  confirmBtn.textContent = cfg.confirmLabel;
  confirmBtn.className   = 'modal-confirm ' + cfg.confirmClass;

  document.getElementById('modal-overlay').classList.add('open');
}

function closeModal() {
  document.getElementById('modal-overlay').classList.remove('open');
  pendingAction = null;
}

async function executeAction() {
  if (!pendingAction) return;
  const {flagId,action} = pendingAction;
  const note = document.getElementById('modal-note').value.trim();
  const button = document.getElementById('modal-confirm-btn');
  if (button.disabled) return;
  button.disabled = true; button.textContent = 'Saving review?';
  const statusMap = {dismiss:'dismissed',warn:'warned',hide:'hidden',remove:'actioned',escalate:'escalated'};
  try {
    await apiPatch('/admin/flags/' + encodeURIComponent(flagId), {status:statusMap[action],action_taken:note || action});
    if (reviewStarts[flagId]) { sessionTimes.push(Date.now() - reviewStarts[flagId]); delete reviewStarts[flagId]; }
    const flag = allFlags.find(f=>f.id === flagId);
    if (flag) { flag.status = statusMap[action]; flag.action_taken = note || action; }
    sessionResolved++;
    closeModal(); updateStats(); renderQueue();
    const messages = {dismiss:'Flag dismissed. The dream remains visible.',warn:'Content warning added.',hide:'Dream hidden from the matching pool.',remove:'Content removed from the community.',escalate:'Report escalated for administrator review.'};
    showToast(messages[action], action === 'dismiss' ? 'success' : 'warn');
  } catch(err) { showToast('Review could not be saved: ' + err.message, 'error'); }
  finally { button.disabled = false; button.textContent = ACTION_CONFIG[action].confirmLabel; }
}

// ════════════════════════════════════════════════════════════
// FILTERS
// ════════════════════════════════════════════════════════════
function setFilter(filter, el) {
  currentFilter = filter;
  document.querySelectorAll('.filter-pills .pill').forEach(p => p.classList.remove('active'));
  if (el) el.classList.add('active');
  renderQueue();
}

// ════════════════════════════════════════════════════════════
// GUIDELINES TOGGLE
// ════════════════════════════════════════════════════════════
function toggleGuidelines() {
  const panel = document.getElementById('guidelines-panel');
  panel.classList.toggle('open');
  panel.querySelector('.guidelines-toggle').setAttribute('aria-expanded', String(panel.classList.contains('open')));
}

// ════════════════════════════════════════════════════════════
// DEMO QUEUE (shown when backend endpoint not yet live)
// ════════════════════════════════════════════════════════════


// ════════════════════════════════════════════════════════════
// API HELPERS
// ════════════════════════════════════════════════════════════
async function apiGet(path) {
  return requestPanelApi(path);
}

async function apiPatch(path, body) {
  return requestPanelApi(path, {method:'PATCH',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
}

// ════════════════════════════════════════════════════════════
// UTILITIES
// ════════════════════════════════════════════════════════════
function showToast(msg, type = 'success') {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = 'toast show ' + type;
  clearTimeout(t._t);
  t._t = setTimeout(() => t.classList.remove('show'), 3500);
}

function escHtml(str) {
  return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

function getTimeAgo(iso) {
  const diff = Date.now() - new Date(iso).getTime();
  const m = Math.floor(diff / 60000);
  if (m < 1) return 'just now';
  if (m < 60) return m + 'm ago';
  const h = Math.floor(m / 60);
  if (h < 24) return h + 'h ago';
  return Math.floor(h/24) + 'd ago';
}

function maskEmail(email) {
  if (!email) return '—';
  const [user, domain] = email.split('@');
  return user.slice(0,2) + '***@' + domain;
}

// ════════════════════════════════════════════════════════════
// INIT
// ════════════════════════════════════════════════════════════
window.addEventListener('DOMContentLoaded', async () => {
  const restored = await tryRestore();
  if (restored) { onLoginSuccess(); } 
});
</script>
</body>
</html>
