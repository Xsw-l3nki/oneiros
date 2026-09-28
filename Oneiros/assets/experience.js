// ════════════════════════════════════════════════════════════
// Oneiros — Experience layer
// Motion, the Dream Canvas, dream details, insights and the small rituals
// around remembering. Loaded after app.js and shares its globals
// (currentUser, api, goTo, showToast, escHtml, dreamJournal …).
// ════════════════════════════════════════════════════════════

(() => {
  const root = document.documentElement;
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)');
  const MOTION_KEY = 'oneiros_motion';

  const store = {
    get(key) { try { return localStorage.getItem(key); } catch { return null; } },
    set(key, value) { try { localStorage.setItem(key, value); } catch {} },
    remove(key) { try { localStorage.removeItem(key); } catch {} },
  };

  const motionAllowed = () => !root.classList.contains('motion-paused') && !reducedMotion.matches;
  const onLanding = () => document.getElementById('page-landing')?.classList.contains('active');

  // ════════════════════════════════════════════════════════════
  // HERO PARTICLE FIELD — stars, drifting light motes, the odd shooting star
  // ════════════════════════════════════════════════════════════
  const particles = (() => {
    const canvas = document.getElementById('dream-particles');
    const ctx = canvas?.getContext?.('2d');
    if (!ctx) return { start() {}, stop() {}, nudge() {} };

    let w = 0, h = 0, stars = [], motes = [], raf = 0, running = false, last = 0;
    let heroVisible = true, shooting = null, nextShot = 3500;
    const pointer = { x: 0, y: 0, tx: 0, ty: 0 };
    const sprites = {};

    function sprite(hue) {
      if (sprites[hue]) return sprites[hue];
      const c = document.createElement('canvas');
      c.width = c.height = 64;
      const g = c.getContext('2d');
      const grd = g.createRadialGradient(32, 32, 0, 32, 32, 32);
      grd.addColorStop(0, `hsla(${hue},90%,94%,1)`);
      grd.addColorStop(0.2, `hsla(${hue},80%,82%,.5)`);
      grd.addColorStop(1, `hsla(${hue},80%,70%,0)`);
      g.fillStyle = grd;
      g.fillRect(0, 0, 64, 64);
      return (sprites[hue] = c);
    }

    function mote(anywhere) {
      return {
        x: Math.random() * w,
        y: anywhere ? Math.random() * h : h + 20,
        r: Math.random() * 2.2 + 0.6,
        vy: -(Math.random() * 0.16 + 0.04),
        vx: (Math.random() - 0.5) * 0.06,
        a: 0,
        max: Math.random() * 0.5 + 0.2,
        hue: [258, 272, 200, 35][Math.floor(Math.random() * 4)],
        depth: Math.random() * 0.8 + 0.4,
        sway: Math.random() * Math.PI * 2,
      };
    }

    function resize() {
      const rect = canvas.getBoundingClientRect();
      if (!rect.width || !rect.height) return;
      const dpr = Math.min(window.devicePixelRatio || 1, 2);
      w = rect.width; h = rect.height;
      canvas.width = Math.round(w * dpr);
      canvas.height = Math.round(h * dpr);
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      const area = w * h;
      stars = Array.from({ length: Math.round(Math.min(240, area / 6500)) }, () => ({
        x: Math.random() * w, y: Math.random() * h * 0.78,
        r: Math.random() * 1.1 + 0.2, a: Math.random() * 0.6 + 0.2,
        tw: Math.random() * 0.0022 + 0.0004, ph: Math.random() * Math.PI * 2,
        depth: Math.random() * 0.6 + 0.2,
      }));
      motes = Array.from({ length: Math.round(Math.min(64, area / 24000)) }, () => mote(true));
      if (!running) draw(performance.now(), 0);
    }

    function draw(t, dt) {
      pointer.x += (pointer.tx - pointer.x) * 0.045;
      pointer.y += (pointer.ty - pointer.y) * 0.045;
      ctx.clearRect(0, 0, w, h);
      ctx.fillStyle = '#ece6ff';
      for (const s of stars) {
        ctx.globalAlpha = s.a * (0.55 + 0.45 * Math.sin(t * s.tw + s.ph));
        ctx.beginPath();
        ctx.arc(s.x + pointer.x * s.depth * 12, s.y + pointer.y * s.depth * 8, s.r, 0, Math.PI * 2);
        ctx.fill();
      }
      const step = dt / 16.7;
      for (const m of motes) {
        m.sway += 0.012 * step;
        m.x += (m.vx + Math.sin(m.sway) * 0.05) * step;
        m.y += m.vy * step;
        m.a = Math.min(m.max, m.a + 0.004 * step);
        if (m.y < -20 || m.x < -30 || m.x > w + 30) Object.assign(m, mote(false));
        const fade = m.y < h * 0.18 ? Math.max(0, m.y / (h * 0.18)) : 1;
        ctx.globalAlpha = m.a * fade;
        const size = m.r * 12;
        ctx.drawImage(sprite(m.hue), m.x + pointer.x * m.depth * 26 - size / 2, m.y + pointer.y * m.depth * 16 - size / 2, size, size);
      }
      nextShot -= dt;
      if (!shooting && nextShot <= 0 && dt) {
        shooting = { x: w * (0.35 + Math.random() * 0.6), y: h * Math.random() * 0.32, vx: -(4 + Math.random() * 3), vy: 1.4 + Math.random(), life: 1 };
        nextShot = 6500 + Math.random() * 9000;
      }
      if (shooting) {
        const s = shooting;
        s.x += s.vx * step; s.y += s.vy * step; s.life -= 0.013 * step;
        const tail = 20;
        const g = ctx.createLinearGradient(s.x, s.y, s.x - s.vx * tail, s.y - s.vy * tail);
        g.addColorStop(0, 'rgba(255,250,240,.95)');
        g.addColorStop(1, 'rgba(200,187,244,0)');
        ctx.globalAlpha = Math.max(0, s.life);
        ctx.strokeStyle = g;
        ctx.lineWidth = 1.3;
        ctx.beginPath();
        ctx.moveTo(s.x, s.y);
        ctx.lineTo(s.x - s.vx * tail, s.y - s.vy * tail);
        ctx.stroke();
        if (s.life <= 0) shooting = null;
      }
      ctx.globalAlpha = 1;
    }

    function frame(t) {
      if (!running) return;
      raf = requestAnimationFrame(frame);
      const dt = Math.min(50, t - (last || t));
      last = t;
      draw(t, dt);
    }

    function start() {
      if (running || !motionAllowed() || !heroVisible || document.hidden || !onLanding()) return;
      running = true;
      last = 0;
      raf = requestAnimationFrame(frame);
    }

    function stop() {
      running = false;
      cancelAnimationFrame(raf);
    }

    new ResizeObserver(resize).observe(canvas);
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(([entry]) => {
        heroVisible = entry.isIntersecting;
        heroVisible ? start() : stop();
      }).observe(canvas);
    }
    document.addEventListener('visibilitychange', () => (document.hidden ? stop() : start()));

    return {
      start, stop,
      nudge(x, y) { pointer.tx = x; pointer.ty = y; },
    };
  })();

  // ════════════════════════════════════════════════════════════
  // PARALLAX, AURA AND MAGNETIC CALLS TO ACTION (landing only)
  // ════════════════════════════════════════════════════════════
  const heroPicture = document.querySelector('.hero-scene picture');
  const heroContent = document.querySelector('.hero-content');
  const aura = document.createElement('div');
  aura.className = 'dream-aura';
  aura.setAttribute('aria-hidden', 'true');
  document.body.appendChild(aura);

  const pointerState = { x: 0.5, y: 0.5, px: -999, py: -999 };
  let parallaxQueued = false;

  function applyParallax() {
    parallaxQueued = false;
    if (!onLanding() || !motionAllowed()) {
      if (heroPicture) heroPicture.style.transform = '';
      if (heroContent) { heroContent.style.transform = ''; heroContent.style.opacity = ''; }
      aura.style.opacity = '0';
      return;
    }
    const scroll = Math.min(window.scrollY, 1200);
    const dx = (pointerState.x - 0.5), dy = (pointerState.y - 0.5);
    if (heroPicture) heroPicture.style.transform = `translate3d(${dx * -18}px, ${scroll * 0.2 + dy * -12}px, 0) scale(1.06)`;
    if (heroContent) {
      heroContent.style.transform = `translate3d(0, ${scroll * -0.1}px, 0)`;
      heroContent.style.opacity = String(Math.max(0.25, 1 - scroll / 900));
    }
    if (finePointer.matches) {
      aura.style.opacity = '1';
      aura.style.transform = `translate3d(${pointerState.px}px, ${pointerState.py}px, 0)`;
    }
  }

  function queueParallax() {
    if (!parallaxQueued) { parallaxQueued = true; requestAnimationFrame(applyParallax); }
  }

  window.addEventListener('scroll', queueParallax, { passive: true });
  window.addEventListener('pointermove', e => {
    pointerState.x = e.clientX / window.innerWidth;
    pointerState.y = e.clientY / window.innerHeight;
    pointerState.px = e.clientX;
    pointerState.py = e.clientY;
    particles.nudge(pointerState.x - 0.5, pointerState.y - 0.5);
    queueParallax();
  }, { passive: true });
  document.addEventListener('pointerleave', () => { aura.style.opacity = '0'; });

  document.querySelectorAll('.dream-hero .btn-primary, .last-invitation .btn-primary, .collective-copy .btn-ghost, .nav-cta').forEach(btn => {
    btn.classList.add('magnetic');
    btn.addEventListener('pointermove', e => {
      if (!finePointer.matches || !motionAllowed()) return;
      const r = btn.getBoundingClientRect();
      btn.style.transform = `translate(${(e.clientX - r.left - r.width / 2) * 0.18}px, ${(e.clientY - r.top - r.height / 2) * 0.3}px)`;
    });
    btn.addEventListener('pointerleave', () => { btn.style.transform = ''; });
  });

  // ════════════════════════════════════════════════════════════
  // SCROLL REVEALS AND LIVING NUMBERS
  // ════════════════════════════════════════════════════════════
  document.body.classList.add('motion-ready');
  const revealObserver = 'IntersectionObserver' in window
    ? new IntersectionObserver(entries => {
        for (const entry of entries) {
          if (!entry.isIntersecting) continue;
          entry.target.classList.add('visible');
          revealObserver.unobserve(entry.target);
        }
      }, { rootMargin: '0px 0px -8% 0px', threshold: 0.12 })
    : null;

  function observeReveals() {
    document.querySelectorAll('.page.active .reveal:not(.visible)').forEach(el => {
      revealObserver ? revealObserver.observe(el) : el.classList.add('visible');
    });
  }

  function countUp(el) {
    const match = /^([\d.,]+)([KM]?)$/.exec(el.textContent.trim());
    if (!match || el.dataset.counted === el.textContent) return;
    const target = parseFloat(match[1].replace(/,/g, ''));
    const suffix = match[2];
    const decimals = (match[1].split('.')[1] || '').length;
    const finalText = el.textContent;
    el.dataset.counted = finalText;
    if (!motionAllowed() || !target) return;
    const start = performance.now();
    const tick = now => {
      const p = Math.min(1, (now - start) / 1600);
      const eased = 1 - Math.pow(1 - p, 4);
      el.textContent = p < 1 ? (target * eased).toFixed(decimals) + suffix : finalText;
      if (p < 1) requestAnimationFrame(tick);
      el.dataset.counted = el.textContent;
    };
    requestAnimationFrame(tick);
  }

  const strip = document.querySelector('.universe-strip');
  if (strip) {
    let stripSeen = false;
    const runCounts = () => stripSeen && strip.querySelectorAll('.stat-num').forEach(countUp);
    new MutationObserver(runCounts).observe(strip, { childList: true, subtree: true, characterData: true });
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(([entry], obs) => {
        if (!entry.isIntersecting) return;
        stripSeen = true;
        runCounts();
        obs.disconnect();
      }, { threshold: 0.4 }).observe(strip);
    }
  }

  // ════════════════════════════════════════════════════════════
  // MOTION PREFERENCE
  // ════════════════════════════════════════════════════════════
  function applyMotion(paused) {
    root.classList.toggle('motion-paused', paused);
    const btn = document.getElementById('motion-toggle');
    if (btn) {
      btn.textContent = paused ? 'Resume motion' : 'Pause motion';
      btn.setAttribute('aria-pressed', String(paused));
    }
    paused ? particles.stop() : particles.start();
    queueParallax();
  }

  window.toggleMotion = function () {
    const paused = !root.classList.contains('motion-paused');
    store.set(MOTION_KEY, paused ? 'paused' : 'on');
    applyMotion(paused);
    showToast(paused ? 'Motion paused · Everything stays still' : 'Motion resumed');
  };

  applyMotion(store.get(MOTION_KEY) === 'paused');
  reducedMotion.addEventListener?.('change', () => applyMotion(root.classList.contains('motion-paused')));

  // ════════════════════════════════════════════════════════════
  // DREAM CANVAS — a generative painting drawn from the dream itself
  // ════════════════════════════════════════════════════════════
  const PALETTES = {
    wonder:    { sky: ['#070a22', '#241f5c', '#6c5bb6'], glow: '#f6ddb0', accent: '#b9abec', land: '#120f2c' },
    peace:     { sky: ['#041219', '#0f3a49', '#3f8c95'], glow: '#dcf6f1', accent: '#93d6ef', land: '#07202a' },
    joy:       { sky: ['#160c29', '#62305b', '#e28d6e'], glow: '#ffe4ad', accent: '#f4b8a6', land: '#241028' },
    fear:      { sky: ['#030207', '#1b0a1d', '#471531'], glow: '#d0495d', accent: '#8a2b4a', land: '#070309' },
    longing:   { sky: ['#060919', '#1d2150', '#5b4e8e'], glow: '#ecbccf', accent: '#c7a2d9', land: '#0d0f26' },
    confusion: { sky: ['#090617', '#2a164f', '#1e6e78'], glow: '#caf1ad', accent: '#8fd3c4', land: '#0c0a1f' },
    nocturne:  { sky: ['#080b18', '#1c1b3f', '#4a3f7c'], glow: '#f0e7ff', accent: '#c8bbf4', land: '#0d0d24' },
  };
  const EMOTION_PALETTE = { wonder: 'wonder', awe: 'wonder', peace: 'peace', joy: 'joy', fear: 'fear', terror: 'fear', dread: 'fear', longing: 'longing', sadness: 'longing', confusion: 'confusion' };
  const MOTIFS = {
    water: /\b(ocean|sea|water|river|lake|wave|waves|rain|swim|swimming|drown|flood|shore|beach|tide)\b/i,
    flight: /\b(fly|flying|flew|float|floating|sky|wing|wings|bird|birds|air|soar)\b/i,
    nature: /\b(forest|tree|trees|garden|flower|flowers|field|meadow|grass|jungle|woods)\b/i,
    city: /\b(city|building|buildings|house|tower|towers|room|school|street|hallway|stairs|office|castle|architecture)\b/i,
    door: /\b(door|doorway|portal|gate|arch|threshold)\b/i,
    fire: /\b(fire|flame|flames|burn|burning|ember|volcano)\b/i,
    dark: /\b(dark|darkness|shadow|shadows|chase|chased|monster|night)\b/i,
    light: /\b(light|sun|glow|glowing|shine|bright|gold|golden)\b/i,
    mountain: /\b(mountain|mountains|hill|hills|cliff|valley)\b/i,
  };

  function hashString(str) {
    let h1 = 0xdeadbeef, h2 = 0x41c6ce57;
    for (let i = 0; i < str.length; i++) {
      const ch = str.charCodeAt(i);
      h1 = Math.imul(h1 ^ ch, 2654435761);
      h2 = Math.imul(h2 ^ ch, 1597334677);
    }
    h1 = Math.imul(h1 ^ (h1 >>> 16), 2246822507) ^ Math.imul(h2 ^ (h2 >>> 13), 3266489909);
    return h1 >>> 0;
  }

  function seededRandom(seed) {
    return () => {
      seed |= 0; seed = (seed + 0x6d2b79f5) | 0;
      let t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
      t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
      return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
  }

  function hexToRgba(hex, alpha) {
    const n = parseInt(hex.slice(1), 16);
    return `rgba(${(n >> 16) & 255},${(n >> 8) & 255},${n & 255},${alpha})`;
  }

  function ridge(g, rnd, W, baseY, amp, color, jag = 1) {
    const phase = rnd() * 1000, freq = (0.002 + rnd() * 0.004) * (1536 / W);
    g.beginPath();
    g.moveTo(0, baseY);
    for (let x = 0; x <= W; x += Math.max(2, W / 192)) {
      const y = baseY - Math.abs(Math.sin(x * freq + phase)) * amp * 0.6
        - Math.sin(x * freq * 2.7 + phase * 2) * amp * 0.25 * jag
        - Math.sin(x * freq * 7.1 + phase) * amp * 0.08 * jag;
      g.lineTo(x, y);
    }
    g.lineTo(W, g.canvas.height);
    g.lineTo(0, g.canvas.height);
    g.closePath();
    g.fillStyle = color;
    g.fill();
  }

  function paintCanvas({ text = '', emotions = [], answers = [] }, W = 1536, H = 1024) {
    const k = W / 1536;  // scale for thumbnails
    const canvas = document.createElement('canvas');
    canvas.width = W; canvas.height = H;
    const g = canvas.getContext('2d');
    const source = [text, ...emotions, ...answers].join(' ');
    const rnd = seededRandom(hashString(source || 'oneiros'));
    const lowered = emotions.map(e => e.toLowerCase());
    const moodKey = lowered.map(e => EMOTION_PALETTE[e]).find(Boolean)
      || (MOTIFS.dark.test(source) ? 'fear' : MOTIFS.water.test(source) ? 'peace' : MOTIFS.light.test(source) ? 'joy' : 'nocturne');
    const pal = PALETTES[moodKey];
    const has = Object.fromEntries(Object.entries(MOTIFS).map(([k, re]) => [k, re.test(source)]));
    const horizon = H * (has.water ? 0.64 : 0.7);

    // Sky
    const sky = g.createLinearGradient(0, 0, 0, horizon);
    sky.addColorStop(0, pal.sky[0]);
    sky.addColorStop(0.62, pal.sky[1]);
    sky.addColorStop(1, pal.sky[2]);
    g.fillStyle = sky;
    g.fillRect(0, 0, W, H);

    // Nebulae
    g.globalCompositeOperation = 'screen';
    for (let i = 0; i < 7; i++) {
      const x = rnd() * W, y = rnd() * horizon * 0.8, r = (180 + rnd() * 420) * k;
      const neb = g.createRadialGradient(x, y, 0, x, y, r);
      neb.addColorStop(0, hexToRgba(i % 2 ? pal.accent : pal.glow, 0.1 + rnd() * 0.12));
      neb.addColorStop(1, hexToRgba(pal.accent, 0));
      g.fillStyle = neb;
      g.fillRect(0, 0, W, H);
    }
    g.globalCompositeOperation = 'source-over';

    // Stars
    const starCount = Math.round((has.light ? 160 : moodKey === 'fear' ? 220 : 520) * Math.max(k * k, 0.12));
    for (let i = 0; i < starCount; i++) {
      const x = rnd() * W, y = rnd() * horizon * 0.95, r = (rnd() < 0.96 ? rnd() * 1.3 + 0.2 : rnd() * 2.4 + 1) * Math.max(k, 0.45);
      g.globalAlpha = 0.25 + rnd() * 0.7;
      g.fillStyle = rnd() < 0.8 ? '#f4f0ff' : pal.glow;
      g.beginPath(); g.arc(x, y, r, 0, Math.PI * 2); g.fill();
    }
    g.globalAlpha = 1;

    // The orb: moon, sun or eclipse
    const ox = W * (0.55 + rnd() * 0.25), oy = horizon * (0.34 + rnd() * 0.18), or = (70 + rnd() * (has.light ? 90 : 60)) * k;
    const halo = g.createRadialGradient(ox, oy, or * 0.6, ox, oy, or * 5.5);
    halo.addColorStop(0, hexToRgba(pal.glow, 0.45));
    halo.addColorStop(0.35, hexToRgba(pal.glow, 0.12));
    halo.addColorStop(1, hexToRgba(pal.glow, 0));
    g.fillStyle = halo;
    g.fillRect(0, 0, W, H);
    const disc = g.createRadialGradient(ox - or * 0.3, oy - or * 0.35, or * 0.1, ox, oy, or);
    disc.addColorStop(0, '#fffaf2');
    disc.addColorStop(0.7, pal.glow);
    disc.addColorStop(1, hexToRgba(pal.glow, 0.85));
    g.fillStyle = disc;
    g.beginPath(); g.arc(ox, oy, or, 0, Math.PI * 2); g.fill();
    if (moodKey === 'fear') {
      g.fillStyle = pal.sky[0];
      g.beginPath(); g.arc(ox + or * 0.18, oy - or * 0.08, or * 0.93, 0, Math.PI * 2); g.fill();
    } else if (!has.light) {
      g.globalAlpha = 0.08;
      g.fillStyle = '#6e6590';
      for (let i = 0; i < 9; i++) {
        g.beginPath(); g.arc(ox + (rnd() - 0.5) * or * 1.3, oy + (rnd() - 0.5) * or * 1.3, or * (0.06 + rnd() * 0.16), 0, Math.PI * 2); g.fill();
      }
      g.globalAlpha = 1;
    }

    // Luminous ribbons for flight and wonder
    if (has.flight || moodKey === 'wonder') {
      for (let i = 0; i < 3; i++) {
        const y0 = horizon * (0.25 + rnd() * 0.5);
        const rib = g.createLinearGradient(0, 0, W, 0);
        rib.addColorStop(0, hexToRgba(pal.accent, 0));
        rib.addColorStop(0.5, hexToRgba(pal.glow, 0.35));
        rib.addColorStop(1, hexToRgba(pal.accent, 0));
        g.strokeStyle = rib;
        g.lineWidth = (1.5 + rnd() * 3) * Math.max(k, 0.4);
        g.beginPath();
        g.moveTo(-50, y0);
        g.bezierCurveTo(W * 0.3, y0 - 220 * k * rnd(), W * 0.6, y0 + 200 * k * rnd(), W + 50, y0 + (rnd() * 200 - 100) * k);
        g.stroke();
      }
      if (has.flight) {
        g.strokeStyle = hexToRgba('#0b0a1c', 0.75);
        g.lineWidth = 2.2 * Math.max(k, 0.4);
        for (let i = 0; i < 7; i++) {
          const bx = W * (0.15 + rnd() * 0.5), by = horizon * (0.2 + rnd() * 0.4), s = (8 + rnd() * 14) * k;
          g.beginPath();
          g.moveTo(bx - s, by - s * 0.3);
          g.quadraticCurveTo(bx - s * 0.4, by - s * 0.55, bx, by);
          g.quadraticCurveTo(bx + s * 0.4, by - s * 0.55, bx + s, by - s * 0.3);
          g.stroke();
        }
      }
    }

    // Clouds and mist
    for (let i = 0; i < 16; i++) {
      const cx = rnd() * W, cy = horizon * (0.45 + rnd() * 0.6), rx = (120 + rnd() * 320) * k;
      const cloud = g.createRadialGradient(cx, cy, 0, cx, cy, rx);
      cloud.addColorStop(0, hexToRgba(pal.accent, 0.07 + rnd() * 0.08));
      cloud.addColorStop(1, hexToRgba(pal.accent, 0));
      g.fillStyle = cloud;
      g.beginPath(); g.ellipse(cx, cy, rx, rx * 0.32, 0, 0, Math.PI * 2); g.fill();
    }

    // Distant towers
    if (has.city) {
      for (let i = 0; i < 14; i++) {
        const tw = (26 + rnd() * 60) * k, th = (90 + rnd() * 260) * k, tx = rnd() * W, ty = horizon - th + (has.water ? 0 : 30 * k);
        g.fillStyle = hexToRgba(pal.land, 0.85);
        g.fillRect(tx, ty, tw, th + 40 * k);
        g.fillStyle = hexToRgba(pal.glow, 0.55);
        for (let wy = ty + 10 * k; wy < horizon - 8 * k; wy += 16 * k) {
          for (let wx = tx + 6 * k; wx < tx + tw - 6 * k; wx += 12 * k) if (rnd() < 0.28) g.fillRect(wx, wy, Math.max(1, 4 * k), Math.max(1, 6 * k));
        }
      }
    }

    // Land
    if (!has.water || has.mountain || has.nature) {
      ridge(g, rnd, W, horizon - 10 * k, (has.mountain ? 260 : 150) * k, hexToRgba(pal.sky[1], 0.9), 1.2);
      const mist = g.createLinearGradient(0, horizon - 120 * k, 0, horizon + 40 * k);
      mist.addColorStop(0, hexToRgba(pal.accent, 0));
      mist.addColorStop(1, hexToRgba(pal.accent, 0.18));
      g.fillStyle = mist;
      g.fillRect(0, horizon - 120 * k, W, 160 * k);
      ridge(g, rnd, W, horizon + 40 * k, (has.mountain ? 180 : 110) * k, pal.land, 1);
    }

    // Water with the orb's reflection
    if (has.water) {
      const sea = g.createLinearGradient(0, horizon, 0, H);
      sea.addColorStop(0, hexToRgba(pal.sky[2], 0.9));
      sea.addColorStop(0.4, pal.sky[1]);
      sea.addColorStop(1, pal.sky[0]);
      g.fillStyle = sea;
      g.fillRect(0, horizon, W, H - horizon);
      for (let y = horizon + 6 * k; y < H; y += Math.max(1.5, 5 * k) + (y - horizon) * 0.03) {
        const spread = 30 * k + (y - horizon) * 0.55;
        const width = spread * (0.4 + rnd() * 0.9);
        g.globalAlpha = Math.max(0, 0.75 - (y - horizon) / (H - horizon));
        g.fillStyle = pal.glow;
        g.fillRect(ox - width / 2 + (rnd() - 0.5) * spread * 0.6, y, width, Math.max(0.8, 1.6 * k));
      }
      g.globalAlpha = 0.18;
      g.strokeStyle = '#ffffff';
      g.lineWidth = 1;
      for (let i = 0; i < 70; i++) {
        const wy = horizon + 10 * k + rnd() * (H - horizon);
        const wx = rnd() * W, len = (20 + rnd() * 90) * k;
        g.beginPath(); g.moveTo(wx, wy); g.quadraticCurveTo(wx + len / 2, wy - 3 * k, wx + len, wy); g.stroke();
      }
      g.globalAlpha = 1;
    }

    // A forest on the nearest ridge
    if (has.nature) {
      g.fillStyle = '#05050f';
      for (let i = 0; i < 90; i++) {
        const tx = rnd() * W, th = (40 + rnd() * 120) * k, ty = H - rnd() * (H - horizon) * 0.5;
        g.beginPath(); g.moveTo(tx, ty - th); g.lineTo(tx - th * 0.22, ty); g.lineTo(tx + th * 0.22, ty); g.closePath(); g.fill();
      }
    }

    // A glowing doorway
    if (has.door) {
      const dw = (90 + rnd() * 50) * k, dh = dw * 1.9, dx = W * (0.2 + rnd() * 0.2), dy = horizon + (has.water ? 30 : 60) * k - dh;
      const doorGlow = g.createRadialGradient(dx + dw / 2, dy + dh / 2, 10 * k, dx + dw / 2, dy + dh / 2, dh * 1.1);
      doorGlow.addColorStop(0, hexToRgba(pal.glow, 0.5));
      doorGlow.addColorStop(1, hexToRgba(pal.glow, 0));
      g.fillStyle = doorGlow;
      g.fillRect(dx - dh, dy - dh / 2, dw + dh * 2, dh * 2);
      g.fillStyle = hexToRgba('#fff6e6', 0.92);
      g.beginPath();
      g.moveTo(dx, dy + dh); g.lineTo(dx, dy + dw / 2);
      g.arc(dx + dw / 2, dy + dw / 2, dw / 2, Math.PI, 0);
      g.lineTo(dx + dw, dy + dh); g.closePath(); g.fill();
    }

    // Rising embers or drifting light
    const embers = Math.round((has.fire ? 160 : 90) * Math.max(k, 0.35));
    for (let i = 0; i < embers; i++) {
      const x = rnd() * W, y = rnd() * H, r = (1 + rnd() * 3.2) * Math.max(k, 0.5);
      const color = has.fire ? '#ff9a5a' : pal.glow;
      const spark = g.createRadialGradient(x, y, 0, x, y, r * 4);
      spark.addColorStop(0, hexToRgba(color, 0.55 + rnd() * 0.35));
      spark.addColorStop(1, hexToRgba(color, 0));
      g.fillStyle = spark;
      g.beginPath(); g.arc(x, y, r * 4, 0, Math.PI * 2); g.fill();
    }

    // Film grain and vignette
    const grain = document.createElement('canvas');
    grain.width = grain.height = 128;
    const gg = grain.getContext('2d');
    const noise = gg.createImageData(128, 128);
    for (let i = 0; i < noise.data.length; i += 4) {
      const v = rnd() * 255;
      noise.data[i] = noise.data[i + 1] = noise.data[i + 2] = v;
      noise.data[i + 3] = 18;
    }
    gg.putImageData(noise, 0, 0);
    g.fillStyle = g.createPattern(grain, 'repeat');
    g.fillRect(0, 0, W, H);
    const vignette = g.createRadialGradient(W / 2, H / 2, H * 0.35, W / 2, H / 2, H * 0.95);
    vignette.addColorStop(0, 'rgba(0,0,0,0)');
    vignette.addColorStop(1, 'rgba(2,3,10,0.72)');
    g.fillStyle = vignette;
    g.fillRect(0, 0, W, H);

    return canvas;
  }

  window.DreamCanvas = {
    async paint(options) {
      await new Promise(r => setTimeout(r, motionAllowed() ? 650 : 30));  // let the "surfacing" state render
      const canvas = paintCanvas(options);
      const blob = await new Promise((resolve, reject) =>
        canvas.toBlob(b => (b ? resolve(b) : reject(new Error('Canvas export failed'))), 'image/jpeg', 0.9));
      return new File([blob], 'dream-canvas.jpg', { type: 'image/jpeg' });
    },
    /** A painting of any size as a canvas, for share cards, HD downloads and the Dream Book. */
    render(options, width = 1536, height = 1024) {
      return paintCanvas(options, width, height);
    },
    /** A cached painting of a saved dream, as a data URL. */
    painting(dream, width = 480, height = 320) {
      return dreamPainting(dream, width, height);
    },
  };

  // Every dream without its own image gets a small painting of itself
  const thumbCache = new Map();
  function dreamPainting(dream, width = 480, height = 320) {
    const key = `${dream.id}:${width}:${hashString((dream.title || '') + (dream.content || '') + (dream.emotions || []).join())}`;
    if (!thumbCache.has(key)) {
      const canvas = paintCanvas({
        text: `${dream.title || ''} ${dream.content || ''}`,
        emotions: dream.emotions || [],
        answers: dream.themes || [],
      }, width, height);
      thumbCache.set(key, canvas.toDataURL('image/jpeg', 0.84));
    }
    return thumbCache.get(key);
  }

  const idle = window.requestIdleCallback || (fn => setTimeout(fn, 16));
  let painting = false;
  function paintJournalThumbs() {
    if (painting) return;
    painting = true;
    const step = () => {
      const slot = document.querySelector('.journal-img[data-thumb]:not(.painted)');
      if (!slot) { painting = false; return; }
      slot.classList.add('painted');
      const dream = dreamJournal.find(d => d.id === slot.dataset.thumb);
      if (dream) {
        const img = new Image();
        img.alt = '';
        img.className = 'thumb-painted';
        img.src = dreamPainting(dream);
        slot.replaceChildren(img);
      }
      idle(step);
    };
    idle(step);
  }
  const journalList = document.getElementById('journal-list');
  if (journalList) new MutationObserver(paintJournalThumbs).observe(journalList, { childList: true });

  // ════════════════════════════════════════════════════════════
  // DIALOGS — dream details and platform information
  // ════════════════════════════════════════════════════════════
  function openDialog(dialog) {
    if (dialog.open) return;
    if (typeof dialog.showModal === 'function') dialog.showModal();
    else dialog.setAttribute('open', '');
  }

  function closeDialog(dialog) {
    if (typeof dialog.close === 'function') dialog.close();
    else dialog.removeAttribute('open');
  }

  document.querySelectorAll('dialog.dream-dialog').forEach(dialog => {
    dialog.addEventListener('click', e => { if (e.target === dialog) closeDialog(dialog); });
  });

  const INFO = {
    privacy: `
      <span class="eyebrow">YOUR PRIVACY</span>
      <h2>Your dreams belong to you.</h2>
      <p>Oneiros is a journal first. Here is exactly what happens with what you share.</p>
      <h3>What we keep</h3>
      <ul>
        <li>Your email address and password (stored as a secure one-way hash) so you can sign in.</li>
        <li>Your date of birth, used only to confirm you are 18 or older.</li>
        <li>An optional display name and country, which you can change at any time.</li>
        <li>The dreams you save, and any image or voice recording you attach to them.</li>
      </ul>
      <h3>Who can see a dream</h3>
      <ul>
        <li><strong>Private</strong>: only you. Never matched, never shown to anyone.</li>
        <li><strong>Public</strong>: compared with other public dreams to find resonances. Matched dreamers see an anonymous preview (title, an excerpt, themes and your country), never your name or email.</li>
        <li><strong>Research</strong>: counted only in anonymous, aggregate statistics.</li>
      </ul>
      <h3>Connections</h3>
      <p>A conversation begins only when both dreamers agree. Connected dreamers see each other's display name and country, never an email address. You can block anyone at any time.</p>
      <h3>Your controls</h3>
      <ul>
        <li>Edit, change the visibility of, or delete any dream from your journal.</li>
        <li>Export your journal as a spreadsheet from Profile &amp; Settings.</li>
        <li>Delete your account, which removes your dreams, images, recordings, matches and conversations.</li>
      </ul>
      <p>Images and recordings are delivered through private, short-lived links. A Dream Canvas is painted in your own browser.</p>
      <h3>Payments</h3>
      <p>Lucid passes are paid through PayFast or Paystack. Your card or bank details go straight to them and never reach Oneiros; we keep only the order record (what you bought, the amount, the date and a reference) for your receipts and our accounts.</p>`,
    terms: `
      <span class="eyebrow">TERMS OF USE</span>
      <h2>A gentle agreement.</h2>
      <p>By creating an account you agree to the following, written plainly.</p>
      <ul>
        <li>You are 18 or older, and the account is yours alone.</li>
        <li>Your words stay yours. You allow Oneiros to store them and to show them only as your visibility settings allow.</li>
        <li>Be kind. No harassment, hate, threats, sexual content involving minors, or sharing someone else's private information.</li>
        <li>Avoid including names, addresses or other identifying details in public dreams.</li>
        <li>Moderators may hide content or suspend accounts that break these terms. You can report a dream or block a dreamer at any time.</li>
        <li>Oneiros offers reflection and connection, not medical or psychological advice. If your dreams trouble you, please speak to a qualified professional.</li>
        <li>The service is provided as it is, and features may change as it grows.</li>
      </ul>
      <h3>Lucid passes</h3>
      <ul>
        <li>Lucid is sold as once-off passes for a set number of nights. A pass is never a subscription: it does not renew and you are never charged again unless you choose to buy another.</li>
        <li>A pass begins as soon as your payment clears and ends on the date shown in your profile. Buying another pass adds its nights to the time you have left.</li>
        <li>Gift codes and promotional codes can be redeemed once per account, unless stated otherwise, and cannot be exchanged for money.</li>
        <li>If something goes wrong with a purchase, contact us using the email on your receipt within 7 days. Refunds are considered case by case, and always where the law requires them.</li>
        <li>Your journal and free features stay yours when a pass ends.</li>
      </ul>`,
  };

  window.showInfo = function (kind) {
    const dialog = document.getElementById('info-dialog');
    document.getElementById('info-content').innerHTML = INFO[kind] || INFO.privacy;
    openDialog(dialog);
    dialog.scrollTop = 0;
  };

  const PRIVACY_LABEL = { public: 'Public', private: 'Private', research_only: 'Research' };
  let openDreamId = null;

  window.openDream = async function (dreamId) {
    const dialog = document.getElementById('dream-dialog');
    const detail = document.getElementById('dream-detail');
    openDreamId = dreamId;
    currentDreamId = dreamId;
    detail.innerHTML = '<div class="empty-state"><span class="empty-symbol">◌</span>Returning to this dream…</div>';
    openDialog(dialog);
    try {
      const dream = await api('GET', `/dreams/${dreamId}`);
      if (!dream || openDreamId !== dreamId) return;
      renderDreamDetail(dream);
    } catch {
      detail.innerHTML = '<div class="empty-state"><span class="empty-symbol">☾</span><h3>This dream could not be opened.</h3>Please try again in a moment.</div>';
    }
  };

  function renderDreamDetail(dream) {
    const date = new Date(dream.dreamed_at).toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    const tags = [...new Set([...(dream.emotions || []), ...(dream.themes || []), ...(dream.symbols || [])])].slice(0, 12);
    const meta = [
      dream.privacy === 'public' ? `✨ ${dream.match_count || 0} resonance${dream.match_count === 1 ? '' : 's'}` : null,
      dream.is_recurring ? '🔁 Recurring' : null,
      dream.narrative_arc ? `Arc: ${escHtml(dream.narrative_arc)}` : null,
      dream.word_count ? `${dream.word_count} words` : null,
    ].filter(Boolean).join(' · ');
    document.getElementById('dream-detail').innerHTML = `
      <span class="eyebrow">${escHtml(date.toUpperCase())} · ${PRIVACY_LABEL[dream.privacy] || 'Private'}</span>
      <h2>${escHtml(dream.title || 'Untitled dream')}</h2>
      ${dream.image_url
        ? `<img class="dream-detail-image" src="${escHtml(dream.image_url)}" alt="Image attached to this dream">`
        : `<img class="dream-detail-image is-painted" src="${dreamPainting(dream, 960, 640)}" alt="A painting of this dream">`}
      ${dream.audio_url ? `<audio class="dream-detail-audio" controls preload="none" src="${escHtml(dream.audio_url)}"></audio>` : ''}
      ${meta ? `<p class="detail-meta">${meta}</p>` : ''}
      ${tags.length ? `<div class="detail-tags">${tags.map(t => `<span class="journal-tag">${escHtml(t)}</span>`).join('')}</div>` : ''}
      <div class="detail-fields">
        <label>Title<input id="detail-title" maxlength="200" value="${escHtml(dream.title || '')}" placeholder="Give this dream a name"></label>
        <label>What you remember<textarea id="detail-content" maxlength="10000">${escHtml(dream.content || '')}</textarea></label>
        <label>Visibility<select id="detail-privacy">
          ${Object.entries({ private: 'Private · only me', public: 'Public · find connections', research_only: 'Research · aggregate insights' })
            .map(([value, label]) => `<option value="${value}" ${dream.privacy === value ? 'selected' : ''}>${label}</option>`).join('')}
        </select></label>
      </div>
      <div class="detail-actions">
        <button class="btn-primary" id="detail-save" onclick="saveDreamDetail('${dream.id}')">Save changes <span>↗</span></button>
        <button class="btn-ghost" onclick="showDreamResonances('${dream.id}')">See resonances</button>
        <label class="btn-ghost detail-upload">${dream.image_url ? 'Replace image' : 'Add an image'}
          <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" hidden onchange="attachDreamImage('${dream.id}', this)"></label>
        <button class="danger-btn" onclick="deleteDreamFromDetail('${dream.id}')">Delete</button>
      </div>`;
    document.getElementById('dream-detail').dataset.original = JSON.stringify({ title: dream.title || '', content: dream.content || '', privacy: dream.privacy });
    document.dispatchEvent(new CustomEvent('oneiros:dream-detail', { detail: dream }));
  }

  function upsertJournalDream(dream) {
    const index = dreamJournal.findIndex(d => d.id === dream.id);
    if (index >= 0) dreamJournal[index] = dream;
    filterJournal();
  }

  window.saveDreamDetail = async function (dreamId) {
    const original = JSON.parse(document.getElementById('dream-detail').dataset.original || '{}');
    const next = {
      title: document.getElementById('detail-title').value.trim(),
      content: document.getElementById('detail-content').value.trim(),
      privacy: document.getElementById('detail-privacy').value,
    };
    const changes = Object.fromEntries(Object.entries(next).filter(([key, value]) => value !== original[key]));
    if (!Object.keys(changes).length) { showToast('Nothing has changed'); return; }
    if ('content' in changes && changes.content.length < 10) { showToast('✍️ A dream needs at least a few words', 'error'); return; }
    setButtonLoading('detail-save', true);
    try {
      const updated = await api('PATCH', `/dreams/${dreamId}`, changes);
      if (!updated) return;
      upsertJournalDream(updated);
      renderDreamDetail(updated);
      showToast(changes.privacy === 'public' ? '✨ Saved · Looking for resonances' : '✓ Dream updated');
    } catch {} finally {
      setButtonLoading('detail-save', false);
    }
  };

  window.showDreamResonances = function (dreamId) {
    closeDreamDialog();
    goTo('matches');
    loadMatchesForDream(dreamId);
  };

  window.attachDreamImage = async function (dreamId, input) {
    const file = input.files?.[0];
    if (!file) return;
    if (file.size > (appCapabilities.max_file_size || 10 * 1024 * 1024)) { showToast('⚠️ Image must be under 10MB', 'error'); return; }
    showToast('🖼️ Attaching your image…');
    const url = await uploadImageToSupabase(dreamId, file);
    if (!url) { showToast('The image could not be attached. Please try a JPG, PNG or WEBP under 10MB.', 'error'); return; }
    showToast('✓ Image attached');
    const dream = await api('GET', `/dreams/${dreamId}`).catch(() => null);
    if (dream) { upsertJournalDream(dream); renderDreamDetail(dream); }
  };

  window.deleteDreamFromDetail = async function (dreamId) {
    if (!confirm('Delete this dream forever? Its image, recording and resonances will be removed too.')) return;
    try {
      await api('DELETE', `/dreams/${dreamId}`);
      dreamJournal = dreamJournal.filter(d => d.id !== dreamId);
      filterJournal();
      closeDreamDialog();
      showToast('Dream released');
      if (document.getElementById('page-journal').classList.contains('active')) loadJournal();
    } catch {}
  };

  window.closeDreamDialog = function () {
    openDreamId = null;
    closeDialog(document.getElementById('dream-dialog'));
  };

  // ════════════════════════════════════════════════════════════
  // INSIGHTS — patterns in the quiet
  // ════════════════════════════════════════════════════════════
  function countValues(dreams, key) {
    const counts = {};
    for (const dream of dreams) for (const value of dream[key] || []) counts[value] = (counts[value] || 0) + 1;
    return Object.entries(counts).sort((a, b) => b[1] - a[1]);
  }

  function bars(entries, emptyText) {
    if (!entries.length) return `<p class="insight-empty">${emptyText}</p>`;
    const max = Math.max(...entries.map(([, n]) => n), 1);
    return entries.map(([label, n]) => `
      <div class="insight-bar">
        <div><span>${escHtml(label.charAt(0).toUpperCase() + label.slice(1))}</span><span>${n}</span></div>
        <div class="insight-track"><span data-width="${Math.max(4, Math.round((n / max) * 100))}"></span></div>
      </div>`).join('');
  }

  window.loadInsights = async function () {
    const box = document.getElementById('insights-content');
    if (!box) return;
    box.innerHTML = '<div class="empty-state"><span class="empty-symbol">◌</span>Reading the patterns in your nights…</div>';
    const [me, collective, journal] = await Promise.all([
      api('GET', '/research/me').catch(() => null),
      api('GET', '/research/global').catch(() => null),
      api('GET', '/dreams?limit=50').catch(() => null),
    ]);
    const dreams = journal?.dreams || [];
    if (journal) dreamJournal = dreams;
    if (!dreams.length) {
      box.innerHTML = `<div class="empty-state"><span class="empty-symbol">✦</span><h3>Your patterns begin with one dream.</h3>
        Save a few dreams and this space will start to reflect your emotions, recurring themes and rhythms.
        <br><button class="btn-primary" onclick="goTo('dashboard')">Remember a dream <span>↗</span></button></div>`;
      return;
    }

    const themes = countValues(dreams, 'themes').slice(0, 6);
    const symbols = countValues(dreams, 'symbols').slice(0, 10);
    const emotions = (me?.top_emotions || []).map(e => [e.emotion, e.count]);
    const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    const byDay = weekdays.map(day => [day, 0]);
    dreams.forEach(d => { const i = new Date(d.dreamed_at).getDay(); if (!Number.isNaN(i)) byDay[i][1]++; });
    const words = dreams.map(d => d.word_count || (d.content || '').split(/\s+/).filter(Boolean).length);
    const avgWords = Math.round(words.reduce((a, b) => a + b, 0) / words.length);
    const publicShare = Math.round(dreams.filter(d => d.privacy === 'public').length / dreams.length * 100);
    const collectiveThemes = (collective?.top_themes || []).slice(0, 6).map(t => [t.theme, t.count]);
    const shared = themes.filter(([t]) => collectiveThemes.some(([c]) => c === t)).map(([t]) => t);

    box.innerHTML = `
      <section class="insight-panel insight-summary">
        <div class="insight-stat"><strong>${me?.total_dreams ?? dreams.length}</strong><span>dreams remembered</span></div>
        <div class="insight-stat"><strong>${me?.total_matches ?? 0}</strong><span>resonances found</span></div>
        <div class="insight-stat"><strong>${me?.recurring_dreams ?? 0}</strong><span>recurring dreams</span></div>
        <div class="insight-stat"><strong>${avgWords}</strong><span>words per dream</span></div>
      </section>
      <section class="insight-panel">
        <span class="eyebrow">EMOTIONAL PALETTE</span>
        <h2>How your nights <em>feel.</em></h2>
        <p>The feelings you name most often when you wake.</p>
        ${bars(emotions, 'Choose how a dream felt when you save it, and your palette will appear here.')}
      </section>
      <section class="insight-panel">
        <span class="eyebrow">RETURNING THEMES</span>
        <h2>What keeps <em>coming back.</em></h2>
        <p>Themes found across your most recent dreams.</p>
        ${bars(themes, 'Themes appear as your dreams grow in detail.')}
      </section>
      <section class="insight-panel">
        <span class="eyebrow">YOUR RHYTHM</span>
        <h2>When you <em>remember.</em></h2>
        <p>Dreams by the night they happened.</p>
        ${bars(byDay.filter(([, n]) => n > 0), 'No rhythm yet.')}
      </section>
      <section class="insight-panel">
        <span class="eyebrow">THE COLLECTIVE, THIS WEEK</span>
        <h2>What the world is <em>dreaming.</em></h2>
        <p>${shared.length ? `You share <strong>${shared.map(escHtml).join(', ')}</strong> with dreamers around the world.` : 'Anonymous themes from public and research dreams worldwide.'}</p>
        ${bars(collectiveThemes, 'The collective is quiet this week.')}
      </section>
      ${symbols.length ? `<section class="insight-panel insight-wide">
        <span class="eyebrow">SYMBOLS</span>
        <h2>Images your mind <em>returns to.</em></h2>
        <div class="insight-chips">${symbols.map(([s, n]) => `<span class="journal-tag">${escHtml(s)} · ${n}</span>`).join('')}</div>
      </section>` : ''}
      <p class="insights-note">Drawn from your ${dreams.length} most recent dream${dreams.length === 1 ? '' : 's'} · ${publicShare}% shared publicly. Collective insights are anonymous and aggregated.</p>`;

    document.dispatchEvent(new CustomEvent('oneiros:insights', { detail: { dreams, me, collective, box } }));
    requestAnimationFrame(() => requestAnimationFrame(() => {
      box.querySelectorAll('.insight-track span').forEach(bar => { bar.style.width = bar.dataset.width + '%'; });
    }));
  };

  // ════════════════════════════════════════════════════════════
  // DRAFTS — nothing you remember is lost to a closed tab
  // ════════════════════════════════════════════════════════════
  const draftState = document.getElementById('draft-state');
  const dreamText = document.getElementById('dream-text');
  const dreamTitle = document.getElementById('dream-title');
  const draftKey = () => (currentUser ? 'oneiros_draft_' + currentUser.id : null);
  let draftTimer = null, stateTimer = null;

  function setDraftState(text, settleAfter) {
    if (!draftState) return;
    draftState.textContent = text;
    clearTimeout(stateTimer);
    if (settleAfter) stateTimer = setTimeout(() => { draftState.textContent = 'A space to remember'; }, settleAfter);
  }

  function saveDraft() {
    const key = draftKey();
    if (!key) return;
    const title = dreamTitle.value, text = dreamText.value;
    if (!title.trim() && !text.trim()) { store.remove(key); setDraftState('A space to remember'); return; }
    store.set(key, JSON.stringify({ title, text, at: Date.now() }));
    setDraftState('Draft kept on this device · ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }));
  }

  function restoreDraft() {
    const key = draftKey();
    if (!key || dreamText.value.trim() || dreamTitle.value.trim()) return;
    try {
      const draft = JSON.parse(store.get(key) || 'null');
      if (!draft || Date.now() - draft.at > 14 * 864e5) return;
      dreamTitle.value = draft.title || '';
      dreamText.value = draft.text || '';
      if (draft.text || draft.title) setDraftState('Your unfinished dream is waiting', 6000);
    } catch {}
  }

  [dreamText, dreamTitle].forEach(el => el?.addEventListener('input', () => {
    clearTimeout(draftTimer);
    draftTimer = setTimeout(saveDraft, 600);
  }));

  document.addEventListener('oneiros:dream-saved', () => setDraftState('Saved to your journal ✦', 5000));
  document.addEventListener('oneiros:signed-out', () => {
    try { Object.keys(localStorage).filter(k => k.startsWith('oneiros_draft_')).forEach(k => store.remove(k)); } catch {}
    if (dreamText) dreamText.value = '';
    if (dreamTitle) dreamTitle.value = '';
    setDraftState('A space to remember');
  });

  // ════════════════════════════════════════════════════════════
  // NAVIGATION HOOKS AND KEYBOARD RITUALS
  // ════════════════════════════════════════════════════════════
  const baseGoTo = window.goTo;
  window.goTo = function (page) {
    if (!document.getElementById('page-' + page)) return;
    baseGoTo(page);
    document.body.dataset.view = page;
    closeMobileMenu();
    if (page === 'insights' && currentUser) loadInsights();
    if (page === 'dashboard') restoreDraft();
    if (page === 'landing') particles.start(); else particles.stop();
    observeReveals();
    queueParallax();
  };

  const baseOpenModal = window.openModal;
  window.openModal = function (type) {
    baseOpenModal(type);
    setTimeout(() => {
      // Never pull focus away from a field the dreamer (or their password manager) is already using
      if (document.getElementById('modal-box')?.contains(document.activeElement)) return;
      document.getElementById(type === 'login' ? 'login-email' : 'reg-email')?.focus();
    }, 60);
  };

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      if (document.getElementById('modal-overlay')?.classList.contains('open')) closeModal();
      closeNotif();
    }
    if (e.key === 'Enter' && (e.metaKey || e.ctrlKey) && e.target === dreamText) {
      e.preventDefault();
      submitDream();
    }
    if (e.key === 'Enter' && !e.shiftKey) {
      if (e.target.id === 'login-email') { e.preventDefault(); document.getElementById('login-password')?.focus(); }
      else if (e.target.id === 'login-password') { e.preventDefault(); login(); }
    }
  });

  observeReveals();
  if (onLanding()) particles.start();
})();
