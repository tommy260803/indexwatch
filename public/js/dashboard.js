// ===================== INDEXWATCH DASHBOARD v2 =====================

// Global state
let INDEX_DATA = [];
let currentFilter = 'all';
let currentSearch = '';
let sortKey = 'frag';
let sortDir = -1;
let dashboardPollController = null;
let isDashboardActive = false;

const FRAG_STATUS = f => f > 30 ? 'crit' : f >= 5 ? 'warn' : 'ok';
const STATUS_COLOR = {crit:'var(--crit)', warn:'var(--warn)', ok:'var(--ok)'};

// ===================== UTILITIES =====================
function showToast(msg) {
  const el = document.getElementById('toast');
  const msgEl = document.getElementById('toastMsg');
  if (!el || !msgEl) return;
  msgEl.textContent = msg;
  el.classList.add('show');
  clearTimeout(window._toastTimer);
  window._toastTimer = setTimeout(() => el.classList.remove('show'), 3000);
}

/** Escape HTML to prevent XSS */
function escapeHtml(str) {
  if (!str) return '';
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

// ===================== DASHBOARD DATA FETCH =====================
async function fetchDashboardData() {
  if (!isDashboardActive) return;
  
  const kpiTotal = document.getElementById('kpi-total');
  const kpiCritical = document.getElementById('kpi-critical');
  const kpiWarning = document.getElementById('kpi-warning');
  const kpiOk = document.getElementById('kpi-ok');
  const alertFeed = document.getElementById('alertFeed');
  const indexTableBody = document.getElementById('indexTableBody');

  // Show loading state
  if (alertFeed && !alertFeed.querySelector('.alert-item')) {
    alertFeed.innerHTML = '<div class="loading-state"><span>Cargando alertas...</span></div>';
  }

  try {
    const res = await fetch('/api/dashboard/data', { signal: dashboardPollController?.signal });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const data = await res.json();

    // Update KPIs
    if (kpiTotal) kpiTotal.textContent = data.kpis?.total ?? 0;
    if (kpiCritical) kpiCritical.textContent = data.kpis?.critical ?? 0;
    if (kpiWarning) kpiWarning.textContent = data.kpis?.warning ?? 0;
    if (kpiOk) kpiOk.textContent = data.kpis?.ok ?? 0;

    // Update donut
    const donut = document.getElementById('donut-total');
    const legendOk = document.getElementById('legend-ok');
    const legendWarn = document.getElementById('legend-warning');
    const legendCrit = document.getElementById('legend-critical');
    if (donut) donut.textContent = data.kpis?.total ?? 0;
    if (legendOk) legendOk.textContent = data.kpis?.ok ?? 0;
    if (legendWarn) legendWarn.textContent = data.kpis?.warning ?? 0;
    if (legendCrit) legendCrit.textContent = data.kpis?.critical ?? 0;

    // Update alerts with safe render
    if (alertFeed && data.alerts) {
      alertFeed.innerHTML = '';
      if (data.alerts.length === 0) {
        alertFeed.innerHTML = '<div class="empty-state">No hay alertas activas</div>';
      } else {
        data.alerts.forEach(a => {
          const severityColor = a.severity === 'critical' ? 'var(--crit)' :
                                a.severity === 'warning' ? 'var(--warn)' : 'var(--ok)';
          const item = document.createElement('div');
          item.className = 'alert-item';
          item.innerHTML = `
            <span class="alert-dot" style="background:${severityColor}"></span>
            <div class="alert-body">
              <div class="alert-text">${escapeHtml(a.text || '')}</div>
              <div class="alert-time">${escapeHtml(a.time_ago || '')}</div>
            </div>`;
          alertFeed.appendChild(item);
        });
      }
    }

    // Update index table data
    if (data.indexes) {
      INDEX_DATA = data.indexes;
      if (indexTableBody) renderTable();
    }

  } catch (e) {
    if (e.name === 'AbortError') return; // Ignore aborted requests
    console.error('Error fetching dashboard data:', e);
    if (alertFeed) {
      alertFeed.innerHTML = '<div class="error-state">Error al cargar datos</div>';
    }
  }
}

// ===================== INDEX TABLE =====================
function renderTable() {
  const tbody = document.getElementById('indexTableBody');
  const footCount = document.getElementById('tableFootCount');
  if (!tbody || !footCount) return;

  let rows = INDEX_DATA.filter(r => {
    const st = FRAG_STATUS(r.frag);
    if (currentFilter !== 'all' && st !== currentFilter) return false;
    const q = currentSearch.toLowerCase();
    if (q && !r.index?.toLowerCase().includes(q) && !r.table?.toLowerCase().includes(q)) return false;
    return true;
  });

  rows.sort((a,b) => {
    const av = sortKey === 'frag' ? (a.frag||0) : (a.size||0);
    const bv = sortKey === 'frag' ? (b.frag||0) : (b.size||0);
    return (av - bv) * sortDir;
  });

  tbody.innerHTML = '';
  if (rows.length === 0) {
    tbody.innerHTML = '<tr><td colspan="7" class="empty-state">No se encontraron índices</td></tr>';
    footCount.textContent = 'Sin resultados';
    return;
  }

  rows.forEach(r => {
    const status = FRAG_STATUS(r.frag);
    const color = STATUS_COLOR[status];
    const badge = r.action === 'OK' ? 'ok' : r.action === 'REORGANIZE' ? 'warn' : 'crit';

    const tr = document.createElement('tr');
    tr.onclick = () => openDrawer(r);
    tr.innerHTML = `
      <td class="cell-checkbox"><input type="checkbox" class="row-check" onclick="event.stopPropagation()" onchange="updateBulkBar()"></td>
      <td class="cell-table">${escapeHtml(r.table || '')}</td>
      <td class="cell-index">${escapeHtml(r.index || '')}</td>
      <td>
        <div class="frag-cell">
          <span class="frag-pct" style="color:${color}">${(r.frag||0).toFixed(1)}%</span>
          <div class="frag-bar-track"><div class="frag-bar-fill" style="width:${Math.min(r.frag||0,100)}%;background:${color}"></div></div>
        </div>
      </td>
      <td class="cell-index">${r.size || 0}</td>
      <td class="cell-date">${escapeHtml(r.lastReorg || 'N/A')}</td>
      <td><span class="badge ${badge}">${escapeHtml(r.action || 'OK')}</span></td>`;
    tbody.appendChild(tr);
  });

  footCount.textContent = `Mostrando ${rows.length} de ${INDEX_DATA.length} índices`;
  updateBulkBar();
}

function updateBulkBar() {
  const bulkCount = document.getElementById('bulkCount');
  const bulkBar = document.getElementById('bulkBar');
  if (!bulkCount || !bulkBar) return;
  const checked = document.querySelectorAll('.row-check:checked').length;
  bulkCount.textContent = checked;
  bulkBar.classList.toggle('show', checked > 0);
}

function openDrawer(r) {
  const ids = ['drawer','drawerOverlay','drawerTable','drawerIndexName','drawerFrag','drawerSize','drawerLastReorg','drawerAction'];
  const [drawer, overlay, dTable, dName, dFrag, dSize, dLast, dAction] = ids.map(id => document.getElementById(id));
  if (!drawer || !overlay) return;

  if (dTable) dTable.textContent = r?.table || '';
  if (dName) dName.textContent = r?.index || '';
  if (dFrag) { dFrag.textContent = (r?.frag||0).toFixed(1) + '%'; dFrag.style.color = STATUS_COLOR[FRAG_STATUS(r?.frag)]; }
  if (dSize) dSize.textContent = (r?.size||0) + ' MB';
  if (dLast) dLast.textContent = r?.lastReorg || 'N/A';
  if (dAction) dAction.textContent = r?.action || 'OK';

  drawer.classList.add('show');
  overlay.classList.add('show');
}

function closeDrawer() {
  document.getElementById('drawer')?.classList.remove('show');
  document.getElementById('drawerOverlay')?.classList.remove('show');
}

// ===================== INITIALIZATION =====================
document.addEventListener('DOMContentLoaded', function() {
  isDashboardActive = window.location.pathname === '/dashboard' || window.location.pathname === '/indices';
  
  if (isDashboardActive) {
    dashboardPollController = new AbortController();
    fetchDashboardData();
    setInterval(fetchDashboardData, 30000);
  }

  // Search/filter/sort listeners
  document.getElementById('searchInput')?.addEventListener('input', e => { currentSearch = e.target.value; renderTable(); });
  document.querySelectorAll('#filterPills .pill').forEach(p => {
    p.addEventListener('click', () => {
      document.querySelectorAll('#filterPills .pill').forEach(x => x.classList.remove('active'));
      p.classList.add('active');
      currentFilter = p.dataset.filter;
      renderTable();
    });
  });
  document.querySelectorAll('th.sortable').forEach(th => {
    th.addEventListener('click', () => {
      const key = th.dataset.sort;
      sortDir = sortKey === key ? sortDir * -1 : -1;
      sortKey = key;
      renderTable();
    });
  });

  document.getElementById('selectAll')?.addEventListener('change', e => {
    document.querySelectorAll('.row-check').forEach(c => c.checked = e.target.checked);
    updateBulkBar();
  });

  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDrawer(); });
  
  // Abort polling when leaving page
  window.addEventListener('beforeunload', () => {
    dashboardPollController?.abort();
  });
});