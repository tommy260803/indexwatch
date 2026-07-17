// ===================== UTILIDADES COMUNES =====================
function showToast(msg){
  const toastMsgEl = document.getElementById('toastMsg');
  const toastEl = document.getElementById('toast');
  if (toastMsgEl && toastEl) {
    toastMsgEl.textContent = msg;
    toastEl.classList.add('show');
    clearTimeout(window._toastTimer);
    window._toastTimer = setTimeout(() => toastEl.classList.remove('show'), 3000);
  }
}

// ===================== CENTRO DE OPERACIONES (solo para /actions =====================
const queueItems = [
  { table: 'Ventas.dbo.Pedidos', index: 'IX_Pedidos_Fecha', frag: 38.4, action: 'REBUILD' },
  { table: 'CRM.dbo.Clientes', index: 'IX_Clientes_Email', frag: 34.1, action: 'REBUILD' },
  { table: 'Inventario.dbo.Stock', index: 'IX_Inventario_SKU', frag: 22.7, action: 'REORGANIZE' }
];

function updateAction(selectElement, index) {
  const badge = document.getElementById('badge-' + index);
  if (!badge) return;
  const newAction = selectElement.value;
  badge.textContent = newAction;
  badge.className = 'badge ' + (newAction === 'REBUILD' ? 'crit' : newAction === 'REORGANIZE' ? 'warn' : 'ok');
  queueItems[index].action = newAction;
  updateCounters();
  updateScript();
}

function removeItem(index) {
  if (confirm('¿Eliminar este índice de la cola?')) {
    const row = document.querySelector(`.sel-row[data-index="${index}"]`);
    if (row) row.remove();
    updateCounters();
    updateScript();
    showToast('Índice eliminado de la cola');
  }
}

function addDemoIndices() {
  const existingNames = queueItems.map(item => item.index);
  const demoExtra = { table: 'Ventas.dbo.Facturas', index: 'IX_Facturas_NumDoc', frag: 2.1, action: 'UPDATE STATISTICS' };
  if (!existingNames.includes(demoExtra.index)) {
    queueItems.push(demoExtra);
    const list = document.getElementById('opsSelectionList');
    if (!list) return;
    const newRow = document.createElement('div');
    newRow.className = 'sel-row';
    newRow.dataset.index = queueItems.length - 1;
    newRow.innerHTML = `
      <span class="name"><span class="tbl">${demoExtra.table}.</span>${demoExtra.index}</span>
      <span style="font-size:12px;color:var(--text-faint);margin-right:4px;">Fragmentación: ${demoExtra.frag}%</span>
      <select onchange="updateAction(this, ${queueItems.length - 1})" style="background:var(--panel-2);border:1px solid var(--border);border-radius:5px;padding:4px 8px;color:var(--text);font-size:12px;font-family:var(--mono);">
        <option value="REBUILD">REBUILD</option>
        <option value="REORGANIZE">REORGANIZE</option>
        <option value="UPDATE STATISTICS" selected>UPDATE STATISTICS</option>
      </select>
      <span class="badge ok" id="badge-${queueItems.length - 1}">UPDATE STATISTICS</span>
      <button onclick="removeItem(${queueItems.length - 1})" style="background:none;border:none;color:var(--text-faint);cursor:pointer;padding:4px;border-radius:4px;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    `;
    list.appendChild(newRow);
    updateCounters();
    updateScript();
    showToast('Índice añadido a la cola (simulado)');
  } else {
    showToast('El índice ya está en la cola');
  }
}

function clearQueue() {
  if (queueItems.length === 0) {
    showToast('La cola ya está vacía');
    return;
  }
  if (confirm('¿Eliminar todos los índices de la cola?')) {
    const list = document.getElementById('opsSelectionList');
    if (!list) return;
    list.innerHTML = `
      <div style="color:var(--text-faint);padding:12px;text-align:center;font-size:13px;">
        No hay índices en cola. Añade desde la lista de índices.
      </div>
    `;
    queueItems.length = 0;
    updateCounters();
    updateScript();
    showToast('Cola vaciada');
  }
}

function updateCounters() {
  let rebuild = 0, reorg = 0, stats = 0;
  queueItems.forEach(item => {
    if (item.action === 'REBUILD') rebuild++;
    else if (item.action === 'REORGANIZE') reorg++;
    else if (item.action === 'UPDATE STATISTICS') stats++;
  });
  const rebuildCountEl = document.getElementById('rebuildCount');
  const reorgCountEl = document.getElementById('reorgCount');
  const statsCountEl = document.getElementById('statsCount');
  const queueCountEl = document.getElementById('queueCount');
  const queueSummaryEl = document.getElementById('queueSummary');
  if (rebuildCountEl) rebuildCountEl.textContent = rebuild + ' índice' + (rebuild !== 1 ? 's' : '');
  if (reorgCountEl) reorgCountEl.textContent = reorg + ' índice' + (reorg !== 1 ? 's' : '');
  if (statsCountEl) statsCountEl.textContent = stats + ' índice' + (stats !== 1 ? 's' : '');
  if (queueCountEl) queueCountEl.textContent = queueItems.length;
  if (queueSummaryEl) queueSummaryEl.textContent = `REBUILD: ${rebuild} · REORGANIZE: ${reorg} · STATS: ${stats}`;
}

function updateScript() {
  const box = document.getElementById('scriptBox');
  if (!box) return;
  if (queueItems.length === 0) {
    box.innerHTML = `<span class="cm">-- No hay índices en cola. Añade algunos para generar el script.</span>`;
    return;
  }
  let lines = [`<span class="cm">-- Generado por IndexWatch · ${new Date().toLocaleString()}</span>`];
  queueItems.forEach(item => {
    const parts = item.table.split('.');
    const db = parts[0] || 'dbo';
    const schema = parts[1] || 'dbo';
    const tableName = parts[2] || item.table;
    let sql = '';
    if (item.action === 'REORGANIZE') {
      sql = `ALTER INDEX [${item.index}] ON [${db}].[${schema}].[${tableName}] REORGANIZE;`;
    } else if (item.action === 'REBUILD') {
      sql = `ALTER INDEX [${item.index}] ON [${db}].[${schema}].[${tableName}] REBUILD WITH (ONLINE = ON, FILLFACTOR = 90);`;
    } else if (item.action === 'UPDATE STATISTICS') {
      sql = `UPDATE STATISTICS [${db}].[${schema}].[${tableName}] [${item.index}];`;
    }
    lines.push(`<span class="kw">${sql}</span>`);
    lines.push(`<span class="cm">GO</span>`);
  });
  box.innerHTML = lines.join('\n');
}

function copyScript(){
  const textBox = document.getElementById('scriptBox');
  if (!textBox) return;
  const text = textBox.innerText;
  if(navigator.clipboard){
    navigator.clipboard.writeText(text).then(() => showToast('Script copiado al portapapeles'));
  } else {
    showToast('Script copiado al portapapeles');
  }
}

// ===================== LISTA DE ÍNDICES (solo para /indices =====================
let INDEX_DATA = [];
const FRAG_STATUS = f => f > 30 ? 'crit' : f >= 5 ? 'warn' : 'ok';
const STATUS_COLOR = {crit:'var(--crit)', warn:'var(--warn)', ok:'var(--ok)'};
let currentFilter = 'all';
let currentSearch = '';
let sortKey = 'frag';
let sortDir = -1;

function renderTable(){
  const tbody = document.getElementById('indexTableBody');
  const tableFootCount = document.getElementById('tableFootCount');
  if (!tbody || !tableFootCount) return;
  let rows = INDEX_DATA.filter(r => {
    const status = FRAG_STATUS(r.frag);
    if(currentFilter !== 'all' && status !== currentFilter) return false;
    const q = currentSearch.toLowerCase();
    if(q && !r.index.toLowerCase().includes(q) && !r.table.toLowerCase().includes(q)) return false;
    return true;
  });
  rows.sort((a,b) => {
    let av = sortKey === 'frag' ? a.frag : a.size;
    let bv = sortKey === 'frag' ? b.frag : b.size;
    return (av - bv) * sortDir;
  });
  tbody.innerHTML = rows.map((r, i) => {
    const status = FRAG_STATUS(r.frag);
    const color = STATUS_COLOR[status];
    const actionBadge = r.action === 'OK' ? 'ok' : r.action === 'REORGANIZE' ? 'warn' : 'crit';
    const seed = r.index.length;
    const bars = Array.from({length:8}, (_,k) => 4 + ((seed*7+k*13) % 14));
    const sparkBars = bars.map(h => `<span style="height:${h}px;background:${color};opacity:${0.45 + (h/18)*0.55}"></span>`).join('');
    return `
    <tr onclick="openDrawer(${INDEX_DATA.indexOf(r)})">
      <td class="cell-checkbox" onclick="event.stopPropagation()"><input type="checkbox" class="row-check" data-idx="${INDEX_DATA.indexOf(r)}" onchange="updateBulkBar()"></td>
      <td class="cell-table">${r.table}</td>
      <td class="cell-index">${r.index}</td>
      <td>
        <div class="frag-cell">
          <span class="frag-pct" style="color:${color}">${r.frag.toFixed(1)}%</span>
          <div class="frag-bar-track"><div class="frag-bar-fill" style="width:${Math.min(r.frag,100)}%;background:${color}"></div></div>
          <div class="pulse-spark">${sparkBars}</div>
        </div>
      </td>
      <td class="cell-index">${r.size}</td>
      <td class="cell-date">${r.lastReorg}</td>
      <td><span class="badge ${actionBadge}">${r.action}</span></td>
    </tr>`;
  }).join('');
  tableFootCount.textContent = `Mostrando ${rows.length} de ${INDEX_DATA.length} índices`;
  updateBulkBar();
}

function updateBulkBar(){
  const bulkCount = document.getElementById('bulkCount');
  const bulkBar = document.getElementById('bulkBar');
  if (!bulkCount || !bulkBar) return;
  const checked = document.querySelectorAll('.row-check:checked').length;
  bulkCount.textContent = checked;
  bulkBar.classList.toggle('show', checked > 0);
}

function openDrawer(idx){
  const drawerTable = document.getElementById('drawerTable');
  const drawerIndexName = document.getElementById('drawerIndexName');
  const drawerFrag = document.getElementById('drawerFrag');
  const drawerSize = document.getElementById('drawerSize');
  const drawerLastReorg = document.getElementById('drawerLastReorg');
  const drawerAction = document.getElementById('drawerAction');
  const drawer = document.getElementById('drawer');
  const drawerOverlay = document.getElementById('drawerOverlay');
  if (!drawerTable || !drawerIndexName || !drawerFrag || !drawerSize || !drawerLastReorg || !drawerAction || !drawer || !drawerOverlay) return;
  const r = INDEX_DATA[idx];
  if (!r) return;
  const status = FRAG_STATUS(r.frag);
  const color = STATUS_COLOR[status];
  const actionBadge = r.action === 'OK' ? 'ok' : r.action === 'REORGANIZE' ? 'warn' : 'crit';
  drawerTable.textContent = r.table;
  drawerIndexName.textContent = r.index;
  drawerFrag.textContent = r.frag.toFixed(1) + '%';
  drawerFrag.style.color = color;
  drawerSize.textContent = r.size + ' MB';
  drawerLastReorg.textContent = r.lastReorg;
  drawerAction.textContent = r.action;
  drawerAction.style.color = color;
  drawer.classList.add('show');
  drawerOverlay.classList.add('show');
}

function closeDrawer(){
  const drawer = document.getElementById('drawer');
  const drawerOverlay = document.getElementById('drawerOverlay');
  if (!drawer || !drawerOverlay) return;
  drawer.classList.remove('show');
  drawerOverlay.classList.remove('show');
}

// ===================== AJUSTES (solo para /settings =====================
function updateThreshold(which){
  if(which === 'reorg'){
    const reorgThreshold = document.getElementById('reorgThreshold');
    const reorgVal = document.getElementById('reorgVal');
    if (reorgThreshold && reorgVal) {
      reorgVal.textContent = reorgThreshold.value + '%';
    }
  } else {
    const rebuildThreshold = document.getElementById('rebuildThreshold');
    const rebuildVal = document.getElementById('rebuildVal');
    if (rebuildThreshold && rebuildVal) {
      rebuildVal.textContent = rebuildThreshold.value + '%';
    }
  }
}

// ===================== DATOS DEL DASHBOARD =====================
async function fetchDashboardData() {
  try {
    const res = await fetch('/api/dashboard/data');
    const data = await res.json();
    
    // Update KPIs if elements exist
    if(document.getElementById('kpi-total')) document.getElementById('kpi-total').textContent = data.kpis.total;
    if(document.getElementById('kpi-critical')) document.getElementById('kpi-critical').textContent = data.kpis.critical;
    if(document.getElementById('kpi-warning')) document.getElementById('kpi-warning').textContent = data.kpis.warning;
    if(document.getElementById('kpi-ok')) document.getElementById('kpi-ok').textContent = data.kpis.ok;
    
    // Update Legends if elements exist
    if(document.getElementById('donut-total')) document.getElementById('donut-total').textContent = data.kpis.total;
    if(document.getElementById('legend-ok')) document.getElementById('legend-ok').textContent = data.kpis.ok;
    if(document.getElementById('legend-warning')) document.getElementById('legend-warning').textContent = data.kpis.warning;
    if(document.getElementById('legend-critical')) document.getElementById('legend-critical').textContent = data.kpis.critical;
    
    // Update Alerts if element exists
    const alertFeed = document.getElementById('alertFeed');
    if (alertFeed) {
        let html = '';
        data.alerts.forEach(a => {
            let colorVar = a.status === 'CRITICAL' ? 'var(--crit)' : a.status === 'WARNING' ? 'var(--warn)' : 'var(--ok)';
            html += `
            <div class="alert-item">
              <span class="alert-dot" style="background:${colorVar}"></span>
              <div class="alert-body">
                <div class="alert-text">${a.text}</div>
                <div class="alert-time">${a.time_ago}</div>
              </div>
            </div>`;
        });
        alertFeed.innerHTML = html;
    }

    // Update Table Data if elements exist
    INDEX_DATA = data.indexes;
    renderTable();
    
  } catch(e) {
    console.error("Error fetching dashboard data:", e);
  }
}

// ===================== INICIALIZACIÓN =====================
document.addEventListener('DOMContentLoaded', function() {
  // Inicializar datos del dashboard y lista de índices
  fetchDashboardData();
  
  // Solo actualizar cada 30s si estamos en el dashboard o indices
  if (window.location.pathname === '/dashboard' || window.location.pathname === '/indices') {
    setInterval(fetchDashboardData, 30000); // Cambiado a 30s para no spamear
  }
  
  // Inicializar contadores y script para centro de operaciones
  if (document.getElementById('rebuildCount') || document.getElementById('opsSelectionList')) {
    updateCounters();
    updateScript();
  }

  // Event listeners para lista de índices
  const searchInput = document.getElementById('searchInput');
  if (searchInput) {
    searchInput.addEventListener('input', e => {
      currentSearch = e.target.value;
      renderTable();
    });
  }

  const filterPills = document.querySelectorAll('#filterPills .pill');
  filterPills.forEach(pill => {
    pill.addEventListener('click', () => {
      document.querySelectorAll('#filterPills .pill').forEach(p => p.classList.remove('active'));
      pill.classList.add('active');
      currentFilter = pill.dataset.filter;
      renderTable();
    });
  });

  const sortableThs = document.querySelectorAll('th.sortable');
  sortableThs.forEach(th => {
    th.addEventListener('click', () => {
      const key = th.dataset.sort;
      if(sortKey === key){ sortDir *= -1; } else { sortKey = key; sortDir = -1; }
      renderTable();
    });
  });

  const selectAll = document.getElementById('selectAll');
  if (selectAll) {
    selectAll.addEventListener('change', e => {
      document.querySelectorAll('.row-check').forEach(c => c.checked = e.target.checked);
      updateBulkBar();
    });
  }

  // Cerrar drawer con Escape
  document.addEventListener('keydown', e => { if(e.key === 'Escape') closeDrawer(); });
});

