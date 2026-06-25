// ===================== CENTRO DE OPERACIONES (MAQUETADO) =====================

// Datos de ejemplo de la cola (simulados)
const queueItems = [
  { table: 'Ventas.dbo.Pedidos', index: 'IX_Pedidos_Fecha', frag: 38.4, action: 'REBUILD' },
  { table: 'CRM.dbo.Clientes', index: 'IX_Clientes_Email', frag: 34.1, action: 'REBUILD' },
  { table: 'Inventario.dbo.Stock', index: 'IX_Inventario_SKU', frag: 22.7, action: 'REORGANIZE' }
];

// Función para actualizar la acción de un elemento
function updateAction(selectElement, index) {
  const newAction = selectElement.value;
  const badge = document.getElementById('badge-' + index);
  const row = selectElement.closest('.sel-row');
  // Actualizar badge
  badge.textContent = newAction;
  badge.className = 'badge ' + (newAction === 'REBUILD' ? 'crit' : newAction === 'REORGANIZE' ? 'warn' : 'ok');
  // Actualizar datos internos (solo para el maquetado)
  queueItems[index].action = newAction;
  // Actualizar contadores y script
  updateCounters();
  updateScript();
}

// Función para eliminar un elemento
function removeItem(index) {
  if (confirm('¿Eliminar este índice de la cola?')) {
    const row = document.querySelector(`.sel-row[data-index="${index}"]`);
    if (row) row.remove();
    // Actualizar contadores y script
    updateCounters();
    updateScript();
    showToast('Índice eliminado de la cola');
  }
}

// Función para añadir índices de ejemplo (simula "Añadir seleccionados")
function addDemoIndices() {
  // Solo para demostración, añadimos un índice extra si no existe ya
  const existingNames = queueItems.map(item => item.index);
  const demoExtra = { table: 'Ventas.dbo.Facturas', index: 'IX_Facturas_NumDoc', frag: 2.1, action: 'UPDATE STATISTICS' };
  if (!existingNames.includes(demoExtra.index)) {
    queueItems.push(demoExtra);
    // Crear la fila en el DOM
    const list = document.getElementById('opsSelectionList');
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
      <button onclick="removeItem(${queueItems.length - 1})" style="background:none;border:none;color:var(--text-faint);cursor:pointer;padding:4px;border-radius:4px;hover:background:var(--panel-2);">
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

// Función para limpiar toda la cola
function clearQueue() {
  if (queueItems.length === 0) {
    showToast('La cola ya está vacía');
    return;
  }
  if (confirm('¿Eliminar todos los índices de la cola?')) {
    const list = document.getElementById('opsSelectionList');
    list.innerHTML = `
      <div style="color:var(--text-faint);padding:12px;text-align:center;font-size:13px;">
        No hay índices en cola. Añade desde la lista de índices.
      </div>
    `;
    // Vaciar array y actualizar
    queueItems.length = 0;
    updateCounters();
    updateScript();
    showToast('Cola vaciada');
  }
}

// Función para actualizar los contadores de las tarjetas
function updateCounters() {
  let rebuild = 0, reorg = 0, stats = 0;
  queueItems.forEach(item => {
    if (item.action === 'REBUILD') rebuild++;
    else if (item.action === 'REORGANIZE') reorg++;
    else if (item.action === 'UPDATE STATISTICS') stats++;
  });
  document.getElementById('rebuildCount').textContent = rebuild + ' índice' + (rebuild !== 1 ? 's' : '');
  document.getElementById('reorgCount').textContent = reorg + ' índice' + (reorg !== 1 ? 's' : '');
  document.getElementById('statsCount').textContent = stats + ' índice' + (stats !== 1 ? 's' : '');
  document.getElementById('queueCount').textContent = queueItems.length;
  document.getElementById('queueSummary').textContent = `REBUILD: ${rebuild} · REORGANIZE: ${reorg} · STATS: ${stats}`;
}

// Función para actualizar el script T-SQL
function updateScript() {
  const box = document.getElementById('scriptBox');
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

// Sobrescribir copyScript para que funcione con el script dinámico (ya existe)
// No es necesario modificar copyScript porque ya usa innerText.

// Inicializar contadores y script al cargar la página
document.addEventListener('DOMContentLoaded', function() {
  updateCounters();
  updateScript();
});

/* ===================== DATA ===================== */
let INDEX_DATA = [];

const FRAG_STATUS = f => f > 30 ? 'crit' : f >= 5 ? 'warn' : 'ok';
const STATUS_COLOR = {crit:'var(--crit)', warn:'var(--warn)', ok:'var(--ok)'};

/* ===================== NAVIGATION ===================== */
function goToPage(name){
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.getElementById('page-' + name).classList.add('active');
  document.querySelectorAll('.nav-item').forEach(n => n.classList.toggle('active', n.dataset.page === name));
  window.scrollTo({top:0, behavior:'instant'});
}
document.querySelectorAll('.nav-item').forEach(item => {
  item.addEventListener('click', e => { e.preventDefault(); goToPage(item.dataset.page); });
});

/* ===================== TABLE RENDER ===================== */
let currentFilter = 'all';
let currentSearch = '';
let sortKey = 'frag';
let sortDir = -1;

function renderTable(){
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

  const tbody = document.getElementById('indexTableBody');
  tbody.innerHTML = rows.map((r, i) => {
    const status = FRAG_STATUS(r.frag);
    const color = STATUS_COLOR[status];
    const actionBadge = r.action === 'OK' ? 'ok' : r.action === 'REORGANIZE' ? 'warn' : 'crit';
    // deterministic pseudo-random heights for sparkline based on index name
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

  document.getElementById('tableFootCount').textContent = `Mostrando ${rows.length} de ${INDEX_DATA.length} índices`;
  updateBulkBar();
}

document.getElementById('searchInput').addEventListener('input', e => {
  currentSearch = e.target.value;
  
async function fetchDashboardData() {
    try {
        const res = await fetch('/api/dashboard/data');
        const data = await res.json();
        
        // Update KPIs
        if(document.getElementById('kpi-total')) document.getElementById('kpi-total').textContent = data.kpis.total;
        if(document.getElementById('kpi-critical')) document.getElementById('kpi-critical').textContent = data.kpis.critical;
        if(document.getElementById('kpi-warning')) document.getElementById('kpi-warning').textContent = data.kpis.warning;
        if(document.getElementById('kpi-ok')) document.getElementById('kpi-ok').textContent = data.kpis.ok;
        
        // Update Legends
        if(document.getElementById('donut-total')) document.getElementById('donut-total').textContent = data.kpis.total;
        if(document.getElementById('legend-ok')) document.getElementById('legend-ok').textContent = data.kpis.ok;
        if(document.getElementById('legend-warning')) document.getElementById('legend-warning').textContent = data.kpis.warning;
        if(document.getElementById('legend-critical')) document.getElementById('legend-critical').textContent = data.kpis.critical;
        
        // Update Alerts
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

        // Update Table Data
        INDEX_DATA = data.indexes;
        renderTable();
        
    } catch(e) {
        console.error("Error fetching dashboard data:", e);
    }
}

fetchDashboardData();
setInterval(fetchDashboardData, 3000);

});

document.querySelectorAll('#filterPills .pill').forEach(pill => {
  pill.addEventListener('click', () => {
    document.querySelectorAll('#filterPills .pill').forEach(p => p.classList.remove('active'));
    pill.classList.add('active');
    currentFilter = pill.dataset.filter;
    
async function fetchDashboardData() {
    try {
        const res = await fetch('/api/dashboard/data');
        const data = await res.json();
        
        // Update KPIs
        if(document.getElementById('kpi-total')) document.getElementById('kpi-total').textContent = data.kpis.total;
        if(document.getElementById('kpi-critical')) document.getElementById('kpi-critical').textContent = data.kpis.critical;
        if(document.getElementById('kpi-warning')) document.getElementById('kpi-warning').textContent = data.kpis.warning;
        if(document.getElementById('kpi-ok')) document.getElementById('kpi-ok').textContent = data.kpis.ok;
        
        // Update Legends
        if(document.getElementById('donut-total')) document.getElementById('donut-total').textContent = data.kpis.total;
        if(document.getElementById('legend-ok')) document.getElementById('legend-ok').textContent = data.kpis.ok;
        if(document.getElementById('legend-warning')) document.getElementById('legend-warning').textContent = data.kpis.warning;
        if(document.getElementById('legend-critical')) document.getElementById('legend-critical').textContent = data.kpis.critical;
        
        // Update Alerts
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

        // Update Table Data
        INDEX_DATA = data.indexes;
        renderTable();
        
    } catch(e) {
        console.error("Error fetching dashboard data:", e);
    }
}

fetchDashboardData();
setInterval(fetchDashboardData, 3000);

  });
});

document.querySelectorAll('th.sortable').forEach(th => {
  th.addEventListener('click', () => {
    const key = th.dataset.sort;
    if(sortKey === key){ sortDir *= -1; } else { sortKey = key; sortDir = -1; }
    
async function fetchDashboardData() {
    try {
        const res = await fetch('/api/dashboard/data');
        const data = await res.json();
        
        // Update KPIs
        if(document.getElementById('kpi-total')) document.getElementById('kpi-total').textContent = data.kpis.total;
        if(document.getElementById('kpi-critical')) document.getElementById('kpi-critical').textContent = data.kpis.critical;
        if(document.getElementById('kpi-warning')) document.getElementById('kpi-warning').textContent = data.kpis.warning;
        if(document.getElementById('kpi-ok')) document.getElementById('kpi-ok').textContent = data.kpis.ok;
        
        // Update Legends
        if(document.getElementById('donut-total')) document.getElementById('donut-total').textContent = data.kpis.total;
        if(document.getElementById('legend-ok')) document.getElementById('legend-ok').textContent = data.kpis.ok;
        if(document.getElementById('legend-warning')) document.getElementById('legend-warning').textContent = data.kpis.warning;
        if(document.getElementById('legend-critical')) document.getElementById('legend-critical').textContent = data.kpis.critical;
        
        // Update Alerts
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

        // Update Table Data
        INDEX_DATA = data.indexes;
        renderTable();
        
    } catch(e) {
        console.error("Error fetching dashboard data:", e);
    }
}

fetchDashboardData();
setInterval(fetchDashboardData, 3000);

  });
});

document.getElementById('selectAll').addEventListener('change', e => {
  document.querySelectorAll('.row-check').forEach(c => c.checked = e.target.checked);
  updateBulkBar();
});

function updateBulkBar(){
  const checked = document.querySelectorAll('.row-check:checked').length;
  document.getElementById('bulkCount').textContent = checked;
  document.getElementById('bulkBar').classList.toggle('show', checked > 0);
}

/* ===================== DRAWER ===================== */
function openDrawer(idx){
  const r = INDEX_DATA[idx];
  const status = FRAG_STATUS(r.frag);
  const color = STATUS_COLOR[status];
  const actionBadge = r.action === 'OK' ? 'ok' : r.action === 'REORGANIZE' ? 'warn' : 'crit';

  document.getElementById('drawerTable').textContent = r.table;
  document.getElementById('drawerIndexName').textContent = r.index;
  document.getElementById('drawerFrag').textContent = r.frag.toFixed(1) + '%';
  document.getElementById('drawerFrag').style.color = color;
  document.getElementById('drawerSize').textContent = r.size + ' MB';
  document.getElementById('drawerLastReorg').textContent = r.lastReorg;
  document.getElementById('drawerAction').textContent = r.action;
  document.getElementById('drawerAction').style.color = color;

  document.getElementById('drawer').classList.add('show');
  document.getElementById('drawerOverlay').classList.add('show');
}
function closeDrawer(){
  document.getElementById('drawer').classList.remove('show');
  document.getElementById('drawerOverlay').classList.remove('show');
}
document.addEventListener('keydown', e => { if(e.key === 'Escape') closeDrawer(); });

/* ===================== SETTINGS ===================== */
function updateThreshold(which){
  if(which === 'reorg'){
    document.getElementById('reorgVal').textContent = document.getElementById('reorgThreshold').value + '%';
  } else {
    document.getElementById('rebuildVal').textContent = document.getElementById('rebuildThreshold').value + '%';
  }
}

/* ===================== TOAST ===================== */
function showToast(msg){
  document.getElementById('toastMsg').textContent = msg;
  const t = document.getElementById('toast');
  t.classList.add('show');
  clearTimeout(window._toastTimer);
  window._toastTimer = setTimeout(() => t.classList.remove('show'), 3000);
}

/* ===================== SCRIPT COPY ===================== */
function copyScript(){
  const text = document.getElementById('scriptBox').innerText;
  if(navigator.clipboard){
    navigator.clipboard.writeText(text).then(() => showToast('Script copiado al portapapeles'));
  } else {
    showToast('Script copiado al portapapeles');
  }
}

/* ===================== INIT ===================== */

async function fetchDashboardData() {
    try {
        const res = await fetch('/api/dashboard/data');
        const data = await res.json();
        
        // Update KPIs
        if(document.getElementById('kpi-total')) document.getElementById('kpi-total').textContent = data.kpis.total;
        if(document.getElementById('kpi-critical')) document.getElementById('kpi-critical').textContent = data.kpis.critical;
        if(document.getElementById('kpi-warning')) document.getElementById('kpi-warning').textContent = data.kpis.warning;
        if(document.getElementById('kpi-ok')) document.getElementById('kpi-ok').textContent = data.kpis.ok;
        
        // Update Legends
        if(document.getElementById('donut-total')) document.getElementById('donut-total').textContent = data.kpis.total;
        if(document.getElementById('legend-ok')) document.getElementById('legend-ok').textContent = data.kpis.ok;
        if(document.getElementById('legend-warning')) document.getElementById('legend-warning').textContent = data.kpis.warning;
        if(document.getElementById('legend-critical')) document.getElementById('legend-critical').textContent = data.kpis.critical;
        
        // Update Alerts
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

        // Update Table Data
        INDEX_DATA = data.indexes;
        renderTable();
        
    } catch(e) {
        console.error("Error fetching dashboard data:", e);
    }
}

fetchDashboardData();
setInterval(fetchDashboardData, 3000);
