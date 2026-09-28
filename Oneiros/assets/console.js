/* Oneiros Console. The API re-checks every permission; hiding a control here is only for clarity. */
'use strict';

const APP_BASE = new URL('.', location.href);
const RT_KEY = 'oneiros_console_rt';
let accessToken = null;
let useFallback = null;           // null = unknown; true = api.php?_route=... (no mod_rewrite)
let me = null;                    // {user, permissions, app_name, version, environment}
let refreshing = null;
const state = { users: { q: '', filter: 'all', page: 1 }, audit: { q: '', action: '', page: 1 } };

// ─── Utilities ───────────────────────────────────────────────
const $ = (sel, root = document) => root.querySelector(sel);
const esc = v => String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
const can = perm => !!me?.permissions.includes(perm);
const fmtNum = n => Number(n || 0).toLocaleString('en-ZA');
const fmtDate = iso => iso ? new Date(iso.includes('T') ? iso : iso.replace(' ', 'T') + 'Z').toLocaleString('en-ZA', { dateStyle: 'medium', timeStyle: 'short' }) : '—';
const money = (cents, sym = 'R') => sym + (cents / 100).toLocaleString('en-ZA', { minimumFractionDigits: cents % 100 ? 2 : 0 });

function toast(message, isError = false) {
  const el = $('#toast');
  el.textContent = message;
  el.classList.toggle('error', isError);
  el.hidden = false;
  clearTimeout(toast.timer);
  toast.timer = setTimeout(() => { el.hidden = true; }, isError ? 7000 : 3500);
}

function apiUrl(path, fallback) {
  const clean = path.replace(/^\//, '');
  if (!fallback) return new URL('api/' + clean, APP_BASE).href;
  const [route, query] = clean.split('?');
  return new URL('api.php?_route=' + route + (query ? '&' + query : ''), APP_BASE).href;
}

async function rawFetch(path, options = {}) {
  const headers = { ...(options.body ? { 'Content-Type': 'application/json' } : {}), ...(accessToken ? { Authorization: 'Bearer ' + accessToken } : {}) };
  const send = fb => fetch(apiUrl(path, fb), { ...options, headers });
  if (useFallback === null) {
    const res = await send(false);
    if (res.status !== 404 || (await res.clone().json().catch(() => null))?.route) { useFallback = false; return res; }
    useFallback = true;
  }
  return send(useFallback);
}

async function api(path, options = {}) {
  if (options.body && typeof options.body !== 'string') options = { ...options, body: JSON.stringify(options.body) };
  let res = await rawFetch(path, options);
  if (res.status === 401 && localStorage.getItem(RT_KEY)) {
    refreshing ??= restoreSession().finally(() => { refreshing = null; });
    if (await refreshing) res = await rawFetch(path, options);
    else { showLogin('Your session ended. Please sign in again.'); throw new Error('Session ended'); }
  }
  const data = await res.json().catch(() => ({ error: 'The server sent an unreadable response (status ' + res.status + ').' }));
  if (!res.ok) throw Object.assign(new Error(data.error || 'Request failed'), { status: res.status, data });
  return data;
}

// ─── Session ─────────────────────────────────────────────────
async function restoreSession() {
  const rt = localStorage.getItem(RT_KEY);
  if (!rt) return false;
  try {
    const res = await rawFetch('/auth/refresh', { method: 'POST', body: JSON.stringify({ refresh_token: rt }) });
    const data = await res.json();
    if (!res.ok) { localStorage.removeItem(RT_KEY); return false; }
    accessToken = data.access_token;
    localStorage.setItem(RT_KEY, data.refresh_token);
    return true;
  } catch { return false; }
}

function showLogin(message = '') {
  accessToken = null;
  me = null;
  $('#app').hidden = true;
  $('#login-screen').hidden = false;
  $('#login-error').textContent = message;
  $('#login-email').focus();
}

async function login(event) {
  event.preventDefault();
  const button = $('#login-form button');
  const email = $('#login-email').value.trim();
  const password = $('#login-password').value;
  if (!email || !password) { $('#login-error').textContent = 'Enter your email and password.'; return; }
  button.disabled = true;
  try {
    const res = await rawFetch('/auth/login', { method: 'POST', body: JSON.stringify({ email, password }) });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error || 'Sign in failed.');
    if (!data.user?.is_admin && !data.user?.is_moderator) throw new Error('This account does not have Console access. Ask an admin to add you as staff.');
    accessToken = data.access_token;
    localStorage.setItem(RT_KEY, data.refresh_token);
    $('#login-password').value = '';
    await start();
  } catch (error) {
    $('#login-error').textContent = error.message;
  } finally { button.disabled = false; }
}

function logout() {
  const rt = localStorage.getItem(RT_KEY);
  if (rt) rawFetch('/auth/logout', { method: 'POST', body: JSON.stringify({ refresh_token: rt }) }).catch(() => {});
  localStorage.removeItem(RT_KEY);
  showLogin();
}

async function start() {
  try { me = await api('/console/me'); }
  catch (error) { showLogin(error.status === 403 ? 'This account does not have Console access.' : error.message); return; }
  $('#login-screen').hidden = true;
  $('#app').hidden = false;
  $('#who').textContent = `${me.user.email} · ${me.user.role}`;
  const env = $('#env-badge');
  env.textContent = `${me.environment} · v${me.version}`;
  env.className = 'badge ' + (me.environment === 'production' ? 'ok' : 'warn');
  document.querySelectorAll('[data-perm]').forEach(el => { el.hidden = !can(el.dataset.perm); });
  route();
}

// ─── Navigation ──────────────────────────────────────────────
const sections = { overview: renderOverview, health: renderHealth, settings: renderSettings, users: renderUsers,
  staff: renderStaff, moderation: renderModeration, email: renderEmail, audit: renderAudit };

function route() {
  if (!me) return;
  let name = location.hash.slice(1).split('/')[0] || 'overview';
  const link = document.querySelector(`.sidebar a[data-section="${name}"]`);
  if (!sections[name] || !link || link.hidden) name = 'overview';
  document.querySelectorAll('.sidebar a[data-section]').forEach(a => a.classList.toggle('active', a.dataset.section === name));
  document.querySelectorAll('.content > section').forEach(s => { s.hidden = s.id !== 'section-' + name; });
  $('#sidebar').classList.remove('open');
  $('#menu-btn').setAttribute('aria-expanded', 'false');
  const el = $('#section-' + name);
  el.innerHTML = '<p class="muted">Loading…</p>';
  sections[name](el).catch(error => { el.innerHTML = `<p class="error">${esc(error.message)}</p>`; });
}

// ─── Dialog ──────────────────────────────────────────────────
/** Opens a dialog. actions: [{label, cls, onClick(form) → Promise<boolean close?>}] */
function openDialog(title, html, actions) {
  const dialog = $('#dialog');
  $('#dialog-title').textContent = title;
  $('#dialog-content').innerHTML = html;
  $('#dialog-error').textContent = '';
  const bar = $('#dialog-actions');
  bar.innerHTML = '';
  for (const action of [...actions, { label: 'Close', cls: 'ghost' }]) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn ' + (action.cls || '');
    button.textContent = action.label;
    button.addEventListener('click', async () => {
      if (!action.onClick) { dialog.close(); return; }
      button.disabled = true;
      try { if (await action.onClick($('#dialog-content')) !== false) dialog.close(); }
      catch (error) { $('#dialog-error').textContent = error.message; }
      finally { button.disabled = false; }
    });
    bar.append(button);
  }
  dialog.showModal();
}

// ─── Overview ────────────────────────────────────────────────
async function renderOverview(el) {
  const o = await api('/console/overview');
  const pill = (label, good, text) => `<span class="badge ${good ? 'ok' : 'warn'}">${esc(label)}: ${esc(text)}</span>`;
  const stat = (num, label) => `<div class="card stat"><div class="num">${esc(num)}</div><div class="label">${esc(label)}</div></div>`;
  const max = Math.max(1, ...o.signups_14d.map(d => d.count));
  const days = [];
  for (let i = 13; i >= 0; i--) {
    const d = new Date(Date.now() - i * 864e5).toISOString().slice(0, 10);
    days.push({ date: d, count: o.signups_14d.find(r => r.date === d)?.count || 0 });
  }
  el.innerHTML = `
    <div class="head"><h2>Overview</h2><span class="spacer"></span><button class="btn small" id="ov-refresh">Refresh</button></div>
    <div class="pills">
      ${pill('Site', !o.status.maintenance_mode, o.status.maintenance_mode ? 'maintenance' : 'live')}
      ${pill('Sign-ups', o.status.registrations_open, o.status.registrations_open ? 'open' : 'closed')}
      ${pill('Email', o.status.mail_enabled, o.status.mail_enabled ? 'sending' : 'off')}
      ${o.content.pending_flags ? `<a class="badge warn" href="#moderation">${o.content.pending_flags} report(s) waiting</a>` : ''}
    </div>
    <div class="cards">
      ${stat(fmtNum(o.users.total), 'Dreamers')}
      ${stat(fmtNum(o.users.online_now), 'Online now')}
      ${stat(fmtNum(o.users.active_24h), 'Active in 24 h')}
      ${stat(fmtNum(o.users.new_24h) + ' / ' + fmtNum(o.users.new_7d), 'New: 24 h / 7 days')}
      ${stat(fmtNum(o.content.dreams_24h), 'Dreams in 24 h')}
      ${stat(fmtNum(o.content.dreams_total), 'Dreams kept')}
      ${stat(fmtNum(o.content.messages_24h), 'Messages in 24 h')}
      ${stat(fmtNum(o.users.lucid), 'Lucid members')}
      ${stat(fmtNum(o.users.suspended), 'Suspended')}
      ${stat(fmtNum(o.email.pending) + (o.email.failed ? ` (+${o.email.failed} failed)` : ''), 'Emails waiting')}
      ${o.finance ? stat(money(o.finance.revenue_30d_cents, o.finance.currency_symbol), `Revenue, 30 days (${o.finance.orders_30d} orders)`) : ''}
    </div>
    <div class="panel">
      <h3>Sign-ups, last 14 days</h3>
      <div class="bars" role="img" aria-label="Sign-ups per day for the last 14 days">
        ${days.map(d => `<span style="height:${Math.round(d.count / max * 100)}%" title="${esc(d.date)}: ${d.count}"></span>`).join('')}
      </div>
      <div class="bar-labels"><span>${esc(days[0].date)}</span><span>today</span></div>
    </div>`;
  $('#ov-refresh').onclick = () => route();
}

// ─── Health ──────────────────────────────────────────────────
async function renderHealth(el) {
  const h = await api('/console/health');
  const groups = {};
  h.checks.forEach(c => (groups[c.group] ??= []).push(c));
  const schemaFail = h.checks.some(c => c.label === 'Schema' && c.status === 'fail');
  el.innerHTML = `
    <div class="head"><h2>Health</h2>
      <span class="badge ${h.overall}">${h.summary.ok} ok · ${h.summary.warn} warnings · ${h.summary.fail} failing</span>
      <span class="spacer"></span>
      ${can('health.migrate') ? `<button class="btn small ${schemaFail ? 'primary' : ''}" id="migrate-btn">Run database migrations</button>` : ''}
      ${can('health.logs') ? '<button class="btn small" id="logs-btn">Error logs</button>' : ''}
      <button class="btn small" id="health-refresh">Check again</button>
    </div>
    <p class="muted small">Checked ${esc(fmtDate(h.checked_at))}. Public check for uptime monitors: <code>${esc(apiUrl('/health', useFallback))}</code></p>
    ${Object.entries(groups).map(([group, checks]) => `
      <div class="panel"><h3>${esc(group)}</h3><div class="table-wrap"><table><tbody>
        ${checks.map(c => `<tr><td style="width:40%"><span class="status-dot ${c.status}" aria-hidden="true"></span>${esc(c.label)}</td>
          <td><span class="sr-only">${c.status}: </span>${esc(c.detail)}</td></tr>`).join('')}
      </tbody></table></div></div>`).join('')}`;
  $('#health-refresh').onclick = () => route();
  $('#migrate-btn')?.addEventListener('click', () => openDialog('Run database migrations',
    '<p>This adds any missing tables and columns. It never deletes data, and running it twice is safe.</p><p class="muted small">Back up the database first if you can (cPanel → Backup).</p>',
    [{ label: 'Run migrations', cls: 'primary', onClick: async () => {
      const r = await api('/console/health?action=migrate', { method: 'POST' });
      toast(r.steps.length ? 'Done: ' + r.steps.join(', ') : 'Already up to date');
      route();
    } }]));
  $('#logs-btn')?.addEventListener('click', async () => {
    const r = await api('/console/health?logs=1');
    openDialog('Error logs', r.logs.length ? r.logs.map(l => `<h3>${esc(l.file)} <span class="muted small">${fmtNum(Math.round(l.size / 1024))} KB · updated ${esc(fmtDate(l.modified))}</span></h3>
      <pre class="log">${esc(l.lines.slice().reverse().join('\n'))}</pre>`).join('') : '<p>No error log files found.</p>', []);
  });
}

// ─── Settings ────────────────────────────────────────────────
let settingsCache = [];

async function renderSettings(el) {
  const data = await api('/console/settings');
  settingsCache = data.settings;
  const groups = {};
  settingsCache.forEach(s => (groups[s.group] ??= []).push(s));
  const tierNote = can('users.lucid') ? '' : '<p class="muted small">As a moderator you can change Operations and Features settings. Other settings are shown for reference; financial settings are hidden.</p>';
  el.innerHTML = `
    <div class="head"><h2>Settings</h2></div>
    ${data.storage_error ? `<p class="error">Settings cannot be saved yet: ${esc(data.storage_error)}. An admin can fix this on the Health page.</p>` : ''}
    ${tierNote}
    <p class="muted small">Values saved here apply immediately and override includes/config.php. “Reset” returns a value to the config file or default. Every change is recorded in the audit log.</p>
    ${Object.entries(groups).map(([group, items]) => `
      <form class="panel settings-group" data-group="${esc(group)}">
        <h3>${esc(group)}</h3>
        ${items.map(settingRow).join('')}
        ${items.some(i => i.editable) ? `<div class="group-actions"><span class="dirty-note" hidden>Unsaved changes</span>
          <button type="button" class="btn ghost small" data-undo>Undo</button><button type="submit" class="btn primary small">Save ${esc(group)}</button></div>` : ''}
      </form>`).join('')}`;
  el.querySelectorAll('.settings-group').forEach(form => {
    form.addEventListener('input', () => { form.querySelector('.dirty-note')?.removeAttribute('hidden'); });
    form.addEventListener('submit', event => { event.preventDefault(); saveGroup(form); });
    form.querySelector('[data-undo]')?.addEventListener('click', () => route());
    form.querySelectorAll('[data-reset]').forEach(b => b.addEventListener('click', () => resetSetting(b.dataset.reset)));
    form.querySelectorAll('[data-add-plan]').forEach(b => b.addEventListener('click', () => {
      b.closest('.control').querySelector('tbody').insertAdjacentHTML('beforeend', planRow('', { name: '', days: 30, price_cents: 5000 }, true));
    }));
    form.addEventListener('click', event => {
      if (event.target.matches('[data-remove-plan]')) { event.target.closest('tr').remove(); form.querySelector('.dirty-note')?.removeAttribute('hidden'); }
    });
  });
}

function planRow(id, plan, editable) {
  const dis = editable ? '' : 'disabled';
  return `<tr>
    <td><input aria-label="Pass id" data-f="id" value="${esc(id)}" ${dis} pattern="[a-z0-9\\-]{2,40}"></td>
    <td><input aria-label="Name" data-f="name" value="${esc(plan.name)}" ${dis}></td>
    <td><input aria-label="Days" data-f="days" type="number" min="1" value="${esc(plan.days)}" ${dis}></td>
    <td><input aria-label="Price in cents" data-f="price_cents" type="number" min="500" value="${esc(plan.price_cents)}" ${dis}></td>
    <td><input aria-label="Featured" data-f="featured" type="checkbox" ${plan.featured ? 'checked' : ''} ${dis}></td>
    <td>${editable ? '<button type="button" class="btn ghost small" data-remove-plan aria-label="Remove pass">✕</button>' : ''}</td></tr>`;
}

function settingRow(s) {
  const id = 'set-' + s.key;
  const dis = s.editable ? '' : 'disabled';
  let control;
  switch (s.type) {
    case 'bool':
      control = `<label class="switch"><input type="checkbox" id="${id}" data-key="${s.key}" ${s.value ? 'checked' : ''} ${dis}> <span>${s.value ? 'On' : 'Off'}</span></label>`;
      break;
    case 'select':
      control = `<select id="${id}" data-key="${s.key}" ${dis}>${s.options.map(o => `<option ${o === s.value ? 'selected' : ''}>${esc(o)}</option>`).join('')}</select>`;
      break;
    case 'int':
      control = `<input id="${id}" data-key="${s.key}" type="number" step="1" min="${s.min ?? ''}" max="${s.max ?? ''}" value="${esc(s.value)}" ${dis}>`;
      break;
    case 'megabytes':
      control = `<input id="${id}" data-key="${s.key}" type="number" step="1" min="${s.min / 1048576}" max="${s.max / 1048576}" value="${esc(Math.round(s.value / 1048576))}" ${dis}>`;
      break;
    case 'text':
      control = `<textarea id="${id}" data-key="${s.key}" maxlength="${s.max ?? 500}" ${dis}>${esc(s.value)}</textarea>`;
      break;
    case 'secret':
      control = `<input id="${id}" data-key="${s.key}" type="password" autocomplete="new-password" placeholder="${s.is_set ? 'Set (' + esc(s.hint) + ') — type to replace' : 'Not set'}" ${dis}>
        ${s.decrypt_failed ? '<span class="error small">The stored value cannot be decrypted (key lost or changed). Enter it again.</span>' : ''}
        ${s.editable && s.is_set ? `<label class="switch small"><input type="checkbox" data-clear="${s.key}"> Clear this secret</label>` : ''}`;
      break;
    case 'cents_list':
      control = `<input id="${id}" data-key="${s.key}" value="${esc((s.value || []).join(', '))}" ${dis}><span class="muted small">Comma-separated, in cents: ${esc((s.value || []).map(c => money(c)).join(', '))}</span>`;
      break;
    case 'plans':
      control = `<div class="table-wrap"><table class="plans" data-key="${s.key}"><thead><tr><th>Id</th><th>Name</th><th>Days</th><th>Price (cents)</th><th>Featured</th><th></th></tr></thead>
        <tbody>${Object.entries(s.value || {}).map(([pid, p]) => planRow(pid, p, s.editable)).join('')}</tbody></table></div>
        ${s.editable ? '<button type="button" class="btn small" data-add-plan>Add a pass</button>' : ''}`;
      break;
    default:
      control = `<input id="${id}" data-key="${s.key}" type="${s.type === 'email' ? 'email' : s.type === 'url' ? 'url' : 'text'}" maxlength="${s.max ?? 500}" value="${esc(s.value)}" ${dis}>`;
  }
  const sourceBadge = { console: 'badge ok', environment: 'badge warn', 'config file': 'badge', default: 'badge' }[s.source];
  return `<div class="setting">
    <div><label for="${id}"><strong>${esc(s.label)}</strong></label>
      ${s.help ? `<div class="help">${esc(s.help)}</div>` : ''}
      <div class="meta"><span class="${sourceBadge}">${esc(s.source)}</span>
        ${!s.editable ? `<span class="badge">${s.source === 'environment' ? 'locked by server' : 'read-only for your role'}</span>` : ''}
        ${s.source === 'console' && s.editable ? `<button type="button" class="btn ghost small" data-reset="${s.key}">Reset</button>` : ''}
        ${s.updated_at && s.source === 'console' ? `<span>saved ${esc(fmtDate(s.updated_at))}</span>` : ''}</div></div>
    <div class="control">${control}</div></div>`;
}

function readSetting(form, s) {
  if (s.type === 'plans') {
    const plans = {};
    form.querySelectorAll(`table[data-key="${s.key}"] tbody tr`).forEach(tr => {
      const f = name => tr.querySelector(`[data-f="${name}"]`);
      const pid = f('id').value.trim();
      if (!pid) throw new Error('Every pass needs an id (for example lucid-30).');
      if (plans[pid]) throw new Error(`Pass id “${pid}” is used twice.`);
      plans[pid] = { name: f('name').value.trim(), days: Number(f('days').value), price_cents: Number(f('price_cents').value), ...(f('featured').checked ? { featured: true } : {}) };
    });
    return plans;
  }
  const input = form.querySelector(`[data-key="${s.key}"]`);
  if (s.type === 'bool') return input.checked;
  if (s.type === 'int') return input.value === '' ? NaN : Number(input.value);
  if (s.type === 'megabytes') return Math.round(Number(input.value) * 1048576);
  if (s.type === 'cents_list') return input.value.split(',').map(v => v.trim()).filter(Boolean).map(Number);
  return input.value;
}

async function saveGroup(form) {
  const changes = {};
  try {
    for (const s of settingsCache.filter(x => x.group === form.dataset.group && x.editable)) {
      if (s.type === 'secret') {
        if (form.querySelector(`[data-clear="${s.key}"]`)?.checked) changes[s.key] = '';
        else if (form.querySelector(`[data-key="${s.key}"]`).value !== '') changes[s.key] = form.querySelector(`[data-key="${s.key}"]`).value;
        continue;
      }
      const value = readSetting(form, s);
      if (JSON.stringify(value) !== JSON.stringify(s.value)) changes[s.key] = value;
    }
  } catch (error) { toast(error.message, true); return; }
  if (!Object.keys(changes).length) { toast('Nothing changed'); return; }
  const risky = ['maintenance_mode', 'frontend_url', 'feature_registrations', 'feature_payments', 'debug'].filter(k => k in changes);
  const doSave = async () => {
    const r = await api('/console/settings', { method: 'PATCH', body: { changes } });
    toast(`Saved ${r.saved.length} setting${r.saved.length === 1 ? '' : 's'}`);
    route();
  };
  if (!risky.length) { try { await doSave(); } catch (error) { toast(error.message, true); } return; }
  openDialog('Confirm change', `<p>These changes take effect for everyone immediately:</p><ul>${risky.map(k => {
    const s = settingsCache.find(x => x.key === k);
    return `<li><strong>${esc(s.label)}</strong> → ${esc(typeof changes[k] === 'boolean' ? (changes[k] ? 'On' : 'Off') : changes[k])}</li>`;
  }).join('')}</ul>`, [{ label: 'Save', cls: 'primary', onClick: doSave }]);
}

function resetSetting(key) {
  const s = settingsCache.find(x => x.key === key);
  openDialog('Reset setting', `<p>Return <strong>${esc(s.label)}</strong> to the value in the config file or the default?</p>`,
    [{ label: 'Reset', cls: 'primary', onClick: async () => {
      await api('/console/settings', { method: 'PATCH', body: { changes: { [key]: null } } });
      toast('Reset'); route();
    } }]);
}

// ─── Users ───────────────────────────────────────────────────
async function renderUsers(el) {
  const s = state.users;
  const r = await api(`/console/users?q=${encodeURIComponent(s.q)}&filter=${s.filter}&page=${s.page}`);
  const roleBadge = u => u.role === 'dreamer' ? '' : `<span class="badge ${u.role === 'admin' ? 'ok' : ''}">${u.role}</span>`;
  el.innerHTML = `
    <div class="head"><h2>Users</h2><span class="muted">${fmtNum(r.total)} found</span></div>
    <form class="filters" id="user-search">
      <input type="search" name="q" placeholder="Search email, name or account id" value="${esc(s.q)}" aria-label="Search users">
      <select name="filter" aria-label="Filter users">${['all', 'active', 'suspended', 'new', 'staff', 'lucid'].map(f => `<option ${f === s.filter ? 'selected' : ''}>${f}</option>`).join('')}</select>
      <button class="btn">Search</button>
    </form>
    <div class="panel table-wrap"><table>
      <thead><tr><th>Account</th><th>Status</th><th>Region</th><th>Last active</th><th>Joined</th></tr></thead>
      <tbody>${r.users.map(u => `<tr class="clickable" tabindex="0" data-id="${esc(u.id)}">
        <td><strong>${esc(u.display_name || '—')}</strong><br><span class="muted small">${esc(u.email)}</span></td>
        <td>${u.is_active ? '<span class="badge ok">active</span>' : '<span class="badge fail">suspended</span>'} ${roleBadge(u)} ${u.is_lucid ? '<span class="badge">Lucid</span>' : ''}</td>
        <td>${esc(u.region || '—')}</td><td>${esc(fmtDate(u.last_active_at))}</td><td>${esc(fmtDate(u.created_at))}</td></tr>`).join('') || '<tr><td colspan="5" class="muted">No accounts match.</td></tr>'}
      </tbody></table>
      <div class="pager"><button class="btn small" id="u-prev" ${r.page <= 1 ? 'disabled' : ''}>Previous</button>
        <span class="muted small">Page ${r.page} of ${r.pages}</span>
        <button class="btn small" id="u-next" ${r.page >= r.pages ? 'disabled' : ''}>Next</button></div></div>`;
  $('#user-search').onsubmit = e => { e.preventDefault(); const f = new FormData(e.target); Object.assign(s, { q: f.get('q').trim(), filter: f.get('filter'), page: 1 }); route(); };
  $('#u-prev').onclick = () => { s.page--; route(); };
  $('#u-next').onclick = () => { s.page++; route(); };
  el.querySelectorAll('tr[data-id]').forEach(tr => {
    tr.onclick = () => openUser(tr.dataset.id);
    tr.onkeydown = e => { if (e.key === 'Enter') openUser(tr.dataset.id); };
  });
}

async function openUser(id) {
  const { user: u } = await api('/console/users?id=' + encodeURIComponent(id));
  const isStaffTarget = u.role !== 'dreamer';
  const mayTouch = !isStaffTarget || me.user.role === 'admin';
  const self = u.id === me.user.id;
  const a = u.activity;
  const html = `
    <dl class="kv">
      <dt>Email</dt><dd>${esc(u.email)}</dd><dt>Name</dt><dd>${esc(u.display_name || '—')}</dd>
      <dt>Role</dt><dd>${esc(u.role)}</dd><dt>Status</dt><dd>${u.is_active ? 'active' : 'suspended'}</dd>
      <dt>Lucid</dt><dd>${u.is_lucid ? 'until ' + esc(fmtDate(u.premium_until)) : 'no'}${u.is_patron ? ' · Patron' : ''}</dd>
      <dt>Region</dt><dd>${esc(u.region || '—')}</dd>
      <dt>Joined</dt><dd>${esc(fmtDate(u.created_at))}</dd><dt>Last active</dt><dd>${esc(fmtDate(u.last_active_at))}</dd>
      <dt>Activity</dt><dd>${a.dreams} dreams (${a.dreams_removed} removed) · ${a.connections} connections · ${a.reports_against} reports against · ${a.sessions} signed-in devices · streak ${a.current_streak}</dd>
      <dt>Account id</dt><dd class="small">${esc(u.id)}</dd>
    </dl>
    ${u.orders ? `<h3>Orders</h3>${u.orders.length ? `<div class="table-wrap"><table><tbody>${u.orders.map(o => `<tr><td>${esc(o.plan_name)}</td><td>${money(o.amount_cents)}</td><td>${esc(o.status)}</td><td>${esc(fmtDate(o.created_at))}</td></tr>`).join('')}</tbody></table></div>` : '<p class="muted small">None</p>'}` : ''}
    <h3>Staff history</h3>${u.history.length ? `<ul class="small">${u.history.map(h => `<li>${esc(fmtDate(h.created_at))} — ${esc(h.summary)} <span class="muted">(${esc(h.actor_email)})</span></li>`).join('')}</ul>` : '<p class="muted small">No staff actions yet.</p>'}
    <label for="u-reason">Reason or note (saved in the audit log)</label><input id="u-reason" maxlength="300">
    ${can('users.edit') ? `<details><summary>Edit details</summary><label for="u-name">Display name</label><input id="u-name" value="${esc(u.display_name || '')}" maxlength="60">
      <label for="u-email">Email</label><input id="u-email" type="email" value="${esc(u.email)}"></details>` : ''}
    ${can('users.lucid') ? `<details><summary>Lucid pass</summary><label for="u-days">Days to add</label><input id="u-days" type="number" min="1" max="3650" value="30"></details>` : ''}
    ${can('staff.manage') && !self ? `<details><summary>Role</summary><label for="u-role">Role</label><select id="u-role">${['dreamer', 'moderator', 'admin'].map(r => `<option ${r === u.role ? 'selected' : ''}>${r}</option>`).join('')}</select></details>` : ''}
    ${can('users.delete') && !self ? `<details><summary>Delete account</summary><p class="small">Deletes the account, dreams, messages and uploaded files. This cannot be undone.</p>
      <label for="u-confirm">Type ${esc(u.email)} to confirm</label><input id="u-confirm" autocomplete="off"></details>` : ''}
    ${!mayTouch ? '<p class="muted small">Only an admin can act on staff accounts.</p>' : ''}`;
  const act = (action, body = {}) => async root => {
    body.reason = root.querySelector('#u-reason')?.value || '';
    const r = await api(`/console/users?id=${encodeURIComponent(u.id)}&action=${action}`, { method: 'POST', body });
    toast(r.deleted ? 'Account deleted' : 'Done');
    if (location.hash.startsWith('#users')) route();
    return true;
  };
  const actions = [];
  if (mayTouch && !self && can('users.suspend')) actions.push(u.is_active ? { label: 'Suspend', cls: 'danger', onClick: act('suspend') } : { label: 'Reactivate', cls: 'primary', onClick: act('reactivate') });
  if (mayTouch && can('users.signout')) actions.push({ label: 'Sign out everywhere', onClick: act('signout') });
  if (can('users.edit')) actions.push({ label: 'Save details', onClick: root => act('edit', { display_name: root.querySelector('#u-name').value, email: root.querySelector('#u-email').value })(root) });
  if (can('users.lucid')) {
    actions.push({ label: 'Add Lucid days', onClick: root => act('lucid_grant', { days: Number(root.querySelector('#u-days').value) })(root) });
    if (u.is_lucid) actions.push({ label: 'End Lucid', onClick: act('lucid_end') });
  }
  if (can('staff.manage') && !self) actions.push({ label: 'Save role', onClick: root => act('role', { role: root.querySelector('#u-role').value })(root) });
  if (can('users.delete') && !self) actions.push({ label: 'Delete', cls: 'danger', onClick: root => act('delete', { confirm_email: root.querySelector('#u-confirm').value })(root) });
  openDialog(u.display_name || u.email, html, actions);
}

// ─── Staff ───────────────────────────────────────────────────
async function renderStaff(el) {
  const r = await api('/console/staff');
  const perms = Object.entries(r.permissions);
  el.innerHTML = `
    <div class="head"><h2>Staff</h2></div>
    <form class="panel" id="add-staff"><h3>Add staff</h3>
      <p class="muted small">The person must already have an Oneiros account. They sign in here with their usual password.</p>
      <div class="filters"><input name="email" type="email" placeholder="their@email" required aria-label="Email">
        <select name="role" aria-label="Role"><option>moderator</option><option>admin</option></select>
        <button class="btn primary">Add</button></div></form>
    <div class="panel table-wrap"><table><thead><tr><th>Person</th><th>Role</th><th>Status</th><th>Last active</th></tr></thead>
      <tbody>${r.staff.map(s => `<tr class="clickable" tabindex="0" data-id="${esc(s.id)}"><td><strong>${esc(s.display_name || '—')}</strong><br><span class="muted small">${esc(s.email)}</span></td>
        <td><span class="badge ${s.role === 'admin' ? 'ok' : ''}">${s.role}</span></td><td>${s.is_active ? 'active' : 'suspended'}</td><td>${esc(fmtDate(s.last_active_at))}</td></tr>`).join('')}</tbody></table>
      <p class="muted small">Select a person to change or remove their role.</p></div>
    <div class="panel table-wrap"><h3>What each role can do</h3><table class="matrix"><thead><tr><th>Permission</th><th>Admin</th><th>Moderator</th></tr></thead>
      <tbody>${perms.map(([p, roles]) => `<tr><td>${esc(p)}</td><td>${roles.includes('admin') ? '✓' : '—'}</td><td>${roles.includes('moderator') ? '✓' : '—'}</td></tr>`).join('')}</tbody></table>
      <p class="muted small">Moderators can change Operations and Features settings only, and never see financial settings or revenue.</p></div>`;
  $('#add-staff').onsubmit = async e => {
    e.preventDefault();
    const f = new FormData(e.target);
    try { const res = await api('/console/staff', { method: 'POST', body: { email: f.get('email').trim(), role: f.get('role') } }); toast(res.message); route(); }
    catch (error) { toast(error.message, true); }
  };
  el.querySelectorAll('tr[data-id]').forEach(tr => { tr.onclick = () => openUser(tr.dataset.id); tr.onkeydown = e => { if (e.key === 'Enter') openUser(tr.dataset.id); }; });
}

// ─── Moderation ──────────────────────────────────────────────
async function renderModeration(el) {
  const r = await api('/admin/flags');
  const pending = r.flags.filter(f => f.status === 'pending');
  el.innerHTML = `
    <div class="head"><h2>Moderation</h2><span class="muted">${pending.length} waiting</span><span class="spacer"></span>
      <a class="btn small" href="oneiros-moderation.php" target="_blank" rel="noopener">Open Care Studio ↗</a></div>
    ${pending.length ? pending.map(f => `<div class="panel" data-id="${esc(f.id)}">
      <p><span class="badge warn">${esc(f.reason)}</span> <span class="muted small">reported ${esc(fmtDate(f.created_at))} · ${f.flag_count} report(s) on this dream</span></p>
      <p>${esc(f.dream_content_preview || '(dream no longer exists)')}</p>
      ${f.notes ? `<p class="muted small">Reporter's note: ${esc(f.notes)}</p>` : ''}
      <label class="small" for="note-${esc(f.id)}">Note to the dreamer (optional)</label><input id="note-${esc(f.id)}" maxlength="255">
      <div class="actions">
        <button class="btn small" data-status="dismissed">Dismiss</button>
        <button class="btn small" data-status="warned">Warn dreamer</button>
        <button class="btn small" data-status="hidden">Make private</button>
        <button class="btn small danger" data-status="actioned">Remove dream</button>
      </div></div>`).join('') : '<p class="panel muted">Nothing waiting. Thank you for keeping Oneiros kind.</p>'}`;
  el.querySelectorAll('[data-status]').forEach(b => b.onclick = async () => {
    const panel = b.closest('[data-id]');
    try {
      await api('/admin/flags/' + encodeURIComponent(panel.dataset.id), { method: 'PATCH', body: { status: b.dataset.status, action_taken: panel.querySelector('input').value } });
      toast('Report updated'); route();
    } catch (error) { toast(error.message, true); }
  });
}

// ─── Email ───────────────────────────────────────────────────
async function renderEmail(el) {
  const r = await api('/console/email');
  el.innerHTML = `
    <div class="head"><h2>Email</h2><span class="badge ${r.enabled ? 'ok' : 'warn'}">${r.enabled ? 'sending from ' + esc(r.from) : 'sending is off'}</span></div>
    <div class="cards">
      <div class="card stat"><div class="num">${fmtNum(r.pending)}</div><div class="label">Waiting</div></div>
      <div class="card stat"><div class="num">${fmtNum(r.sent)}</div><div class="label">Sent</div></div>
      <div class="card stat"><div class="num">${fmtNum(r.failed)}</div><div class="label">Failed</div></div>
    </div>
    <div class="actions"><button class="btn primary" id="mail-send" ${r.enabled && r.pending ? '' : 'disabled'}>Send 50 waiting emails now</button>
      <button class="btn" id="mail-retry" ${r.failed ? '' : 'disabled'}>Retry failed emails</button></div>
    <p class="muted small">Emails normally go out through a cron job: <code>php tools/console.php mail</code> every 5 minutes (see DEPLOY-AFRIHOST.md). Turn sending on in Settings → Email.</p>
    ${r.recent_failures.length ? `<div class="panel table-wrap"><h3>Recent failures</h3><table><thead><tr><th>To</th><th>Subject</th><th>Attempts</th><th>Queued</th></tr></thead><tbody>
      ${r.recent_failures.map(f => `<tr><td>${esc(f.to_email)}</td><td>${esc(f.subject)}</td><td>${f.attempts}</td><td>${esc(fmtDate(f.created_at))}</td></tr>`).join('')}</tbody></table></div>` : ''}`;
  $('#mail-send').onclick = async () => { try { const x = await api('/console/email?action=process', { method: 'POST' }); toast(`Sent ${x.sent}`); route(); } catch (e) { toast(e.message, true); } };
  $('#mail-retry').onclick = async () => { try { const x = await api('/console/email?action=retry', { method: 'POST' }); toast(`Re-queued ${x.requeued}`); route(); } catch (e) { toast(e.message, true); } };
}

// ─── Audit log ───────────────────────────────────────────────
async function renderAudit(el) {
  const s = state.audit;
  const r = await api(`/console/audit?q=${encodeURIComponent(s.q)}&action=${encodeURIComponent(s.action)}&page=${s.page}`);
  const actions = ['', 'settings', 'user', 'staff', 'moderation', 'email', 'health', 'codes', 'orders'];
  el.innerHTML = `
    <div class="head"><h2>Audit log</h2><span class="muted">${fmtNum(r.total)} entries${can('audit.view_all') ? '' : ' (your own actions)'}</span></div>
    <form class="filters" id="audit-search">
      <input type="search" name="q" placeholder="Search text, staff email or target id" value="${esc(s.q)}" aria-label="Search the audit log">
      <select name="action" aria-label="Kind of action">${actions.map(a => `<option value="${a}" ${a === s.action ? 'selected' : ''}>${a || 'all actions'}</option>`).join('')}</select>
      <button class="btn">Search</button></form>
    <div class="panel table-wrap"><table><thead><tr><th>When</th><th>Who</th><th>What</th></tr></thead><tbody>
      ${r.entries.map(e => `<tr><td class="small">${esc(fmtDate(e.created_at))}</td><td class="small">${esc(e.actor_email || 'system')}<br><span class="muted">${esc(e.actor_role)}${e.ip ? ' · ' + esc(e.ip) : ''}</span></td>
        <td><span class="badge">${esc(e.action)}</span> ${esc(e.summary)}${e.details ? `<details><summary class="small muted">details</summary><pre class="log">${esc(JSON.stringify(e.details, null, 2))}</pre></details>` : ''}</td></tr>`).join('') || '<tr><td colspan="3" class="muted">No entries.</td></tr>'}
    </tbody></table>
    <div class="pager"><button class="btn small" id="a-prev" ${r.page <= 1 ? 'disabled' : ''}>Previous</button><span class="muted small">Page ${r.page} of ${r.pages}</span>
      <button class="btn small" id="a-next" ${r.page >= r.pages ? 'disabled' : ''}>Next</button></div></div>`;
  $('#audit-search').onsubmit = e => { e.preventDefault(); const f = new FormData(e.target); Object.assign(s, { q: f.get('q').trim(), action: f.get('action'), page: 1 }); route(); };
  $('#a-prev').onclick = () => { s.page--; route(); };
  $('#a-next').onclick = () => { s.page++; route(); };
}

// ─── Boot ────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
  $('#login-form').addEventListener('submit', login);
  $('#dialog-form').addEventListener('submit', e => e.preventDefault());  // Enter in a field must not close the dialog
  $('#logout-btn').addEventListener('click', logout);
  $('#menu-btn').addEventListener('click', () => {
    const open = $('#sidebar').classList.toggle('open');
    $('#menu-btn').setAttribute('aria-expanded', String(open));
  });
  document.addEventListener('change', e => {
    if (e.target.matches('.switch input[data-key]')) e.target.nextElementSibling.textContent = e.target.checked ? 'On' : 'Off';
  });
  window.addEventListener('hashchange', route);
  if (await restoreSession()) await start();
  else showLogin();
});
