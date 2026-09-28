
// ════════════════════════════════════════════════════════════
// Oneiros — Full API-wired Frontend
// ════════════════════════════════════════════════════════════

// ── CONFIG ── Change this to your Railway URL when deployed
const APP_BASE = new URL('.', location.href);
const API_BASE = new URL('api', APP_BASE).href;  // ← update after deploy

// ── STATE ──
let accessToken = null;
let currentUser = null;
let recording = false;
let recInterval = null;
let recSeconds = 0;
let currentMethod = 'text';
let aiStep = 0;
let dreamJournal = [];
let allMatches = [];
let currentDreamId = null;
let notifPollInterval = null;

// ── VOICE + AI PAINT STATE ──
let mediaRecorder      = null;
let audioChunks        = [];
let speechRecognition  = null;
let audioBlob          = null;
let aiGeneratedImageUrl = null;
let aiInterviewAnswers = [];

// ════════════════════════════════════════════════════════════
// STAR FIELD
// ════════════════════════════════════════════════════════════
(function initStars() {
  const sf = document.getElementById('starfield');
  if (!sf) return;
  const count = 60;
  for (let i = 0; i < count; i++) {
    const s = document.createElement('div');
    s.className = 'star';
    const size = Math.random() * 2.5 + 1;
    s.style.cssText = `
      width:${size}px;height:${size}px;
      left:${Math.random()*100}%;top:${Math.random()*100}%;
      --dur:${(Math.random()*4+2).toFixed(1)}s;
      --delay:-${(Math.random()*5).toFixed(1)}s;
      opacity:${Math.random()*0.4+0.1};
    `;
    sf.appendChild(s);
  }
})();

// ════════════════════════════════════════════════════════════
// NUMBER COUNTER ANIMATION
// ════════════════════════════════════════════════════════════
function animateCount(el, target, duration = 1200) {
  if (!el) return;
  const start = 0;
  let startTime;
  const step = (timestamp) => {
    if (!startTime) startTime = timestamp;
    const elapsed = timestamp - startTime;
    const progress = Math.min(elapsed / duration, 1);
    const eased = 1 - Math.pow(1 - progress, 3);
    const current = Math.round(start + (target - start) * eased);
    el.textContent = current.toLocaleString();
    if (progress < 1) requestAnimationFrame(step);
    else { el.textContent = target.toLocaleString(); }
  };
  requestAnimationFrame(step);
}

// ════════════════════════════════════════════════════════════
// REGISTRATION STEP FLOW
// ════════════════════════════════════════════════════════════
function regNextStep() {
  const email    = document.getElementById('reg-email')?.value.trim();
  const password = document.getElementById('reg-password')?.value;
  const dob      = document.getElementById('reg-dob')?.value;

  if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    showToast('⚠️ Enter a valid email address', 'error'); return;
  }
  if (!password || password.length < 8) {
    showToast('⚠️ Password must be at least 8 characters', 'error'); return;
  }
  if (!dob) {
    showToast('⚠️ Date of birth is required', 'error'); return;
  }
  // Age check
  const age = (Date.now() - new Date(dob).getTime()) / (365.25 * 24 * 3600 * 1000);
  if (age < 18) {
    showToast('⚠️ You must be 18 or older to join', 'error'); return;
  }

  document.getElementById('reg-step-1').style.display = 'none';
  document.getElementById('reg-step-2').style.display = 'block';
  document.getElementById('reg-step-dot-1').classList.remove('active');
  document.getElementById('reg-step-dot-2').classList.add('active');
}

function regPrevStep() {
  document.getElementById('reg-step-2').style.display = 'none';
  document.getElementById('reg-step-1').style.display = 'block';
  document.getElementById('reg-step-dot-2').classList.remove('active');
  document.getElementById('reg-step-dot-1').classList.add('active');
}

// ════════════════════════════════════════════════════════════
// API CLIENT
// ════════════════════════════════════════════════════════════

// Auto-detect if mod_rewrite is working. Cached after first request.
let useRewriteFallback = true;

function buildApiUrl(path) {
  // Strip leading slash
  const cleanPath = path.startsWith('/') ? path.substring(1) : path;
  if (useRewriteFallback === true) {
    // Use front controller fallback: /api.php?_route=auth/login
    const [route, query] = cleanPath.split('?');
    const sep = query ? '&' : '';
    return `${API_BASE.replace(/\/api$/, '')}/api.php?_route=${route}${sep}${query || ''}`;
  }
  // Default: clean URL via .htaccess rewrite
  return API_BASE + path;
}

async function api(method, path, body = null, requiresAuth = true) {
  const headers = { 'Content-Type': 'application/json' };
  if (requiresAuth && accessToken) headers['Authorization'] = `Bearer ${accessToken}`;

  const opts = { method, headers };
  if (body) opts.body = JSON.stringify(body);

  try {
    let res = await fetch(buildApiUrl(path), opts);

    // First request? If 404, fall back to direct api.php
    if (res.status === 404 && useRewriteFallback === null) {
      console.log('Oneiros: mod_rewrite seems disabled — using direct routing');
      useRewriteFallback = true;
      res = await fetch(buildApiUrl(path), opts);
    } else if (useRewriteFallback === null && res.status !== 404) {
      useRewriteFallback = false;
    }

    // Token expired — try refresh
    if (res.status === 401 && requiresAuth) {
      const refreshed = await tryRefresh();
      if (refreshed) {
        headers['Authorization'] = `Bearer ${accessToken}`;
        const retry = await fetch(buildApiUrl(path), { ...opts, headers });
        const result = await retry.json();
        if (!retry.ok) throw Object.assign(new Error(result.error || 'Request failed'), { status: retry.status, data: result });
        return result;
      } else {
        handleLogout();
        return null;
      }
    }

    let data;
    try { data = await res.json(); }
    catch (e) {
      // Server returned HTML (probably error page)
      throw new Error('Server error — check cPanel error log');
    }
    if (!res.ok) throw Object.assign(new Error(data.error || data.message || 'Request failed'), { status: res.status, data });
    return data;
  } catch (err) {
    showToast(err.name === 'TypeError' ? 'Cannot reach the server. Your draft is still here.' : err.message, 'error');
    if (err.status === 402 && typeof openLucid === 'function') setTimeout(() => openLucid(err.data?.upgrade || 'limit'), 600);
    throw err;
  }
}

async function tryRefresh() {
  const rt = localStorage.getItem('oneiros_refresh_token');
  if (!rt) return false;
  try {
    const res = await fetch(buildApiUrl('/auth/refresh'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ refresh_token: rt })
    });
    if (!res.ok) return false;
    const data = await res.json();
    setTokens(data.access_token, data.refresh_token);
    currentUser = data.user;
    return true;
  } catch { return false; }
}

function setTokens(access, refresh) {
  accessToken = access;
  if (refresh) localStorage.setItem('oneiros_refresh_token', refresh);
}

// ════════════════════════════════════════════════════════════
// NAVIGATION
// ════════════════════════════════════════════════════════════

function goTo(page) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.getElementById('page-' + page).classList.add('active');
  document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
  const nb = document.getElementById('nb-' + page);
  if (nb) nb.classList.add('active');
  closeNotif();
  window.scrollTo(0, 0);

  // Load data for each page when navigating to it
  if (page === 'dashboard' && currentUser) loadDashboard();
  if (page === 'journal' && currentUser) loadJournal();
  if (page === 'matches' && currentUser) loadMatches();
  if (page === 'map' && currentUser) loadMap();
}

// ════════════════════════════════════════════════════════════
// AUTH
// ════════════════════════════════════════════════════════════

function openModal(type) {
  document.getElementById('modal-overlay').classList.add('open');
  document.getElementById('modal-login').style.display = type === 'login' ? 'block' : 'none';
  document.getElementById('modal-register').style.display = type === 'register' ? 'block' : 'none';
}

function closeModal(e) {
  if (!e || e.target === document.getElementById('modal-overlay')) {
    document.getElementById('modal-overlay').classList.remove('open');
  }
}

function switchModal(type) {
  document.getElementById('modal-login').style.display = type === 'login' ? 'block' : 'none';
  document.getElementById('modal-register').style.display = type === 'register' ? 'block' : 'none';
}

async function login() {
  const isRegister = document.getElementById('modal-register').style.display !== 'none';

  if (isRegister) {
    // Validate consents
    if (!document.getElementById('tos').checked) { showToast('⚠️ Please accept the Terms of Service', 'error'); return; }
    if (!document.getElementById('research').checked) { showToast('⚠️ Research consent is required to join', 'error'); return; }
    if (!document.getElementById('age').checked) { showToast('⚠️ You must confirm you are 18 or older', 'error'); return; }

    const email    = document.getElementById('reg-email')?.value.trim()    || document.querySelector('#modal-register input[type=email]')?.value.trim();
    const password = document.getElementById('reg-password')?.value         || document.querySelector('#modal-register input[type=password]')?.value;
    const dob      = document.getElementById('reg-dob')?.value              || document.querySelector('#modal-register input[type=date]')?.value;

    if (!email || !password || !dob) { showToast('⚠️ Please fill in all fields', 'error'); return; }

    setButtonLoading('register-btn', true);
    try {
      const data = await api('POST', '/auth/register', {
        email, password,
        date_of_birth: dob,
        research_consent: true,
        tos_accepted: true,
        region: document.getElementById('reg-region').selectedOptions[0].textContent,
        region_code: document.getElementById('reg-region').value,
        referral_code: (() => { try { return localStorage.getItem('oneiros_ref') || ''; } catch { return ''; } })()
      }, false);
      if (!data) return;
      setTokens(data.access_token, data.refresh_token);
      currentUser = data.user;
      onLoginSuccess();
    } catch (err) {
      showToast('⚠️ ' + err.message, 'error');
    } finally {
      setButtonLoading('register-btn', false);
    }
  } else {
    const email = document.querySelector('#modal-login input[type=email]').value.trim();
    const password = document.querySelector('#modal-login input[type=password]').value;

    if (!email || !password) { showToast('⚠️ Please enter your email and password', 'error'); return; }

    setButtonLoading('login-btn', true);
    try {
      const data = await api('POST', '/auth/login', { email, password }, false);
      if (!data) return;
      setTokens(data.access_token, data.refresh_token);
      currentUser = data.user;
      onLoginSuccess();
    } catch (err) {
      showToast('⚠️ Invalid email or password', 'error');
    } finally {
      setButtonLoading('login-btn', false);
    }
  }
}

function onLoginSuccess() {
  document.getElementById('modal-overlay').classList.remove('open');
  document.getElementById('nav-auth-links').style.display = 'none';
  document.getElementById('nav-user-links').style.display = 'flex';

  // Update avatar initials everywhere
  const name = currentUser.display_name || currentUser.email;
  const initials = name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0,2);
  document.querySelectorAll('.avatar, #mobile-avatar').forEach(el => el.textContent = initials);

  // Show mobile bottom nav + mobile header on small screens
  if (window.innerWidth <= 768) {
    document.getElementById('bottom-nav').style.display = 'flex';
    document.getElementById('main-nav').style.display = 'none';
    document.getElementById('mobile-header').style.display = 'flex';
  } else {
    document.getElementById('bottom-nav').style.display = 'none';
  }

  goTo('dashboard');
  showToast('✨ Welcome to Oneiros');
  document.dispatchEvent(new Event('oneiros:signed-in'));
  startNotifPolling();
  initPushNotifications();
  // Start onboarding tour for first-time users (small delay so dashboard loads)
  setTimeout(() => tourStart(), 800);
}

async function logout() {
  try {
    const rt = localStorage.getItem('oneiros_refresh_token');
    await api('POST', '/auth/logout', { refresh_token: rt });
  } catch {}
  handleLogout();
}

function handleLogout() {
  accessToken = null;
  currentUser = null;
  localStorage.removeItem('oneiros_refresh_token');
  clearInterval(notifPollInterval);
  clearInterval(chatPollInterval);
  dreamJournal = [];
  allMatches = [];
  activeConnectionId = null;
  document.getElementById('chat-pane').innerHTML = `<div class="chat-select-hint"><div style="font-size:2.5rem;opacity:0.25">🌙</div>
    <div style="font-family:'Cormorant Garamond',serif;font-size:1.2rem;font-style:italic;color:var(--indigo-light)">Select a conversation</div></div>`;
  document.querySelector('.journal-list').innerHTML = '';
  document.getElementById('dash-streak')?.remove();
  document.dispatchEvent(new Event('oneiros:signed-out'));
  document.getElementById('nav-auth-links').style.display = 'flex';
  document.getElementById('nav-user-links').style.display = 'none';
  document.getElementById('bottom-nav').style.display = 'none';
  document.getElementById('mobile-header').style.display = 'none';
  document.getElementById('main-nav').style.display = 'flex';
  goTo('landing');
}

// ════════════════════════════════════════════════════════════
// DASHBOARD
// ════════════════════════════════════════════════════════════

async function loadDashboard() {
  if (!currentUser) return;

  try {
    const [meStats, matchData, globalStats] = await Promise.all([
      api('GET', '/research/me').catch(() => null),
      api('GET', '/matches?limit=3').catch(() => null),
      api('GET', '/research/global').catch(() => null),
    ]);

    // ─── Personal stats cards ───
    const totalMatches = meStats?.total_matches ?? 0;
    const totalDreams  = meStats?.total_dreams ?? 0;
    const recurring    = meStats?.recurring_dreams ?? 0;
    const connections  = meStats?.connections ?? 0;

    // Resonance card (last night's matches — count of matches from past 24h)
    const lastNightMatches = (matchData?.matches || [])
      .filter(m => (Date.now() - new Date(m.created_at).getTime()) < 24*60*60*1000).length;

    const resonanceEl = document.getElementById('stat-resonance') || document.querySelector('.resonance-num');
    if (resonanceEl) resonanceEl.textContent = lastNightMatches;

    const matchesEl = document.getElementById('stat-matches');
    const dreamsEl  = document.getElementById('stat-dreams');
    if (matchesEl) matchesEl.textContent = totalMatches.toLocaleString();
    if (dreamsEl)  dreamsEl.textContent  = totalDreams.toLocaleString();
    // Also update by class for backwards compat
    const cardValues = document.querySelectorAll('.card-value');
    if (!matchesEl && cardValues[0]) cardValues[0].textContent = totalMatches.toLocaleString();
    if (!dreamsEl  && cardValues[1]) cardValues[1].textContent = totalDreams.toLocaleString();

    // Sub-text under "Total Matches"
    const matchesSub = document.querySelector('.dash-grid-3 .card:nth-of-type(2) .card-sub');
    if (matchesSub) {
      const countries = globalStats?.active_countries ?? 0;
      matchesSub.textContent = countries > 0 ? `Across ${countries} countries` : 'Awaiting your first match';
    }

    // Sub-text under "Dreams Logged"
    const dreamsSub = document.querySelector('.dash-grid-3 .card:nth-of-type(3) .card-sub');
    if (dreamsSub) {
      dreamsSub.textContent = `Since joining · ${recurring} recurring`;
    }

    // Resonance sub-text
    const resonanceSub = document.querySelector('.resonance-sub');
    if (resonanceSub) {
      const avg = totalDreams > 0 ? Math.round(totalMatches / totalDreams) : 0;
      if (lastNightMatches === 0) {
        resonanceSub.textContent = totalDreams === 0
          ? 'Log your first dream to find resonances'
          : 'Awaiting last night\'s analysis';
      } else if (lastNightMatches > avg) {
        resonanceSub.textContent = `↑ ${lastNightMatches - avg} more than your average`;
      } else if (lastNightMatches < avg) {
        resonanceSub.textContent = `↓ ${avg - lastNightMatches} fewer than your average`;
      } else {
        resonanceSub.textContent = 'Right on your average';
      }
    }

    // ─── Recent matches preview ───
    if (matchData?.matches?.length) {
      renderDashboardMatches(matchData.matches);
    } else {
      const container = document.querySelector('.match-preview');
      if (container) {
        container.innerHTML = `
          <div style="padding:1.5rem;text-align:center;color:var(--indigo-light);font-size:0.85rem;
                      background:rgba(255,255,255,0.4);border-radius:0.875rem;
                      border:1px dashed rgba(167,139,202,0.3)">
            ${totalDreams === 0
              ? '🌙 Log your first dream to discover resonances around the world'
              : '✨ Matches will appear here as the world dreams alongside you'}
          </div>`;
      }
    }
  } catch (err) {
    console.error('Dashboard load error:', err);
  }
}

function renderDashboardMatches(matches) {
  const container = document.querySelector('.match-preview');
  if (!container) return;
  container.innerHTML = matches.map(m => {
    const score = Math.round(m.score);
    const intensity = score >= 80 ? 'high' : score >= 60 ? 'mid' : 'low';
    return `
      <div class="match-row" onclick="goTo('matches')">
        <div class="match-pulse ${intensity}"><div class="pulse-dot"></div></div>
        <div class="match-info">
          <div class="match-title">${escHtml(m.matched_dream_preview?.title || 'Untitled dream')}</div>
          <div class="match-meta">
            ${m.region_code ? flagEmoji(m.region_code) : '🌍'} ${m.region || 'Unknown region'}
            · Emotions: ${(m.matched_dream_preview?.emotions || []).slice(0,2).join(', ') || '—'}
          </div>
        </div>
        <div class="match-pct">${score}%</div>
      </div>`;
  }).join('');
}

// ════════════════════════════════════════════════════════════
// DREAM JOURNAL
// ════════════════════════════════════════════════════════════

async function loadJournal() {
  const container = document.querySelector('.journal-list');
  if (!dreamJournal.length) container.innerHTML = '<div class="empty-state"><span class="empty-symbol">◌</span>Gathering your dreams…</div>';

  try {
    const data = await api('GET', '/dreams?limit=50');
    dreamJournal = data?.dreams || [];
    const recurring = dreamJournal.filter(d => d.is_recurring).length;
    document.querySelector('#page-journal .page-sub').textContent = data?.total
      ? `${data.total} dream${data.total === 1 ? '' : 's'} remembered · ${recurring} recurring`
      : 'Your journal is waiting for its first page.';
    filterJournal();
  } catch {}
}

function filterJournal() {
  const container = document.querySelector('.journal-list');
  if (!container) return;
  if (!dreamJournal.length) {
    container.innerHTML = `<div class="empty-state"><span class="empty-symbol">☾</span><h3>The first page is still blank.</h3>
      Write down whatever you remember, even a single image. Every dream you save lives here.
      <br><button class="btn-primary" onclick="goTo('dashboard');document.getElementById('dream-text').focus()">Remember a dream <span>↗</span></button></div>`;
    return;
  }
  const query  = (document.getElementById('journal-search')?.value || '').trim().toLowerCase();
  const filter = document.getElementById('journal-filter')?.value || 'all';
  const shown = dreamJournal.filter(d => {
    if (filter === 'recurring' && !d.is_recurring) return false;
    if (!['all', 'recurring'].includes(filter) && d.privacy !== filter) return false;
    if (!query) return true;
    return [d.title, d.content, ...(d.themes || []), ...(d.emotions || []), ...(d.symbols || [])]
      .some(v => String(v || '').toLowerCase().includes(query));
  });
  container.innerHTML = shown.length
    ? shown.map(renderJournalEntry).join('')
    : `<div class="empty-state"><span class="empty-symbol">⌕</span><h3>Nothing matches that yet.</h3>Try another word, or clear the filter to see every dream.</div>`;
}

function renderJournalEntry(dream) {
  const emoji = getDreamEmoji(dream.themes);
  const date = new Date(dream.dreamed_at).toLocaleDateString('en-ZA', { day: 'numeric', month: 'short', year: 'numeric' });
  const preview = dream.content.slice(0, 180) + (dream.content.length > 180 ? '...' : '');
  const tags = [...(dream.emotions || []), ...(dream.themes || [])].slice(0, 3);

  return `
    <div class="journal-entry" role="button" tabindex="0" onclick="openDream('${dream.id}')" onkeydown="if(event.key==='Enter')openDream('${dream.id}')">
      <div class="journal-entry-inner">
        <div class="journal-img" ${dream.image_url ? '' : `data-thumb="${dream.id}"`}>${dream.image_url ? `<img src="${escHtml(dream.image_url)}" alt="" loading="lazy">` : emoji}</div>
        <div class="journal-body">
          <div class="journal-meta">
            <span class="journal-date">${date}</span>
            <span class="privacy-tag ${dream.privacy === 'public' ? 'pub' : 'priv'}">
              ${dream.privacy === 'public' ? 'Public' : dream.privacy === 'private' ? 'Private' : 'Research'}
            </span>
            ${dream.is_recurring ? '<span class="recurring-tag">🔁 Recurring</span>' : ''}
          </div>
          <div class="journal-title">${escHtml(dream.title || 'Untitled Dream')}</div>
          <div class="journal-excerpt">${escHtml(preview)}</div>
          <div class="journal-footer">
            ${tags.map(t => `<span class="journal-tag">${escHtml(t)}</span>`).join('')}
            <span class="journal-matches" style="margin-left:auto">
              ${dream.privacy === 'private' ? '🔒 Private' : dream.privacy === 'research_only' ? '◌ Research' : `✨ ${dream.match_count || 0} ${dream.match_count === 1 ? 'match' : 'matches'}`}
            </span>
          </div>
        </div>
      </div>
    </div>`;
}

function openDream(dreamId) {
  currentDreamId = dreamId;
  goTo('matches');
  loadMatchesForDream(dreamId);
}

// ════════════════════════════════════════════════════════════
// DREAM SUBMISSION
// ════════════════════════════════════════════════════════════

async function submitDream() {
  const txt = document.getElementById('dream-text').value.trim();
  if (!txt) { showToast('✍️ Describe your dream first', 'error'); return; }
  if (txt.length < 10) { showToast('✍️ Please describe your dream in more detail', 'error'); return; }

  // Collect selected emotions from AI interview steps
  const selectedEmotions = Array.from(document.querySelectorAll('.emotion-choice.selected'))
    .map(el => el.textContent.toLowerCase().trim())
    .filter(e => ['terror','wonder','peace','joy','confusion','longing','sadness','dread','fear'].includes(e));

  setButtonLoading('submit-btn', true);
  try {
    const dream = await api('POST', '/dreams', {
      content: txt,
      emotions: selectedEmotions,
      privacy: 'public',
      dreamed_at: new Date().toISOString()
    });

    if (!dream) return;
    currentDreamId = dream.id;

    // Reset capture UI
    document.getElementById('dream-text').value = '';
    document.getElementById('dream-title').value = '';
    document.querySelectorAll('.emotion-choice').forEach(el=>el.classList.remove('selected'));
    localStorage.removeItem('oneiros_draft_' + currentUser.id);
    document.getElementById('ai-interview').classList.remove('show');
    document.getElementById('recorder-ui').classList.remove('show');
    document.querySelectorAll('.method-btn').forEach(b => b.classList.remove('active'));
    document.querySelector('.method-btn').classList.add('active');
    aiStep = 0;

    showToast('🌙 Dream logged · Finding resonances across the globe...');

    // Poll for matches after backend processes
    setTimeout(async () => {
      try {
        const matchData = await api('GET', `/dreams/${dream.id}/matches?limit=5`);
        if (matchData?.matches?.length) {
          showToast(`✨ ${matchData.total} dreamers share your vision!`);
          loadDashboard();
        } else {
          showToast('🌙 Dream saved · Matching in progress...');
        }
      } catch {}
    }, 2500);

  } catch (err) {
    showToast('⚠️ Failed to save dream: ' + err.message, 'error');
  } finally {
    setButtonLoading('submit-btn', false);
  }
}

// ════════════════════════════════════════════════════════════
// MATCHES
// ════════════════════════════════════════════════════════════

// Guards against an older request (all matches) rendering over a newer one (one dream's matches)
let matchesRequestSeq = 0;

async function loadMatches() {
  const seq = ++matchesRequestSeq;
  const container = document.getElementById('matches-container');
  container.innerHTML = '<div class="empty-state"><span class="empty-symbol">◌</span>Listening for resonances…</div>';

  try {
    const data = await api('GET', '/matches?limit=50');
    if (seq !== matchesRequestSeq) return;
    allMatches = data?.matches || [];
    document.querySelector('#page-matches .page-sub').textContent = allMatches.length
      ? `${data.total} resonance${data.total === 1 ? '' : 's'} with dreamers around the world`
      : 'Discover familiar themes across different lives.';
    if (!allMatches.length) {
      container.innerHTML = `<div class="empty-state"><span class="empty-symbol">✧</span><h3>No resonances yet.</h3>
        Public dreams are compared with dreams from around the world. Save a dream as <strong>Public</strong> to begin.
        <br><button class="btn-primary" onclick="goTo('dashboard')">Remember a dream <span>↗</span></button></div>`;
      return;
    }
    renderMatches(allMatches);
    appendLockedTeaser(data.locked_count);
  } catch {}
}

// Free dreamers see their closest resonances; the rest wait behind Lucid
function appendLockedTeaser(locked) {
  if (!locked) return;
  document.getElementById('matches-container').insertAdjacentHTML('beforeend', `
    <div class="lucid-teaser">
      <div class="lucid-teaser-orbs" aria-hidden="true">${'<span></span>'.repeat(Math.min(locked, 5))}</div>
      <div><strong>${locked} more dreamer${locked === 1 ? '' : 's'} resonated with you.</strong>
      <p>Lucid reveals every resonance, not just the closest five.</p></div>
      <button class="btn-primary" onclick="openLucid('matches')">Reveal them <span>✦</span></button>
    </div>`);
}

async function loadMatchesForDream(dreamId) {
  const seq = ++matchesRequestSeq;
  const container = document.getElementById('matches-container');
  container.innerHTML = '<div class="empty-state"><span class="empty-symbol">◌</span>Listening for resonances…</div>';
  try {
    const data = await api('GET', `/dreams/${dreamId}/matches?limit=50`);
    if (seq !== matchesRequestSeq) return;
    allMatches = data?.matches || [];
    const dream = dreamJournal.find(d => d.id === dreamId);
    document.querySelector('#page-matches .page-sub').innerHTML =
      `Resonances for <em>${escHtml(dream?.title || 'this dream')}</em> · <button class="text-link" onclick="loadMatches()">Show all</button>`;
    if (!allMatches.length) {
      container.innerHTML = `<div class="empty-state"><span class="empty-symbol">✧</span><h3>This dream is still travelling.</h3>
        ${dream && dream.privacy !== 'public' ? 'Only public dreams are matched. Make this dream public to find dreamers who share it.' : 'No one has shared this dream yet. New matches appear as the world dreams.'}</div>`;
      return;
    }
    renderMatches(allMatches);
    appendLockedTeaser(data.locked_count);
  } catch {}
}

function renderMatches(matches) {
  const container = document.getElementById('matches-container');
  if (!matches.length) {
    container.innerHTML = '<div style="text-align:center;padding:3rem;color:var(--indigo-light)">No matches found.</div>';
    return;
  }
  container.innerHTML = matches.map(m => {
    const score = Math.round(m.score);
    const pctClass = score >= 90 ? 'pct-95' : score >= 80 ? 'pct-87' : score >= 70 ? 'pct-76' : 'pct-68';
    const dotColor = score >= 80 ? 'var(--pulse)' : score >= 65 ? 'var(--rose)' : 'var(--gold)';
    const preview = m.matched_dream_preview;
    const isConnected = m.connection_status === 'connected';
    const isPending = m.connection_status === 'pending';

    const dims = [
      m.theme_score > 40 ? `<span class="match-dim dim-theme">Theme: ${(preview.themes||[]).slice(0,2).join(', ') || 'similar'}</span>` : '',
      m.emotion_score > 40 ? `<span class="match-dim dim-emotion">Emotion: ${(preview.emotions||[]).slice(0,2).join(', ') || 'resonant'}</span>` : '',
      m.symbol_score > 40 ? `<span class="match-dim dim-visual">Visual: ${(preview.symbols||[]).slice(0,2).join(', ') || 'similar'}</span>` : '',
      m.narrative_score > 60 ? `<span class="match-dim dim-narr">Narrative: ${preview.narrative_arc || 'similar arc'}</span>` : ''
    ].filter(Boolean).join('');

    return `
      <div class="match-card">
        <div style="position:relative;padding-bottom:24px;flex-shrink:0">
          <div class="match-big-pulse ${pctClass}">
            <div class="big-dot" style="background:${dotColor}"></div>
          </div>
          <div class="big-pct">${score}%</div>
        </div>
        <div class="match-card-body">
          <div class="match-card-title">${escHtml(preview.title || 'Untitled Dream')}</div>
          <div class="match-card-excerpt">${escHtml(preview.content_preview || '')}${preview.content_preview?.length >= 200 ? '...' : ''}</div>
          <div class="match-dims">${dims || '<span class="match-dim dim-theme">General resonance</span>'}</div>
        </div>
        <div class="match-card-side">
          <div class="region-flag">${m.region_code ? flagEmoji(m.region_code) : '🌍'}</div>
          <div class="region-name">${escHtml(m.region || 'Unknown')}</div>
          <button class="connect-btn ${isConnected ? 'connected' : isPending ? 'connected' : ''}"
            onclick="connectDreamer(this, '${m.match_id}')"
            ${isConnected || isPending ? 'disabled' : ''}>
            ${isConnected ? '✓ Connected' : isPending ? '✓ Pending' : 'Connect'}
          </button>
        </div>
      </div>`;
  }).join('');
}

async function connectDreamer(btn, matchId) {
  if (btn.disabled) return;
  btn.disabled = true;
  btn.textContent = '...';

  try {
    const match = allMatches.find(m => m.match_id === matchId);
    if (!match) { btn.textContent = 'Connect'; btn.disabled = false; return; }

    const receiverId = match.matched_user_id;
    if (!receiverId) {
      btn.textContent = 'Connect';
      btn.disabled = false;
      showToast('⚠️ Cannot connect — recipient not found', 'error');
      return;
    }

    await api('POST', '/connections/request', {
      receiver_id: receiverId,
      match_id: matchId
    });

    btn.textContent = '✓ Pending';
    btn.classList.add('connected');
    showToast('🔗 Connection request sent anonymously');
  } catch (err) {
    btn.textContent = 'Connect';
    btn.disabled = false;
    showToast('⚠️ ' + err.message, 'error');
  }
}

function filterMatches(pill, dimension) {
  setPill(pill);
  if (!allMatches.length) return;
  if (dimension === 'all') { renderMatches(allMatches); return; }
  const filtered = allMatches.filter(m => {
    if (dimension === 'theme') return m.theme_score >= 50;
    if (dimension === 'emotion') return m.emotion_score >= 50;
    if (dimension === 'visual') return m.symbol_score >= 50;
    if (dimension === 'narrative') return m.narrative_score >= 60;
    return true;
  });
  renderMatches(filtered);
}

function filterTime(pill, range) {
  setPill(pill);
  if (!allMatches.length) return;
  const now = Date.now();
  const filtered = allMatches.filter(m => {
    const age = now - new Date(m.created_at).getTime();
    if (range === 'night') return age <= 24 * 60 * 60 * 1000;
    if (range === 'week') return age <= 7 * 24 * 60 * 60 * 1000;
    return true;
  });
  renderMatches(filtered);
}

// ════════════════════════════════════════════════════════════
// GLOBAL DREAM MAP
// ════════════════════════════════════════════════════════════

// Region code → approximate SVG [x, y] coordinates (viewBox 0 0 900 420)
const REGION_COORDS = {
  'US': [140,130], 'CA': [125,88], 'MX': [112,168],
  'BR': [190,280], 'AR': [175,325], 'CO': [168,240], 'VE': [178,218], 'PE': [160,270],
  'GB': [390,78],  'FR': [415,88], 'DE': [428,80],  'IT': [445,97],  'ES': [400,100],
  'NL': [420,74],  'BE': [418,80], 'PT': [393,104], 'SE': [438,60],  'NO': [432,55],
  'PL': [460,76],  'UA': [488,82], 'RO': [475,88],  'CH': [430,90],  'AT': [448,88],
  'RU': [560,65],  'TR': [492,100], 'GR': [468,103],
  'ZA': [435,272], 'NG': [418,197], 'KE': [472,212], 'ET': [470,192],
  'EG': [458,148], 'GH': [405,195], 'MA': [398,132], 'TZ': [468,228],
  'CN': [668,112], 'JP': [722,108], 'KR': [712,113], 'IN': [600,142],
  'PK': [578,132], 'BD': [618,140], 'ID': [680,198], 'TH': [652,165],
  'VN': [663,170], 'PH': [700,165], 'MY': [670,185], 'SG': [674,190],
  'AU': [730,282], 'NZ': [792,316],
  'AE': [536,142], 'SA': [512,148], 'IR': [548,126], 'IL': [486,115],
  'CL': [172,326],
};

async function loadMap() {
  try {
    const [mapData, globalStats] = await Promise.all([
      api('GET', '/research/map', null, false).catch(() => null),
      api('GET', '/research/global').catch(() => null),
    ]);

    // ── Stats ──
    const el = (id) => document.getElementById(id);
    if (globalStats) {
      const dreams = globalStats.total_dreams || 0;
      const countries = globalStats.active_countries || 0;
      const topTheme = globalStats.top_themes?.[0]?.theme || null;
      if (el('map-stat-dreams'))    el('map-stat-dreams').textContent    = dreams.toLocaleString();
      if (el('map-stat-countries')) el('map-stat-countries').textContent = countries;
      if (el('map-stat-theme'))     el('map-stat-theme').textContent     = topTheme ? topTheme.replace(/^./, c => c.toUpperCase()) : '—';
    }
    if (mapData) {
      const online = mapData.online_now || 0;
      if (el('map-stat-online')) el('map-stat-online').textContent = online.toLocaleString();
      const liveEl = document.querySelector('.map-live');
      if (liveEl) liveEl.innerHTML = `<span class="live-dot"></span> ${online.toLocaleString()} dreaming now`;
    }

    // ── Dynamic map points ──
    const pointsGroup = document.getElementById('map-points');
    if (!pointsGroup || !mapData?.points?.length) return;

    const maxCount = Math.max(...mapData.points.map(p => p.dream_count), 1);
    let svgHTML = '';

    for (const p of mapData.points) {
      const coords = REGION_COORDS[p.region_code];
      if (!coords) continue;
      const [cx, cy] = coords;

      const ratio = p.dream_count / maxCount;
      const r     = Math.max(5, Math.min(22, 5 + ratio * 17));
      const isOnline = p.active_24h > 0;
      const color   = isOnline ? 'rgba(167,139,202,' : 'rgba(200,169,122,';

      const dur = (2.5 + Math.random() * 2).toFixed(1);
      const delay = (Math.random() * 2).toFixed(1);

      svgHTML += `
        <g class="map-point" onmouseenter="showTip(event,${JSON.stringify(p.region)},${JSON.stringify(p.dream_count + ' dreams · ' + p.top_theme)})" onmouseleave="hideTip()">
          <circle cx="${cx}" cy="${cy}" r="${r}" fill="${color}0.15)" opacity="0.8">
            <animate attributeName="r" values="${r};${r*1.6};${r}" dur="${dur}s" begin="${delay}s" repeatCount="indefinite"/>
            <animate attributeName="opacity" values="0.8;0.15;0.8" dur="${dur}s" begin="${delay}s" repeatCount="indefinite"/>
          </circle>
          <circle cx="${cx}" cy="${cy}" r="${Math.max(3, r * 0.38)}" fill="${color}0.85)"/>
          ${isOnline ? `<circle cx="${cx}" cy="${cy}" r="2.5" fill="#5dca7f" class="map-online-dot"/>` : ''}
        </g>`;
    }

    pointsGroup.innerHTML = svgHTML;

  } catch (err) {
    console.error('Map load error:', err);
  }
}

// ════════════════════════════════════════════════════════════
// NOTIFICATIONS
// ════════════════════════════════════════════════════════════

async function loadNotifications() {
  if (!currentUser) return;
  try {
    const panel = document.getElementById('notif-panel');
    const panelOpen = panel.classList.contains('open');
    const data = await api('GET', '/notifications?limit=15');
    if (!data) return;

    const hasRequests = data.notifications?.some(n => n.type === 'connection_request');
    const pending = hasRequests ? await api('GET', '/connections?status=pending').catch(() => []) : [];
    const pendingIds = new Set((pending || []).map(r => r.id));

    const unread = panelOpen ? 0 : data.unread_count;
    document.querySelectorAll('.notif-badge').forEach(b => {
      b.style.display = unread > 0 ? 'block' : 'none';
    });
    const bnBadge = document.getElementById('bn-badge');
    if (bnBadge) bnBadge.style.display = unread > 0 ? 'block' : 'none';
    notifyInBrowser(data.notifications || []);

    const existing = panel.querySelector('.notif-list');
    if (existing) existing.remove();

    const list = document.createElement('div');
    list.className = 'notif-list';

    if (!data.notifications?.length) {
      list.innerHTML = '<div style="font-size:0.82rem;color:var(--indigo-light);text-align:center;padding:1rem">Nothing new tonight. Resonances and messages will appear here.</div>';
    } else {
      list.innerHTML = data.notifications.map(n => {
        const requestId = n.type === 'connection_request' ? n.data?.connectionId : null;
        const target = { new_matches: 'matches', high_resonance: 'matches', connection_request: 'messages',
          connection_accepted: 'messages', new_message: 'messages', recurring_dream: 'journal' }[n.type];
        return `
        <div class="notif-item ${n.is_read ? '' : 'notif-unread'}" ${target ? `onclick="if(!event.target.closest('button'))goTo('${target}')" style="cursor:pointer"` : ''}>
          <div class="notif-icon">${notifIcon(n.type)}</div>
          <div>
            <div class="notif-text"><strong>${escHtml(n.title)}</strong><br>${escHtml(n.body)}</div>
            <div class="notif-time">${timeAgo(n.created_at)}</div>
            ${requestId && pendingIds.has(requestId) ? `<div class="request-actions">
              <button onclick="respondToRequest('${escHtml(requestId)}', true, this)">Accept</button>
              <button onclick="respondToRequest('${escHtml(requestId)}', false, this)">Decline</button></div>` : ''}
          </div>
        </div>`;
      }).join('');
    }

    panel.appendChild(list);

    // Only mark as read once the dreamer has actually opened the panel
    if (panelOpen && data.unread_count > 0) {
      await api('PATCH', '/notifications/read', {});
    }
  } catch {}
}

function startNotifPolling() {
  clearInterval(notifPollInterval);
  loadNotifications();
  notifPollInterval = setInterval(loadNotifications, 30000); // every 30s
}

function toggleNotif() {
  const panel = document.getElementById('notif-panel');
  panel.classList.toggle('open');
  if (panel.classList.contains('open')) loadNotifications();
}

function closeNotif() {
  document.getElementById('notif-panel').classList.remove('open');
}

// ── MOBILE MENU TOGGLE ──
function toggleMobileMenu() {
  // Pages without their own menu (messages, profile) open Home's menu
  if (!document.querySelector('.page.active .sidebar')) goTo('dashboard');
  const sidebar = document.querySelector('.page.active .sidebar');
  const overlay = document.getElementById('sidebar-overlay');
  if (!sidebar) return;
  sidebar.classList.toggle('open');
  if (overlay) overlay.classList.toggle('show');
}

function closeMobileMenu() {
  document.querySelectorAll('.sidebar.open').forEach(s => s.classList.remove('open'));
  const overlay = document.getElementById('sidebar-overlay');
  if (overlay) overlay.classList.remove('show');
}

// Auto-close menu when nav item clicked on mobile
document.addEventListener('click', e => {
  if (window.innerWidth <= 900 && e.target.closest('.sidebar-item')) {
    setTimeout(closeMobileMenu, 100);
  }
});

document.addEventListener('click', e => {
  const panel = document.getElementById('notif-panel');
  if (panel.classList.contains('open') && !panel.contains(e.target) && !e.target.closest('.notif-btn')) {
    panel.classList.remove('open');
  }
});

// ════════════════════════════════════════════════════════════
// DREAM CAPTURE — VOICE & AI
// ════════════════════════════════════════════════════════════

function setMethod(btn, method) {
  document.querySelectorAll('.method-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  currentMethod = method;
  document.getElementById('recorder-ui').classList.remove('show');
  document.getElementById('ai-interview').classList.remove('show');
  aiStep = 0;
  if (method === 'voice') document.getElementById('recorder-ui').classList.add('show');
  if (method === 'ai') document.getElementById('ai-interview').classList.add('show');
}

function toggleRecording() {
  const btn    = document.getElementById('rec-btn');
  const bars   = document.querySelectorAll('.wave-bar');
  const status = document.getElementById('rec-status');

  if (!recording) {
    // ── REQUEST MICROPHONE ──
    (navigator.mediaDevices && window.MediaRecorder ? navigator.mediaDevices.getUserMedia({ audio: true, video: false }) : Promise.reject(new Error('Voice recording needs HTTPS and a supported browser. You can still type your dream.')))
      .then(stream => {
        // Determine best MIME type
        const mimeType = ['audio/webm;codecs=opus','audio/webm','audio/ogg'].find(t => {
          try { return MediaRecorder.isTypeSupported(t); } catch { return false; }
        }) || '';

        // MediaRecorder — captures audio file for server upload
        audioChunks = [];
        audioBlob   = null;
        try {
          mediaRecorder = mimeType ? new MediaRecorder(stream, { mimeType }) : new MediaRecorder(stream);
        } catch {
          mediaRecorder = new MediaRecorder(stream);
        }
        mediaRecorder.ondataavailable = e => { if (e.data && e.data.size > 0) audioChunks.push(e.data); };
        mediaRecorder.onstop = () => {
          audioBlob = new Blob(audioChunks, { type: mediaRecorder.mimeType || 'audio/webm' });
          stream.getTracks().forEach(t => t.stop());
        };
        mediaRecorder.start(1000);

        // Web Speech API — live transcript in textarea
        const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (SR) {
          speechRecognition = new SR();
          speechRecognition.continuous     = true;
          speechRecognition.interimResults = true;
          speechRecognition.lang           = 'en-US';
          let finalText = '';
          speechRecognition.onresult = event => {
            let interim = '';
            for (let i = event.resultIndex; i < event.results.length; i++) {
              if (event.results[i].isFinal) finalText += event.results[i][0].transcript + ' ';
              else interim += event.results[i][0].transcript;
            }
            document.getElementById('dream-text').value = finalText + interim;
          };
          speechRecognition.onerror = e => {
            if (e.error !== 'no-speech') console.warn('Speech:', e.error);
          };
          try { speechRecognition.start(); } catch {}
        }

        recording = true;
        btn.classList.add('recording');
        btn.textContent = '⏹️';
        bars.forEach(b => b.classList.add('active'));
        recSeconds = 0;
        if (status) status.textContent = '🔴 Recording · Speak your dream clearly...';
        if (!SR) showToast('🎙️ Recording audio · Transcript not supported in this browser — type below');

        const maxSeconds = currentUser?.is_premium ? 600 : 180;
        recInterval = setInterval(() => {
          recSeconds++;
          const m = Math.floor(recSeconds / 60), s = recSeconds % 60;
          document.getElementById('rec-time').textContent = m + ':' + (s < 10 ? '0' : '') + s;
          if (recSeconds >= maxSeconds && recording) {
            toggleRecording();
            showToast(currentUser?.is_premium ? 'Ten minutes captured · Your recording is saved with the dream'
              : 'Free voice notes are up to three minutes · Lucid records up to ten');
          }
        }, 1000);
      })
      .catch(err => {
        if (err.name === 'NotAllowedError') {
          showToast('⚠️ Microphone blocked — allow access in browser settings then try again', 'error');
        } else {
          showToast('⚠️ Could not start recording: ' + err.message, 'error');
        }
      });

  } else {
    // ── STOP RECORDING ──
    recording = false;
    btn.classList.remove('recording');
    btn.textContent = '🎙️';
    bars.forEach(b => b.classList.remove('active'));
    clearInterval(recInterval);

    if (speechRecognition) { try { speechRecognition.stop(); } catch {} speechRecognition = null; }
    if (mediaRecorder && mediaRecorder.state === 'recording') mediaRecorder.stop();

    const transcript = document.getElementById('dream-text').value.trim();
    if (status) {
      status.textContent = transcript
        ? '✓ Transcript captured · Review and click Log Dream'
        : '🎙️ Tap to record again · Or describe your dream in the box below';
    }
    showToast(transcript ? '🎙️ Recording complete · Transcript ready' : '🎙️ Recording stopped');
    if (!transcript) document.getElementById('dream-text').focus();
  }
}

const AI_QUESTIONS = [
  { q: 'Was the figure you saw moving toward you or away?', opts: ['Toward me', 'Away from me', 'Standing still', 'No figure'] },
  { q: 'What was the dominant colour of the environment?', opts: ['White / pale', 'Blue / indigo', 'Gold / amber', 'Grey / mist'] },
  { q: 'What emotion was strongest in this dream?', opts: ['Wonder', 'Terror', 'Peace', 'Confusion', 'Longing'] },
  { q: 'Did you feel safe or threatened?', opts: ['Safe', 'Threatened', 'Both at once', 'Neutral'] }
];

function selectAI(el) {
  document.querySelectorAll('.ai-option').forEach(o => o.classList.remove('selected'));
  el.classList.add('selected');
  aiInterviewAnswers.push(el.textContent.trim());
  aiStep++;

  if (aiStep < AI_QUESTIONS.length) {
    setTimeout(() => {
      const interview = document.getElementById('ai-interview');
      const q = AI_QUESTIONS[aiStep];
      interview.querySelector('.ai-q').innerHTML = `<strong>✦ Oneiros AI:</strong> ${q.q}`;
      interview.querySelector('.ai-options').innerHTML = q.opts
        .map(o => `<div class="ai-option" onclick="selectAI(this)">${o}</div>`).join('');
    }, 350);
  } else {
    setTimeout(() => {
      document.getElementById('ai-interview').classList.remove('show');
      const paintControls = document.getElementById('ai-paint-controls');
      if (paintControls) paintControls.style.display = 'block';
      showToast('✦ Dream profile ready · Create a canvas, or add more detail first');
      document.getElementById('dream-text').focus();
    }, 350);
  }
}

let aiPaintToken = null;   // server-side AI painting awaiting attachment to the next saved dream
let canvasFile = null;     // locally generated Dream Canvas, uploaded after the dream is saved
let appCapabilities = {};

async function loadCapabilities() {
  try {
    const res = await fetch(buildApiUrl('/capabilities'));
    if (res.ok) appCapabilities = await res.json();
  } catch {}
  const btn = document.getElementById('ai-paint-btn');
  if (btn) btn.textContent = appCapabilities.ai_paint ? 'Paint my dream with AI' : 'Create a dream canvas';
}

function paintButtonLabel() {
  return appCapabilities.ai_paint ? 'Paint my dream with AI' : 'Create a dream canvas';
}

function clearPainting(hidePreview = true) {
  if (aiGeneratedImageUrl?.startsWith('blob:')) URL.revokeObjectURL(aiGeneratedImageUrl);
  aiGeneratedImageUrl = null;
  aiPaintToken = null;
  canvasFile = null;
  const preview = document.getElementById('ai-paint-preview');
  if (preview && hidePreview) { preview.style.display = 'none'; preview.innerHTML = ''; }
}

function showPaintingPreview(url, label) {
  const preview = document.getElementById('ai-paint-preview');
  preview.style.display = 'block';
  preview.innerHTML = `
    <figure class="paint-preview">
      <img src="${escHtml(url)}" alt="${escHtml(label)} of your dream">
      <figcaption>✧ ${escHtml(label)} · attached when you save</figcaption>
    </figure>
    <div class="paint-actions">
      <button type="button" onclick="generateDreamPainting()">↻ Paint again</button>
      <button type="button" onclick="clearPainting()">Remove</button>
    </div>`;
}

async function generateDreamPainting() {
  const text    = document.getElementById('dream-text').value.trim();
  const answers = aiInterviewAnswers.slice();
  const emotions = [...document.querySelectorAll('.emotion-choice.selected')].map(el => el.textContent.trim());

  if (text.length < 10 && !answers.length) {
    showToast('✍️ Describe your dream or answer the questions first', 'error');
    return;
  }

  const btn     = document.getElementById('ai-paint-btn');
  const preview = document.getElementById('ai-paint-preview');
  btn.disabled    = true;
  btn.textContent = appCapabilities.ai_paint ? '✨ Painting your dreamscape…' : '✨ Weaving your dream canvas…';
  clearPainting(false);
  preview.style.display = 'block';
  preview.innerHTML = '<div class="paint-developing"><span></span>Your dream is surfacing…</div>';

  try {
    if (appCapabilities.ai_paint) {
      const prompt = [text, answers.join(', '), emotions.join(', ')].filter(Boolean).join('. ').slice(0, 3500);
      try {
        const data = await api('POST', '/dreams/paint', { prompt: prompt.padEnd(10, '.') });
        aiPaintToken = data.paint_token;
        aiGeneratedImageUrl = data.image_url;
        showPaintingPreview(data.image_url, 'AI painting');
        showToast('🎨 Your dreamscape is ready');
        return;
      } catch {
        // Provider unavailable or rate-limited: fall back to the local canvas below
      }
    }
    if (!window.DreamCanvas) throw new Error('Dream Canvas unavailable');
    canvasFile = await window.DreamCanvas.paint({ text, emotions, answers });
    aiGeneratedImageUrl = URL.createObjectURL(canvasFile);
    showPaintingPreview(aiGeneratedImageUrl, 'Dream canvas');
    showToast('🎨 Your dream canvas is ready');
  } catch {
    clearPainting(false);
    preview.innerHTML = '<div class="paint-developing is-error">The canvas could not be painted. Your words are still safe. Try again, or save the dream as it is.</div>';
  } finally {
    btn.disabled    = false;
    btn.textContent = paintButtonLabel();
  }
}

// ════════════════════════════════════════════════════════════
// FILTER PILLS
// ════════════════════════════════════════════════════════════

function setPill(el) {
  el.closest('.filter-pills').querySelectorAll('.pill').forEach(p => p.classList.remove('active'));
  el.classList.add('active');
}

// ════════════════════════════════════════════════════════════
// MAP TOOLTIP
// ════════════════════════════════════════════════════════════

function showTip(e, country, detail) {
  const tip = document.getElementById('map-tooltip');
  const rect = e.target.closest('.map-container').getBoundingClientRect();
  document.getElementById('tip-country').textContent = country;
  document.getElementById('tip-detail').textContent = detail;
  tip.style.left = (e.clientX - rect.left) + 'px';
  tip.style.top = (e.clientY - rect.top) + 'px';
  tip.style.opacity = '1';
}

function hideTip() {
  document.getElementById('map-tooltip').style.opacity = '0';
}

// ════════════════════════════════════════════════════════════
// UTILITIES
// ════════════════════════════════════════════════════════════

function showToast(msg, type = 'success') {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.toggle('error', type === 'error');
  t.classList.add('show');
  clearTimeout(t._timeout);
  t._timeout = setTimeout(() => t.classList.remove('show'), 3500);
}

function setButtonLoading(id, loading) {
  const btn = document.getElementById(id);
  if (!btn) return;
  btn.disabled = loading;
  btn._original = btn._original || btn.textContent;
  btn.textContent = loading ? '...' : btn._original;
}

function escHtml(str) {
  if (!str) return '';
  return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function todayISO() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function timeAgo(iso) {
  const diff = Date.now() - new Date(iso).getTime();
  const m = Math.floor(diff / 60000);
  if (m < 1) return 'just now';
  if (m < 60) return `${m}m ago`;
  const h = Math.floor(m / 60);
  if (h < 24) return `${h}h ago`;
  return `${Math.floor(h / 24)}d ago`;
}

function flagEmoji(code) {
  if (!code || code.length !== 2) return '🌍';
  const offset = 0x1F1E6 - 65;
  return String.fromCodePoint(code.charCodeAt(0) + offset) +
         String.fromCodePoint(code.charCodeAt(1) + offset);
}

function getDreamEmoji(themes = []) {
  const map = { water:'🌊', flying:'🌤️', falling:'🌀', pursuit:'🏃', architecture:'🏛️',
    darkness:'🌑', light:'✨', figures:'👤', nature:'🌿', transformation:'🦋',
    fire:'🔥', animals:'🐺', death:'💀', time:'⏳', surreal:'🌀' };
  for (const t of themes) if (map[t]) return map[t];
  return '🌙';
}

function notifIcon(type) {
  const icons = { new_matches:'✨', high_resonance:'💫', connection_request:'🔗',
    connection_accepted:'🤝', new_message:'💬', recurring_dream:'🌀', research_milestone:'📊',
    premium_activated:'✦', premium_expiring:'⏳' };
  return icons[type] || '🔔';
}

// ════════════════════════════════════════════════════════════
// MESSAGES / CHAT
// ════════════════════════════════════════════════════════════

let activeConnectionId = null;
let chatPollInterval = null;
let uploadedImageUrl = null;
let selectedFile = null;

let chatPartners = {};

async function loadMessages() {
  try {
    const [data, pending] = await Promise.all([
      api('GET', '/connections'),
      api('GET', '/connections?status=pending').catch(() => []),
    ]);
    const listEl = document.getElementById('convo-list-items');

    const requestsHtml = (pending || []).map(r => `
      <div class="convo-item convo-request">
        <div class="convo-avatar">✧</div>
        <div class="convo-info">
          <div class="convo-name">A dreamer wants to connect</div>
          <div class="convo-preview">${r.region_code ? flagEmoji(r.region_code) + ' ' : ''}${escHtml(r.region || 'Somewhere in the world')}${r.match_score ? ` · ${r.match_score}% resonance` : ''}</div>
          <div class="request-actions">
            <button onclick="respondToRequest('${r.id}', true, this)">Accept</button>
            <button onclick="respondToRequest('${r.id}', false, this)">Decline</button>
          </div>
        </div>
      </div>`).join('');

    chatPartners = {};
    const convoHtml = (data || []).map(({ connection, partner, unread_count }) => {
      const name = partner?.display_name || 'Connected Dreamer';
      const initials = name.split(/\s+/).map(w => w[0]).join('').toUpperCase().slice(0, 2) || '✧';
      chatPartners[connection.id] = { id: partner?.id, name, initials };
      return `
        <div class="convo-item ${activeConnectionId === connection.id ? 'active' : ''}" data-connection="${connection.id}"
             onclick="openChat('${connection.id}')">
          <div class="convo-avatar">${escHtml(initials)}</div>
          <div class="convo-info">
            <div class="convo-name">${escHtml(name)}${partner?.is_lucid ? ' <span class="lucid-mark" title="Lucid dreamer">✦</span>' : ''}</div>
            <div class="convo-preview">${partner?.region_code ? flagEmoji(partner.region_code) + ' ' : ''}${escHtml(partner?.region || 'Somewhere in the world')}</div>
          </div>
          <div class="convo-time">${connection.connected_at ? timeAgo(connection.connected_at) : ''}</div>
          ${unread_count > 0 ? '<div class="convo-unread"></div>' : ''}
        </div>`;
    }).join('');

    if (!requestsHtml && !convoHtml) {
      listEl.innerHTML = `<div class="no-connections-msg">
        <div style="font-size:2rem;margin-bottom:0.75rem;opacity:0.4">🔗</div>
        No connections yet.<br>When a dream resonates, send a connection request from Matches. Conversations begin once you both agree.
      </div>`;
      return;
    }
    listEl.innerHTML = requestsHtml + convoHtml;
  } catch {}
}

async function respondToRequest(connectionId, accept, btn) {
  btn?.closest('.request-actions')?.querySelectorAll('button').forEach(b => b.disabled = true);
  try {
    if (accept) {
      await api('PATCH', `/connections/${connectionId}/accept`, {});
      showToast('🤝 Connected · You can now talk about your dreams');
    } else {
      await api('DELETE', `/connections/${connectionId}/reject`);
      showToast('Request declined. They will not be told.');
    }
    await loadMessages();
    loadNotifications();
    if (accept) openChat(connectionId);
  } catch {
    btn?.closest('.request-actions')?.querySelectorAll('button').forEach(b => b.disabled = false);
  }
}

async function blockPartner(connectionId) {
  const partner = chatPartners[connectionId];
  if (!partner?.id) return;
  if (!confirm(`Block ${partner.name}? The conversation ends and you will no longer be matched with each other.`)) return;
  try {
    await api('POST', '/connections/block', { target_id: partner.id });
    clearInterval(chatPollInterval);
    activeConnectionId = null;
    document.getElementById('chat-pane').innerHTML = `<div class="chat-select-hint"><div style="font-size:2.5rem;opacity:0.25">🌙</div><div style="font-size:0.85rem;color:var(--indigo-light)">You won't hear from this dreamer again.</div></div>`;
    document.querySelector('.convo-list')?.classList.remove('has-active');
    showToast('Dreamer blocked');
    loadMessages();
  } catch {}
}

async function openChat(connectionId) {
  activeConnectionId = connectionId;
  clearInterval(chatPollInterval);
  const { name: partnerName = 'Connected Dreamer', initials = '✧' } = chatPartners[connectionId] || {};

  const pane = document.getElementById('chat-pane');
  pane.innerHTML = `
    <div class="chat-header">
      <button class="chat-back-btn" style="display:none" onclick="closeChatOnMobile()" aria-label="Back to conversations">←</button>
      <div class="chat-header-avatar">${escHtml(initials)}</div>
      <div style="flex:1;min-width:0">
        <div class="chat-header-name">${escHtml(partnerName)}</div>
        <div class="chat-header-sub">Connected dreamer · Private conversation</div>
      </div>
      <button class="text-link" onclick="blockPartner('${connectionId}')" style="font-size:0.72rem;opacity:0.7">Block</button>
    </div>
    <div class="chat-messages" id="chat-messages">
      <div style="text-align:center;color:var(--indigo-light);font-size:0.82rem;padding:2rem">Loading messages...</div>
    </div>
    <div class="chat-input-area">
      <div class="chat-input-row">
        <textarea class="chat-input" id="chat-input" placeholder="Share a dream, a thought..." rows="1"
          onkeydown="handleChatKey(event)" oninput="autoResizeChat(this)"></textarea>
        <button class="chat-send" onclick="sendChatMessage()">→</button>
      </div>
    </div>`;

  // Mark active convo
  document.querySelectorAll('.convo-item').forEach(el => el.classList.remove('active'));
  document.querySelector(`[data-connection="${connectionId}"]`)?.classList.add('active');
  document.querySelector('.convo-list')?.classList.add('has-active');

  await fetchMessages();
  chatPollInterval = setInterval(fetchMessages, 5000);
  document.getElementById('chat-input')?.focus({ preventScroll: true });
}

function closeChatOnMobile() {
  document.querySelector('.convo-list')?.classList.remove('has-active');
}

async function fetchMessages() {
  if (!activeConnectionId) return;
  try {
    const data = await api('GET', `/connections/${activeConnectionId}/messages`);
    const container = document.getElementById('chat-messages');
    if (!container) return;

    if (!data?.messages?.length) {
      container.innerHTML = `
        <div class="chat-empty">
          <div class="chat-empty-icon">🌙</div>
          <div class="chat-empty-text">The dream space is quiet</div>
          <div class="chat-empty-sub">Send the first message to begin</div>
        </div>`;
      return;
    }

    const wasAtBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 60;

    container.innerHTML = data.messages.map(m => {
      const isMine = m.sender_id === currentUser?.id;
      return `
        <div class="msg-row ${isMine ? 'mine' : 'theirs'}">
          <div class="msg-bubble">${escHtml(m.content)}</div>
          <div class="msg-time">${timeAgo(m.created_at)}</div>
        </div>`;
    }).join('');

    if (wasAtBottom) container.scrollTop = container.scrollHeight;
  } catch {}
}

async function sendChatMessage() {
  const input = document.getElementById('chat-input');
  const content = input?.value.trim();
  if (!content || !activeConnectionId) return;

  input.value = '';
  input.style.height = 'auto';

  try {
    await api('POST', `/connections/${activeConnectionId}/messages`, { content });
    await fetchMessages();
  } catch (err) {
    showToast('⚠️ Failed to send message', 'error');
    input.value = content;
  }
}

function handleChatKey(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    sendChatMessage();
  }
}

function autoResizeChat(el) {
  el.style.height = 'auto';
  el.style.height = Math.min(el.scrollHeight, 120) + 'px';
}

// ════════════════════════════════════════════════════════════
// PROFILE & SETTINGS
// ════════════════════════════════════════════════════════════

async function loadProfile() {
  if (!currentUser) return;

  // Fill hero
  const name = currentUser.display_name || currentUser.email || 'Dreamer';
  const initials = name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0,2);
  document.getElementById('profile-avatar-big').textContent = initials;
  document.getElementById('profile-display-name').textContent = currentUser.display_name || 'Anonymous Dreamer';
  document.getElementById('profile-email').textContent = currentUser.email || '';
  document.getElementById('profile-since').textContent = currentUser.created_at
    ? 'Member since ' + new Date(currentUser.created_at).toLocaleDateString('en-ZA', { month: 'long', year: 'numeric' })
    : '';

  // Pre-fill settings inputs
  document.getElementById('setting-display-name').value = currentUser.display_name || '';
  document.getElementById('setting-region').value = currentUser.region || '';
  document.getElementById('setting-region-code').value = (currentUser.region_code || '').toUpperCase();

  // Set push button state
  const pushBtn = document.getElementById('push-toggle-btn');
  if (pushBtn) {
    pushBtn.textContent = browserAlertsEnabled() ? 'Disable' : 'Enable';
  }

  // Load stats
  try {
    const stats = await api('GET', '/research/me');
    if (stats) {
      const nums = document.querySelectorAll('#profile-stats-row .profile-stat-num');
      if (nums[0]) nums[0].textContent = stats.total_dreams || 0;
      if (nums[1]) nums[1].textContent = stats.total_matches?.toLocaleString() || 0;
      if (nums[2]) nums[2].textContent = stats.recurring_dreams || 0;
      if (nums[3]) nums[3].textContent = stats.connections || 0;
    }
  } catch {}
}

async function saveIdentity() {
  const display_name = document.getElementById('setting-display-name').value.trim();
  const region = document.getElementById('setting-region').value.trim();
  const region_code = document.getElementById('setting-region-code').value.trim().toUpperCase();

  const btn = document.getElementById('save-identity-btn');
  btn.disabled = true; btn.textContent = 'Saving...';

  try {
    await api('PATCH', '/auth/profile', { display_name, region, region_code });
    currentUser = { ...currentUser, display_name, region, region_code };

    // Update nav avatar
    const initials = (display_name || currentUser.email).slice(0,2).toUpperCase();
    document.querySelector('.avatar').textContent = initials;
    document.getElementById('profile-avatar-big').textContent = initials;
    document.getElementById('profile-display-name').textContent = display_name || 'Anonymous Dreamer';

    showToast('✓ Identity saved');
  } catch (err) {
    showToast('⚠️ ' + err.message, 'error');
  } finally {
    btn.disabled = false; btn.textContent = 'Save Identity';
  }
}

async function savePassword() {
  const newPwd = document.getElementById('setting-new-password').value;
  const confirmPwd = document.getElementById('setting-confirm-password').value;

  if (newPwd.length < 8) { showToast('⚠️ Password must be at least 8 characters', 'error'); return; }
  if (newPwd !== confirmPwd) { showToast('⚠️ Passwords do not match', 'error'); return; }

  const btn = document.getElementById('save-password-btn');
  btn.disabled = true; btn.textContent = 'Updating...';

  try {
    await api('PATCH', '/auth/password', { password: newPwd });
    document.getElementById('setting-new-password').value = '';
    document.getElementById('setting-confirm-password').value = '';
    showToast('✓ Password updated');
  } catch (err) {
    showToast('⚠️ ' + err.message, 'error');
  } finally {
    btn.disabled = false; btn.textContent = 'Update Password';
  }
}

async function exportDreams() {
  try {
    const data = await api('GET', '/dreams?limit=500');
    if (!data?.dreams?.length) { showToast('No dreams to export yet', 'error'); return; }

    const rows = [
      ['Date', 'Title', 'Content', 'Emotions', 'Themes', 'Privacy', 'Matches'],
      ...data.dreams.map(d => [
        new Date(d.dreamed_at).toLocaleDateString(),
        d.title || '',
        `"${(d.content || '').replace(/"/g, '""')}"`,
        (d.emotions || []).join('; '),
        (d.themes || []).join('; '),
        d.privacy,
        d.match_count || 0
      ])
    ];

    const csv = rows.map(r => r.join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'oneiros-dreams.csv'; a.click();
    URL.revokeObjectURL(url);
    showToast('✓ Dreams exported as CSV');
  } catch (err) {
    showToast('⚠️ Export failed: ' + err.message, 'error');
  }
}

async function confirmDeleteAccount() {
  const confirmed = confirm('Are you absolutely sure? This will permanently delete all your dreams, matches, and connections. This cannot be undone.');
  if (!confirmed) return;
  const reconfirmed = confirm('Last chance — delete your Oneiros account forever?');
  if (!reconfirmed) return;
  try {
    await api('DELETE', '/auth/account');
    handleLogout();
    showToast('Account deleted. Goodbye, dreamer.');
  } catch (err) {
    showToast('⚠️ ' + err.message, 'error');
  }
}

// ════════════════════════════════════════════════════════════
// IMAGE UPLOAD
// ════════════════════════════════════════════════════════════

function handleDragOver(e) {
  e.preventDefault();
  document.getElementById('upload-zone').classList.add('dragover');
}

function handleDragLeave(e) {
  document.getElementById('upload-zone').classList.remove('dragover');
}

function handleDrop(e) {
  e.preventDefault();
  document.getElementById('upload-zone').classList.remove('dragover');
  const file = e.dataTransfer.files[0];
  if (file) processImageFile(file);
}

function handleImageSelect(e) {
  const file = e.target.files[0];
  if (file) processImageFile(file);
}

function processImageFile(file) {
  if (!file.type.startsWith('image/')) { showToast('⚠️ Please select an image file', 'error'); return; }
  if (file.size > 10 * 1024 * 1024) { showToast('⚠️ Image must be under 10MB', 'error'); return; }

  selectedFile = file;
  const reader = new FileReader();
  reader.onload = e => {
    document.getElementById('upload-preview-img').src = e.target.result;
    document.getElementById('upload-preview').style.display = 'block';
    document.getElementById('upload-zone').style.display = 'none';
  };
  reader.readAsDataURL(file);
}

function removeUploadedImage(e) {
  e.stopPropagation();
  selectedFile = null;
  uploadedImageUrl = null;
  document.getElementById('upload-preview').style.display = 'none';
  document.getElementById('upload-zone').style.display = 'block';
  document.getElementById('dream-image-input').value = '';
}

async function uploadImageToSupabase(dreamId, file) {
  // Upload via backend which handles Supabase Storage
  const formData = new FormData();
  formData.append('image', file);
  formData.append('dream_id', dreamId);

  const progressBar = document.getElementById('upload-progress-bar');
  const progressEl = document.getElementById('upload-progress');
  progressEl.style.display = 'block';

  // Simulate upload progress
  let progress = 0;
  const interval = setInterval(() => {
    progress = Math.min(progress + 15, 85);
    progressBar.style.width = progress + '%';
  }, 200);

  try {
    const res = await fetch(buildApiUrl('/dreams/' + dreamId + '/image'), {
      method: 'POST',
      headers: { 'Authorization': 'Bearer ' + accessToken },
      body: formData
    });
    clearInterval(interval);
    progressBar.style.width = '100%';
    setTimeout(() => { progressEl.style.display = 'none'; progressBar.style.width = '0%'; }, 600);

    if (res.ok) {
      const data = await res.json();
      return data.image_url;
    }
  } catch {
    clearInterval(interval);
    progressEl.style.display = 'none';
  }
  return null;
}

// ════════════════════════════════════════════════════════════
// UPDATED setMethod — show/hide image upload zone
// ════════════════════════════════════════════════════════════

// Override setMethod to handle voice, AI paint, image, and text modes
window.setMethod = function(btn, method) {
  document.querySelectorAll('.method-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  currentMethod = method;

  // Hide all method-specific panels
  document.getElementById('recorder-ui').classList.remove('show');
  document.getElementById('ai-interview').classList.remove('show');
  document.getElementById('upload-zone').style.display = 'none';
  document.getElementById('upload-preview').style.display = selectedFile ? 'block' : 'none';

  const paintControls = document.getElementById('ai-paint-controls');
  const paintPreview  = document.getElementById('ai-paint-preview');

  if (method !== 'ai') {
    if (paintControls) paintControls.style.display = 'none';
    // Keep preview visible if already has a painted image (user switching modes)
    if (paintPreview && !aiGeneratedImageUrl) paintPreview.style.display = 'none';
  }

  aiStep = 0;
  aiInterviewAnswers = [];

  // Stop any active recording when switching methods
  if (method !== 'voice' && recording) {
    recording = false;
    const rb = document.getElementById('rec-btn');
    if (rb) { rb.classList.remove('recording'); rb.textContent = '🎙️'; }
    document.querySelectorAll('.wave-bar').forEach(b => b.classList.remove('active'));
    clearInterval(recInterval);
    if (speechRecognition) { try { speechRecognition.stop(); } catch {} speechRecognition = null; }
    if (mediaRecorder && mediaRecorder.state === 'recording') mediaRecorder.stop();
  }

  if (method === 'voice') {
    document.getElementById('recorder-ui').classList.add('show');
  }
  if (method === 'ai') {
    document.getElementById('ai-interview').classList.add('show');
    if (paintControls) paintControls.style.display = 'block';
    if (paintPreview && aiGeneratedImageUrl) paintPreview.style.display = 'block';
  }
  if (method === 'image') {
    if (!selectedFile) document.getElementById('upload-zone').style.display = 'block';
    else document.getElementById('upload-preview').style.display = 'block';
  }
}

// ════════════════════════════════════════════════════════════
// UPDATED goTo — load new pages
// ════════════════════════════════════════════════════════════

const _origGoTo = goTo;
window.goTo = function(page) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  const pageEl = document.getElementById('page-' + page);
  if (!pageEl) return;
  pageEl.classList.add('active');
  document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
  const nb = document.getElementById('nb-' + page);
  if (nb) nb.classList.add('active');

  // Sync bottom nav
  document.querySelectorAll('.bottom-nav-item').forEach(b => b.classList.remove('active'));
  const bnMap = { dashboard:'bn-dash', journal:'bn-journal', matches:'bn-matches', map:'bn-map', messages:'bn-messages' };
  const bn = document.getElementById(bnMap[page] || '');
  if (bn) bn.classList.add('active');

  closeNotif();
  window.scrollTo(0, 0);

  if (!currentUser) return;
  if (page === 'dashboard') { loadDashboard(); loadStreakMini(); }
  if (page === 'journal') loadJournal();
  if (page === 'matches') loadMatches();
  if (page === 'map') loadMap();
  if (page === 'messages') { clearInterval(chatPollInterval); loadMessages(); }
  if (page === 'profile') loadProfile();
  if (page === 'streaks') loadStreaks();
}

// ════════════════════════════════════════════════════════════
// UPDATED submitDream — include image upload
// ════════════════════════════════════════════════════════════

window.submitDream = async function() {
  if (recording) { showToast('Stop your recording before saving.', 'error'); return; }
  const txt = document.getElementById('dream-text').value.trim();

  // Allow submit with only AI painting (no text required in that case)
  if (!txt && !aiGeneratedImageUrl) {
    showToast('✍️ Describe your dream first', 'error');
    return;
  }
  if (txt && txt.length < 10 && !aiGeneratedImageUrl) {
    showToast('✍️ Please describe your dream in more detail', 'error');
    return;
  }

  const content = txt || 'A dream remembered as an image — see the attached painting.';

  const validEmotions = new Set(['terror','wonder','peace','joy','confusion','longing','sadness','dread','fear','awe']);
  const selectedEmotions = Array.from(document.querySelectorAll('.emotion-choice.selected'))
    .map(el => el.textContent.toLowerCase().trim())
    .filter(e => validEmotions.has(e));

  const btn = document.getElementById('submit-btn');
  btn.disabled = true;
  btn.textContent = '✦ Logging...';

  try {
    // Build dream payload — include AI image if present
    const payload = {
      content,
      title: document.getElementById('dream-title').value.trim(),
      emotions:    selectedEmotions,
      privacy: document.getElementById('dream-privacy').value,
      dreamed_at: document.getElementById('dream-date').value ? new Date(document.getElementById('dream-date').value + 'T12:00:00').toISOString() : new Date().toISOString(),
      has_audio:   !!audioBlob,
    };

    if (aiPaintToken) payload.paint_token = aiPaintToken;

    const dream = await api('POST', '/dreams', payload);
    if (!dream) return;

    // Upload the dreamer's own image, or else the locally painted Dream Canvas
    const imageFile = selectedFile || (!aiPaintToken ? canvasFile : null);
    if (imageFile) {
      showToast('🖼️ Attaching your image...');
      const imageResult = await uploadImageToSupabase(dream.id, imageFile);
      if (!imageResult) showToast('Dream saved, but the image could not be attached. Open it from your journal to try again.', 'error');
      selectedFile = null;
      document.getElementById('upload-preview').style.display = 'none';
      document.getElementById('dream-image-input').value = '';
    }

    // Upload audio blob if voice was used
    if (audioBlob) {
      try {
        showToast('🎙️ Saving voice recording...');
        const ext       = audioBlob.type.includes('ogg') ? 'ogg' : 'webm';
        const audioForm = new FormData();
        audioForm.append('audio',    audioBlob, 'dream.' + ext);
        audioForm.append('dream_id', dream.id);
        audioForm.append('transcript', content);

        const audioResponse = await fetch(buildApiUrl('/audio/upload'), {
          method:  'POST',
          headers: { 'Authorization': 'Bearer ' + accessToken },
          body:    audioForm,
        });
        if (!audioResponse.ok) showToast('Dream saved, but audio upload failed.', 'error');
      } catch { /* non-fatal — dream is already saved */ }
      audioBlob = null;
      audioChunks = [];
    }

    // ── Reset all UI state ──
    document.getElementById('dream-text').value = '';
    document.getElementById('dream-title').value = '';
    document.querySelectorAll('.emotion-choice').forEach(el=>el.classList.remove('selected'));
    localStorage.removeItem('oneiros_draft_' + currentUser.id);
    document.getElementById('ai-interview').classList.remove('show');
    document.getElementById('recorder-ui').classList.remove('show');

    const paintControls = document.getElementById('ai-paint-controls');
    const paintPreview  = document.getElementById('ai-paint-preview');
    if (paintControls) paintControls.style.display = 'none';
    if (paintPreview)  { paintPreview.style.display = 'none'; paintPreview.innerHTML = ''; }

    const recStatus = document.getElementById('rec-status');
    if (recStatus) recStatus.textContent = '🎙️ Tap to start recording · Live transcript appears below';

    document.getElementById('upload-zone').style.display    = 'none';
    document.getElementById('upload-preview').style.display = 'none';
    document.querySelectorAll('.method-btn').forEach(b => b.classList.remove('active'));
    const firstMethodBtn = document.querySelector('.method-btn');
    if (firstMethodBtn) firstMethodBtn.classList.add('active');
    currentMethod = 'text';
    aiStep = 0;
    aiInterviewAnswers = [];
    clearPainting();
    const dateInput = document.getElementById('dream-date');
    if (dateInput) dateInput.value = todayISO();
    document.dispatchEvent(new CustomEvent('oneiros:dream-saved', { detail: dream }));

    showToast('🌙 Dream logged · Finding resonances across the globe...');

    // After a moment, check for matches and update UI
    setTimeout(async () => {
      try {
        const matchData = await api('GET', `/dreams/${dream.id}/matches?limit=5`);
        if (matchData?.total > 0) {
          showToast(`✨ ${matchData.total} dreamer${matchData.total > 1 ? 's' : ''} share your vision!`);
        }
        loadDashboard();
        // Refresh journal if user is on journal page
        if (document.getElementById('page-journal')?.classList.contains('active')) {
          loadJournal();
        }
        await checkNewBadgesAfterDream();
      } catch {}
    }, 2500);

  } catch (err) {
    showToast('⚠️ Failed to save dream: ' + (err.message || 'unknown error'), 'error');
  } finally {
    btn.disabled    = false;
    btn.textContent = 'Save my dream ↗';
  }
}


// ════════════════════════════════════════════════════════════
// ONBOARDING TOUR
// ════════════════════════════════════════════════════════════

const TOUR_STEPS = [
  {
    // Step 1: Dream capture card
    target:    '.capture-card',
    page:      'dashboard',
    icon:      '✍️',
    title:     'Log your dream <em>every morning</em>',
    body:      'Describe what you dreamed in as much detail as you can — right after you wake up, before memories fade. Voice, text, image, or let AI paint it for you. <strong>The more detail, the better your matches.</strong>',
    arrow:     'arrow-bottom',
    position:  'above'
  },
  {
    // Step 2: Capture methods
    target:    '.capture-methods',
    page:      'dashboard',
    icon:      '🎙️',
    title:     'Four ways to capture',
    body:      '<strong>Text</strong> — write it out. <strong>Voice</strong> — record the moment you wake up. <strong>Image</strong> — upload a sketch or photo. <strong>AI Paint</strong> — answer a few questions and the AI generates a visual of your dream.',
    arrow:     'arrow-bottom',
    position:  'above'
  },
  {
    // Step 3: Resonance card
    target:    '.resonance-card',
    page:      'dashboard',
    icon:      '✨',
    title:     'Your <em>dream resonance</em>',
    body:      'This number tells you how many people around the world shared a dream similar to yours last night. The matching engine compares themes, emotions, visual symbols, and narrative arc across all dreams logged globally.',
    arrow:     'arrow-top',
    position:  'below'
  },
  {
    // Step 4: Matches page
    target:    '#nb-matches',
    page:      'dashboard',
    icon:      '🌍',
    title:     'See your <em>matches</em>',
    body:      'Click Matches to see exactly who dreamed the same thing — which country they\'re from, the strength of the resonance as a glowing pulse, and which dimensions matched (theme, emotion, visual, narrative).',
    arrow:     'arrow-top',
    position:  'below'
  },
  {
    // Step 5: Anonymity model
    target:    null,
    page:      'dashboard',
    icon:      '🔒',
    title:     'You are completely <em>anonymous</em>',
    body:      'No one can see who you are until <strong>both</strong> of you independently choose to connect. Before that, other users see only a match count — no name, no avatar, no location below country level. Your privacy is the foundation.',
    arrow:     'arrow-none',
    position:  'center'
  },
  {
    // Step 6: Journal privacy
    target:    '#nb-journal',
    page:      'dashboard',
    icon:      '📖',
    title:     'Your private <em>dream journal</em>',
    body:      'Every dream you log is stored in your personal journal. You control visibility — <strong>Public</strong> dreams enter the global matching pool. <strong>Private</strong> dreams are yours alone, never matched, never seen by anyone.',
    arrow:     'arrow-top',
    position:  'below'
  },
  {
    // Step 7: Global map
    target:    '#nb-map',
    page:      'dashboard',
    icon:      '🌍',
    title:     'The <em>global dream map</em>',
    body:      'Watch the world dream in real time. Pulsing clusters show where the most active dreamers are right now. Hover any country to see how many dreams were logged and what themes dominate that region.',
    arrow:     'arrow-top',
    position:  'below'
  },
  {
    // Step 8: Streaks
    target:    null,
    page:      'dashboard',
    icon:      '🔥',
    title:     'Build your <em>streak</em>',
    body:      'Log a dream every morning to build your streak. The longer your streak, the more data you contribute to research — and the richer your personal dream history becomes. Earn badges along the way.',
    arrow:     'arrow-none',
    position:  'center'
  },
  {
    // Step 9: Research
    target:    null,
    page:      'dashboard',
    icon:      '🔬',
    title:     'You\'re part of something <em>larger</em>',
    body:      'Every dream you log (with your consent, given at registration) contributes anonymized data to scientific research into human dreaming. Oneiros exists to answer a question nobody has answered yet: <strong>why do we dream the same things?</strong>',
    arrow:     'arrow-none',
    position:  'center'
  }
];

let tourStep     = -1;   // -1 = welcome, 0..N-1 = steps, N = done
let tourActive   = false;
let tourResizeObs = null;

function tourShouldShow() {
  // A purchase, gift or chosen pass takes the stage first; the tour waits for the next visit
  let busy = false;
  try { busy = !!sessionStorage.getItem('oneiros_suppress_tour'); } catch {}
  return !localStorage.getItem('oneiros-tour-done') && currentUser && !busy;
}

function tourStart() {
  if (!tourShouldShow()) return;
  tourActive = true;
  tourStep   = -1;
  document.getElementById('tour-welcome').classList.add('active');
}

function tourNext() {
  document.getElementById('tour-welcome').classList.remove('active');
  document.getElementById('tour-done').classList.remove('active');
  tourStep++;

  if (tourStep >= TOUR_STEPS.length) {
    tourEnd();
    return;
  }
  renderTourStep(tourStep);
}

function tourBack() {
  if (tourStep <= 0) return;
  tourStep--;
  renderTourStep(tourStep);
}

function tourSkip() {
  tourCleanup();
  localStorage.setItem('oneiros-tour-done', '1');
  document.getElementById('tour-welcome').classList.remove('active');
}

function tourFinish() {
  document.getElementById('tour-done').classList.remove('active');
  tourCleanup();
  localStorage.setItem('oneiros-tour-done', '1');
  // Focus the dream capture input to invite first dream
  setTimeout(() => {
    const input = document.getElementById('dream-text');
    if (input) input.focus();
  }, 300);
}

function tourEnd() {
  tourCleanup(false);
  document.getElementById('tour-done').classList.add('active');
}

function tourCleanup(hideCard = true) {
  tourActive = false;
  document.getElementById('tour-overlay').classList.remove('active');
  document.getElementById('tour-spotlight').style.display = 'none';
  if (hideCard) document.getElementById('tour-card').style.display = 'none';
  if (tourResizeObs) { tourResizeObs.disconnect(); tourResizeObs = null; }
}

function renderTourStep(step) {
  const s = TOUR_STEPS[step];

  // Navigate to correct page if needed
  if (s.page && document.getElementById('page-' + s.page)?.className.indexOf('active') === -1) {
    goTo(s.page);
  }

  // Build dots
  const dots = TOUR_STEPS.map((_, i) =>
    `<div class="tour-dot ${i === step ? 'active' : ''}"></div>`
  ).join('');

  // Populate card content
  document.getElementById('tour-step-label-text').textContent = `Step ${step + 1} of ${TOUR_STEPS.length}`;
  document.getElementById('tour-dots').innerHTML = dots;
  document.getElementById('tour-icon').textContent  = s.icon;
  document.getElementById('tour-title').innerHTML   = s.title;
  document.getElementById('tour-body').innerHTML    = s.body;
  document.getElementById('tour-next-btn').textContent = step === TOUR_STEPS.length - 1 ? 'Finish ✓' : 'Next →';
  document.getElementById('tour-back-btn').style.display = step > 0 ? 'block' : 'none';

  // Set arrow class
  const card = document.getElementById('tour-card');
  card.className = `tour-card ${s.arrow || 'arrow-none'}`;
  card.style.display = 'block';

  // Activate overlay
  document.getElementById('tour-overlay').classList.add('active');

  // Position spotlight + card
  if (s.target) {
    const target = document.querySelector(s.target);
    if (target) {
      positionTourOnTarget(target, s.position, card);
      // Re-position on resize
      if (tourResizeObs) tourResizeObs.disconnect();
      tourResizeObs = new ResizeObserver(() => positionTourOnTarget(target, s.position, card));
      tourResizeObs.observe(document.body);
    } else {
      centerTourCard(card);
    }
  } else {
    document.getElementById('tour-spotlight').style.display = 'none';
    centerTourCard(card);
  }
}

function positionTourOnTarget(target, position, card) {
  const rect    = target.getBoundingClientRect();
  const pad     = 10;  // padding around spotlight
  const spotlight = document.getElementById('tour-spotlight');

  // Position spotlight
  spotlight.style.display = 'block';
  spotlight.style.top     = (rect.top    - pad) + 'px';
  spotlight.style.left    = (rect.left   - pad) + 'px';
  spotlight.style.width   = (rect.width  + pad * 2) + 'px';
  spotlight.style.height  = (rect.height + pad * 2) + 'px';

  // Scroll target into view if needed
  if (rect.top < 80 || rect.bottom > window.innerHeight - 80) {
    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    setTimeout(() => positionTourOnTarget(target, position, card), 400);
    return;
  }

  // Position card relative to spotlight
  const cardW   = Math.min(360, window.innerWidth - 32);
  const gap     = 16;

  let top, left;

  if (position === 'above') {
    top  = rect.top - pad - card.offsetHeight - gap;
    left = rect.left + rect.width / 2 - cardW / 2;
  } else if (position === 'below') {
    top  = rect.bottom + pad + gap;
    left = rect.left + rect.width / 2 - cardW / 2;
  } else if (position === 'left') {
    top  = rect.top + rect.height / 2 - card.offsetHeight / 2;
    left = rect.left - pad - cardW - gap;
  } else {
    top  = rect.bottom + pad + gap;
    left = rect.left + rect.width / 2 - cardW / 2;
  }

  // Clamp to viewport
  left = Math.max(16, Math.min(left, window.innerWidth  - cardW - 16));
  top  = Math.max(80, Math.min(top,  window.innerHeight - card.offsetHeight - 80));

  card.style.top   = top + 'px';
  card.style.left  = left + 'px';
  card.style.width = cardW + 'px';
}

function centerTourCard(card) {
  document.getElementById('tour-spotlight').style.display = 'none';
  const cardW = Math.min(360, window.innerWidth - 32);
  card.style.width  = cardW + 'px';
  card.style.top    = (window.innerHeight / 2 - card.offsetHeight / 2) + 'px';
  card.style.left   = (window.innerWidth  / 2 - cardW / 2) + 'px';
}



let streakData = null;
let badgeData  = null;

async function loadStreaks() {
  try {
    const [streak, badges, lb] = await Promise.all([
      api('GET', '/streaks/me'),
      api('GET', '/streaks/badges/me'),
      api('GET', '/streaks/leaderboard')
    ]);

    streakData = streak;
    badgeData  = badges;

    renderStreakCard(streak);
    renderBadgeShowcase(badges);
    renderLeaderboard(lb?.leaderboard || []);

    // Also update sidebar badge count
    const earnedCount = badges?.badges?.length || 0;
    document.querySelectorAll('.sidebar-item').forEach(el => {
      if (el.textContent.includes('Streaks')) {
        const existing = el.querySelector('.sidebar-badge');
        if (!existing && earnedCount > 0) {
          const badge = document.createElement('span');
          badge.className = 'sidebar-badge';
          badge.textContent = earnedCount;
          el.appendChild(badge);
        }
      }
    });
  } catch (err) {
    document.getElementById('streak-card-container').innerHTML =
      `<div style="padding:2rem;text-align:center;color:var(--indigo-light)">Log a dream to start your streak!</div>`;
  }
}

function renderStreakCard(streak) {
  const current = streak?.current_streak || 0;
  const longest = streak?.longest_streak || 0;

  // Calculate next milestone
  const milestones = [3, 7, 14, 30, 60, 100, 200, 365];
  const nextMilestone = milestones.find(m => m > current) || 365;
  const progress = Math.min((current / nextMilestone) * 100, 100);

  // Last 7 days indicator
  const today = new Date();
  const last7 = Array.from({length: 7}, (_, i) => {
    const d = new Date(today);
    d.setDate(d.getDate() - (6 - i));
    return d.toISOString().slice(0, 10);
  });

  const dreamDates = new Set(); // Would be populated from API in production
  const dayDots = last7.map((date, i) => {
    const isToday = i === 6;
    const hasDream = isToday ? current > 0 : (i >= 7 - current && current > 0);
    return `<div class="streak-day ${isToday && hasDream ? 'today' : hasDream ? 'filled' : ''}"></div>`;
  }).join('');

  const flameEmoji = current === 0 ? '💤' : current < 7 ? '🔥' : current < 30 ? '🌟' : '⭐';

  document.getElementById('streak-card-container').innerHTML = `
    <div class="streak-card" style="cursor:pointer" onclick="goTo('streaks')">
      <div class="streak-flame">${flameEmoji}</div>
      <div class="streak-info">
        <div class="streak-number">${current}<span>nights</span></div>
        <div class="streak-label">Current Dream Streak</div>
        <div class="streak-best">Personal best: ${longest} nights</div>
        <div class="streak-days" style="margin-top:0.6rem">${dayDots}</div>
      </div>
      <div class="streak-progress">
        <div class="streak-next-milestone">Next: ${nextMilestone} 🎯</div>
        <div class="streak-bar-wrap">
          <div class="streak-bar-fill" style="width:${progress}%"></div>
        </div>
        <div style="font-size:0.68rem;color:rgba(255,255,255,0.4);margin-top:4px">${current}/${nextMilestone}</div>
      </div>
    </div>`;
}

function renderBadgeShowcase(data) {
  if (!data) return;
  const earned    = data.badges || [];
  const allBadges = data.all_badges || [];
  const earnedIds = new Set(earned.map(b => b.badge.id));
  const container = document.getElementById('badge-showcase');

  const tierOrder = { legend: 0, gold: 1, silver: 2, bronze: 3 };
  const sorted    = [...allBadges].sort((a, b) => {
    const aEarned = earnedIds.has(a.id) ? 0 : 1;
    const bEarned = earnedIds.has(b.id) ? 0 : 1;
    if (aEarned !== bEarned) return aEarned - bEarned;
    return (tierOrder[a.tier] || 3) - (tierOrder[b.tier] || 3);
  });

  container.innerHTML = `
    <div class="badge-section-header">
      <div class="badge-section-title">Badges</div>
      <div class="badge-section-count">${earned.length} / ${allBadges.length} earned</div>
    </div>
    <div class="badge-grid">
      ${sorted.map(badge => {
        const isEarned = earnedIds.has(badge.id);
        const tierClass = `tier-${badge.tier}`;
        return `
          <div class="badge-item ${isEarned ? '' : 'locked'}" data-tooltip="${escHtml(badge.desc)}">
            <div class="badge-tier-dot ${tierClass}"></div>
            <div class="badge-icon">${badge.icon}</div>
            <div class="badge-name">${escHtml(badge.name)}</div>
          </div>`;
      }).join('')}
    </div>`;
}

function renderLeaderboard(entries) {
  const container = document.getElementById('leaderboard-container');
  if (!entries.length) {
    container.innerHTML = `<div style="padding:2rem;text-align:center;color:var(--indigo-light);font-size:0.85rem">No streak data yet — log your first dream tonight!</div>`;
    return;
  }
  container.innerHTML = entries.map((entry, i) => {
    const rankClass = i === 0 ? 'top1' : i === 1 ? 'top2' : i === 2 ? 'top3' : '';
    const medal     = i === 0 ? '🥇' : i === 1 ? '🥈' : i === 2 ? '🥉' : '';
    return `
      <div class="leaderboard-row">
        <div class="leaderboard-rank ${rankClass}">${medal || entry.rank}</div>
        <div class="leaderboard-name">${escHtml(entry.display)}</div>
        ${entry.region ? `<div class="leaderboard-region">${flagEmoji(null)}</div>` : ''}
        <div class="leaderboard-streak">${entry.current_streak}<span>nights</span></div>
      </div>`;
  }).join('');
}

// Achievement toast — called when server returns new badges
function showAchievementToast(badge) {
  const el   = document.getElementById('achievement-toast');
  document.getElementById('achievement-icon').textContent = badge.icon || '🌙';
  document.getElementById('achievement-name').textContent = badge.name;
  document.getElementById('achievement-desc').textContent = badge.desc;
  el.classList.add('show');
  // Dismiss after 5 seconds
  clearTimeout(el._t);
  el._t = setTimeout(() => el.classList.remove('show'), 5000);
}

// After submitting a dream, check if new badges were awarded
async function checkNewBadgesAfterDream() {
  try {
    const data = await api('GET', '/streaks/badges/me');
    if (!data?.badges) return;

    // Compare with cached badge list
    if (!badgeData) { badgeData = data; return; }
    const prevIds = new Set(badgeData.badges.map(b => b.badge.id));
    const newBadges = data.badges.filter(b => !prevIds.has(b.badge.id));
    badgeData = data;

    // Show achievement toast for each new badge (staggered)
    newBadges.forEach((b, i) => {
      setTimeout(() => showAchievementToast(b.badge), i * 2000);
    });
  } catch {}
}

// Add streak mini-display to dashboard
async function loadStreakMini() {
  try {
    const streak = await api('GET', '/streaks/me');
    if (!streak) return;
    const current = streak.current_streak || 0;
    // Update the dash-date line to include streak
    const dateEl = document.querySelector('.dash-date');
    if (!dateEl) return;
    let streakEl = document.getElementById('dash-streak');
    if (!streakEl) {
      streakEl = document.createElement('span');
      streakEl.id = 'dash-streak';
      dateEl.appendChild(streakEl);
    }
    const flame = current >= 30 ? '⭐' : current >= 7 ? '🌟' : '🔥';
    streakEl.innerHTML = current > 0 ? ` &nbsp;${flame} <strong>${current}-night streak</strong>` : '';
  } catch {}
}



// ════════════════════════════════════════════════════════════
// BROWSER ALERTS
// Shared hosting has no web-push (VAPID) service, so alerts are raised by the open page
// itself when new notifications arrive while Oneiros sits in a background tab.
// ════════════════════════════════════════════════════════════

let alertsPrimed = false;

function browserAlertsEnabled() {
  return localStorage.getItem('oneiros_browser_alerts') === '1'
    && 'Notification' in window && Notification.permission === 'granted';
}

async function initPushNotifications() {
  alertsPrimed = false;
}

function notifyInBrowser(notifications) {
  const unread = notifications.filter(n => !n.is_read);
  let seen;
  try { seen = new Set(JSON.parse(sessionStorage.getItem('oneiros_alerted') || '[]')); } catch { seen = new Set(); }
  const fresh = unread.filter(n => !seen.has(n.id));
  unread.forEach(n => seen.add(n.id));
  try { sessionStorage.setItem('oneiros_alerted', JSON.stringify([...seen].slice(-200))); } catch {}
  // The first poll after sign-in only records what is already waiting
  const shouldAlert = alertsPrimed && document.hidden && browserAlertsEnabled();
  alertsPrimed = true;
  if (!shouldAlert) return;
  fresh.slice(0, 3).forEach(n => {
    const options = { body: n.body, icon: 'assets/icon-192.png', badge: 'assets/icon-192.png', tag: n.id, data: { url: 'oneiros.php' } };
    const viaWorker = navigator.serviceWorker?.controller
      ? navigator.serviceWorker.ready.then(reg => reg.showNotification(n.title, options))
      : Promise.reject();
    viaWorker.catch(() => {
      try {
        const note = new Notification(n.title, options);
        note.onclick = () => { window.focus(); note.close(); };
      } catch {}
    });
  });
}

async function togglePushFromProfile() {
  const btn = document.getElementById('push-toggle-btn');
  if (browserAlertsEnabled()) {
    localStorage.removeItem('oneiros_browser_alerts');
    btn.textContent = 'Enable';
    showToast('Browser alerts turned off');
    return;
  }
  if (!('Notification' in window)) {
    showToast('This browser does not support alerts. In-app notifications still work.', 'error');
    return;
  }
  const permission = await Notification.requestPermission();
  if (permission !== 'granted') {
    showToast('Alerts are blocked for this site. You can allow them in your browser settings.', 'error');
    return;
  }
  localStorage.setItem('oneiros_browser_alerts', '1');
  btn.textContent = 'Disable';
  showToast('🔔 Browser alerts on · We will let you know while Oneiros is open');
}

if ('serviceWorker' in navigator) {
  navigator.serviceWorker.addEventListener('message', event => {
    if (event.data?.type === 'DREAM_SYNCED') {
      showToast('🌙 Offline dream synced');
      loadDashboard();
    }
  });
}

// ════════════════════════════════════════════════════════════

let deferredInstallPrompt = null;

function initPWA() {
  // Register service worker
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('sw.js')
      .then(reg => {
        console.log('Oneiros SW registered:', reg.scope);
        // Check for updates on each load
        reg.addEventListener('updatefound', () => {
          const newWorker = reg.installing;
          newWorker?.addEventListener('statechange', () => {
            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
              showToast('✨ Oneiros updated — refresh for the latest version');
            }
          });
        });
      })
      .catch(err => console.log('SW registration failed:', err));
  }

  // Capture the install prompt (Android / desktop Chrome)
  window.addEventListener('beforeinstallprompt', e => {
    e.preventDefault();
    deferredInstallPrompt = e;
    // Only show if not already installed and not dismissed this session
    if (!sessionStorage.getItem('pwa-dismissed') && !isInStandaloneMode()) {
      setTimeout(() => showInstallPrompt(), 3000); // small delay feels less pushy
    }
  });

  // Hide prompt if app gets installed
  window.addEventListener('appinstalled', () => {
    document.getElementById('pwa-prompt').classList.remove('show');
    deferredInstallPrompt = null;
    showToast('🌙 Oneiros installed! Find it on your home screen.');
  });

  // iOS — show instructions manually since iOS doesn't fire beforeinstallprompt
  if (isIOS() && !isInStandaloneMode() && !sessionStorage.getItem('ios-dismissed')) {
    setTimeout(() => {
      document.getElementById('ios-prompt').classList.add('show');
    }, 4000);
  }

  // Online / offline detection
  window.addEventListener('offline', () => {
    document.getElementById('offline-banner').classList.add('show');
  });
  window.addEventListener('online', () => {
    document.getElementById('offline-banner').classList.remove('show');
    showToast('🌐 Back online');
  });
  if (!navigator.onLine) {
    document.getElementById('offline-banner').classList.add('show');
  }
}

function showInstallPrompt() {
  if (!deferredInstallPrompt) return;
  document.getElementById('pwa-prompt').classList.add('show');
}

async function installPWA() {
  if (!deferredInstallPrompt) return;
  document.getElementById('pwa-prompt').classList.remove('show');
  deferredInstallPrompt.prompt();
  const { outcome } = await deferredInstallPrompt.userChoice;
  deferredInstallPrompt = null;
  if (outcome === 'dismissed') sessionStorage.setItem('pwa-dismissed', '1');
}

function dismissInstallPrompt() {
  document.getElementById('pwa-prompt').classList.remove('show');
  sessionStorage.setItem('pwa-dismissed', '1');
}

function isIOS() {
  return /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
}

function isInStandaloneMode() {
  return window.matchMedia('(display-mode: standalone)').matches
    || window.navigator.standalone === true;
}

// ── Handle resize / orientation change ──
window.addEventListener('resize', () => {
  if (!currentUser) return;
  const isMobile = window.innerWidth <= 768;
  document.getElementById('bottom-nav').style.display = isMobile ? 'flex' : 'none';
  document.getElementById('mobile-header').style.display = isMobile ? 'flex' : 'none';
  document.getElementById('main-nav').style.display = isMobile ? 'none' : 'flex';
});

// ════════════════════════════════════════════════════════════
// LANDING PAGE PUBLIC STATS
// ════════════════════════════════════════════════════════════
async function loadPublicStats() {
  try {
    // Try clean URL first, then fallback
    let res, data;
    for (const fb of [true]) {
      useRewriteFallback = fb;
      try {
        res = await fetch(buildApiUrl('/research/public'));
        if (res.status === 404) continue;
        data = await res.json();
        break;
      } catch {}
    }
    if (!data) return;
    // A brand-new community reads better without a row of zeros
    document.querySelector('.universe-strip')?.classList.toggle('is-quiet', !(data.total_dreams > 0));
    const formatNum = n => n >= 1000000 ? (n/1000000).toFixed(1)+'M'
                       : n >= 1000    ? (n/1000).toFixed(0)+'K'
                       : String(n);
    const el = id => document.getElementById(id);
    if (el('hero-dreams'))    el('hero-dreams').textContent    = formatNum(data.total_dreams || 0);
    if (el('hero-countries')) el('hero-countries').textContent = data.active_countries || 0;
    if (el('hero-matches'))   el('hero-matches').textContent   = formatNum(data.total_matches || 0);
    if (el('hero-active'))    el('hero-active').textContent    = data.dreamers_active_now || 0;
  } catch (err) {
    console.error('Public stats load failed:', err);
  }
}

// ════════════════════════════════════════════════════════════
// INITIALIZATION
// ════════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', async () => {
  // Initialize PWA features
  initPWA();

  // Load public stats for landing page (fire and forget)
  loadPublicStats();
  loadCapabilities();
  const dateInput = document.getElementById('dream-date');
  if (dateInput) { dateInput.value = todayISO(); dateInput.max = todayISO(); }

  // Try to restore existing session
  const rt = localStorage.getItem('oneiros_refresh_token');
  if (rt) {
    const ok = await tryRefresh();
    if (ok && currentUser) {
      document.getElementById('nav-auth-links').style.display = 'none';
      document.getElementById('nav-user-links').style.display = 'flex';

      const name = currentUser?.display_name || currentUser?.email || '';
      const initials = name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0,2);
      document.querySelectorAll('.avatar, #mobile-avatar').forEach(el => el.textContent = initials);

      if (window.innerWidth <= 768) {
        document.getElementById('bottom-nav').style.display = 'flex';
        document.getElementById('main-nav').style.display = 'none';
        document.getElementById('mobile-header').style.display = 'flex';
      }

      goTo('dashboard');
      document.dispatchEvent(new Event('oneiros:signed-in'));
      startNotifPolling();
      // Show tour for first-time users even on session restore
      setTimeout(() => { if (typeof tourStart === 'function') tourStart(); }, 800);
      return;
    }
  }

  goTo('landing');
  document.dispatchEvent(new Event('oneiros:signed-out-ready'));
});
