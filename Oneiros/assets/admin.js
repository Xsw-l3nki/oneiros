/* Accessible interaction details shared by the two observatory workspaces. */
let panelRefreshPromise = null;
async function requestPanelApi(path, options = {}) {
  const send = () => fetch(buildApiUrl(path), {...options, headers:{...options.headers, ...(accessToken ? {'Authorization':'Bearer '+accessToken} : {})}});
  let response = await send();
  if (response.status === 404 && useRewriteFallback !== true) { useRewriteFallback = true; response = await send(); }
  if (response.status === 401 && accessToken) {
    if (!panelRefreshPromise) {
      panelRefreshPromise = (typeof tryAdminRestore === 'function' ? tryAdminRestore() : tryRestore()).finally(()=>{ panelRefreshPromise = null; });
    }
    if (await panelRefreshPromise) response = await send();
    else { doLogout(); throw new Error('Your session has ended. Please sign in again.'); }
  }
  const data = await response.json().catch(()=>({error:'The service is temporarily unavailable. Please try again.'}));
  if (!response.ok) throw new Error(data.error || 'Request failed. Please try again.');
  return data;
}
function escHtml(value) {
  return String(value ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
function showChartEmpty(canvas, message) {
  canvas.style.display = 'none';
  let empty = canvas.parentElement.querySelector('.chart-empty');
  if (!empty) { empty = document.createElement('div'); empty.className = 'chart-empty'; canvas.after(empty); }
  empty.textContent = message;
}
function clearChartEmpty(canvas) {
  canvas.style.display = '';
  canvas.parentElement.querySelector('.chart-empty')?.remove();
}
function csvCell(value) {
  let cell = String(value ?? '');
  if (/^[=+@\-\t\r]/.test(cell)) cell = "'" + cell;
  return '"' + cell.replaceAll('"', '""') + '"';
}
function downloadCSV(rows, name) {
  const blob = new Blob(['\uFEFF' + rows.map(row=>row.map(csvCell).join(',')).join('\r\n')], {type:'text/csv;charset=utf-8'});
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url; link.download = name + '-' + new Date().toISOString().slice(0,10) + '.csv';
  document.body.append(link); link.click(); link.remove();
  setTimeout(()=>URL.revokeObjectURL(url),1000);
}
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.sidebar-item,.pill,.guidelines-toggle').forEach(el => {
    el.setAttribute('role', 'button');
    el.tabIndex = 0;
    el.addEventListener('keydown', event => {
      if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); el.click(); }
    });
  });
  document.querySelectorAll('table').forEach(table => {
    if (table.parentElement.classList.contains('table-scroll')) return;
    const wrap = document.createElement('div');
    wrap.className = 'table-scroll';
    wrap.tabIndex = 0;
    wrap.setAttribute('role','region');
    wrap.setAttribute('aria-label', 'Scrollable data table');
    table.before(wrap);
    wrap.append(table);
  });
  document.querySelectorAll('canvas').forEach(canvas => {
    canvas.setAttribute('role', 'img');
    canvas.setAttribute('aria-label', canvas.closest('.chart-card')?.querySelector('.chart-card-title')?.textContent || 'Research chart');
  });
  document.querySelectorAll('input,select,textarea').forEach(input => {
    if (!input.id) return;
    const label = input.parentElement.querySelector('label');
    if (label) label.htmlFor = input.id;
    else if (!input.hasAttribute('aria-label')) input.setAttribute('aria-label', input.getAttribute('placeholder') || input.id.replaceAll('-',' '));
  });
  document.getElementById('login-email')?.setAttribute('autocomplete', 'username');
  document.getElementById('login-password')?.setAttribute('autocomplete', 'current-password');
  document.getElementById('login-error')?.setAttribute('role', 'alert');
  const toast = document.getElementById('toast');
  if (toast) { toast.setAttribute('role', 'status'); toast.setAttribute('aria-live', 'polite'); }
  const overlay = document.getElementById('modal-overlay');
  if (overlay) {
    const modal = overlay.querySelector('.modal');
    modal.setAttribute('role','dialog');
    modal.setAttribute('aria-modal','true');
    modal.setAttribute('aria-labelledby','modal-title');
    let returnFocus = null;
    new MutationObserver(() => {
      if (overlay.classList.contains('open')) {
        returnFocus = document.activeElement;
        modal.querySelector('.modal-cancel')?.focus();
      } else returnFocus?.focus();
    }).observe(overlay,{attributes:true,attributeFilter:['class']});
    overlay.addEventListener('click', event => { if (event.target === overlay) closeModal(); });
    overlay.addEventListener('keydown', event => {
      if (event.key !== 'Tab') return;
      const controls = [...modal.querySelectorAll('button,textarea')].filter(el => el.offsetParent !== null && !el.disabled);
      const first = controls[0], last = controls.at(-1);
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
    });
  }
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      if (typeof closeModal === 'function') closeModal();
      if (typeof closeAdminMenu === 'function') closeAdminMenu();
    }
  });
  const login = document.querySelector('.login-box,.login-card');
  login?.querySelector('#login-email')?.addEventListener('keydown', event => {
    if (event.key === 'Enter') { event.preventDefault(); document.getElementById('login-password')?.focus(); }
  });
});
