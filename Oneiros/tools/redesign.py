from pathlib import Path
import re

root=Path(__file__).resolve().parents[1]
p=root/'oneiros.php'
s=p.read_text(encoding='utf-8')
css=re.search(r'<style>(.*?)</style>',s,re.S).group(1)
(root/'assets/base.css').write_text(css,encoding='utf-8')
s=re.sub(r'<style>.*?</style>', '<link rel="preload" as="image" href="assets/dreamscape.webp">\n<link rel="stylesheet" href="assets/base.css?v=2.0.0">\n<link rel="stylesheet" href="assets/dream.css?v=2.0.0">',s,count=1,flags=re.S)
js=re.search(r'<script>\s*//.*?</script>',s,re.S).group(0)[8:-9]
js=js.replace("const API_BASE = window.location.origin + '/api';", "const APP_BASE = new URL('.', location.href);\nconst API_BASE = new URL('api', APP_BASE).href;")
js=js.replace('let useRewriteFallback = null;', 'let useRewriteFallback = true;')
js=js.replace("navigator.serviceWorker.register('/sw.js')", "navigator.serviceWorker.register(new URL('sw.js', APP_BASE).href)")
js=js.replace("navigator.serviceWorker.register('./sw.js')", "navigator.serviceWorker.register(new URL('sw.js', APP_BASE).href)")
js=js.replace('End-to-end encrypted','Private conversation')
js=js.replace("  event?.currentTarget?.classList.add('active');", "  document.querySelector(`[data-connection=\"${connectionId}\"]`)?.classList.add('active');")
js=js.replace("region: 'South Africa',\n        region_code: 'ZA'", "region: document.getElementById('reg-region').selectedOptions[0].textContent,\n        region_code: document.getElementById('reg-region').value")
js=js.replace("privacy:     'public',", "privacy: document.getElementById('dream-privacy').value,")
js=js.replace("content,\n      emotions:", "content,\n      title: document.getElementById('dream-title').value.trim(),\n      emotions:")
js=js.replace("dreamed_at:  new Date().toISOString(),", "dreamed_at: document.getElementById('dream-date').value ? new Date(document.getElementById('dream-date').value + 'T12:00:00').toISOString() : new Date().toISOString(),")
js=js.replace("const selectedEmotions = Array.from(document.querySelectorAll('.ai-option.selected'))", "const selectedEmotions = Array.from(document.querySelectorAll('.emotion-choice.selected'))")
js=js.replace("document.getElementById('dream-text').value = '';", "document.getElementById('dream-text').value = '';\n    document.getElementById('dream-title').value = '';\n    document.querySelectorAll('.emotion-choice').forEach(el=>el.classList.remove('selected'));\n    localStorage.removeItem('oneiros_draft_' + currentUser.id);")
js=js.replace("await uploadImageToSupabase(dream.id, selectedFile);", "const imageResult = await uploadImageToSupabase(dream.id, selectedFile);\n      if (!imageResult) showToast('Dream saved, but image upload failed. You can add it from your journal.', 'error');")
js=js.replace("await fetch(buildApiUrl('/audio/upload'),", "const audioResponse = await fetch(buildApiUrl('/audio/upload'),")
js=js.replace("body:    audioForm,\n        });", "body:    audioForm,\n        });\n        if (!audioResponse.ok) showToast('Dream saved, but audio upload failed.', 'error');")
js=js.replace("  const txt = document.getElementById('dream-text').value.trim();\n\n  // Allow", "  if (recording) { showToast('Stop your recording before saving.', 'error'); return; }\n  const txt = document.getElementById('dream-text').value.trim();\n\n  // Allow")
js=js.replace("navigator.mediaDevices.getUserMedia({ audio: true, video: false })", "(navigator.mediaDevices && window.MediaRecorder ? navigator.mediaDevices.getUserMedia({ audio: true, video: false }) : Promise.reject(new Error('Voice recording needs HTTPS and a supported browser. You can still type your dream.')))")
js=js.replace("    const dreamDates = new Set();", "    const dreamDates = new Set();")
js=js.replace("for (const fb of [false, true])", "for (const fb of [true])")
js=js.replace("else { el.textContent = target.toLocaleString(); delete animateCount.startTime; }", "else { el.textContent = target.toLocaleString(); }")
js=js.replace("  const start = 0;\n  const step", "  const start = 0;\n  let startTime;\n  const step").replace('animateCount.startTime','startTime')
js=js.replace("if (!res.ok) return false;\n    const data = await res.json();", "if (!res.ok) return false;\n    const data = await res.json();")
js=js.replace("return await retry.json();", "const result = await retry.json();\n        if (!retry.ok) throw new Error(result.error || 'Request failed');\n        return result;")
js=js.replace("if (err.name !== 'TypeError') showToast('⚠️ ' + err.message, 'error');", "showToast(err.name === 'TypeError' ? 'Cannot reach the server. Your draft is still here.' : err.message, 'error');")
(root/'assets/app.js').write_text(js,encoding='utf-8')
s=re.sub(r'<script>\s*//.*?</script>', '<script src="assets/app.js?v=2.0.0"></script>\n<script src="assets/experience.js?v=2.0.0"></script>',s,count=1,flags=re.S)
s=s.replace("$appVersion = 'v1.2.0';", "$appVersion = 'v2.0.0';")
s=s.replace('#2D1B4E','#080b18').replace('#FAF7F2','#080b18')
s=s.replace('href="/og-image.png"','href="assets/dreamscape.webp"').replace('content="/og-image.png"','content="assets/dreamscape.webp"')
s=s.replace('<body>', '<body data-view="landing">\n<a class="skip-link" href="#main-content">Skip to content</a>')
s=s.replace('<nav id="main-nav">','<nav id="main-nav" aria-label="Main navigation">')
s=s.replace('<div class="nav-logo" onclick="goTo(\'landing\')">One<span>Iros</span></div>', '<button class="nav-logo" onclick="goTo(currentUser ? \'dashboard\' : \'landing\')" aria-label="Oneiros home"><span class="logo-orbit" aria-hidden="true">◌</span> oneiros<span class="logo-star">✦</span></button>')
s=s.replace('<div class="nav-links" id="nav-auth-links">', '<div class="landing-nav"><a href="#journey">The experience</a><a href="#universe">Our universe</a><button onclick="showInfo(\'privacy\')">Your privacy</button></div>\n<div class="nav-links" id="nav-auth-links">')
s=s.replace('Join for Free','Begin your journey <span aria-hidden="true">↗</span>')
s=s.replace('id="nb-dash"','id="nb-dashboard"')
s=s.replace('<button class="notif-btn" onclick="toggleNotif()">','<button class="notif-btn" onclick="toggleNotif()" aria-label="Notifications">')
s=s.replace('<div class="modal" id="modal-box" style="position:relative">','<div class="modal" id="modal-box" style="position:relative" role="dialog" aria-modal="true" aria-label="Your Oneiros account">')
s=s.replace('<button class="modal-close" onclick="closeModal()">','<button class="modal-close" onclick="closeModal()" aria-label="Close account dialog">')
s=s.replace('<input class="form-input" type="email"', '<input class="form-input" id="login-email" aria-label="Email" autocomplete="email" type="email"')
s=s.replace('<input class="form-input" type="password"', '<input class="form-input" id="login-password" aria-label="Password" autocomplete="current-password" type="password"')
s=s.replace('id="reg-email" type=', 'id="reg-email" aria-label="Email" autocomplete="email" type=')
s=s.replace('id="reg-password" type=', 'id="reg-password" aria-label="New password" autocomplete="new-password" type=')
s=s.replace('id="reg-dob" type=', 'id="reg-dob" aria-label="Date of birth" type=')
s=s.replace('Create your dream journal — free forever','A little space for your infinite inner world.')
s=s.replace('No names, locations, or identifying information is ever shared.', 'Public entries are visible to other dreamers. Private entries stay in your journal; research entries contribute only to aggregate insights. Avoid including identifying details in dream text.')
s=s.replace('<a href="#" style="color:var(--indigo-mid)">Terms of Service</a> &amp; Privacy Policy', '<button class="text-link" onclick="showInfo(\'terms\')">Terms of Service</button> &amp; <button class="text-link" onclick="showInfo(\'privacy\')">Privacy Policy</button>')
s=s.replace('<button class="btn-primary" style="width:100%;margin-top:0.5rem;padding:0.7rem" onclick="regNextStep()">', '<div class="form-group"><label class="form-label" for="reg-region">Your region</label><select class="form-input" id="reg-region"><option value="ZA">South Africa</option><option value="US">United States</option><option value="GB">United Kingdom</option><option value="AU">Australia</option><option value="CA">Canada</option><option value="IN">India</option><option value="DE">Germany</option><option value="FR">France</option><option value="BR">Brazil</option><option value="JP">Japan</option><option value="NG">Nigeria</option><option value="KE">Kenya</option><option value="NZ">New Zealand</option><option value="">Prefer not to say</option></select></div><button class="btn-primary" style="width:100%;margin-top:0.5rem;padding:0.7rem" onclick="regNextStep()">')
s=re.sub(r'<!-- PREMIUM UPGRADE MODAL -->.*?<!-- ACHIEVEMENT TOAST -->','<!-- ACHIEVEMENT TOAST -->',s,flags=re.S)
start=s.index('<div class="page active" id="page-landing">')
end=s.index('<!-- ═══════════ DASHBOARD',start)
landing='''<main class="page active" id="page-landing">
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
  <section class="last-invitation reveal"><div class="eyebrow">SEE YOU ON THE OTHER SIDE</div><h2>The next chapter<br>begins <em>tonight.</em></h2><button class="btn-primary" onclick="openModal('register')">Begin your dream journal <span>↗</span></button><p>No perfect words needed. Just what you remember.</p></section>
  <footer class="dream-footer"><a class="nav-logo" href="#">◌ oneiros<span class="logo-star">✦</span></a><p>For everything you are, even in your sleep.</p><div><button onclick="showInfo('privacy')">Privacy</button><button onclick="showInfo('terms')">Terms</button><button onclick="toggleMotion()" id="motion-toggle" aria-pressed="false">Pause motion</button></div><span>© '''+str(2026)+''' Oneiros</span></footer>
</main>
'''
s=s[:start]+landing+'\n'+s[end:]
s=s.replace('Good morning, <em>Lucid Dreamer</em>', 'Welcome to your <em>dreamspace.</em>')
s=s.replace('Wednesday, 11 March 2026 · What did you dream last night?', 'A quiet moment to remember.')
s=s.replace('<div class="capture-prompt">"Describe your dream, or use voice, image, or let AI paint it for you..."</div>', '<div class="capture-topline"><span class="eyebrow">YOUR NEXT CHAPTER</span><span class="draft-state" id="draft-state">A space to remember</span></div><label class="capture-prompt" for="dream-text">Where did your mind wander?</label><input id="dream-title" class="dream-title-input" aria-label="Dream title" placeholder="Give this dream a name (optional)" maxlength="200">')
s=s.replace('placeholder="I was standing at the edge of a glass city that floated above endless clouds. A figure dressed in white walked towards me but never arrived..."', 'placeholder="A place, a feeling, a fleeting detail… start with what stayed with you." maxlength="50000"')
s=s.replace('<!-- AI INTERVIEW -->','<div class="capture-meta"><label>Dream date<input type="date" id="dream-date" aria-label="Dream date"></label><label>Visibility<select id="dream-privacy" aria-label="Dream visibility"><option value="private">Private · only me</option><option value="public">Public · find connections</option><option value="research_only">Research · aggregate insights</option></select></label></div><div class="emotion-selector"><span>How did it feel?</span>'+''.join('<button class="emotion-choice" aria-pressed="false" onclick="this.classList.toggle(\'selected\');this.setAttribute(\'aria-pressed\',this.classList.contains(\'selected\'))">'+e+'</button>' for e in ['Wonder','Peace','Joy','Fear','Longing','Confusion'])+'</div><!-- AI INTERVIEW -->')
s=s.replace('✨ AI Paint','✧ Dream art').replace('🎨 Generate Dream Painting','Create a dream canvas').replace('✦ OneIros AI:', '✦ Recall prompt:')
s=s.replace('47 dreamers shared your vision last night · All-time: 1,284 matches across 34 countries','Discover familiar themes across different lives.')
s=s.replace('Live dream activity across 91 countries · Country-level precision only · All data anonymized','Explore shared dreams, one country at a time. Locations are approximate.')
s=s.replace('847 dreamers active','Loading activity…').replace('↑ 12 more than your average','Your connections begin with a dream.').replace('Across 34 countries','Shared themes and feelings').replace('Since joining · 3 recurring','Every dream has a place here')
s=s.replace('id="journal-list"', 'id="journal-list"')
s=s.replace('<div class="journal-list"', '<div class="journal-toolbar"><label class="search-field"><span>⌕</span><input type="search" id="journal-search" placeholder="Search your dreams…" aria-label="Search dreams" oninput="filterJournal()"></label><select id="journal-filter" aria-label="Filter journal" onchange="filterJournal()"><option value="all">All dreams</option><option value="private">Private</option><option value="public">Public</option><option value="research_only">Research</option><option value="recurring">Recurring</option></select><button class="btn-primary" onclick="goTo(\'dashboard\');document.getElementById(\'dream-text\').focus()">+ New dream</button></div><div class="journal-list"')
s=s.replace('<!-- Push notifications -->','<!-- Notifications -->')
s=s.replace('Push Notifications','Browser notifications').replace('Morning match alerts direct to your device','New activity alerts while Oneiros is open').replace('Notifications are sent when you receive new dream matches, connection requests, or messages.','In-app notifications are always available. Optional browser alerts work while this app is open and your browser supports them.')
s=s.replace("🌙 You're offline — your dream journal is still available", "You’re offline. Your current draft stays on this device; reconnect to save.")
s=s.replace('Log Dream →','Save my dream ↗')
s=s.replace('<!-- ═══════════ PROFILE ═══════════ -->','<div class="page" id="page-insights"><div class="dash-layout"><div class="sidebar"></div><div class="dash-main"><div class="page-header"><div class="eyebrow">YOUR INNER UNIVERSE</div><h1 class="page-title">Patterns in the <em>quiet.</em></h1><p class="page-sub">A reflection of your journal, alongside the collective dream.</p></div><div id="insights-content" class="insights-grid"></div></div></div></div>\n<!-- ═══════════ PROFILE ═══════════ -->')
s=s.replace('<script src="assets/app.js', '<dialog id="dream-dialog" class="dream-dialog" aria-label="Dream details"><button class="dialog-close" onclick="closeDreamDialog()" aria-label="Close dream">×</button><div id="dream-detail"></div></dialog><dialog id="info-dialog" class="dream-dialog info-dialog" aria-label="Platform information"><button class="dialog-close" onclick="document.getElementById(\'info-dialog\').close()" aria-label="Close information">×</button><div id="info-content"></div></dialog><div id="toast" role="status" aria-live="polite"></div>\n<script src="assets/app.js')
p.write_text(s,encoding='utf-8')
(root/'oneiros.html').write_text('<!doctype html><html lang="en"><meta charset="utf-8"><meta http-equiv="refresh" content="0;url=oneiros.php"><title>Oneiros</title><a href="oneiros.php">Enter Oneiros</a></html>',encoding='utf-8')
print('Main app extracted and redesigned.')
