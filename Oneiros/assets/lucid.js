// ════════════════════════════════════════════════════════════
// Oneiros — Lucid
// Once-off passes (never recurring), gifts, codes and invitations, plus the
// rituals around them: wind-down soundscapes, breathing, intentions, symbol
// reflections, deep insights, the journal calendar, share cards and the Dream Book.
// Loaded after app.js and experience.js; shares their globals.
// ════════════════════════════════════════════════════════════

(() => {
  const store = {
    get(key) { try { return localStorage.getItem(key); } catch { return null; } },
    set(key, value) { try { localStorage.setItem(key, value); } catch {} },
    remove(key) { try { localStorage.removeItem(key); } catch {} },
    session(key, value) {
      try { if (value === undefined) return sessionStorage.getItem(key); if (value === null) sessionStorage.removeItem(key); else sessionStorage.setItem(key, value); } catch { return null; }
    },
  };
  const isLucid = () => !!currentUser?.is_premium;
  const esc = value => escHtml(String(value ?? ''));
  const fmtDate = (iso, opts = { day: 'numeric', month: 'long', year: 'numeric' }) => iso ? new Date(iso).toLocaleDateString(undefined, opts) : '';
  const daysLeft = () => currentUser?.premium_until ? Math.max(0, Math.ceil((new Date(currentUser.premium_until) - Date.now()) / 864e5)) : 0;
  const localDay = (offset = 0) => { const d = new Date(); d.setDate(d.getDate() + offset); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`; };

  let pricing = null;
  let membership = null;

  async function loadPricing() {
    if (pricing) return pricing;
    try {
      const res = await fetch(buildApiUrl('/payments/plans'));
      if (res.ok) pricing = await res.json();
    } catch {}
    return pricing;
  }

  async function refreshMembership() {
    if (!currentUser) return null;
    try { membership = await api('GET', '/payments/status'); } catch { return null; }
    currentUser.is_premium = membership.is_premium;
    currentUser.premium_until = membership.premium_until;
    currentUser.is_patron = membership.is_patron;
    applyLucidState();
    return membership;
  }

  // ════════════════════════════════════════════════════════════
  // LUCID STATE ACROSS THE APP
  // ════════════════════════════════════════════════════════════
  function applyLucidState() {
    const lucid = isLucid();
    document.body.classList.toggle('is-lucid', lucid);
    document.body.classList.toggle('is-patron', !!currentUser?.is_patron);
    const chip = document.getElementById('lucid-chip');
    if (chip) chip.hidden = !currentUser || lucid;
    document.querySelectorAll('.sidebar-lucid-label').forEach(el => {
      el.textContent = lucid ? `Lucid · ${daysLeft()} night${daysLeft() === 1 ? '' : 's'}` : 'Lucid';
    });
    applyTheme();
    renderDashSlot();
    if (document.getElementById('page-profile')?.classList.contains('active')) renderMembership(false);
  }

  // ════════════════════════════════════════════════════════════
  // THE LUCID DIALOG — passes, gifts, codes, support
  // ════════════════════════════════════════════════════════════
  const HEADLINES = {
    matches: ['Meet every dreamer', 'who shared your night.'],
    connections: ['Reach out to every', 'resonant dreamer.'],
    insights: ['See the deeper', 'patterns in your nights.'],
    reflections: ['Every symbol,', 'gently reflected.'],
    sounds: ['The full wind-down', 'library is waiting.'],
    book: ['Keep your dreams', 'in a book of their own.'],
    hd: ['Your paintings,', 'in full resolution.'],
    themes: ['Dream in', 'another colour.'],
    practice: ['Practise', 'lucid dreaming.'],
    paint: ['More AI paintings,', 'every day.'],
    gift: ['Give someone', 'a month of dreams.'],
    support: ['Leave a little', 'light behind.'],
  };
  let dialogState = { tab: 'plans', plan: null, code: '', quote: null, provider: null, reason: '' };

  function ensureDialog() {
    let dialog = document.getElementById('lucid-dialog');
    if (dialog) return dialog;
    dialog = document.createElement('dialog');
    dialog.id = 'lucid-dialog';
    dialog.className = 'dream-dialog lucid-dialog';
    dialog.setAttribute('aria-label', 'Oneiros Lucid');
    dialog.addEventListener('click', e => { if (e.target === dialog) dialog.close(); });
    document.body.appendChild(dialog);
    return dialog;
  }

  window.openLucid = async function (reason = '', tab = null, prefill = {}) {
    if (!currentUser) {
      store.session('oneiros_after_login', JSON.stringify({ lucid: true, reason, tab, prefill }));
      store.session('oneiros_suppress_tour', '1');
      openModal('register');
      showToast('Create your free journal first, then choose your pass.');
      return;
    }
    const dialog = ensureDialog();
    dialogState = { tab: tab || (reason === 'gift' ? 'gift' : reason === 'support' ? 'support' : 'plans'), plan: prefill.plan || null, code: prefill.code || '', quote: null, provider: null, reason };
    dialog.innerHTML = '<div class="empty-state"><span class="empty-symbol">✦</span>Opening Lucid…</div>';
    if (!dialog.open) dialog.showModal();
    await loadPricing();
    if (!membership) refreshMembership().then(() => dialog.open && renderDialog());
    renderDialog();
  };

  function planCards(plans, selectable = true) {
    return `<div class="lucid-plan-grid" role="radiogroup" aria-label="Choose a pass">${plans.map(plan => `
      <button type="button" class="lucid-plan ${plan.featured ? 'featured' : ''} ${dialogState.plan === plan.id ? 'selected' : ''}"
        role="radio" aria-checked="${dialogState.plan === plan.id}" ${selectable ? `onclick="lucidSelectPlan('${plan.id}')"` : ''}>
        ${plan.featured ? '<span class="lucid-plan-badge">Most loved</span>' : plan.saving_percent >= 15 ? `<span class="lucid-plan-badge quiet">${plan.saving_percent}% less a night</span>` : ''}
        <span class="lucid-plan-name">${esc(plan.name)}</span>
        <span class="lucid-plan-price">${esc(plan.price)}</span>
        <span class="lucid-plan-days">${plan.days} nights · ${esc(plan.per_night)} a night</span>
      </button>`).join('')}</div>`;
  }

  function renderDialog() {
    const dialog = document.getElementById('lucid-dialog');
    if (!dialog) return;
    const p = pricing;
    const providers = p?.providers || [];
    const plans = p?.plans || [];
    if (!dialogState.plan) dialogState.plan = (plans.find(pl => pl.featured) || plans[0])?.id || null;
    const [line1, line2] = HEADLINES[dialogState.reason] || ['Go deeper', 'into your dreams.'];
    const lucid = isLucid();
    const tabs = [['plans', 'Choose a pass'], ['gift', 'Give as a gift'], ['redeem', 'Redeem a code'], ['support', 'Support']];
    const selected = plans.find(pl => pl.id === dialogState.plan);
    const price = dialogState.quote?.plan_id === dialogState.plan ? dialogState.quote : null;
    const providerNames = { payfast: 'PayFast · card, Instant EFT, SnapScan, Zapper', paystack: 'Paystack · card and Apple Pay' };
    if (!dialogState.provider) dialogState.provider = providers[0] || null;

    let body = '';
    if (dialogState.tab === 'plans' || dialogState.tab === 'gift') {
      const gift = dialogState.tab === 'gift';
      body = providers.length ? `
        ${gift ? '<p class="lucid-lead">Choose a pass. After paying you receive a gift code to share, and we can email it for you too.</p>' : ''}
        ${planCards(plans)}
        ${gift ? `<label class="lucid-field">Their email (optional)<input type="email" id="lucid-gift-email" placeholder="friend@example.com" autocomplete="off"></label>` : ''}
        <div class="lucid-code-row">
          <input id="lucid-code" placeholder="Discount code" value="${esc(dialogState.code)}" autocomplete="off" aria-label="Discount code">
          <button type="button" class="btn-ghost" onclick="lucidApplyCode()">Apply</button>
        </div>
        ${price?.discount_cents ? `<p class="lucid-discount">✦ ${price.percent_off}% off with ${esc(price.code)} · you save ${esc(price.discount)}</p>` : ''}
        ${providers.length > 1 ? `<div class="lucid-providers">${providers.map(pr => `
          <label><input type="radio" name="lucid-provider" value="${pr}" ${dialogState.provider === pr ? 'checked' : ''} onchange="lucidSetProvider('${pr}')"> ${esc(providerNames[pr] || pr)}</label>`).join('')}</div>` : ''}
        <button type="button" class="btn-primary lucid-cta" id="lucid-pay" onclick="lucidCheckout(${gift})" ${selected ? '' : 'disabled'}>
          ${gift ? 'Buy this gift' : lucid ? 'Add these nights' : 'Open my Lucid pass'} · ${esc(price?.amount || selected?.price || '')} <span>↗</span>
        </button>
        <p class="lucid-fineprint">Sold by ${esc(p.business?.name || 'Oneiros')}${p.business?.details ? ` · ${esc(p.business.details)}` : ''}${p.business?.email ? ` · ${esc(p.business.email)}` : ''}. Once-off payment in ${esc(p.currency)} through ${esc(providers.length === 1 ? (providerNames[providers[0]] || providers[0]).split(' · ')[0] : 'a secure payment page')}. ${gift ? 'The gift code never expires until it is used.' : 'Your nights begin as soon as payment clears and simply end when the pass is complete. Nothing renews.'}
          <button class="text-link" onclick="showInfo('terms')">Terms</button> · <button class="text-link" onclick="showInfo('privacy')">Privacy</button></p>`
      : `<div class="lucid-soon"><span class="empty-symbol">✦</span><h3>Lucid passes open soon.</h3><p>Until then, redeem a gift or invitation code below, or invite a friend: you both receive ${p?.referral_reward_days || 7} Lucid nights.</p>
          <button class="btn-ghost" onclick="lucidTab('redeem')">Redeem a code</button> <button class="btn-ghost" onclick="document.getElementById('lucid-dialog').close();goTo('profile')">Invite a friend</button></div>`;
    } else if (dialogState.tab === 'redeem') {
      body = `<p class="lucid-lead">Gift codes, invitations and promotional codes unlock Lucid nights instantly.</p>
        <div class="lucid-code-row big"><input id="lucid-redeem" placeholder="GIFT-XXXX-XXXX" value="${esc(dialogState.code)}" autocomplete="off" aria-label="Your code">
        <button type="button" class="btn-primary" onclick="lucidRedeem(document.getElementById('lucid-redeem').value)">Redeem <span>✦</span></button></div>
        <p class="lucid-fineprint">Discount codes are entered with a pass under “Choose a pass”.</p>`;
    } else if (dialogState.tab === 'support') {
      const support = p?.support || [];
      body = `<p class="lucid-lead">Oneiros is made by a small team and kept free for everyone. A once-off contribution keeps the lights on, and a Patron mark will glow beside your name.</p>
        ${providers.length && support.length ? `<div class="lucid-support-grid">${support.map(s => `
          <button type="button" class="lucid-plan ${dialogState.plan === s.id ? 'selected' : ''}" onclick="lucidSelectPlan('${s.id}')"><span class="lucid-plan-price">${esc(s.price)}</span><span class="lucid-plan-days">once-off</span></button>`).join('')}</div>
          <button type="button" class="btn-primary lucid-cta" onclick="lucidCheckout(false)" ${support.some(s => s.id === dialogState.plan) ? '' : 'disabled'}>Leave a little light <span>✦</span></button>
          <p class="lucid-fineprint">A once-off contribution. It does not add Lucid nights.</p>`
        : '<p class="lucid-fineprint">Online contributions open soon. Thank you for being here.</p>'}`;
    }

    dialog.innerHTML = `
      <button class="dialog-close" onclick="document.getElementById('lucid-dialog').close()" aria-label="Close">×</button>
      <div class="lucid-hero" aria-hidden="true"><span class="lucid-orb"></span><span class="lucid-orb two"></span></div>
      <span class="eyebrow">ONEIROS LUCID · ONCE-OFF, NEVER RECURRING</span>
      <h2>${esc(line1)}<br><em>${esc(line2)}</em></h2>
      ${lucid ? `<p class="lucid-status">✦ Your pass is open until <strong>${esc(fmtDate(currentUser.premium_until))}</strong> (${daysLeft()} nights). Anything you add extends it.</p>` : ''}
      <div class="lucid-tabs" role="tablist">${tabs.map(([id, label]) => `<button role="tab" aria-selected="${dialogState.tab === id}" class="${dialogState.tab === id ? 'active' : ''}" onclick="lucidTab('${id}')">${label}</button>`).join('')}</div>
      <div class="lucid-body">${body}</div>
      ${dialogState.tab === 'plans' ? `<ul class="lucid-perks">${(p?.perks || []).map(perk => `<li><span>${esc(perk.icon)}</span><div><strong>${esc(perk.title)}</strong>${esc(perk.text)}</div></li>`).join('')}</ul>
        <p class="lucid-fineprint">Free always includes your journal, Dream Canvas, matching with your closest ${p?.free?.visible_matches ?? 5} resonances, ${p?.free?.connection_requests_per_week ?? 3} connection requests a week, conversations and insights.</p>` : ''}`;
  }

  window.lucidTab = function (tab) {
    dialogState.tab = tab;
    if (tab === 'support') dialogState.plan = null;
    if ((tab === 'plans' || tab === 'gift') && !pricing?.plans?.some(pl => pl.id === dialogState.plan)) dialogState.plan = null;
    renderDialog();
    setTimeout(() => document.getElementById(tab === 'redeem' ? 'lucid-redeem' : 'lucid-pay')?.focus(), 30);
  };
  window.lucidSelectPlan = function (id) {
    dialogState.plan = id;
    if (dialogState.quote && dialogState.code) lucidApplyCode(true); else renderDialog();
  };
  window.lucidSetProvider = function (provider) { dialogState.provider = provider; };

  window.lucidApplyCode = async function (silent = false) {
    const input = document.getElementById('lucid-code');
    dialogState.code = (input?.value || dialogState.code || '').trim();
    dialogState.quote = null;
    if (!dialogState.code || !dialogState.plan) { renderDialog(); return; }
    try {
      dialogState.quote = await api('POST', '/payments/quote', { plan_id: dialogState.plan, code: dialogState.code });
      if (!silent) showToast(`✦ ${dialogState.quote.percent_off}% off applied`);
    } catch { dialogState.code = ''; }
    renderDialog();
  };

  window.lucidCheckout = async function (gift) {
    const btn = document.getElementById('lucid-pay') || document.querySelector('.lucid-cta');
    const recipient = document.getElementById('lucid-gift-email')?.value.trim() || '';
    const code = document.getElementById('lucid-code')?.value.trim() || dialogState.code || '';
    if (btn) { btn.disabled = true; btn.textContent = 'Opening secure payment…'; }
    try {
      const res = await api('POST', '/payments/checkout', { plan_id: dialogState.plan, gift, recipient_email: recipient, code, provider: dialogState.provider });
      store.session('oneiros_pending_order', res.order.id);
      if (res.redirect.method === 'POST') {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = res.redirect.action;
        Object.entries(res.redirect.fields).forEach(([name, value]) => {
          const input = document.createElement('input');
          input.type = 'hidden'; input.name = name; input.value = value;
          form.appendChild(input);
        });
        document.body.appendChild(form);
        form.submit();
      } else {
        location.href = res.redirect.action;
      }
    } catch {
      if (btn) { btn.disabled = false; renderDialog(); }
    }
  };

  window.lucidRedeem = async function (codeArg) {
    const code = String(codeArg || '').trim();
    if (!code) { showToast('Enter your code first', 'error'); return; }
    try {
      const res = await api('POST', '/payments/redeem', { code });
      Object.assign(currentUser, { is_premium: true, premium_until: res.membership.premium_until });
      refreshMembership();
      document.getElementById('lucid-dialog')?.close();
      celebrate(`${res.days} Lucid nights are yours`, `Your pass is open until ${fmtDate(res.membership.premium_until)}.`);
    } catch {}
  };

  // ════════════════════════════════════════════════════════════
  // RETURNING FROM PAYMENT, INVITATIONS AND GIFT LINKS
  // ════════════════════════════════════════════════════════════
  function celebrate(title, text, extra = '') {
    let dialog = document.getElementById('lucid-celebrate');
    if (!dialog) {
      dialog = document.createElement('dialog');
      dialog.id = 'lucid-celebrate';
      dialog.className = 'dream-dialog lucid-celebrate';
      document.body.appendChild(dialog);
      dialog.addEventListener('click', e => { if (e.target === dialog) dialog.close(); });
    }
    dialog.innerHTML = `
      <div class="celebrate-sky" aria-hidden="true">${'<i></i>'.repeat(18)}</div>
      <span class="celebrate-moon" aria-hidden="true"></span>
      <h2>${esc(title)}</h2><p>${esc(text)}</p>${extra}
      <button class="btn-primary" onclick="document.getElementById('lucid-celebrate').close()">Begin dreaming <span>✦</span></button>`;
    if (!dialog.open) dialog.showModal();
  }

  async function waitForPayment(orderId) {
    let dialog = document.getElementById('lucid-waiting');
    if (!dialog) {
      dialog = document.createElement('dialog');
      dialog.id = 'lucid-waiting';
      dialog.className = 'dream-dialog lucid-waiting';
      document.body.appendChild(dialog);
    }
    dialog.innerHTML = '<span class="celebrate-moon waxing" aria-hidden="true"></span><h2>Your pass is opening…</h2><p>Confirming your payment with the payment provider. This usually takes a few seconds.</p>';
    dialog.showModal();
    for (let attempt = 0; attempt < 30; attempt++) {
      try {
        const res = await api('GET', `/payments/order?id=${encodeURIComponent(orderId)}`);
        if (res.order.status === 'paid') {
          dialog.close();
          Object.assign(currentUser, { is_premium: res.membership.is_premium, premium_until: res.membership.premium_until, is_patron: res.membership.is_patron });
          refreshMembership();
          store.session('oneiros_pending_order', null);
          const o = res.order;
          if (o.kind === 'gift') {
            celebrate('Your gift is ready', `Share this code with someone special. It unlocks ${o.days} Lucid nights.`,
              `<div class="gift-code"><code>${esc(o.gift_code)}</code><button class="btn-ghost" onclick="lucidCopy('${esc(o.gift_code)}')">Copy</button>
               <button class="btn-ghost" onclick="lucidShareGift('${esc(o.gift_code)}', ${o.days})">Share</button></div>`);
          } else if (o.kind === 'support') {
            celebrate('Thank you for the light', 'Your support keeps Oneiros dreaming. A Patron mark now glows beside your name.');
          } else {
            celebrate('Your Lucid pass is open', `${o.days} nights have been added. Your pass is open until ${fmtDate(res.membership.premium_until)}, and nothing renews.`);
          }
          return;
        }
        if (['failed', 'cancelled'].includes(res.order.status)) {
          dialog.close();
          showToast('The payment did not go through. Nothing was charged.', 'error');
          return;
        }
      } catch { break; }
      await new Promise(r => setTimeout(r, 2000));
    }
    dialog.innerHTML = `<span class="celebrate-moon waxing" aria-hidden="true"></span><h2>Almost there</h2>
      <p>Your payment is still being confirmed. You can keep dreaming: we will let you know here as soon as it lands.</p>
      <button class="btn-primary" onclick="document.getElementById('lucid-waiting').close()">Continue</button>`;
  }

  function readUrlIntent() {
    const params = new URLSearchParams(location.search);
    const intent = { payment: params.get('payment'), order: params.get('order'), ref: params.get('ref'), redeem: params.get('redeem') };
    if (intent.ref) store.set('oneiros_ref', intent.ref.replace(/[^A-Za-z0-9]/g, '').toUpperCase());
    if (intent.redeem) store.session('oneiros_redeem', intent.redeem);
    if (intent.payment || intent.redeem) store.session('oneiros_suppress_tour', '1');
    if (intent.payment || intent.ref || intent.redeem) history.replaceState({}, '', location.pathname);
    return intent;
  }
  const urlIntent = readUrlIntent();

  async function handleIntentsSignedIn() {
    if (urlIntent.payment === 'return' && urlIntent.order) {
      urlIntent.payment = null;
      waitForPayment(urlIntent.order);
    } else if (urlIntent.payment === 'cancel' && urlIntent.order) {
      urlIntent.payment = null;
      api('POST', '/payments/cancel', { order_id: urlIntent.order }).catch(() => {});
      showToast('Payment cancelled. Nothing was charged.');
    }
    const redeem = store.session('oneiros_redeem');
    const after = store.session('oneiros_after_login');
    if (redeem) {
      store.session('oneiros_redeem', null);
      setTimeout(() => openLucid('gift', 'redeem', { code: redeem }), 900);
    } else if (after) {
      store.session('oneiros_after_login', null);
      try { const a = JSON.parse(after); setTimeout(() => openLucid(a.reason, a.tab, a.prefill || {}), 1100); } catch {}
    }
  }

  function handleIntentsSignedOut() {
    if (urlIntent.payment) showToast('Sign in to finish opening your Lucid pass.');
    if (store.session('oneiros_redeem')) {
      showToast('Create your free journal or sign in to redeem your gift.');
      setTimeout(() => openModal('register'), 700);
    }
    if (store.get('oneiros_ref') && !currentUser) showInviteBanner();
  }

  function showInviteBanner() {
    if (document.getElementById('invite-banner')) return;
    const days = pricing?.referral_reward_days || 7;
    document.body.insertAdjacentHTML('beforeend', `<div class="invite-banner" id="invite-banner" role="status">
      <span>✦</span><p><strong>A friend invited you.</strong> Save your first dream and you both receive ${days} Lucid nights.</p>
      <button class="btn-primary" onclick="document.getElementById('invite-banner').remove();openModal('register')">Begin</button>
      <button class="invite-close" onclick="document.getElementById('invite-banner').remove()" aria-label="Dismiss">×</button></div>`);
  }

  window.lucidCopy = async function (text) {
    try { await navigator.clipboard.writeText(text); showToast('Copied ✦'); }
    catch { prompt('Copy this:', text); }
  };
  window.lucidShareGift = function (code, days) {
    const url = new URL(`oneiros.php?redeem=${encodeURIComponent(code)}`, APP_BASE).href;
    const text = `I'm giving you ${days} nights of Oneiros Lucid ✦ Redeem your gift: ${url}`;
    if (navigator.share) navigator.share({ title: 'A gift of dreams', text, url }).catch(() => {});
    else lucidCopy(text);
  };

  // ════════════════════════════════════════════════════════════
  // PROFILE — MEMBERSHIP, INVITATIONS, RECEIPTS, APPEARANCE
  // ════════════════════════════════════════════════════════════
  async function renderMembership(fetchFirst = true) {
    const host = document.getElementById('membership-section');
    if (!host || !currentUser) return;
    // Fetching re-renders through applyLucidState, with the latest purchases
    if (fetchFirst) { await refreshMembership(); return; }
    if (!membership) return;
    const m = membership;
    const inviteUrl = m.referral?.code ? new URL(`oneiros.php?ref=${m.referral.code}`, APP_BASE).href : '';
    const lucid = m.is_premium;
    host.innerHTML = `
      <div class="settings-section membership ${lucid ? 'is-open' : ''}">
        <div class="settings-section-title">Membership</div>
        <div class="membership-status">
          <span class="membership-orb" aria-hidden="true"></span>
          <div>
            <h3>${lucid ? `Lucid · ${m.days_left} night${m.days_left === 1 ? '' : 's'} left` : 'Free journal'}${m.is_patron ? ' <span class="patron-mark">Patron</span>' : ''}</h3>
            <p>${lucid ? `Your pass is open until ${esc(fmtDate(m.premium_until))}. It simply ends then: nothing renews or charges again.`
              : m.expired_at ? `Your last Lucid pass completed on ${esc(fmtDate(m.expired_at))}. Your journal and everything in it stay yours.`
              : 'Everything you need to keep a dream journal, free. Lucid is a once-off pass whenever you want more.'}</p>
          </div>
          <button class="save-btn" onclick="openLucid('profile')">${lucid ? 'Add nights' : 'Explore Lucid'}</button>
        </div>
        <div class="membership-grid">
          <div class="membership-box">
            <h4>Have a code?</h4>
            <div class="lucid-code-row"><input id="profile-redeem" placeholder="Gift or promo code" autocomplete="off" aria-label="Gift or promo code"><button class="btn-ghost" onclick="lucidRedeem(document.getElementById('profile-redeem').value)">Redeem</button></div>
            <button class="text-link" onclick="openLucid('gift','gift')">Give Lucid as a gift <span>↗</span></button>
          </div>
          ${inviteUrl && m.referral.reward_days ? `<div class="membership-box">
            <h4>Invite a friend · you both receive ${m.referral.reward_days} nights</h4>
            <div class="lucid-code-row"><input readonly value="${esc(inviteUrl)}" aria-label="Your invitation link" onclick="this.select()">
              <button class="btn-ghost" onclick="lucidCopy('${esc(inviteUrl)}')">Copy</button>${navigator.share ? `<button class="btn-ghost" onclick="lucidShareInvite('${esc(inviteUrl)}')">Share</button>` : ''}</div>
            <p class="settings-info">${m.referral.invited} joined through you · ${m.referral.rewarded} rewarded. Rewards arrive when your friend saves a first dream.</p>
          </div>` : ''}
        </div>
        ${m.orders?.length ? `<div class="membership-orders"><h4>Purchases</h4>${m.orders.map(o => `
          <div class="order-row">
            <span class="order-date">${esc(fmtDate(o.created_at, { day: 'numeric', month: 'short', year: 'numeric' }))}</span>
            <span class="order-name">${esc(o.plan_name)}${o.gift_code ? ` · <code>${esc(o.gift_code)}</code> <button class="text-link" onclick="lucidCopy('${esc(o.gift_code)}')">copy</button>` : ''}</span>
            <span class="order-amount">${esc(o.amount)}</span>
            <span class="order-status status-${o.status}">${o.status === 'paid' ? 'Paid' : o.status === 'refunded' ? 'Refunded' : 'Awaiting payment'}</span>
            ${o.status !== 'pending' ? `<button class="text-link" onclick="lucidReceipt('${o.id}')">Receipt</button>` : '<span></span>'}
          </div>`).join('')}</div>` : ''}
        <p class="settings-info support-line">Love Oneiros? <button class="text-link" onclick="openLucid('support','support')">Leave a little light</button>, a once-off contribution that keeps it free for everyone. <button class="text-link" onclick="showInfo('terms')">Terms</button> · <button class="text-link" onclick="showInfo('privacy')">Privacy</button></p>
      </div>`;
    renderAppearance();
  }

  window.lucidShareInvite = function (url) {
    navigator.share({ title: 'Dream with me on Oneiros', text: 'Keep a dream journal and find people who dream what you dream. We both receive Lucid nights when you save your first dream.', url }).catch(() => {});
  };

  window.lucidReceipt = async function (orderId) {
    const order = membership?.orders?.find(o => o.id === orderId);
    if (!order) return;
    const biz = (await loadPricing())?.business || { name: 'Oneiros' };
    printHtml(`
      <article class="receipt">
        <header><div class="receipt-brand">◌ oneiros ✦</div><div><strong>${esc(biz.name)}</strong><br>${esc(biz.details || '')}<br>${esc(biz.email || '')}</div></header>
        <h1>Receipt</h1>
        <table>
          <tr><th>Reference</th><td>${esc(order.reference)}</td></tr>
          <tr><th>Date</th><td>${esc(fmtDate(order.paid_at || order.created_at))}</td></tr>
          <tr><th>Billed to</th><td>${esc(currentUser.email)}</td></tr>
          <tr><th>Item</th><td>${esc(order.plan_name)}${order.days ? ` · ${order.days} nights` : ''}</td></tr>
          ${order.discount_cents ? `<tr><th>Discount</th><td>${esc(order.discount_code)} applied</td></tr>` : ''}
          <tr><th>Amount</th><td><strong>${esc(order.amount)}</strong> ${esc(order.currency)}</td></tr>
          <tr><th>Payment</th><td>${esc(order.provider === 'payfast' ? 'PayFast' : order.provider === 'paystack' ? 'Paystack' : order.provider)}</td></tr>
          <tr><th>Status</th><td>${order.status === 'refunded' ? 'Refunded' : 'Paid in full'}</td></tr>
        </table>
        <p>A once-off purchase. It does not renew and no further charges will be made.</p>
      </article>`);
  };

  // ── Themes (Lucid) ──
  const THEMES = [
    { id: 'nocturne', name: 'Nocturne', note: 'Midnight lavender', lucid: false },
    { id: 'aurora', name: 'Aurora', note: 'Northern lights', lucid: true },
    { id: 'rose', name: 'Rosé', note: 'Dusk and blush', lucid: true },
    { id: 'eclipse', name: 'Eclipse', note: 'Ink and gold', lucid: true },
  ];
  function applyTheme() {
    const chosen = store.get('oneiros_theme') || 'nocturne';
    const theme = THEMES.find(t => t.id === chosen && (!t.lucid || isLucid()));
    if (theme && theme.id !== 'nocturne') document.body.dataset.theme = theme.id;
    else delete document.body.dataset.theme;
  }
  function renderAppearance() {
    const host = document.getElementById('appearance-section');
    if (!host) return;
    const current = document.body.dataset.theme || 'nocturne';
    host.innerHTML = `<div class="settings-section"><div class="settings-section-title">Appearance</div>
      <div class="theme-grid">${THEMES.map(t => `
        <button class="theme-swatch theme-${t.id} ${current === t.id ? 'active' : ''}" onclick="lucidTheme('${t.id}')" aria-pressed="${current === t.id}">
          <span class="swatch-art" aria-hidden="true"></span><strong>${t.name}</strong><small>${t.note}${t.lucid && !isLucid() ? ' · ✦ Lucid' : ''}</small>
        </button>`).join('')}</div></div>`;
  }
  window.lucidTheme = function (id) {
    const theme = THEMES.find(t => t.id === id);
    if (!theme) return;
    if (theme.lucid && !isLucid()) { openLucid('themes'); return; }
    store.set('oneiros_theme', id);
    applyTheme();
    renderAppearance();
  };

  // ════════════════════════════════════════════════════════════
  // DASHBOARD — pass reminders, last night's intention, guided recall
  // ════════════════════════════════════════════════════════════
  function intentionKey(day) { return currentUser ? `oneiros_intention_${currentUser.id}_${day}` : null; }

  function renderDashSlot() {
    const host = document.getElementById('dash-lucid-slot');
    if (!host) return;
    if (!currentUser) { host.innerHTML = ''; return; }
    const parts = [];
    const left = daysLeft();
    if (isLucid() && left <= 3) {
      parts.push(`<div class="dash-note lucid"><span>✦</span><p>Your Lucid pass completes in <strong>${left} night${left === 1 ? '' : 's'}</strong>. Nothing renews automatically.</p><button class="text-link" onclick="openLucid('dashboard')">Add nights <span>↗</span></button></div>`);
    } else if (!isLucid() && membership?.expired_at && Date.now() - new Date(membership.expired_at) < 14 * 864e5) {
      parts.push(`<div class="dash-note"><span>◌</span><p>Your Lucid pass has completed. Your journal stays exactly as it is.</p><button class="text-link" onclick="openLucid('dashboard')">Continue with Lucid <span>↗</span></button></div>`);
    }
    const intention = store.get(intentionKey(localDay(-1))) || store.get(intentionKey(localDay(0)));
    const answered = store.get(intentionKey(localDay(0)) + '_answered');
    if (intention && !answered && new Date().getHours() < 16) {
      parts.push(`<div class="dash-note intention"><span>☾</span><p>Last night you asked to dream of <em>“${esc(intention)}”</em>. Did it find you?</p>
        <button class="text-link" onclick="lucidIntentionAnswer(true)">It did</button><button class="text-link" onclick="lucidIntentionAnswer(false)">Not this time</button></div>`);
    } else if (new Date().getHours() >= 20) {
      parts.push(`<div class="dash-note"><span>🌘</span><p>The night is near. Set an intention and let a soundscape carry you to sleep.</p><button class="text-link" onclick="goTo('winddown')">Wind down <span>↗</span></button></div>`);
    }
    host.innerHTML = parts.join('');
  }
  window.lucidIntentionAnswer = function (found) {
    store.set(intentionKey(localDay(0)) + '_answered', found ? 'yes' : 'no');
    const intention = store.get(intentionKey(localDay(-1))) || store.get(intentionKey(localDay(0)));
    renderDashSlot();
    if (found) {
      const text = document.getElementById('dream-text');
      if (text && !text.value.trim()) text.value = `My intention was “${intention}”, and it found me. `;
      text?.focus();
      showToast('✦ Write it down while it is fresh');
    } else showToast('Some nights keep their secrets. Try again tonight.');
  };

  const RECALL_PROMPTS = ['Where were you?', 'Who was there?', 'What did you feel?', 'What colours or light?', 'What happened last?', 'Anything impossible?', 'What did you hear?'];
  function renderRecallPrompts() {
    const host = document.getElementById('recall-prompts');
    if (!host || host.childElementCount) return;
    host.innerHTML = '<span>Need a nudge?</span>' + RECALL_PROMPTS.map(q => `<button type="button" onclick="lucidPrompt(this)">${esc(q)}</button>`).join('');
  }
  window.lucidPrompt = function (btn) {
    const text = document.getElementById('dream-text');
    if (!text) return;
    const q = btn.textContent;
    const prefix = text.value && !/\s$/.test(text.value) ? '\n' : '';
    text.value += `${prefix}${q} `;
    text.focus();
    text.setSelectionRange(text.value.length, text.value.length);
    text.dispatchEvent(new Event('input'));
    btn.classList.add('used');
  };

  // ════════════════════════════════════════════════════════════
  // SYMBOL REFLECTIONS — gentle questions, never fortunes
  // ════════════════════════════════════════════════════════════
  const SYMBOLS = [
    ['water', '🌊', 'Water', /\b(water|ocean|sea|lake|river|wave|waves|swim|swimming|flood|tide|rain)\b/i, 'Water in dreams often mirrors how emotions are moving: calm, rising, clear or murky.', 'Was the water still or restless, and were you in it or watching?'],
    ['flying', '🕊', 'Flying', /\b(fly|flying|flew|float|floating|soar|soaring|wings)\b/i, 'Flight can carry a sense of freedom, perspective, or rising above something that felt heavy.', 'Did you choose to fly, or did it simply happen? How did the ground look from above?'],
    ['falling', '🌀', 'Falling', /\b(fall|falling|fell|drop|dropping)\b/i, 'Falling often appears when something feels out of your hands, or when you are letting go.', 'Did you land, wake up, or keep falling? What were you falling from?'],
    ['door', '🚪', 'Doorways', /\b(door|doors|doorway|gate|portal|threshold|arch)\b/i, 'Doors gather around thresholds: choices, changes, or invitations you are still weighing.', 'Was the door open or closed, and did you want to walk through it?'],
    ['key', '🗝', 'Keys', /\b(key|keys|lock|locked|unlock)\b/i, 'Keys can point to access and permission: something you hold, or something you are looking for.', 'What did the key open, or what were you unable to open?'],
    ['mirror', '🪞', 'Mirrors', /\b(mirror|mirrors|reflection|reflected)\b/i, 'Mirrors invite you to look at yourself, sometimes from an unfamiliar angle.', 'Did the reflection match you? What was different?'],
    ['staircase', '🪜', 'Stairs', /\b(stairs|staircase|steps|ladder|climb|climbing)\b/i, 'Stairs often trace movement between levels: progress, effort, or descending into memory.', 'Were you going up or down, and where did the stairs lead?'],
    ['bridge', '🌉', 'Bridges', /\b(bridge|bridges|crossing)\b/i, 'Bridges connect two places, and often appear during transitions between chapters of life.', 'What was on each side, and did you make it across?'],
    ['forest', '🌲', 'Forests', /\b(forest|woods|trees|tree|jungle)\b/i, 'Forests can hold the unknown and the instinctive: places to get lost, or to find something wild.', 'Did the forest feel welcoming or watchful?'],
    ['house', '🏠', 'Houses & rooms', /\b(house|home|room|rooms|hallway|attic|basement|bedroom|kitchen)\b/i, 'Houses often stand in for the self; discovering new rooms can echo discovering new parts of your life.', 'Whose house was it, and did you find a room you did not know existed?'],
    ['city', '🏙', 'Cities', /\b(city|cities|street|streets|town|building|buildings|tower|towers)\b/i, 'Cities can reflect the busy, social world and your place within it.', 'Were you finding your way, or were you lost among the buildings?'],
    ['school', '📝', 'School & tests', /\b(school|exam|exams|test|class|classroom|teacher|homework)\b/i, 'School dreams often surface around being evaluated, or feeling unprepared for something.', 'What were you being asked to do, and who was watching?'],
    ['chase', '🏃', 'Being chased', /\b(chase|chased|chasing|running from|pursued|hunted|escape|escaping)\b/i, 'Pursuit dreams often appear when something asks for attention and we would rather not turn around.', 'If you had turned to face what chased you, what might it have been?'],
    ['teeth', '🦷', 'Teeth', /\b(teeth|tooth)\b/i, 'Teeth dreams are among the most common; they often coincide with worries about appearance, words or control.', 'What were you about to say, or trying to hold together?'],
    ['exposed', '🫥', 'Being exposed', /\b(naked|undressed|exposed|embarrassed)\b/i, 'Feeling exposed can echo vulnerability, or being seen before you feel ready.', 'Who noticed, and did anyone actually seem to mind?'],
    ['lost', '🧭', 'Being lost', /\b(lost|searching|looking for|can't find|cannot find|missing)\b/i, 'Searching dreams can accompany uncertainty about direction, or something you feel is missing.', 'What were you looking for, and would you recognise it if you found it?'],
    ['death', '🕯', 'Endings', /\b(death|dead|dying|died|funeral|grave)\b/i, 'Death in dreams most often speaks of endings and transformation rather than literal loss.', 'What might be ending, or ready to change, in your waking life?'],
    ['baby', '👶', 'Babies & children', /\b(baby|babies|child|children|kid|kids|toddler)\b/i, 'Children can represent new beginnings, fragile hopes, or a younger version of yourself.', 'Were you caring for the child, or were you the child?'],
    ['family', '👵', 'Family', /\b(mother|mom|mum|father|dad|grandmother|grandfather|grandma|grandpa|sister|brother|family)\b/i, 'Family members can carry old patterns, comfort, or conversations that are still unfinished.', 'What did they want from you, or you from them?'],
    ['stranger', '👤', 'Strangers & figures', /\b(stranger|figure|figures|shadow|someone|man|woman|person)\b/i, 'Unknown figures can embody qualities we have not yet recognised as our own.', 'What quality did the figure have that you might recognise in yourself?'],
    ['snake', '🐍', 'Snakes', /\b(snake|snakes|serpent)\b/i, 'Snakes are old symbols of transformation and renewal, and sometimes of hidden unease.', 'Did the snake feel threatening, curious, or wise?'],
    ['dog', '🐕', 'Dogs', /\b(dog|dogs|puppy|wolf|wolves)\b/i, 'Dogs and wolves often carry loyalty, protection or instinct, depending on how they behaved.', 'Was the animal guarding you, guiding you, or wary of you?'],
    ['cat', '🐈', 'Cats', /\b(cat|cats|kitten|lion|tiger)\b/i, 'Cats can reflect independence, intuition, or a quiet curiosity.', 'What was the cat paying attention to?'],
    ['bird', '🐦', 'Birds', /\b(bird|birds|owl|crow|raven|eagle|feather)\b/i, 'Birds often bring messages, perspective, or longing for freedom.', 'If the bird carried a message, what would it say?'],
    ['fire', '🔥', 'Fire', /\b(fire|flame|flames|burn|burning|smoke)\b/i, 'Fire can mean passion and warmth, or something consuming that needs attention.', 'Was the fire warming you, or spreading out of control?'],
    ['moon', '🌙', 'The moon', /\b(moon|moonlight|moonlit)\b/i, 'The moon often watches over dreams of intuition, cycles and the hidden side of things.', 'What did the moonlight reveal that daylight would not?'],
    ['stars', '✨', 'Stars & sky', /\b(star|stars|sky|galaxy|space|planet|planets|cosmos)\b/i, 'Night skies invite wonder, distance, and a sense of being part of something vast.', 'Did you feel small beneath the sky, or connected to it?'],
    ['light', '💡', 'Light', /\b(light|glow|glowing|shine|shining|bright|golden|sunlight|sun)\b/i, 'Light can show clarity, insight or hope, especially when it appears in darkness.', 'Where was the light coming from, and what did it show you?'],
    ['darkness', '🌑', 'Darkness', /\b(dark|darkness|night|black|shadows)\b/i, 'Darkness holds the unseen: the unknown can feel frightening, restful, or full of possibility.', 'Did you want to see what the dark was hiding?'],
    ['time', '⏳', 'Clocks & time', /\b(clock|clocks|time|late|hurry|hurrying|deadline|watch)\b/i, 'Time pressure in dreams often mirrors waking urgency, or a fear of missing a moment.', 'What were you running late for, and would it truly matter?'],
    ['vehicle', '🚗', 'Journeys & vehicles', /\b(car|cars|bus|train|plane|airplane|driving|drive|boat|ship|journey|travel|travelling)\b/i, 'Vehicles can speak about direction and control: who is steering the journey of your life right now?', 'Were you driving, a passenger, or trying to catch it?'],
    ['phone', '📱', 'Phones & messages', /\b(phone|call|calling|message|text|letter)\b/i, 'Messages in dreams often point to something that wants to be said or heard.', 'Did the message get through? Who was on the other end?'],
    ['mountain', '⛰', 'Mountains', /\b(mountain|mountains|hill|hills|cliff|peak|summit)\b/i, 'Mountains often embody challenges, ambitions, and the view that comes after a climb.', 'Were you climbing, resting, or looking up at it?'],
    ['garden', '🌷', 'Gardens & flowers', /\b(garden|gardens|flower|flowers|bloom|blossom|meadow)\b/i, 'Gardens can reflect growth and care: what you are tending in yourself or others.', 'What was growing, and who was looking after it?'],
    ['spiral', '🌀', 'Spirals & mazes', /\b(spiral|maze|labyrinth|circle|circles|loop)\b/i, 'Spirals and mazes often appear when you are circling something important from many sides.', 'Were you getting closer to the centre, or further away?'],
    ['snow', '❄️', 'Snow & ice', /\b(snow|ice|frozen|cold|winter)\b/i, 'Snow and ice can hold stillness, pause, or feelings that have not yet thawed.', 'What felt frozen, and what might melt it?'],
  ];

  function reflectionsFor(dream) {
    const text = `${dream.title || ''} ${dream.content || ''}`;
    const tags = new Set([...(dream.symbols || []), ...(dream.themes || [])].map(t => String(t).toLowerCase()));
    const aliases = { pursuit: 'chase', architecture: 'house', figures: 'stranger', nature: 'forest', loss: 'lost', transformation: 'spiral', animals: 'dog', crowd: 'stranger', child: 'baby', tower: 'city', clock: 'time', weapon: 'chase' };
    return SYMBOLS.filter(([key, , , re]) => re.test(text) || tags.has(key) || [...tags].some(t => aliases[t] === key)).slice(0, 8);
  }

  document.addEventListener('oneiros:dream-detail', e => {
    const dream = e.detail;
    const detail = document.getElementById('dream-detail');
    const fields = detail?.querySelector('.detail-fields');
    if (!fields) return;
    const found = reflectionsFor(dream);
    const free = 2;
    if (found.length) {
      fields.insertAdjacentHTML('beforebegin', `<section class="reflections" aria-label="Symbol reflections">
        <h3>Reflections</h3><p class="reflections-note">Dreams speak a personal language. These are gentle questions, not answers.</p>
        <div class="reflection-grid">${found.map(([, icon, title, , text, question], i) => {
          const locked = !isLucid() && i >= free;
          return `<div class="reflection-card ${locked ? 'locked' : ''}"><span class="reflection-title"><b aria-hidden="true">${icon}</b> ${esc(title)}</span>
            ${locked ? '<p class="blurred">A gentle reflection waits here for Lucid dreamers.</p>' : `<p>${esc(text)}</p><em>${esc(question)}</em>`}</div>`;
        }).join('')}</div>
        ${!isLucid() && found.length > free ? `<button class="text-link" onclick="openLucid('reflections')">Reveal all ${found.length} reflections with Lucid <span>✦</span></button>` : ''}
      </section>`);
    }
    const actions = detail.querySelector('.detail-actions');
    if (actions) {
      actions.insertAdjacentHTML('afterbegin', `<button class="btn-ghost" onclick="lucidShareCard('${dream.id}')">Share card</button>
        <button class="btn-ghost" onclick="lucidHdPainting('${dream.id}')">${isLucid() ? 'Download HD painting' : 'HD painting ✦'}</button>`);
    }
    lastDetailDream = dream;
  });
  let lastDetailDream = null;

  // ── Share cards (free) and HD paintings (Lucid) ──
  function wrapLines(ctx, text, maxWidth, maxLines) {
    const words = text.split(/\s+/);
    const lines = [];
    let line = '';
    for (const word of words) {
      const test = line ? `${line} ${word}` : word;
      if (ctx.measureText(test).width > maxWidth && line) {
        lines.push(line);
        line = word;
        if (lines.length === maxLines) break;
      } else line = test;
    }
    if (lines.length < maxLines && line) lines.push(line);
    if (lines.length === maxLines && words.join(' ').length > lines.join(' ').length) lines[maxLines - 1] = lines[maxLines - 1].replace(/\s*\S*$/, '') + '…';
    return lines;
  }

  function dreamOptions(dream) {
    return { text: `${dream.title || ''} ${dream.content || ''}`, emotions: dream.emotions || [], answers: dream.themes || [] };
  }

  window.lucidShareCard = async function (dreamId) {
    const dream = lastDetailDream?.id === dreamId ? lastDetailDream : dreamJournal.find(d => d.id === dreamId);
    if (!dream || !window.DreamCanvas) return;
    showToast('✧ Painting your card…');
    await document.fonts?.ready;
    const W = 1080, H = 1350;
    const card = document.createElement('canvas');
    card.width = W; card.height = H;
    const g = card.getContext('2d');
    g.fillStyle = '#080b18';
    g.fillRect(0, 0, W, H);
    g.drawImage(window.DreamCanvas.render(dreamOptions(dream), W, 720), 0, 0);
    const fade = g.createLinearGradient(0, 560, 0, 760);
    fade.addColorStop(0, 'rgba(8,11,24,0)');
    fade.addColorStop(1, 'rgba(8,11,24,1)');
    g.fillStyle = fade;
    g.fillRect(0, 560, W, 200);
    g.fillStyle = '#c8bbdf';
    g.font = '500 22px Jost, sans-serif';
    g.fillText(new Date(dream.dreamed_at).toLocaleDateString(undefined, { day: 'numeric', month: 'long', year: 'numeric' }).toUpperCase(), 80, 800);
    g.fillStyle = '#f2edf9';
    g.font = '400 68px "Cormorant Garamond", Georgia, serif';
    wrapLines(g, dream.title || 'A dream', W - 160, 2).forEach((line, i) => g.fillText(line, 80, 880 + i * 74));
    g.fillStyle = '#cfc6e3';
    g.font = 'italic 300 36px "Cormorant Garamond", Georgia, serif';
    const titleLines = Math.min(2, wrapLines(g, dream.title || 'A dream', W - 160, 2).length);
    wrapLines(g, `“${(dream.content || '').slice(0, 320)}”`, W - 160, 5).forEach((line, i) => g.fillText(line, 80, 880 + titleLines * 74 + 30 + i * 48));
    g.font = '400 24px Jost, sans-serif';
    let x = 80;
    (dream.emotions || []).slice(0, 4).forEach(emotion => {
      const w = g.measureText(emotion).width + 44;
      g.strokeStyle = 'rgba(200,187,244,.45)';
      g.lineWidth = 2;
      g.beginPath(); g.roundRect ? g.roundRect(x, 1210, w, 50, 25) : g.rect(x, 1210, w, 50); g.stroke();
      g.fillStyle = '#d9ccef';
      g.fillText(emotion, x + 22, 1244);
      x += w + 14;
    });
    g.fillStyle = '#8f87a8';
    g.font = '400 22px Jost, sans-serif';
    g.fillText(`◌ oneiros ✦  ·  ${location.host}`, 80, 1310);
    const blob = await new Promise(r => card.toBlob(r, 'image/png'));
    const file = new File([blob], 'oneiros-dream.png', { type: 'image/png' });
    if (navigator.canShare?.({ files: [file] })) {
      navigator.share({ files: [file], title: dream.title || 'A dream', text: 'A dream I remembered on Oneiros ✦' }).catch(() => {});
    } else downloadBlob(blob, `oneiros-${(dream.title || 'dream').toLowerCase().replace(/[^a-z0-9]+/g, '-')}.png`);
  };

  window.lucidHdPainting = async function (dreamId) {
    if (!isLucid()) { openLucid('hd'); return; }
    const dream = lastDetailDream?.id === dreamId ? lastDetailDream : dreamJournal.find(d => d.id === dreamId);
    if (!dream || !window.DreamCanvas) return;
    showToast('✧ Painting in high resolution…');
    await new Promise(r => setTimeout(r, 50));
    const canvas = window.DreamCanvas.render(dreamOptions(dream), 3072, 2048);
    canvas.toBlob(blob => downloadBlob(blob, `oneiros-${(dream.title || 'dream').toLowerCase().replace(/[^a-z0-9]+/g, '-')}-hd.jpg`), 'image/jpeg', 0.93);
  };

  function downloadBlob(blob, name) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = name;
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 2000);
  }

  // ════════════════════════════════════════════════════════════
  // PRINTING — receipts and the Dream Book
  // ════════════════════════════════════════════════════════════
  function printHtml(html) {
    const root = document.getElementById('print-root');
    root.innerHTML = html;
    document.querySelectorAll('dialog[open]').forEach(d => d.close());
    document.documentElement.classList.add('printing');
    const images = [...root.querySelectorAll('img')].map(img => img.complete ? null : new Promise(r => { img.onload = img.onerror = r; })).filter(Boolean);
    Promise.all(images).then(() => setTimeout(() => window.print(), 120));
  }
  window.addEventListener('afterprint', () => {
    document.documentElement.classList.remove('printing');
    const root = document.getElementById('print-root');
    if (root) root.innerHTML = '';
  });

  window.lucidDreamBook = async function () {
    if (!isLucid()) { openLucid('book'); return; }
    showToast('❖ Gathering every dream for your book…');
    const dreams = [];
    let total = Infinity;
    for (let page = 1; dreams.length < total && page <= 10; page++) {
      const data = await api('GET', `/dreams?limit=50&page=${page}`).catch(() => null);
      if (!data?.dreams?.length) break;
      total = data.total;
      dreams.push(...data.dreams);
    }
    if (!dreams.length) { showToast('Save a dream first, then your book can begin.', 'error'); return; }
    dreams.sort((a, b) => new Date(a.dreamed_at) - new Date(b.dreamed_at));
    const name = currentUser.display_name || 'a dreamer';
    const range = `${fmtDate(dreams[0].dreamed_at, { month: 'long', year: 'numeric' })} – ${fmtDate(dreams.at(-1).dreamed_at, { month: 'long', year: 'numeric' })}`;
    printHtml(`<div class="dream-book">
      <section class="book-cover"><img src="${window.DreamCanvas.painting(dreams.at(-1), 1200, 800)}" alt="">
        <div><p>ONEIROS</p><h1>The Dream Book<br><em>of ${esc(name)}</em></h1><p>${esc(range)} · ${dreams.length} dream${dreams.length === 1 ? '' : 's'}</p></div></section>
      ${dreams.map(d => `<section class="book-dream">
        <img src="${d.image_url ? esc(d.image_url) : window.DreamCanvas.painting(d, 960, 640)}" alt="">
        <p class="book-date">${esc(fmtDate(d.dreamed_at, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }))}</p>
        <h2>${esc(d.title || 'Untitled dream')}</h2>
        <div class="book-text">${esc(d.content).replace(/\n/g, '<br>')}</div>
        ${[...(d.emotions || []), ...(d.themes || [])].length ? `<p class="book-tags">${[...new Set([...(d.emotions || []), ...(d.themes || [])])].map(esc).join(' · ')}</p>` : ''}
      </section>`).join('')}
    </div>`);
    showToast('Choose “Save as PDF” to keep your Dream Book');
  };

  // ════════════════════════════════════════════════════════════
  // DEEP INSIGHTS (Lucid)
  // ════════════════════════════════════════════════════════════
  const STOPWORDS = new Set('a about above after again against all am an and any are as at be because been before being below between both but by could did do does doing down during each few for from further had has have having he her here hers herself him himself his how i if in into is it its itself just me more most my myself no nor not now of off on once only or other our ours out over own same she should so some such than that the their theirs them then there these they this those through to too under until up very was we were what when where which while who whom why will with would you your yours yourself dream dreamed dreamt dreaming like felt feel was were there something someone could then back one still just around looked seemed started went came got know'.split(' '));
  const EMOTION_COLORS = { wonder: '#c8bbf4', peace: '#93d6ef', joy: '#f4c9a0', fear: '#e88a9b', longing: '#e7b8cc', confusion: '#a8d8b9', sadness: '#9aa7d8', dread: '#b07a8f', terror: '#d46a7e' };

  document.addEventListener('oneiros:insights', e => {
    const { dreams, box } = e.detail;
    const note = box.querySelector('.insights-note');
    if (!isLucid()) {
      note?.insertAdjacentHTML('beforebegin', `<section class="insight-panel insight-wide deep-locked">
        <div class="deep-preview" aria-hidden="true"><span style="height:40%"></span><span style="height:75%"></span><span style="height:55%"></span><span style="height:90%"></span><span style="height:62%"></span><span style="height:80%"></span><span style="height:45%"></span></div>
        <div><span class="eyebrow">DEEP INSIGHTS · LUCID</span><h2>Your emotional <em>tides</em>, dream signs and word constellations.</h2>
        <p>See how your nights change week by week, which images keep returning, and the words your dreams are made of.</p>
        <button class="btn-primary" onclick="openLucid('insights')">Unlock deep insights <span>✦</span></button></div></section>`);
      return;
    }
    // Emotional tides: the last ten weeks
    const weeks = Array.from({ length: 10 }, (_, i) => { const start = new Date(); start.setHours(0, 0, 0, 0); start.setDate(start.getDate() - start.getDay() - (9 - i) * 7); return { start, counts: {} }; });
    dreams.forEach(d => {
      const t = new Date(d.dreamed_at);
      const week = [...weeks].reverse().find(w => t >= w.start);
      if (week) (d.emotions || []).forEach(em => { week.counts[em] = (week.counts[em] || 0) + 1; });
    });
    const maxWeek = Math.max(1, ...weeks.map(w => Object.values(w.counts).reduce((a, b) => a + b, 0)));
    const usedEmotions = [...new Set(weeks.flatMap(w => Object.keys(w.counts)))];
    // Dream signs: images that return
    const signs = {};
    dreams.forEach(d => reflectionsFor(d).forEach(([key, icon, title]) => { signs[key] = signs[key] || { icon, title, n: 0 }; signs[key].n++; }));
    const dreamSigns = Object.values(signs).filter(s => s.n >= 2).sort((a, b) => b.n - a.n).slice(0, 8);
    // Word constellation
    const words = {};
    dreams.forEach(d => (d.content || '').toLowerCase().match(/[a-z']{4,}/g)?.forEach(w => { if (!STOPWORDS.has(w)) words[w] = (words[w] || 0) + 1; }));
    const topWords = Object.entries(words).sort((a, b) => b[1] - a[1]).slice(0, 28);
    const maxWord = topWords[0]?.[1] || 1;
    // Most resonant dreams
    const resonant = dreams.filter(d => d.match_count > 0).sort((a, b) => b.match_count - a.match_count).slice(0, 3);

    note?.insertAdjacentHTML('beforebegin', `
      <section class="insight-panel insight-wide">
        <span class="eyebrow">EMOTIONAL TIDES · LAST TEN WEEKS</span>
        <h2>How your nights <em>ebb and flow.</em></h2>
        ${usedEmotions.length ? `<div class="tides">${weeks.map(w => `<div class="tide" title="Week of ${w.start.toLocaleDateString()}">
            <div class="tide-stack">${usedEmotions.map(em => w.counts[em] ? `<span style="height:${(w.counts[em] / maxWeek) * 100}%;background:${EMOTION_COLORS[em] || '#b9abec'}"></span>` : '').join('')}</div>
            <small>${w.start.toLocaleDateString(undefined, { day: 'numeric', month: 'short' })}</small></div>`).join('')}</div>
          <div class="tide-legend">${usedEmotions.map(em => `<span><i style="background:${EMOTION_COLORS[em] || '#b9abec'}"></i>${esc(em)}</span>`).join('')}</div>`
        : '<p class="insight-empty">Name how your dreams felt when you save them, and your tides will appear.</p>'}
      </section>
      <section class="insight-panel">
        <span class="eyebrow">DREAM SIGNS</span>
        <h2>Images that <em>return.</em></h2>
        <p>Recurring images can become cues: when you notice one, ask yourself whether you are dreaming.</p>
        ${dreamSigns.length ? `<div class="sign-list">${dreamSigns.map(s => `<span><b>${s.icon}</b> ${esc(s.title)} <small>×${s.n}</small></span>`).join('')}</div>` : '<p class="insight-empty">Dream signs appear once an image returns in two dreams.</p>'}
      </section>
      <section class="insight-panel">
        <span class="eyebrow">WORD CONSTELLATION</span>
        <h2>What your dreams are <em>made of.</em></h2>
        <div class="constellation">${topWords.map(([w, n], i) => `<span style="font-size:${12 + (n / maxWord) * 20}px;opacity:${0.5 + (n / maxWord) * 0.5};animation-delay:${(i % 7) * 0.4}s">${esc(w)}</span>`).join('') || '<p class="insight-empty">Write a little more and your words will gather here.</p>'}</div>
      </section>
      ${resonant.length ? `<section class="insight-panel insight-wide">
        <span class="eyebrow">MOST RESONANT</span><h2>Dreams the world <em>shared with you.</em></h2>
        <div class="resonant-list">${resonant.map(d => `<button onclick="openDream('${d.id}')"><img src="${window.DreamCanvas?.painting(d) || ''}" alt=""><span><strong>${esc(d.title || 'Untitled dream')}</strong>${d.match_count} resonance${d.match_count === 1 ? '' : 's'}</span></button>`).join('')}</div>
      </section>` : ''}`);
  });

  // ════════════════════════════════════════════════════════════
  // JOURNAL CALENDAR
  // ════════════════════════════════════════════════════════════
  let calendarMonth = new Date();
  calendarMonth.setDate(1);
  window.setJournalView = function (view) {
    store.set('oneiros_journal_view', view);
    document.querySelectorAll('.view-toggle button').forEach(b => { b.classList.toggle('active', b.dataset.view === view); b.setAttribute('aria-pressed', b.dataset.view === view); });
    const cal = document.getElementById('journal-calendar');
    const list = document.getElementById('journal-list');
    if (!cal || !list) return;
    cal.hidden = view !== 'calendar';
    list.hidden = view === 'calendar';
    if (view === 'calendar') renderCalendar();
  };
  window.lucidCalendarMove = function (step) { calendarMonth.setMonth(calendarMonth.getMonth() + step); renderCalendar(); };

  function renderCalendar(selectedDay = null) {
    const host = document.getElementById('journal-calendar');
    if (!host) return;
    const year = calendarMonth.getFullYear(), month = calendarMonth.getMonth();
    const byDay = {};
    dreamJournal.forEach(d => {
      const t = new Date(d.dreamed_at);
      if (t.getFullYear() === year && t.getMonth() === month) (byDay[t.getDate()] = byDay[t.getDate()] || []).push(d);
    });
    const startPad = (new Date(year, month, 1).getDay() + 6) % 7;
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const today = new Date();
    const cells = [];
    for (let i = 0; i < startPad; i++) cells.push('<span class="cal-day empty"></span>');
    for (let day = 1; day <= daysInMonth; day++) {
      const dreams = byDay[day] || [];
      const isToday = today.getFullYear() === year && today.getMonth() === month && today.getDate() === day;
      cells.push(`<button class="cal-day ${dreams.length ? 'has-dream' : ''} ${isToday ? 'today' : ''} ${selectedDay === day ? 'selected' : ''}" ${dreams.length ? `onclick="lucidCalendarDay(${day})"` : 'disabled'}
        aria-label="${day}${dreams.length ? `, ${dreams.length} dream${dreams.length === 1 ? '' : 's'}` : ''}">
        ${dreams.length ? `<img src="${window.DreamCanvas?.painting(dreams[0], 160, 110) || ''}" alt="">` : ''}<span>${day}</span>${dreams.length > 1 ? `<i>${dreams.length}</i>` : ''}</button>`);
    }
    const count = Object.values(byDay).reduce((a, b) => a + b.length, 0);
    const chosen = selectedDay ? byDay[selectedDay] || [] : [];
    host.innerHTML = `
      <div class="cal-head"><button class="btn-ghost" onclick="lucidCalendarMove(-1)" aria-label="Previous month">←</button>
        <h3>${calendarMonth.toLocaleDateString(undefined, { month: 'long', year: 'numeric' })}<small>${count} dream${count === 1 ? '' : 's'}</small></h3>
        <button class="btn-ghost" onclick="lucidCalendarMove(1)" aria-label="Next month">→</button></div>
      <div class="cal-grid">${['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map(d => `<span class="cal-label">${d}</span>`).join('')}${cells.join('')}</div>
      ${chosen.length ? `<div class="cal-dreams">${chosen.map(d => `<button onclick="openDream('${d.id}')"><strong>${esc(d.title || 'Untitled dream')}</strong><span>${esc((d.content || '').slice(0, 120))}…</span></button>`).join('')}</div>` : ''}
      <p class="settings-info">Showing your ${dreamJournal.length} most recent dreams.</p>`;
  }
  window.lucidCalendarDay = function (day) {
    const dreams = dreamJournal.filter(d => { const t = new Date(d.dreamed_at); return t.getFullYear() === calendarMonth.getFullYear() && t.getMonth() === calendarMonth.getMonth() && t.getDate() === day; });
    if (dreams.length === 1) openDream(dreams[0].id); else renderCalendar(day);
  };
  const journalList = document.getElementById('journal-list');
  if (journalList) new MutationObserver(() => {
    if (store.get('oneiros_journal_view') === 'calendar' && !document.getElementById('journal-calendar').hidden) renderCalendar();
  }).observe(journalList, { childList: true });

  // ════════════════════════════════════════════════════════════
  // WIND DOWN — soundscapes, breathing, intention, lucid practice
  // ════════════════════════════════════════════════════════════
  const Sound = (() => {
    let ctx = null, master = null, timerId = null, timerEnds = 0;
    const playing = {};
    const buffers = {};

    function ensure() {
      if (!ctx) {
        const AC = window.AudioContext || window.webkitAudioContext;
        if (!AC) return false;
        ctx = new AC();
        master = ctx.createGain();
        master.gain.value = 0.9;
        master.connect(ctx.destination);
      }
      if (ctx.state === 'suspended') ctx.resume();
      return true;
    }

    function noise(kind) {
      if (buffers[kind]) return buffers[kind];
      const length = ctx.sampleRate * 6;
      const buffer = ctx.createBuffer(2, length, ctx.sampleRate);
      for (let ch = 0; ch < 2; ch++) {
        const data = buffer.getChannelData(ch);
        let last = 0, b0 = 0, b1 = 0, b2 = 0, b3 = 0, b4 = 0, b5 = 0, b6 = 0;
        for (let i = 0; i < length; i++) {
          const white = Math.random() * 2 - 1;
          if (kind === 'brown') { last = (last + 0.02 * white) / 1.02; data[i] = last * 3.5; }
          else if (kind === 'pink') {
            b0 = 0.99886 * b0 + white * 0.0555179; b1 = 0.99332 * b1 + white * 0.0750759; b2 = 0.96900 * b2 + white * 0.1538520;
            b3 = 0.86650 * b3 + white * 0.3104856; b4 = 0.55000 * b4 + white * 0.5329522; b5 = -0.7616 * b5 - white * 0.0168980;
            data[i] = (b0 + b1 + b2 + b3 + b4 + b5 + b6 + white * 0.5362) * 0.11; b6 = white * 0.115926;
          } else data[i] = white * 0.5;
        }
      }
      return (buffers[kind] = buffer);
    }

    function loop(kind) {
      const src = ctx.createBufferSource();
      src.buffer = noise(kind);
      src.loop = true;
      src.start();
      return src;
    }

    function lfo(target, rate, depth, offset) {
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.frequency.value = rate;
      gain.gain.value = depth;
      target.value = offset;
      osc.connect(gain).connect(target);
      osc.start();
      return osc;
    }

    const RECIPES = {
      ocean(out) {
        const src = loop('brown'), filter = ctx.createBiquadFilter(), swell = ctx.createGain();
        filter.type = 'lowpass';
        lfo(filter.frequency, 0.09, 380, 620);
        lfo(swell.gain, 0.09, 0.35, 0.55);
        src.connect(filter).connect(swell).connect(out);
        return [src];
      },
      rain(out) {
        const src = loop('pink'), hp = ctx.createBiquadFilter(), lp = ctx.createBiquadFilter(), g = ctx.createGain();
        hp.type = 'highpass'; hp.frequency.value = 500;
        lp.type = 'lowpass'; lp.frequency.value = 7500;
        g.gain.value = 0.9;
        src.connect(hp).connect(lp).connect(g).connect(out);
        const drops = setInterval(() => {
          const o = ctx.createOscillator(), dg = ctx.createGain(), t = ctx.currentTime;
          o.type = 'sine'; o.frequency.value = 1800 + Math.random() * 2600;
          dg.gain.setValueAtTime(0.05 * Math.random(), t);
          dg.gain.exponentialRampToValueAtTime(0.0001, t + 0.05);
          o.connect(dg).connect(out); o.start(t); o.stop(t + 0.06);
        }, 90);
        return [src, { stop: () => clearInterval(drops) }];
      },
      brown(out) {
        const src = loop('brown'), lp = ctx.createBiquadFilter();
        lp.type = 'lowpass'; lp.frequency.value = 420;
        src.connect(lp).connect(out);
        return [src];
      },
      forest(out) {
        const src = loop('pink'), lp = ctx.createBiquadFilter(), g = ctx.createGain();
        lp.type = 'lowpass'; lp.frequency.value = 1400; g.gain.value = 0.25;
        src.connect(lp).connect(g).connect(out);
        const chirps = setInterval(() => {
          if (Math.random() < 0.35) return;
          const t = ctx.currentTime, base = 4200 + Math.random() * 600;
          for (let k = 0; k < 3; k++) {
            const o = ctx.createOscillator(), cg = ctx.createGain(), start = t + k * 0.07;
            o.frequency.value = base;
            cg.gain.setValueAtTime(0, start);
            cg.gain.linearRampToValueAtTime(0.025, start + 0.01);
            cg.gain.exponentialRampToValueAtTime(0.0001, start + 0.05);
            o.connect(cg).connect(out); o.start(start); o.stop(start + 0.06);
          }
        }, 420);
        return [src, { stop: () => clearInterval(chirps) }];
      },
      space(out) {
        const nodes = [];
        const lp = ctx.createBiquadFilter();
        lp.type = 'lowpass';
        lfo(lp.frequency, 0.03, 500, 800);
        lp.connect(out);
        [55, 82.41, 110.3, 164.8].forEach((f, i) => {
          const o = ctx.createOscillator(), g = ctx.createGain();
          o.type = i % 2 ? 'triangle' : 'sine';
          o.frequency.value = f;
          o.detune.value = (Math.random() - 0.5) * 12;
          lfo(g.gain, 0.02 + i * 0.013, 0.08, 0.12);
          o.connect(g).connect(lp); o.start();
          nodes.push(o);
        });
        return nodes;
      },
      bowls(out) {
        const strike = () => {
          const t = ctx.currentTime, root = [196, 220, 261.6, 293.7][Math.floor(Math.random() * 4)];
          [1, 2.76, 5.4].forEach((ratio, i) => {
            const o = ctx.createOscillator(), g = ctx.createGain();
            o.frequency.value = root * ratio;
            g.gain.setValueAtTime(0, t);
            g.gain.linearRampToValueAtTime(0.18 / (i + 1), t + 0.03);
            g.gain.exponentialRampToValueAtTime(0.0001, t + 9);
            o.connect(g).connect(out); o.start(t); o.stop(t + 9.2);
          });
        };
        strike();
        const id = setInterval(strike, 8500);
        return [{ stop: () => clearInterval(id) }];
      },
    };

    function start(name, volume = 0.7) {
      if (!ensure() || playing[name]) return false;
      const channel = ctx.createGain();
      channel.gain.setValueAtTime(0, ctx.currentTime);
      channel.gain.linearRampToValueAtTime(volume, ctx.currentTime + 2.5);
      channel.connect(master);
      playing[name] = { channel, nodes: RECIPES[name](channel) };
      return true;
    }
    function stop(name) {
      const p = playing[name];
      if (!p) return;
      delete playing[name];
      const t = ctx.currentTime;
      p.channel.gain.cancelScheduledValues(t);
      p.channel.gain.setValueAtTime(p.channel.gain.value, t);
      p.channel.gain.linearRampToValueAtTime(0, t + 1.5);
      setTimeout(() => { p.nodes.forEach(n => { try { n.stop(); } catch {} }); p.channel.disconnect(); }, 1600);
    }
    function volume(name, value) { if (playing[name]) playing[name].channel.gain.setTargetAtTime(value, ctx.currentTime, 0.2); }
    function stopAll() { Object.keys(playing).forEach(stop); clearTimer(); }
    function clearTimer() { clearTimeout(timerId); timerId = null; timerEnds = 0; if (master) master.gain.setTargetAtTime(0.9, ctx.currentTime, 0.3); }
    function sleepTimer(minutes) {
      clearTimer();
      if (!minutes) return;
      timerEnds = Date.now() + minutes * 60000;
      timerId = setTimeout(() => {
        const t = ctx.currentTime;
        master.gain.setValueAtTime(master.gain.value, t);
        master.gain.linearRampToValueAtTime(0, t + 60);
        setTimeout(() => { stopAll(); renderSoundState(); }, 61000);
      }, Math.max(0, minutes * 60000 - 60000));
    }
    return { start, stop, volume, stopAll, sleepTimer, isPlaying: name => !!playing[name], active: () => Object.keys(playing), timerEnds: () => timerEnds };
  })();

  const SOUNDS = [
    { id: 'ocean', icon: '🌊', name: 'Night ocean', note: 'Slow waves', free: true },
    { id: 'rain', icon: '🌧', name: 'Soft rain', note: 'On a quiet roof' },
    { id: 'brown', icon: '🌫', name: 'Deep hush', note: 'Warm brown noise' },
    { id: 'forest', icon: '🌲', name: 'Night forest', note: 'Crickets and breeze' },
    { id: 'space', icon: '🪐', name: 'Deep space', note: 'Drifting drones' },
    { id: 'bowls', icon: '🔔', name: 'Singing bowls', note: 'Slow resonance' },
  ];

  window.lucidSound = function (id) {
    const sound = SOUNDS.find(s => s.id === id);
    if (!sound) return;
    if (!sound.free && !isLucid()) { openLucid('sounds'); return; }
    if (Sound.isPlaying(id)) Sound.stop(id);
    else {
      if (!isLucid()) Sound.active().forEach(Sound.stop);   // free: one soundscape at a time
      const vol = parseFloat(document.getElementById(`vol-${id}`)?.value || '0.7');
      if (!Sound.start(id, vol)) showToast('Sound is not available in this browser', 'error');
    }
    renderSoundState();
  };
  window.lucidVolume = function (id, value) { Sound.volume(id, parseFloat(value)); };
  window.lucidTimer = function (minutes) {
    if (!isLucid()) { openLucid('sounds'); return; }
    Sound.sleepTimer(Number(minutes));
    showToast(Number(minutes) ? `☾ Sounds will fade out in ${minutes} minutes` : 'Sleep timer off');
    renderSoundState();
  };
  function renderSoundState() {
    SOUNDS.forEach(s => {
      const tile = document.getElementById(`sound-${s.id}`);
      if (tile) { tile.classList.toggle('playing', Sound.isPlaying(s.id)); tile.setAttribute('aria-pressed', Sound.isPlaying(s.id)); }
    });
    const status = document.getElementById('sound-status');
    if (status) {
      const active = Sound.active().map(id => SOUNDS.find(s => s.id === id)?.name).filter(Boolean);
      const ends = Sound.timerEnds();
      status.textContent = active.length ? `Playing ${active.join(' + ')}${ends ? ` · fading at ${new Date(ends).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}` : ''}` : 'Silence';
    }
  }

  // Breathing guide: 4-7-8 or box breathing
  const BREATHS = { calm: [['Breathe in', 4, 'in'], ['Hold', 7, 'hold'], ['Breathe out', 8, 'out']], box: [['Breathe in', 4, 'in'], ['Hold', 4, 'hold'], ['Breathe out', 4, 'out'], ['Rest', 4, 'rest']] };
  let breathTimer = null;
  window.lucidBreath = function (pattern) {
    const orb = document.getElementById('breath-orb');
    const label = document.getElementById('breath-label');
    const btn = document.getElementById('breath-btn');
    if (!orb) return;
    if (breathTimer) {
      clearTimeout(breathTimer); breathTimer = null;
      orb.className = 'breath-orb'; orb.style.transitionDuration = '1.5s';
      label.textContent = 'Ready when you are'; btn.textContent = 'Begin';
      return;
    }
    const steps = BREATHS[pattern || document.getElementById('breath-pattern')?.value || 'calm'];
    let i = 0, rounds = 0;
    btn.textContent = 'Stop';
    const next = () => {
      const [text, secs, phase] = steps[i];
      orb.style.transitionDuration = `${secs}s`;
      orb.className = `breath-orb ${phase}`;
      let left = secs;
      label.textContent = `${text} · ${left}`;
      const tick = setInterval(() => { left--; if (left > 0 && breathTimer) label.textContent = `${text} · ${left}`; else clearInterval(tick); }, 1000);
      i = (i + 1) % steps.length;
      if (i === 0) rounds++;
      breathTimer = setTimeout(rounds >= 8 ? () => { lucidBreath(); document.getElementById('breath-label').textContent = 'Eight rounds complete. Rest now.'; } : next, secs * 1000);
    };
    next();
  };

  // Tonight's intention
  window.lucidSaveIntention = function () {
    const text = document.getElementById('intention-text')?.value.trim();
    const key = intentionKey(localDay(0));
    if (!key) return;
    if (!text) { store.remove(key); showToast('Intention cleared'); return; }
    store.set(key, text.slice(0, 200));
    showToast('☾ Intention set. Repeat it softly as you fall asleep.');
    renderWinddown();
  };
  window.lucidIntentionIdea = function (btn) { const t = document.getElementById('intention-text'); if (t) { t.value = btn.textContent; t.focus(); } };

  // Lucid practice: reality-check reminders while Oneiros is open
  let realityTimer = null;
  function scheduleRealityChecks() {
    clearInterval(realityTimer);
    const minutes = Number(store.get('oneiros_reality_checks') || 0);
    if (!minutes || !isLucid()) return;
    realityTimer = setInterval(() => {
      const text = ['Are you dreaming? Look at your hands and count your fingers.', 'Read something, look away, and read it again. Did it change?', 'Try to breathe through a pinched nose. Can you?'][Math.floor(Math.random() * 3)];
      showToast('✦ Reality check · ' + text);
      if (document.hidden && 'Notification' in window && Notification.permission === 'granted') {
        try { new Notification('Reality check ✦', { body: text, icon: 'assets/icon-192.png', tag: 'reality-check' }); } catch {}
      }
    }, minutes * 60000);
  }
  window.lucidRealityChecks = function (minutes) {
    if (!isLucid()) { openLucid('practice'); renderWinddown(); return; }
    store.set('oneiros_reality_checks', String(minutes));
    scheduleRealityChecks();
    showToast(Number(minutes) ? `✦ A reality check every ${minutes} minutes while Oneiros is open` : 'Reality checks paused');
    if (Number(minutes) && 'Notification' in window && Notification.permission === 'default') Notification.requestPermission();
  };

  const TECHNIQUES = [
    ['Reality checks', 'Several times a day, pause and ask: am I dreaming? Look at your hands, read text twice, or check a clock. The habit follows you into sleep, where the answer can surprise you.'],
    ['MILD: remember to remember', 'As you fall asleep, recall a recent dream and imagine becoming aware inside it. Repeat: “Next time I’m dreaming, I will notice I’m dreaming.”'],
    ['Wake back to bed', 'Set a gentle alarm about five hours after sleeping. Stay awake for 15–30 minutes, thinking about lucid dreaming, then return to sleep. Dreams are longest in the early morning.'],
    ['Dream signs', 'The images that keep returning in your journal are your personal cues. When one appears, let it remind you to check whether you are dreaming.'],
    ['Stay still when you wake', 'Before moving or reaching for your phone, lie still and replay the dream. Movement scatters memory; stillness gathers it.'],
  ];

  function renderWinddown() {
    const host = document.getElementById('winddown-content');
    if (!host || !currentUser) return;
    const intention = store.get(intentionKey(localDay(0))) || '';
    const checks = store.get('oneiros_reality_checks') || '0';
    const ideas = ['Flying over the sea', 'Meeting someone I miss', 'Returning to a childhood home', 'Knowing I am dreaming', 'A place I have never seen'];
    host.innerHTML = `
      <section class="wind-card intention-card">
        <span class="eyebrow">TONIGHT’S INTENTION</span>
        <h2>What would you like to <em>dream of?</em></h2>
        <textarea id="intention-text" rows="2" maxlength="200" placeholder="Tonight I would like to dream of…">${esc(intention)}</textarea>
        <div class="idea-row">${ideas.map(i => `<button type="button" onclick="lucidIntentionIdea(this)">${esc(i)}</button>`).join('')}</div>
        <button class="btn-primary" onclick="lucidSaveIntention()">Set my intention <span>☾</span></button>
        ${intention ? '<p class="settings-info">Tomorrow morning, Home will ask whether it found you.</p>' : ''}
      </section>
      <section class="wind-card breath-card">
        <span class="eyebrow">BREATHE</span>
        <h2>Slow the <em>breath.</em></h2>
        <div class="breath-stage"><div class="breath-orb" id="breath-orb"><span></span></div></div>
        <p class="breath-label" id="breath-label">Ready when you are</p>
        <div class="breath-controls"><select id="breath-pattern" aria-label="Breathing pattern"><option value="calm">4 · 7 · 8 calming breath</option><option value="box">Box breathing 4 · 4 · 4 · 4</option></select>
        <button class="btn-ghost" id="breath-btn" onclick="lucidBreath()">Begin</button></div>
      </section>
      <section class="wind-card sound-card">
        <span class="eyebrow">SOUNDSCAPES</span>
        <h2>Drift on a <em>soundscape.</em></h2>
        <p class="settings-info">Generated live in your browser, never looped recordings.${isLucid() ? ' Layer as many as you like.' : ' Night ocean is free; Lucid opens the whole library, layering and a sleep timer.'}</p>
        <div class="sound-grid">${SOUNDS.map(s => `
          <div class="sound-tile ${!s.free && !isLucid() ? 'locked' : ''}" id="sound-${s.id}">
            <button onclick="lucidSound('${s.id}')" aria-pressed="false"><span class="sound-icon" aria-hidden="true">${s.icon}</span><strong>${s.name}</strong><small>${!s.free && !isLucid() ? '✦ Lucid' : s.note}</small><span class="sound-wave" aria-hidden="true"><i></i><i></i><i></i><i></i></span></button>
            ${s.free || isLucid() ? `<input type="range" id="vol-${s.id}" min="0" max="1" step="0.05" value="0.7" aria-label="${s.name} volume" oninput="lucidVolume('${s.id}', this.value)">` : ''}
          </div>`).join('')}</div>
        <div class="sound-footer"><span id="sound-status">Silence</span>
          <label>Sleep timer <select onchange="lucidTimer(this.value)" aria-label="Sleep timer"><option value="0">Off</option><option value="15">15 min</option><option value="30">30 min</option><option value="45">45 min</option><option value="60">60 min</option><option value="90">90 min</option></select></label>
        </div>
      </section>
      <section class="wind-card practice-card">
        <span class="eyebrow">LUCID PRACTICE</span>
        <h2>Learn to <em>wake inside</em> your dreams.</h2>
        <label class="practice-row">Reality-check reminders ${!isLucid() ? '<small>✦ Lucid</small>' : ''}
          <select onchange="lucidRealityChecks(this.value)" aria-label="Reality check reminders">
            ${[['0', 'Off'], ['30', 'Every 30 minutes'], ['60', 'Every hour'], ['90', 'Every 90 minutes']].map(([v, l]) => `<option value="${v}" ${checks === v && isLucid() ? 'selected' : ''}>${l}</option>`).join('')}
          </select></label>
        <div class="techniques">${TECHNIQUES.map(([title, text]) => `<details><summary>${esc(title)}</summary><p>${esc(text)}</p></details>`).join('')}</div>
      </section>`;
    renderSoundState();
  }

  // ════════════════════════════════════════════════════════════
  // LANDING — public pricing
  // ════════════════════════════════════════════════════════════
  async function renderLandingPlans() {
    const host = document.getElementById('landing-lucid-plans');
    const section = document.getElementById('lucid');
    if (!host || !section) return;
    const p = await loadPricing();
    const navLink = document.querySelector('.landing-nav a[href="#lucid"]');
    const open = !!(p?.plans?.length && p.providers?.length);
    section.hidden = !open;
    if (navLink) navLink.hidden = !open;
    if (!open) return;
    host.innerHTML = p.plans.map(plan => `
      <article class="landing-plan ${plan.featured ? 'featured' : ''}">
        ${plan.featured ? '<span class="lucid-plan-badge">Most loved</span>' : plan.saving_percent >= 15 ? `<span class="lucid-plan-badge quiet">${plan.saving_percent}% less a night</span>` : ''}
        <h3>${esc(plan.name)}</h3>
        <p class="landing-price">${esc(plan.price)}<small> once</small></p>
        <p class="landing-per">${plan.days} nights · ${esc(plan.per_night)} a night</p>
        <button class="${plan.featured ? 'btn-primary' : 'btn-ghost'}" onclick="openLucid('landing','plans',{plan:'${plan.id}'})">Choose ${esc(plan.name.replace('Lucid ', ''))} <span>↗</span></button>
      </article>`).join('');
    document.getElementById('landing-lucid-perks').innerHTML = p.perks.slice(0, 6).map(perk => `<span><b>${esc(perk.icon)}</b> ${esc(perk.title)}</span>`).join('') +
      `<span class="landing-free-note">Free forever: your journal, Dream Canvas, matching, conversations and insights.</span>`;
  }

  // ════════════════════════════════════════════════════════════
  // NAVIGATION AND LIFECYCLE
  // ════════════════════════════════════════════════════════════
  const baseGoTo = window.goTo;
  window.goTo = function (page) {
    baseGoTo(page);
    if (page === 'winddown') renderWinddown();
    if (page === 'profile') renderMembership();
    if (page === 'dashboard') { renderDashSlot(); renderRecallPrompts(); }
    if (page === 'journal') setTimeout(() => setJournalView(store.get('oneiros_journal_view') || 'list'), 0);
  };

  document.addEventListener('oneiros:signed-in', async () => {
    applyLucidState();
    renderRecallPrompts();
    await refreshMembership();
    scheduleRealityChecks();
    handleIntentsSignedIn();
  });
  document.addEventListener('oneiros:signed-out-ready', () => { applyLucidState(); handleIntentsSignedOut(); });
  document.addEventListener('oneiros:signed-out', () => {
    membership = null;
    Sound.stopAll();
    clearInterval(realityTimer);
    document.getElementById('lucid-dialog')?.close();
    ['membership-section', 'appearance-section', 'dash-lucid-slot', 'winddown-content'].forEach(id => { const el = document.getElementById(id); if (el) el.innerHTML = ''; });
    document.body.classList.remove('is-lucid', 'is-patron');
    delete document.body.dataset.theme;
    const chip = document.getElementById('lucid-chip');
    if (chip) chip.hidden = true;
  });
  document.addEventListener('oneiros:dream-saved', () => { if (store.get(intentionKey(localDay(-1)))) store.set(intentionKey(localDay(0)) + '_answered', 'saved'); });

  renderLandingPlans();
})();
