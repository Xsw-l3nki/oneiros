<?php
require_once __DIR__ . '/includes/security.php';
Security::headers();
$csrf = Security::csrfToken();
$appVersion = 'v2.0.0';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Oneiros — Where Dreams Become One</title>

<!-- PWA manifest & theme -->
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#080b18">
<meta name="background-color" content="#080b18">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Oneiros">
<meta name="mobile-web-app-capable" content="yes">
<meta name="application-name" content="Oneiros">

<!-- SEO & share meta -->
<meta name="description" content="Log your dreams. Discover how many people around the world dreamed the same thing last night. A global dream intelligence platform.">
<meta name="keywords" content="dream journal, dream matching, collective unconscious, lucid dreaming, dream analysis, sleep journal, dream community">
<meta name="robots" content="index, follow">
<meta name="author" content="Oneiros">
<meta property="og:title" content="Oneiros — Where Dreams Become One">
<meta property="og:description" content="A global dream intelligence platform. Log, match, and discover the collective unconscious. Your dreams connect you to millions of dreamers worldwide.">
<meta property="og:type" content="website">
<meta property="og:image" content="assets/dreamscape.webp">
<meta property="og:site_name" content="Oneiros">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Oneiros — Where Dreams Become One">
<meta name="twitter:description" content="Log dreams. Discover resonance. Connect with dreamers worldwide.">
<link rel="canonical" href="<?php echo isset($_SERVER['HTTPS']) ? 'https' : 'http'; ?>://<?php echo htmlspecialchars($_SERVER['HTTP_HOST']); ?>/">
<meta name="csrf-token" content="<?php echo $csrf; ?>">

<!-- JSON-LD structured data -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "WebApplication",
  "name": "Oneiros",
  "description": "A global dream intelligence and journaling platform that matches your dreams with others worldwide.",
  "applicationCategory": "LifestyleApplication",
  "operatingSystem": "Any",
  "offers": {
    "@type": "Offer",
    "price": "0",
    "priceCurrency": "USD"
  }
}
</script>

<!-- Apple touch icons (inline SVG data URIs — no separate files needed) -->
<link rel="apple-touch-icon" href="assets/icon-180.png">
<link rel="icon" type="image/svg+xml" href="assets/oneiros-icon.svg">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="preload" as="image" href="assets/dreamscape.webp">
<link rel="stylesheet" href="assets/base.css?v=2.1.0">
<link rel="stylesheet" href="assets/dream.css?v=2.1.0">
<link rel="stylesheet" href="assets/lucid.css?v=2.1.0">
</head>
<body data-view="landing">
<a class="skip-link" href="#main-content">Skip to content</a>
<div class="starfield" id="starfield" aria-hidden="true"></div>
<div class="bg-orbs">
  <div class="orb orb1"></div>
  <div class="orb orb2"></div>
  <div class="orb orb3"></div>
  <div class="orb orb4"></div>
</div>

<!-- NAV -->
<nav id="main-nav" aria-label="Main navigation">
  <button class="nav-logo" onclick="goTo(currentUser ? 'dashboard' : 'landing')" aria-label="Oneiros home"><span class="logo-orbit" aria-hidden="true">◌</span> oneiros<span class="logo-star">✦</span></button>
  <div class="landing-nav"><a href="#journey">The experience</a><a href="#universe">Our universe</a><a href="#lucid">Lucid</a><button onclick="showInfo('privacy')">Your privacy</button></div>
<div class="nav-links" id="nav-auth-links">
    <button class="nav-btn" onclick="openModal('login')">Sign In</button>
    <button class="nav-cta" onclick="openModal('register')">Begin your journey <span aria-hidden="true">↗</span></button>
  </div>
  <div class="nav-user" id="nav-user-links" style="display:none">
    <button class="mobile-menu-btn" onclick="toggleMobileMenu()" aria-label="Menu">☰</button>
    <div class="nav-links">
      <button class="nav-btn" onclick="goTo('dashboard')" id="nb-dashboard">Home</button>
      <button class="nav-btn" onclick="goTo('journal')" id="nb-journal">Journal</button>
      <button class="nav-btn" onclick="goTo('matches')" id="nb-matches">Matches</button>
      <button class="nav-btn" onclick="goTo('map')" id="nb-map">Dream Map</button>
      <button class="nav-btn" onclick="goTo('insights')" id="nb-insights">Insights</button>
      <button class="nav-btn" onclick="goTo('messages')" id="nb-messages">Messages</button>
    </div>
    <button class="lucid-chip" id="lucid-chip" onclick="openLucid('nav')" hidden><span aria-hidden="true">✦</span> Lucid</button>
    <button class="notif-btn" onclick="toggleNotif()" aria-label="Notifications">
      🔔<span class="notif-badge"></span>
    </button>
    <div class="avatar" onclick="goTo('profile')">LD</div>
  </div>
</nav>

<!-- NOTIFICATION PANEL -->
<div class="notif-panel" id="notif-panel">
  <div class="notif-panel-title">Notifications</div>
  <div id="notif-list" class="notif-list" style="padding:1rem;text-align:center;color:var(--indigo-light);font-size:0.82rem">Loading...</div>
</div>

<!-- AUTH MODAL -->
<div class="modal-overlay" id="modal-overlay" onclick="closeModal(event)">
  <div class="modal" id="modal-box" style="position:relative" role="dialog" aria-modal="true" aria-label="Your Oneiros account">
    <button class="modal-close" onclick="closeModal()" aria-label="Close account dialog">✕</button>
    <div id="modal-login">
      <div class="modal-title">Welcome back</div>
      <div class="modal-sub">Step back into the dream</div>
      <div class="form-group">
        <label class="form-label">Email</label>
        <input class="form-input" id="login-email" aria-label="Email" autocomplete="email" type="email" placeholder="your@email.com">
      </div>
      <div class="form-group">
        <label class="form-label">Password</label>
        <input class="form-input" id="login-password" aria-label="Password" autocomplete="current-password" type="password" placeholder="••••••••">
      </div>
      <button class="btn-primary" id="login-btn" style="width:100%;margin-top:0.5rem" onclick="login()">Enter Oneiros</button>
      <div class="modal-footer">No account? <a onclick="switchModal('register')">Join for free</a></div>
    </div>
    <div id="modal-register" style="display:none">
      <div class="modal-steps">
        <div class="modal-step active" id="reg-step-dot-1"></div>
        <div class="modal-step" id="reg-step-dot-2"></div>
      </div>
      <div id="reg-step-1">
        <div class="modal-title" style="font-size:1.5rem;margin-bottom:0.2rem">Begin dreaming</div>
        <div class="modal-sub" style="margin-bottom:1rem">A little space for your infinite inner world.</div>
        <div class="modal-compact-grid">
          <div class="form-group" style="grid-column:1/-1;margin-bottom:0.5rem">
            <label class="form-label" style="font-size:0.72rem">Email</label>
            <input class="form-input" id="reg-email" aria-label="Email" autocomplete="email" type="email" placeholder="your@email.com" style="padding:0.6rem 0.9rem">
          </div>
          <div class="form-group" style="margin-bottom:0.5rem">
            <label class="form-label" style="font-size:0.72rem">Password</label>
            <input class="form-input" id="reg-password" aria-label="New password" autocomplete="new-password" type="password" placeholder="Min. 8 characters" style="padding:0.6rem 0.9rem">
          </div>
          <div class="form-group" style="margin-bottom:0.5rem">
            <label class="form-label" style="font-size:0.72rem">Date of Birth</label>
            <input class="form-input" id="reg-dob" aria-label="Date of birth" type="date" style="padding:0.6rem 0.9rem">
          </div>
        </div>
        <div class="form-group"><label class="form-label" for="reg-region">Your region</label><select class="form-input" id="reg-region"><option value="ZA">South Africa</option><option value="US">United States</option><option value="GB">United Kingdom</option><option value="AU">Australia</option><option value="CA">Canada</option><option value="IN">India</option><option value="DE">Germany</option><option value="FR">France</option><option value="BR">Brazil</option><option value="JP">Japan</option><option value="NG">Nigeria</option><option value="KE">Kenya</option><option value="NZ">New Zealand</option><option value="">Prefer not to say</option></select></div><button class="btn-primary" style="width:100%;margin-top:0.5rem;padding:0.7rem" onclick="regNextStep()">Continue →</button>
        <div class="modal-footer" style="margin-top:0.75rem">Already a dreamer? <a onclick="switchModal('login')">Sign in</a></div>
      </div>
      <div id="reg-step-2" style="display:none">
        <div class="modal-title" style="font-size:1.5rem;margin-bottom:0.2rem">One last step</div>
        <div class="consent-mini">
          Your anonymized dream data contributes to global consciousness research. Public entries are visible to other dreamers. Private entries stay in your journal; research entries contribute only to aggregate insights. Avoid including identifying details in dream text.
        </div>
        <div class="check-row">
          <input type="checkbox" id="tos">
          <label for="tos">I agree to the <button class="text-link" onclick="showInfo('terms')">Terms of Service</button> &amp; <button class="text-link" onclick="showInfo('privacy')">Privacy Policy</button></label>
        </div>
        <div class="check-row">
          <input type="checkbox" id="research">
          <label for="research">I consent to anonymized dream data used for research</label>
        </div>
        <div class="check-row">
          <input type="checkbox" id="age">
          <label for="age">I confirm I am 18 years of age or older</label>
        </div>
        <button class="btn-primary" id="register-btn" style="width:100%;margin-top:0.75rem;padding:0.7rem" onclick="login()">✨ Create My Dream Journal</button>
        <div style="text-align:center;margin-top:0.5rem">
          <a onclick="regPrevStep()" style="font-size:0.75rem;color:var(--indigo-light);cursor:pointer">← Back</a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ACHIEVEMENT TOAST -->
<div class="achievement-toast" id="achievement-toast">
  <div class="achievement-icon" id="achievement-icon">🌙</div>
  <div class="achievement-text">
    <div class="achievement-label">Badge Unlocked</div>
    <div class="achievement-name" id="achievement-name">—</div>
    <div class="achievement-desc" id="achievement-desc">—</div>
  </div>
</div>

<!-- STREAKS & BADGES PAGE -->
<div class="page" id="page-streaks">
  <div style="padding-top:64px">
    <div class="profile-layout">

      <div id="streak-card-container">
        <!-- Populated by JS -->
      </div>

      <!-- Badge showcase -->
      <div class="badge-section" id="badge-showcase">
        <!-- Populated by JS -->
      </div>

      <!-- Leaderboard -->
      <div class="section-title-row" style="margin-bottom:1rem">
        <div class="section-title">Dream Streak Leaderboard</div>
        <div style="font-size:0.75rem;color:var(--indigo-light)">Fully anonymous</div>
      </div>
      <div class="leaderboard" id="leaderboard-container">
        <div style="padding:2rem;text-align:center;color:var(--indigo-light);font-size:0.85rem">Loading...</div>
      </div>

    </div>
  </div>
</div>

<!-- TOAST (already present) -->

<!-- ═══════════ ONBOARDING TOUR ═══════════ -->

<!-- Step 0: Welcome screen -->
<div class="tour-welcome" id="tour-welcome">
  <div class="tour-welcome-card">
    <div class="tour-welcome-icon">🌙</div>
    <div class="tour-welcome-title">Welcome to <em>Oneiros</em></div>
    <div class="tour-welcome-sub">The world dreams every night. Here you'll discover how many people across the globe dreamed the same thing as you — and what that means.</div>
    <div class="tour-welcome-steps">
      <div class="tour-welcome-step">
        <div class="tour-welcome-step-icon">✍️</div>
        <div class="tour-welcome-step-label">Log your dream each morning</div>
      </div>
      <div class="tour-welcome-step">
        <div class="tour-welcome-step-icon">✨</div>
        <div class="tour-welcome-step-label">Discover resonances worldwide</div>
      </div>
      <div class="tour-welcome-step">
        <div class="tour-welcome-step-icon">🌍</div>
        <div class="tour-welcome-step-label">Contribute to dream science</div>
      </div>
    </div>
    <button class="tour-start-btn" onclick="tourNext()">Begin the tour →</button>
    <button class="tour-skip-all" onclick="tourSkip()">I'll explore on my own</button>
  </div>
</div>

<!-- Spotlight overlay -->
<div class="tour-overlay" id="tour-overlay"></div>
<div class="tour-spotlight" id="tour-spotlight" style="display:none"></div>

<!-- Floating tooltip card -->
<div class="tour-card arrow-none" id="tour-card" style="display:none">
  <div class="tour-step-label">
    <span id="tour-step-label-text">Step</span>
    <div class="tour-step-dots" id="tour-dots"></div>
  </div>
  <div class="tour-icon" id="tour-icon">🌙</div>
  <div class="tour-title" id="tour-title">—</div>
  <div class="tour-body" id="tour-body">—</div>
  <div class="tour-actions">
    <button class="tour-skip" onclick="tourSkip()">Skip tour</button>
    <div style="display:flex;gap:0.5rem">
      <button class="tour-back" id="tour-back-btn" onclick="tourBack()" style="display:none">← Back</button>
      <button class="tour-next" id="tour-next-btn" onclick="tourNext()">Next →</button>
    </div>
  </div>
</div>

<!-- Completion screen -->
<div class="tour-done" id="tour-done">
  <div class="tour-done-card">
    <div class="confetti-row">✨ 🌙 ✨</div>
    <div class="tour-done-title">You're ready to dream</div>
    <div class="tour-done-sub">Tonight, before you sleep, think about what you want to dream. Tomorrow morning, before you get up, describe what you saw. The matching engine will do the rest.</div>
    <button class="tour-done-btn" onclick="tourFinish()">Start my dream journal →</button>
  </div>
</div>

<nav class="bottom-nav" id="bottom-nav" style="display:none">
  <button class="bottom-nav-item active" id="bn-dash" onclick="goTo('dashboard')">
    <span class="bottom-nav-icon">🌙</span>
    <span class="bottom-nav-label">Home</span>
  </button>
  <button class="bottom-nav-item" id="bn-journal" onclick="goTo('journal')">
    <span class="bottom-nav-icon">📖</span>
    <span class="bottom-nav-label">Journal</span>
  </button>
  <button class="bottom-nav-item" id="bn-matches" onclick="goTo('matches')">
    <span class="bottom-nav-icon">✨</span>
    <span class="bottom-nav-label">Matches</span>
    <span class="bottom-nav-badge" id="bn-badge" style="display:none"></span>
  </button>
  <button class="bottom-nav-item" id="bn-map" onclick="goTo('map')">
    <span class="bottom-nav-icon">🌍</span>
    <span class="bottom-nav-label">Map</span>
  </button>
  <button class="bottom-nav-item" id="bn-messages" onclick="goTo('messages')">
    <span class="bottom-nav-icon">💬</span>
    <span class="bottom-nav-label">Chat</span>
  </button>
</nav>

<!-- MOBILE NAV HEADER (shown when logged in on small screens) -->
<div class="mobile-header" id="mobile-header" style="display:none">
  <button class="nav-logo" onclick="goTo(currentUser ? 'dashboard' : 'landing')" aria-label="Oneiros home"><span class="logo-orbit" aria-hidden="true">◌</span> oneiros<span class="logo-star">✦</span></button>
  <div style="display:flex;align-items:center;gap:0.5rem">
    <button class="notif-btn" onclick="toggleNotif()" aria-label="Notifications">🔔<span class="notif-badge" id="mobile-notif-badge"></span></button>
    <div class="avatar" id="mobile-avatar" onclick="goTo('profile')">LD</div>
  </div>
</div>


<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeMobileMenu()"></div>

<!-- PWA install prompt (Android / desktop) -->
<div class="pwa-prompt" id="pwa-prompt">
  <div class="pwa-prompt-icon">🌙</div>
  <div class="pwa-prompt-text">
    <div class="pwa-prompt-title">Install Oneiros</div>
    <div class="pwa-prompt-sub">Add to your home screen for the full dream experience</div>
  </div>
  <div class="pwa-prompt-actions">
    <button class="pwa-install-btn primary" onclick="installPWA()">Install</button>
  </div>
  <button class="pwa-dismiss-btn" onclick="dismissInstallPrompt()" title="Dismiss">✕</button>
</div>

<!-- iOS install instructions -->
<div class="ios-install-panel" id="ios-prompt">
  <div class="ios-panel-title">📲 Add Oneiros to your Home Screen</div>
  <div class="ios-steps">
    <div class="ios-step">
      <div class="ios-step-num">1</div>
      <div class="ios-step-icon">⬆</div>
      <span>Tap the <strong>Share</strong> button in Safari's toolbar</span>
    </div>
    <div class="ios-step">
      <div class="ios-step-num">2</div>
      <div class="ios-step-icon">➕</div>
      <span>Select <strong>Add to Home Screen</strong></span>
    </div>
    <div class="ios-step">
      <div class="ios-step-num">3</div>
      <div class="ios-step-icon">🌙</div>
      <span>Tap <strong>Add</strong> — Oneiros launches like a native app</span>
    </div>
  </div>
  <div class="ios-close-btn" onclick="document.getElementById('ios-prompt').classList.remove('show')">CLOSE</div>
</div>

<!-- Offline banner -->
<div class="offline-banner" id="offline-banner">
  You’re offline. Your current draft stays on this device; reconnect to save.
</div>

<!-- ═══════════ LANDING ═══════════ -->
<main class="page active" id="page-landing">
  <section class="dream-hero" id="main-content" tabindex="-1">
    <div class="hero-scene" aria-hidden="true"><picture><source media="(max-width: 640px)" srcset="assets/dreamscape-mobile.webp"><img src="assets/dreamscape.webp" alt="" width="1672" height="941" fetchpriority="high"></picture><div class="scene-shade"></div><canvas id="dream-particles"></canvas><div class="moon-orbit orbit-one"></div><div class="moon-orbit orbit-two"></div></div>
    <div class="hero-content">
      <div class="eyebrow"><span class="little-star">✦</span> A HOME FOR YOUR UNCONSCIOUS</div>
      <h1>Somewhere,<br>someone is<br><em>dreaming with you.</em></h1>
      <p>Catch the worlds you visit in your sleep.<br>Find their meaning. Discover the dreamers<br class="desktop-break"> who have been there, too.</p>
      <div class="hero-actions"><button class="btn-primary" onclick="openModal('register')">Enter your dreamspace <span>↗</span></button><a class="hero-explore" href="#journey"><span class="play-ring">↓</span> Explore the experience</a></div>
      <div class="hero-note"><span class="tiny-orbit">◌</span> Your journal. Your pace. Your universe.</div>
    </div>
    <div class="orbit-note"><span class="live-dot"></span> BETWEEN ASLEEP &amp; AWAKE</div>
    <div class="dream-fragment"><div class="fragment-icon">✧</div><div><span>A GLIMPSE OF THE POSSIBLE</span><p>“The ocean held a thousand moons.”</p></div><span class="fragment-line"></span></div>
    <div class="hero-bottom"><span>SCROLL TO WANDER <span class="scroll-line"></span></span><span>01 — THE THRESHOLD</span></div>
  </section>
  <section class="universe-strip" aria-label="Community statistics"><div class="strip-intro"><span class="live-dot"></span><span>A world connected<br><strong>beneath the surface.</strong></span></div><div class="stat"><div class="stat-num" id="hero-dreams">—</div><div class="stat-label">Dreams collected</div></div><div class="stat"><div class="stat-num" id="hero-countries">—</div><div class="stat-label">Countries connected</div></div><div class="stat"><div class="stat-num" id="hero-matches">—</div><div class="stat-label">Shared resonances</div></div><div class="stat"><div class="stat-num" id="hero-active">—</div><div class="stat-label">Recently active</div></div></section>
  <section class="journey-section" id="journey"><div class="section-heading reveal"><div><div class="eyebrow">01 / REMEMBER. REFLECT. RESONATE.</div><h2>Your nights have<br><em>a story to tell.</em></h2></div><p>Dreams disappear in moments.<br>Give yours somewhere to stay.</p></div>
    <div class="journey-grid">
      <article class="journey-card reveal"><div class="card-art art-journal"><div class="floating-page"><span>LAST NIGHT, I REMEMBER…</span><i></i><i></i><i></i><b>✧</b></div><div class="art-orbit"></div></div><span class="card-step">01 — CAPTURE</span><h3>Keep a little<br>of the extraordinary.</h3><p>Write a fragment, record your voice, or add an image. Every detail is a doorway back.</p><button class="text-link" onclick="openModal('register')">Open your journal <span>↗</span></button></article>
      <article class="journey-card reveal"><div class="card-art art-resonate"><div class="resonance-orb one"></div><div class="resonance-orb two"></div><div class="resonance-thread"></div><span class="art-caption">a familiar feeling, worlds apart</span></div><span class="card-step">02 — CONNECT</span><h3>Different lives.<br>A familiar dream.</h3><p>Explore shared themes, symbols and emotions. Meet someone whose night echoes yours.</p><button class="text-link" onclick="openModal('register')">Find your resonance <span>↗</span></button></article>
      <article class="journey-card reveal"><div class="card-art art-pattern"><div class="pattern-ring ring-a"></div><div class="pattern-ring ring-b"></div><div class="pattern-ring ring-c"></div><span class="pattern-star">✦</span></div><span class="card-step">03 — DISCOVER</span><h3>Get to know your<br>inner universe.</h3><p>Notice recurring patterns, trace your emotions, and make remembering a beautiful ritual.</p><button class="text-link" onclick="openModal('register')">Follow the patterns <span>↗</span></button></article>
    </div>
  </section>
  <section class="collective-section reveal" id="universe"><div class="collective-orb" aria-hidden="true"><div class="globe-latitudes"></div><span class="globe-dot d1"></span><span class="globe-dot d2"></span><span class="globe-dot d3"></span><span class="globe-dot d4"></span></div><div class="collective-copy"><div class="eyebrow">02 / THE COLLECTIVE DREAM</div><h2>An entire world.<br><em>Eyes closed. Connected.</em></h2><p>A forest in Johannesburg. An ocean in Kyoto. A feeling neither of you can quite explain. Explore the themes that connect our sleeping minds on the global dream map.</p><button class="btn-ghost" onclick="openModal('register')">Discover our universe <span>↗</span></button><span class="quiet-note">Country-level insights. Always anonymous.</span></div></section>
  <section class="sanctuary-section reveal"><div class="eyebrow">03 / A SPACE THAT IS YOURS</div><h2>Let your mind wander.<br><em>Your dreams are safe here.</em></h2><div class="trust-grid"><div><span>◈</span><h3>You choose what to share.</h3><p>Keep entries private, contribute to aggregate research, or open them to connection.</p></div><div><span>◎</span><h3>A person before a profile.</h3><p>Meet through shared dreams. Choose a display name and connect when it feels right.</p></div><div><span>↗</span><h3>Always yours to take.</h3><p>Export your journal whenever you like. Edit your memories or delete your account.</p></div></div></section>
  <section class="lucid-section reveal" id="lucid">
    <div class="lucid-section-head"><div class="eyebrow">04 / GO DEEPER WITH LUCID</div><h2>Every resonance.<br><em>Every night, remembered.</em></h2>
      <p>Oneiros is free to keep. Lucid is a once-off pass for when you want to go further: <strong>no subscriptions, nothing renews</strong>. Pay once, dream for as long as your pass lasts.</p></div>
    <div class="lucid-plans" id="landing-lucid-plans" aria-live="polite"></div>
    <div class="lucid-perk-row" id="landing-lucid-perks"></div>
  </section>
  <section class="last-invitation reveal"><div class="eyebrow">SEE YOU ON THE OTHER SIDE</div><h2>The next chapter<br>begins <em>tonight.</em></h2><button class="btn-primary" onclick="openModal('register')">Begin your dream journal <span>↗</span></button><p>No perfect words needed. Just what you remember.</p></section>
  <footer class="dream-footer"><a class="nav-logo" href="#">◌ oneiros<span class="logo-star">✦</span></a><p>For everything you are, even in your sleep.</p><div><button onclick="showInfo('privacy')">Privacy</button><button onclick="showInfo('terms')">Terms</button><button onclick="toggleMotion()" id="motion-toggle" aria-pressed="false">Pause motion</button></div><span>© 2026 Oneiros</span></footer>
</main>

<!-- ═══════════ DASHBOARD ═══════════ -->
<div class="page" id="page-dashboard">
  <div class="dash-layout">
    <div class="sidebar">
      <div class="sidebar-section">Menu</div>
      <div class="sidebar-item active" onclick="goTo('dashboard')"><span class="sidebar-icon">🌙</span> Home</div>
      <div class="sidebar-item" onclick="goTo('journal')"><span class="sidebar-icon">📖</span> My Journal</div>
      <div class="sidebar-item" onclick="goTo('matches')"><span class="sidebar-icon">✨</span> Matches <span class="sidebar-badge" id="sidebar-match-badge" style="display:none">0</span></div>
      <div class="sidebar-item" onclick="goTo('map')"><span class="sidebar-icon">🌍</span> Dream Map</div>
      <div class="sidebar-section">Insights</div>
      <div class="sidebar-item" onclick="goTo('streaks')"><span class="sidebar-icon">🔥</span> Streaks & Badges</div>
      <div class="sidebar-item" onclick="goTo('insights')"><span class="sidebar-icon">📊</span> Dream Insights</div>
      <div class="sidebar-item" onclick="goTo('journal');setTimeout(()=>{const f=document.getElementById('journal-filter');f.value='recurring';filterJournal()},0)"><span class="sidebar-icon">🔁</span> Recurring Dreams</div>
      <div class="sidebar-section">Social</div>
      <div class="sidebar-item" onclick="goTo('messages')"><span class="sidebar-icon">💬</span> Messages</div>
      <div class="sidebar-item" onclick="goTo('winddown')"><span class="sidebar-icon">🌘</span> Wind down</div>
      <div class="sidebar-item sidebar-lucid" onclick="openLucid('sidebar')"><span class="sidebar-icon">✦</span> <span class="sidebar-lucid-label">Lucid</span></div>
      <div class="sidebar-section">Account</div>
      <div class="sidebar-item" onclick="goTo('profile')"><span class="sidebar-icon">⚙️</span> Profile & Settings</div>
      <div class="sidebar-item" onclick="logout()"><span class="sidebar-icon">↩</span> Sign Out</div>
    </div>
    <div class="dash-main">
      <div class="dash-greeting">Welcome to your <em>dreamspace.</em></div>
      <div class="dash-date">A quiet moment to remember.</div>
      <div id="dash-lucid-slot"></div>

      <!-- CAPTURE -->
      <div class="capture-card">
        <div class="capture-topline"><span class="eyebrow">YOUR NEXT CHAPTER</span><span class="draft-state" id="draft-state">A space to remember</span></div><label class="capture-prompt" for="dream-text">Where did your mind wander?</label><input id="dream-title" class="dream-title-input" aria-label="Dream title" placeholder="Give this dream a name (optional)" maxlength="200">
        <div class="recall-prompts" id="recall-prompts" aria-label="Guided recall prompts"></div>
        <textarea class="capture-input" id="dream-text" placeholder="A place, a feeling, a fleeting detail… start with what stayed with you." maxlength="50000" rows="4"></textarea>

        <div class="capture-meta"><label>Dream date<input type="date" id="dream-date" aria-label="Dream date"></label><label>Visibility<select id="dream-privacy" aria-label="Dream visibility"><option value="private">Private · only me</option><option value="public">Public · find connections</option><option value="research_only">Research · aggregate insights</option></select></label></div><div class="emotion-selector"><span>How did it feel?</span><button class="emotion-choice" aria-pressed="false" onclick="this.classList.toggle('selected');this.setAttribute('aria-pressed',this.classList.contains('selected'))">Wonder</button><button class="emotion-choice" aria-pressed="false" onclick="this.classList.toggle('selected');this.setAttribute('aria-pressed',this.classList.contains('selected'))">Peace</button><button class="emotion-choice" aria-pressed="false" onclick="this.classList.toggle('selected');this.setAttribute('aria-pressed',this.classList.contains('selected'))">Joy</button><button class="emotion-choice" aria-pressed="false" onclick="this.classList.toggle('selected');this.setAttribute('aria-pressed',this.classList.contains('selected'))">Fear</button><button class="emotion-choice" aria-pressed="false" onclick="this.classList.toggle('selected');this.setAttribute('aria-pressed',this.classList.contains('selected'))">Longing</button><button class="emotion-choice" aria-pressed="false" onclick="this.classList.toggle('selected');this.setAttribute('aria-pressed',this.classList.contains('selected'))">Confusion</button></div><!-- AI INTERVIEW -->
        <div class="ai-interview" id="ai-interview">
          <div class="ai-q"><strong>✦ Recall prompt:</strong> That sounds visually rich. Let me help you refine it for matching. Was the sky in your dream:</div>
          <div class="ai-options">
            <div class="ai-option" onclick="selectAI(this)">Dawn / sunrise</div>
            <div class="ai-option" onclick="selectAI(this)">Midday bright</div>
            <div class="ai-option" onclick="selectAI(this)">Dusk / twilight</div>
            <div class="ai-option" onclick="selectAI(this)">Night / dark</div>
            <div class="ai-option" onclick="selectAI(this)">Unclear / no sky</div>
          </div>
        </div>

        <!-- RECORDER -->
        <div class="recorder-ui" id="recorder-ui">
          <button class="rec-btn" id="rec-btn" onclick="toggleRecording()">🎙️</button>
          <div class="rec-waveform" id="waveform">
            <div class="wave-bar"></div><div class="wave-bar"></div>
            <div class="wave-bar"></div><div class="wave-bar"></div>
            <div class="wave-bar"></div><div class="wave-bar"></div>
            <div class="wave-bar"></div><div class="wave-bar"></div>
            <div class="wave-bar"></div><div class="wave-bar"></div>
          </div>
          <div class="rec-time" id="rec-time">0:00</div>
          <div id="rec-status" style="font-size:0.78rem;color:var(--indigo-light);margin-top:0.5rem;text-align:center;width:100%">🎙️ Tap to start recording · Live transcript appears below</div>
        </div>

        <!-- AI PAINT CONTROLS -->
        <div id="ai-paint-controls" style="display:none;margin-top:0.75rem">
          <button id="ai-paint-btn" class="paint-cta" onclick="generateDreamPainting()">Create a dream canvas</button>
        </div>
        <div id="ai-paint-preview" style="display:none;margin-top:0.75rem"></div>

        <!-- IMAGE UPLOAD -->
        <div class="upload-zone" id="upload-zone" style="display:none" onclick="document.getElementById('dream-image-input').click()" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)" ondrop="handleDrop(event)">
          <input type="file" id="dream-image-input" accept="image/*" style="display:none" onchange="handleImageSelect(event)">
          <div class="upload-zone-icon">🖼️</div>
          <div class="upload-zone-text">Click or drag a drawing / photo of your dream</div>
          <div class="upload-zone-sub">JPG, PNG, WEBP · Max 10MB</div>
        </div>
        <div class="upload-preview" id="upload-preview" style="display:none">
          <img id="upload-preview-img" src="" alt="Dream image preview">
          <button class="upload-preview-remove" onclick="removeUploadedImage(event)">✕</button>
        </div>
        <div class="upload-progress" id="upload-progress"><div class="upload-progress-bar" id="upload-progress-bar"></div></div>

        <div class="capture-actions">
          <div class="capture-methods">
            <button class="method-btn active" onclick="setMethod(this,'text')">✍️ Text</button>
            <button class="method-btn" onclick="setMethod(this,'voice')">🎙️ Voice</button>
            <button class="method-btn" onclick="setMethod(this,'image')">🖼️ Image</button>
            <button class="method-btn" onclick="setMethod(this,'ai')">✧ Dream art</button>
          </div>
          <button class="submit-dream" id="submit-btn" onclick="submitDream()">Save my dream ↗</button>
        </div>
      </div>

      <!-- STATS ROW -->
      <div class="dash-grid-3">
        <div class="resonance-card">
          <div class="card-label" style="color:rgba(255,255,255,0.6)">Last Night's Resonance</div>
          <div class="resonance-num" id="stat-resonance">—</div>
          <div class="resonance-label">Dreamers shared your vision</div>
          <div class="resonance-sub">Your connections begin with a dream.</div>
        </div>
        <div class="card">
          <div class="card-label">Total Matches</div>
          <div class="card-value" id="stat-matches">—</div>
          <div class="card-sub">Shared themes and feelings</div>
        </div>
        <div class="card">
          <div class="card-label">Dreams Logged</div>
          <div class="card-value" id="stat-dreams">—</div>
          <div class="card-sub">Every dream has a place here</div>
        </div>
      </div>

      <!-- RECENT MATCHES -->
      <div class="section-title-row">
        <div class="section-title">Recent Resonances</div>
        <div class="see-all" onclick="goTo('matches')">See all matches →</div>
      </div>
      <div class="match-preview" id="dash-match-preview">
        <div style="padding:1.5rem;text-align:center;color:var(--indigo-light);font-size:0.85rem;
                    background:rgba(255,255,255,0.4);border-radius:0.875rem;
                    border:1px dashed rgba(167,139,202,0.3)">
          🌙 Log your first dream to discover resonances around the world
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════ JOURNAL ═══════════ -->
<div class="page" id="page-journal">
  <div class="dash-layout">
    <div class="sidebar">
      <div class="sidebar-section">Menu</div>
      <div class="sidebar-item" onclick="goTo('dashboard')"><span class="sidebar-icon">🌙</span> Home</div>
      <div class="sidebar-item active" onclick="goTo('journal')"><span class="sidebar-icon">📖</span> My Journal</div>
      <div class="sidebar-item" onclick="goTo('matches')"><span class="sidebar-icon">✨</span> Matches</div>
      <div class="sidebar-item" onclick="goTo('map')"><span class="sidebar-icon">🌍</span> Dream Map</div>
      <div class="sidebar-item" onclick="goTo('insights')"><span class="sidebar-icon">📊</span> Insights</div>
      <div class="sidebar-item" onclick="goTo('messages')"><span class="sidebar-icon">💬</span> Messages</div>
      <div class="sidebar-item" onclick="goTo('winddown')"><span class="sidebar-icon">🌘</span> Wind down</div>
      <div class="sidebar-item sidebar-lucid" onclick="openLucid('sidebar')"><span class="sidebar-icon">✦</span> <span class="sidebar-lucid-label">Lucid</span></div>
      <div class="sidebar-section">Account</div>
      <div class="sidebar-item" onclick="logout()"><span class="sidebar-icon">↩</span> Sign Out</div>
    </div>
    <div class="dash-main">
      <div class="page-header">
        <div class="page-title">My Dream Journal</div>
        <div class="page-sub">Loading your journal...</div>
      </div>
      <div class="journal-toolbar"><label class="search-field"><span>⌕</span><input type="search" id="journal-search" placeholder="Search your dreams…" aria-label="Search dreams" oninput="filterJournal()"></label><select id="journal-filter" aria-label="Filter journal" onchange="filterJournal()"><option value="all">All dreams</option><option value="private">Private</option><option value="public">Public</option><option value="research_only">Research</option><option value="recurring">Recurring</option></select><div class="view-toggle" role="group" aria-label="Journal view"><button class="active" data-view="list" onclick="setJournalView('list')" aria-pressed="true">List</button><button data-view="calendar" onclick="setJournalView('calendar')" aria-pressed="false">Calendar</button></div><button class="btn-primary" onclick="goTo('dashboard');document.getElementById('dream-text').focus()">+ New dream</button></div><div class="journal-calendar" id="journal-calendar" hidden></div><div class="journal-list" id="journal-list">
        <div style="text-align:center;padding:3rem;color:var(--indigo-light)">Loading your dreams...</div>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════ MATCHES ═══════════ -->
<div class="page" id="page-matches">
  <div class="dash-layout">
    <div class="sidebar">
      <div class="sidebar-section">Menu</div>
      <div class="sidebar-item" onclick="goTo('dashboard')"><span class="sidebar-icon">🌙</span> Home</div>
      <div class="sidebar-item" onclick="goTo('journal')"><span class="sidebar-icon">📖</span> My Journal</div>
      <div class="sidebar-item active" onclick="goTo('matches')"><span class="sidebar-icon">✨</span> Matches</div>
      <div class="sidebar-item" onclick="goTo('map')"><span class="sidebar-icon">🌍</span> Dream Map</div>
      <div class="sidebar-item" onclick="goTo('insights')"><span class="sidebar-icon">📊</span> Insights</div>
      <div class="sidebar-item" onclick="goTo('messages')"><span class="sidebar-icon">💬</span> Messages</div>
      <div class="sidebar-item" onclick="goTo('winddown')"><span class="sidebar-icon">🌘</span> Wind down</div>
      <div class="sidebar-item sidebar-lucid" onclick="openLucid('sidebar')"><span class="sidebar-icon">✦</span> <span class="sidebar-lucid-label">Lucid</span></div>
      <div class="sidebar-section">Account</div>
      <div class="sidebar-item" onclick="logout()"><span class="sidebar-icon">↩</span> Sign Out</div>
    </div>
    <div class="dash-main">
      <div class="page-header">
        <div class="page-title">Dream Resonances</div>
        <div class="page-sub">Discover familiar themes across different lives.</div>
      </div>
      <div class="matches-header">
        <div class="filter-pills">
          <div class="pill active" onclick="filterMatches(this,'all')">All Dimensions</div>
          <div class="pill" onclick="filterMatches(this,'theme')">Theme</div>
          <div class="pill" onclick="filterMatches(this,'emotion')">Emotion</div>
          <div class="pill" onclick="filterMatches(this,'visual')">Visual</div>
          <div class="pill" onclick="filterMatches(this,'narrative')">Narrative</div>
        </div>
        <div class="filter-pills">
          <div class="pill" onclick="filterTime(this,'night')">Last Night</div>
          <div class="pill" onclick="filterTime(this,'week')">7 Days</div>
          <div class="pill active" onclick="filterTime(this,'all')">All Time</div>
        </div>
      </div>
      <div id="matches-container">
        <div style="text-align:center;padding:3rem;color:var(--indigo-light)">Loading resonances...</div>
      </div>

    </div>
  </div>
</div>

<!-- ═══════════ MAP ═══════════ -->
<div class="page" id="page-map">
  <div class="dash-layout">
    <div class="sidebar">
      <div class="sidebar-section">Menu</div>
      <div class="sidebar-item" onclick="goTo('dashboard')"><span class="sidebar-icon">🌙</span> Home</div>
      <div class="sidebar-item" onclick="goTo('journal')"><span class="sidebar-icon">📖</span> My Journal</div>
      <div class="sidebar-item" onclick="goTo('matches')"><span class="sidebar-icon">✨</span> Matches</div>
      <div class="sidebar-item active" onclick="goTo('map')"><span class="sidebar-icon">🌍</span> Dream Map</div>
      <div class="sidebar-item" onclick="goTo('insights')"><span class="sidebar-icon">📊</span> Insights</div>
      <div class="sidebar-item" onclick="goTo('messages')"><span class="sidebar-icon">💬</span> Messages</div>
      <div class="sidebar-item" onclick="goTo('winddown')"><span class="sidebar-icon">🌘</span> Wind down</div>
      <div class="sidebar-item sidebar-lucid" onclick="openLucid('sidebar')"><span class="sidebar-icon">✦</span> <span class="sidebar-lucid-label">Lucid</span></div>
      <div class="sidebar-section">Account</div>
      <div class="sidebar-item" onclick="logout()"><span class="sidebar-icon">↩</span> Sign Out</div>
    </div>
    <div class="dash-main">
      <div class="page-header">
        <div class="page-title">Global Dream Map</div>
        <div class="page-sub">Explore shared dreams, one country at a time. Locations are approximate.</div>
      </div>

      <div class="map-container">
        <div class="map-overlay">
          <div class="map-title">The world is dreaming right now</div>
          <div class="map-live"><span class="live-dot"></span> Loading activity…</div>
        </div>
        <svg class="map-svg" viewBox="0 0 900 420" xmlns="http://www.w3.org/2000/svg">
          <rect width="900" height="420" fill="none"/>
          <path d="M 70 80 L 130 70 L 190 60 L 220 80 L 230 110 L 210 150 L 190 180 L 170 200 L 150 210 L 130 200 L 110 180 L 90 160 L 70 130 Z" fill="rgba(167,139,202,0.15)" stroke="rgba(167,139,202,0.3)" stroke-width="1"/>
          <path d="M 170 230 L 200 220 L 220 240 L 225 270 L 220 310 L 205 340 L 185 350 L 170 330 L 160 300 L 155 270 L 160 245 Z" fill="rgba(167,139,202,0.12)" stroke="rgba(167,139,202,0.25)" stroke-width="1"/>
          <path d="M 380 70 L 430 60 L 460 75 L 470 95 L 455 115 L 430 120 L 405 115 L 385 100 Z" fill="rgba(167,139,202,0.15)" stroke="rgba(167,139,202,0.3)" stroke-width="1"/>
          <path d="M 400 130 L 450 125 L 475 145 L 480 180 L 475 220 L 460 260 L 440 285 L 415 290 L 390 265 L 378 230 L 375 190 L 380 155 Z" fill="rgba(167,139,202,0.12)" stroke="rgba(167,139,202,0.25)" stroke-width="1"/>
          <path d="M 490 55 L 600 45 L 700 60 L 740 90 L 730 130 L 690 155 L 640 160 L 580 150 L 530 140 L 495 120 L 480 90 Z" fill="rgba(167,139,202,0.15)" stroke="rgba(167,139,202,0.3)" stroke-width="1"/>
          <path d="M 680 250 L 740 240 L 780 260 L 790 290 L 775 315 L 740 325 L 700 315 L 680 295 L 675 270 Z" fill="rgba(167,139,202,0.12)" stroke="rgba(167,139,202,0.25)" stroke-width="1"/>
          <g id="map-points"></g>
        </svg>
        <div class="map-tooltip" id="map-tooltip">
          <strong id="tip-country"></strong><br>
          <span id="tip-detail"></span>
        </div>
      </div>

      <div class="map-stats">
        <div class="map-stat">
          <div class="map-stat-num" id="map-stat-dreams">—</div>
          <div class="map-stat-label">Total Dreams</div>
        </div>
        <div class="map-stat">
          <div class="map-stat-num" id="map-stat-countries">—</div>
          <div class="map-stat-label">Countries Active</div>
        </div>
        <div class="map-stat">
          <div class="map-stat-num" id="map-stat-online">—</div>
          <div class="map-stat-label">Dreaming Now</div>
        </div>
        <div class="map-stat">
          <div class="map-stat-num" id="map-stat-theme">—</div>
          <div class="map-stat-label">Top Global Theme</div>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- ═══════════ MESSAGES ═══════════ -->
<div class="page" id="page-messages">
  <div style="padding-top:64px;height:100vh;display:flex;flex-direction:column">
    <div class="messages-layout" style="flex:1;margin-left:0">
      <div class="convo-list">
        <div class="convo-list-header">
          <div class="convo-list-title">Messages</div>
        </div>
        <div id="convo-list-items">
          <div class="no-connections-msg">
            <div style="font-size:2rem;margin-bottom:0.75rem;opacity:0.4">💬</div>
            Loading conversations...
          </div>
        </div>
      </div>
      <div class="chat-pane" id="chat-pane">
        <div class="chat-select-hint">
          <div style="font-size:2.5rem;opacity:0.25">🌙</div>
          <div style="font-family:'Cormorant Garamond',serif;font-size:1.2rem;font-style:italic;color:var(--indigo-light)">Select a conversation</div>
          <div style="font-size:0.8rem;color:var(--indigo-light)">Your connected dreamers appear on the left</div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="page" id="page-insights"><div class="dash-layout"><div class="sidebar">
      <div class="sidebar-section">Menu</div>
      <div class="sidebar-item" onclick="goTo('dashboard')"><span class="sidebar-icon">🌙</span> Home</div>
      <div class="sidebar-item" onclick="goTo('journal')"><span class="sidebar-icon">📖</span> My Journal</div>
      <div class="sidebar-item" onclick="goTo('matches')"><span class="sidebar-icon">✨</span> Matches</div>
      <div class="sidebar-item" onclick="goTo('map')"><span class="sidebar-icon">🌍</span> Dream Map</div>
      <div class="sidebar-item active" onclick="goTo('insights')"><span class="sidebar-icon">📊</span> Insights</div>
      <div class="sidebar-item" onclick="goTo('streaks')"><span class="sidebar-icon">🔥</span> Streaks &amp; Badges</div>
      <div class="sidebar-item" onclick="goTo('winddown')"><span class="sidebar-icon">🌘</span> Wind down</div>
      <div class="sidebar-item sidebar-lucid" onclick="openLucid('sidebar')"><span class="sidebar-icon">✦</span> <span class="sidebar-lucid-label">Lucid</span></div>
      <div class="sidebar-section">Account</div>
      <div class="sidebar-item" onclick="goTo('profile')"><span class="sidebar-icon">⚙️</span> Profile &amp; Settings</div>
      <div class="sidebar-item" onclick="logout()"><span class="sidebar-icon">↩</span> Sign Out</div>
    </div><div class="dash-main"><div class="page-header"><div class="eyebrow">YOUR INNER UNIVERSE</div><h1 class="page-title">Patterns in the <em>quiet.</em></h1><p class="page-sub">A reflection of your journal, alongside the collective dream.</p></div><div id="insights-content" class="insights-grid"></div></div></div></div>
<div class="page" id="page-winddown"><div class="dash-layout"><div class="sidebar">
      <div class="sidebar-section">Menu</div>
      <div class="sidebar-item" onclick="goTo('dashboard')"><span class="sidebar-icon">🌙</span> Home</div>
      <div class="sidebar-item" onclick="goTo('journal')"><span class="sidebar-icon">📖</span> My Journal</div>
      <div class="sidebar-item" onclick="goTo('matches')"><span class="sidebar-icon">✨</span> Matches</div>
      <div class="sidebar-item" onclick="goTo('insights')"><span class="sidebar-icon">📊</span> Insights</div>
      <div class="sidebar-item active" onclick="goTo('winddown')"><span class="sidebar-icon">🌘</span> Wind down</div>
      <div class="sidebar-item sidebar-lucid" onclick="openLucid('sidebar')"><span class="sidebar-icon">✦</span> <span class="sidebar-lucid-label">Lucid</span></div>
      <div class="sidebar-section">Account</div>
      <div class="sidebar-item" onclick="goTo('profile')"><span class="sidebar-icon">⚙️</span> Profile &amp; Settings</div>
      <div class="sidebar-item" onclick="logout()"><span class="sidebar-icon">↩</span> Sign Out</div>
    </div><div class="dash-main"><div class="page-header"><div class="eyebrow">BEFORE SLEEP</div><h1 class="page-title">Let the day <em>dissolve.</em></h1><p class="page-sub">Set an intention, slow your breath, and drift off to a soundscape.</p></div><div id="winddown-content" class="winddown-grid"></div></div></div></div>
<!-- ═══════════ PROFILE ═══════════ -->
<div class="page" id="page-profile">
  <div style="padding-top:64px">
    <div class="profile-layout">

      <div class="profile-hero">
        <div class="profile-avatar-big" id="profile-avatar-big">LD</div>
        <div class="profile-hero-info">
          <div class="profile-name" id="profile-display-name">Lucid Dreamer</div>
          <div class="profile-email" id="profile-email">loading...</div>
          <div class="profile-since" id="profile-since">Member since —</div>
        </div>
        <button class="save-btn" onclick="goTo('dashboard')" style="flex-shrink:0">← Dashboard</button>
      </div>

      <div class="profile-stats-row" id="profile-stats-row">
        <div class="profile-stat"><div class="profile-stat-num">—</div><div class="profile-stat-label">Dreams</div></div>
        <div class="profile-stat"><div class="profile-stat-num">—</div><div class="profile-stat-label">Matches</div></div>
        <div class="profile-stat"><div class="profile-stat-num">—</div><div class="profile-stat-label">Recurring</div></div>
        <div class="profile-stat"><div class="profile-stat-num">—</div><div class="profile-stat-label">Connections</div></div>
      </div>

      <div id="membership-section"></div>
      <div id="appearance-section"></div>

      <!-- Display name -->
      <div class="settings-section">
        <div class="settings-section-title">Identity</div>
        <div class="settings-field">
          <label class="settings-label">Display Name <span style="font-size:0.68rem;color:var(--indigo-light)">(only visible to mutual connections)</span></label>
          <input class="settings-input" id="setting-display-name" type="text" placeholder="Choose an anonymous name...">
        </div>
        <div class="settings-row">
          <div class="settings-field">
            <label class="settings-label">Region</label>
            <input class="settings-input" id="setting-region" type="text" placeholder="e.g. South Africa">
          </div>
          <div class="settings-field">
            <label class="settings-label">Country Code</label>
            <input class="settings-input" id="setting-region-code" type="text" placeholder="e.g. ZA" maxlength="2" style="text-transform:uppercase">
          </div>
        </div>
        <button class="save-btn" id="save-identity-btn" onclick="saveIdentity()">Save Identity</button>
      </div>

      <!-- Password -->
      <div class="settings-section">
        <div class="settings-section-title">Security</div>
        <div class="settings-row">
          <div class="settings-field">
            <label class="settings-label">New Password</label>
            <input class="settings-input" id="setting-new-password" type="password" placeholder="Min. 8 characters">
          </div>
          <div class="settings-field">
            <label class="settings-label">Confirm Password</label>
            <input class="settings-input" id="setting-confirm-password" type="password" placeholder="Repeat new password">
          </div>
        </div>
        <button class="save-btn" id="save-password-btn" onclick="savePassword()">Update Password</button>
      </div>

      <!-- Notifications -->
      <div class="settings-section">
        <div class="settings-section-title">Notifications</div>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
          <div>
            <div style="font-size:0.87rem;font-weight:500;color:var(--indigo)">Browser notifications</div>
            <div class="settings-info" style="margin-top:0.2rem">New activity alerts while Oneiros is open</div>
          </div>
          <button id="push-toggle-btn" onclick="togglePushFromProfile()" class="save-btn" style="flex-shrink:0">
            Enable
          </button>
        </div>
        <div class="settings-info">In-app notifications are always available. Optional browser alerts work while this app is open and your browser supports them.</div>
      </div>

      <!-- Research & consent -->
      <div class="settings-section">
        <div class="settings-section-title">Research & Data</div>
        <div class="settings-info">
          Your anonymized dream data contributes to global sleep and consciousness research. This consent was accepted at registration and is a condition of platform use.
          You may request a full export or deletion of your personal data at any time.
        </div>
        <div style="display:flex;gap:0.75rem;margin-top:1.2rem;flex-wrap:wrap">
          <button class="save-btn" onclick="exportDreams()">Export My Dreams</button>
        </div>
      </div>

      <!-- Danger zone -->
      <div class="settings-section">
        <div class="settings-section-title" style="color:rgba(180,60,60,0.7)">Danger Zone</div>
        <div class="settings-info">Deleting your account permanently removes all your dreams, matches, and connections. Research data already processed cannot be reversed.</div>
        <div style="margin-top:1rem;display:flex;gap:0.75rem">
          <button class="danger-btn" onclick="logout()">Sign Out</button>
          <button class="danger-btn" onclick="confirmDeleteAccount()">Delete Account</button>
        </div>
      </div>

    </div>
  </div>
</div>

<dialog id="dream-dialog" class="dream-dialog" aria-label="Dream details"><button class="dialog-close" onclick="closeDreamDialog()" aria-label="Close dream">×</button><div id="dream-detail"></div></dialog><dialog id="info-dialog" class="dream-dialog info-dialog" aria-label="Platform information"><button class="dialog-close" onclick="document.getElementById('info-dialog').close()" aria-label="Close information">×</button><div id="info-content"></div></dialog><div id="toast" role="status" aria-live="polite"></div><div id="print-root" aria-hidden="true"></div>
<script src="assets/app.js?v=2.1.0"></script>
<script src="assets/experience.js?v=2.1.0"></script>
<script src="assets/lucid.js?v=2.1.0"></script>
</body>
</html>
