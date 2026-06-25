<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>IndexWatch — Consola de Fragmentación de Índices</title>
<link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
</head>
<body>

<div class="shell">

  <!-- ===================== SIDEBAR ===================== -->
  <aside class="sidebar">
    <div class="brand">
      <div class="brand-mark">
        <svg viewBox="0 0 24 24" fill="none"><path d="M3 12c0-1 .5-2 1.5-2s1.5 2 2.5 2 1.5-4 2.5-4 1.5 6 2.5 6 1.5-5 2.5-5 1.5 3 2.5 3 1.5-1 2.5-1" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </div>
      <div class="brand-name">IndexWatch<span class="dot">.</span><span class="dim">sql</span></div>
    </div>

    <div class="nav-section-label">Monitoreo</div>
    <a class="nav-item active" data-page="dashboard">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
      Panel de control
    </a>
    <a class="nav-item" data-page="indices">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16M4 12h16M4 18h10"/></svg>
      Lista de índices
    </a>
    <a class="nav-item" data-page="actions">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M13 2L4 14h6l-1 8 9-12h-6l1-8z"/></svg>
      Centro de operaciones
    </a>

    <div class="nav-section-label">Sistema</div>
    <a class="nav-item" data-page="settings">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 11-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06A1.65 1.65 0 005 15a1.65 1.65 0 00-1.51-1H3a2 2 0 110-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06A1.65 1.65 0 009 4.6a1.65 1.65 0 001-1.51V3a2 2 0 114 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06A1.65 1.65 0 0019 9c.21.45.59.79 1.51 1H21a2 2 0 110 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
      Configuración y alertas
    </a>

    <div class="sidebar-foot">
      <div class="pulse-row"><span class="dot-live"></span> Conectado · SQL01-PROD</div>
      <div>Último barrido: hace 4 min</div>
    </div>
  </aside>

  <!-- ===================== MAIN ===================== -->
  <main class="main">

    <!-- ============================================================ -->
    <!-- PAGE 1 — DASHBOARD                                            -->
    <!-- ============================================================ -->
    <section class="page active" id="page-dashboard">
      <div class="page-head">
        <div>
          <div class="page-eyebrow">Resumen ejecutivo</div>
          <div class="page-title">Panel de control</div>
          <div class="page-sub">Estado de fragmentación de índices en todas las bases monitoreadas</div>
        </div>
        <button class="btn btn-primary" onclick="goToPage('indices')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 21l-4.3-4.3M19 11a8 8 0 11-16 0 8 8 0 0116 0z"/></svg>
          Revisar índices
        </button>
      </div>

      <div class="kpi-grid">
        <div class="kpi-card total">
          <div class="kpi-top">
            <span class="kpi-label">Total de índices</span>
            <div class="kpi-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18M9 4v16"/></svg></div>
          </div>
          <div class="kpi-value" id="kpi-total">186</div>
          <div class="kpi-foot">En 4 bases de datos</div>
        </div>
        <div class="kpi-card crit">
          <div class="kpi-top">
            <span class="kpi-label">Críticos (&gt;30%)</span>
            <div class="kpi-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4m0 4h.01M10.3 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.7 3.86a2 2 0 00-3.4 0z"/></svg></div>
          </div>
          <div class="kpi-value" id="kpi-critical">14</div>
          <div class="kpi-foot">Requieren REBUILD inmediato</div>
        </div>
        <div class="kpi-card warn">
          <div class="kpi-top">
            <span class="kpi-label">Recomendados (5–30%)</span>
            <div class="kpi-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8v4l3 3M12 22a10 10 0 100-20 10 10 0 000 20z"/></svg></div>
          </div>
          <div class="kpi-value" id="kpi-warning">37</div>
          <div class="kpi-foot">Candidatos a REORGANIZE</div>
        </div>
        <div class="kpi-card ok">
          <div class="kpi-top">
            <span class="kpi-label">Saludables (&lt;5%)</span>
            <div class="kpi-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg></div>
          </div>
          <div class="kpi-value" id="kpi-ok">135</div>
          <div class="kpi-foot">Sin acción necesaria</div>
        </div>
      </div>

      <div class="dash-grid">
        <div class="panel">
          <div class="panel-head">
            <span class="panel-title">Distribución de fragmentación</span>
          </div>
          <div class="donut-wrap">
            <svg width="150" height="150" viewBox="0 0 150 150">
              <circle cx="75" cy="75" r="58" fill="none" stroke="var(--border)" stroke-width="18"/>
              <circle cx="75" cy="75" r="58" fill="none" stroke="var(--ok)" stroke-width="18"
                stroke-dasharray="219.8 145.2" stroke-dashoffset="0" transform="rotate(-90 75 75)" stroke-linecap="round"/>
              <circle cx="75" cy="75" r="58" fill="none" stroke="var(--warn)" stroke-width="18"
                stroke-dasharray="72.7 292.3" stroke-dashoffset="-219.8" transform="rotate(-90 75 75)" stroke-linecap="round"/>
              <circle cx="75" cy="75" r="58" fill="none" stroke="var(--crit)" stroke-width="18"
                stroke-dasharray="27.5 337.5" stroke-dashoffset="-292.5" transform="rotate(-90 75 75)" stroke-linecap="round"/>
              <text id="donut-total" x="75" y="71" text-anchor="middle" font-family="JetBrains Mono, monospace" font-size="26" font-weight="700" fill="#E4E8EE">186</text>
              <text x="75" y="88" text-anchor="middle" font-family="Inter, sans-serif" font-size="10.5" fill="#8A93A3">índices</text>
            </svg>
            <div class="donut-legend">
              <div class="legend-row"><span class="legend-dot" style="background:var(--ok)"></span><span class="lbl">Saludable</span><span class="val" id="legend-ok">135</span></div>
              <div class="legend-row"><span class="legend-dot" style="background:var(--warn)"></span><span class="lbl">Recomendado</span><span class="val" id="legend-warning">37</span></div>
              <div class="legend-row"><span class="legend-dot" style="background:var(--crit)"></span><span class="lbl">Crítico</span><span class="val" id="legend-critical">14</span></div>
            </div>
          </div>
        </div>

        <div class="panel">
          <div class="panel-head">
            <span class="panel-title">Últimas alertas</span>
            <a class="panel-link" href="#" onclick="goToPage('settings');return false;">Configurar</a>
          </div>
          <div class="alert-feed" id="alertFeed">
            <div class="alert-item">
              <span class="alert-dot" style="background:var(--crit)"></span>
              <div class="alert-body">
                <div class="alert-text">Índice <code>IX_Pedidos_Fecha</code> en <code>Ventas</code> superó el 30% de fragmentación.</div>
                <div class="alert-time">Hace 12 min</div>
              </div>
            </div>
            <div class="alert-item">
              <span class="alert-dot" style="background:var(--crit)"></span>
              <div class="alert-body">
                <div class="alert-text">Índice <code>IX_Clientes_Email</code> en <code>CRM</code> superó el 30% de fragmentación.</div>
                <div class="alert-time">Hace 47 min</div>
              </div>
            </div>
            <div class="alert-item">
              <span class="alert-dot" style="background:var(--warn)"></span>
              <div class="alert-body">
                <div class="alert-text">Índice <code>IX_Inventario_SKU</code> alcanzó 22% de fragmentación. Candidato a REORGANIZE.</div>
                <div class="alert-time">Hace 2 h</div>
              </div>
            </div>
            <div class="alert-item">
              <span class="alert-dot" style="background:var(--ok)"></span>
              <div class="alert-body">
                <div class="alert-text">REBUILD completado en <code>IX_Facturas_NumDoc</code> · <code>Ventas</code>. Fragmentación: 2%.</div>
                <div class="alert-time">Hace 3 h</div>
              </div>
            </div>
            <div class="alert-item">
              <span class="alert-dot" style="background:var(--warn)"></span>
              <div class="alert-body">
                <div class="alert-text">Índice <code>IX_Productos_Categoria</code> alcanzó 18% de fragmentación.</div>
                <div class="alert-time">Hace 5 h</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ============================================================ -->
    <!-- PAGE 2 — LISTA DE ÍNDICES                                     -->
    <!-- ============================================================ -->
    <section class="page" id="page-indices">
      <div class="page-head">
        <div>
          <div class="page-eyebrow">Inventario completo</div>
          <div class="page-title">Lista de índices</div>
          <div class="page-sub">186 índices · ordena, filtra y selecciona para ejecutar mantenimiento</div>
        </div>
        <div class="select-box">
          <select id="dbSelect">
            <option>Ventas</option>
            <option>CRM</option>
            <option>Inventario</option>
            <option>Todas las bases</option>
          </select>
        </div>
      </div>

      <div class="toolbar">
        <div class="search-box">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 21l-4.3-4.3M19 11a8 8 0 11-16 0 8 8 0 0116 0z"/></svg>
          <input type="text" id="searchInput" placeholder="Buscar índice o tabla…">
        </div>
        <div class="filter-pills" id="filterPills">
          <span class="pill active" data-filter="all"><span class="dot" style="background:var(--text-faint)"></span>Todos</span>
          <span class="pill crit" data-filter="crit"><span class="dot" style="background:var(--crit)"></span>Crítico</span>
          <span class="pill warn" data-filter="warn"><span class="dot" style="background:var(--warn)"></span>Recomendado</span>
          <span class="pill ok" data-filter="ok"><span class="dot" style="background:var(--ok)"></span>Saludable</span>
        </div>
      </div>

      <div class="bulk-bar" id="bulkBar">
        <span><span class="count" id="bulkCount">0</span> índices seleccionados</span>
        <div class="spacer"></div>
        <button class="btn btn-sm" onclick="goToPage('actions')">Ir a operaciones</button>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th class="cell-checkbox"><input type="checkbox" id="selectAll"></th>
              <th>Tabla</th>
              <th>Índice</th>
              <th class="sortable" data-sort="frag">Fragmentación <span class="sort-arrow">▾</span></th>
              <th class="sortable" data-sort="size">Tamaño (MB)</th>
              <th>Última reorganización</th>
              <th>Acción recomendada</th>
            </tr>
          </thead>
          <tbody id="indexTableBody">
            <!-- rows injected by JS -->
          </tbody>
          <tfoot>
            <tr><td colspan="7" id="tableFootCount">Mostrando 186 de 186 índices</td></tr>
          </tfoot>
        </table>
      </div>
    </section>

    <!-- ============================================================ -->
    <!-- PAGE 3 — CENTRO DE OPERACIONES (MAQUETADO ESTÁTICO)          -->
    <!-- ============================================================ -->
    <section class="page" id="page-actions">
      <div class="page-head">
        <div>
          <div class="page-eyebrow">Mantenimiento</div>
          <div class="page-title">Centro de operaciones</div>
          <div class="page-sub">Gestiona los índices en cola y asigna acciones personalizadas</div>
        </div>
        <div>
          <button class="btn" onclick="addDemoIndices()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
            Añadir seleccionados
          </button>
          <button class="btn" onclick="clearQueue()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
            Limpiar cola
          </button>
        </div>
      </div>

      <!-- Panel de cola dinámica (con datos de ejemplo) -->
      <div class="panel" style="margin-bottom:20px;">
        <div class="panel-head">
          <span class="panel-title">Índices en cola (<span id="queueCount">3</span>)</span>
          <span id="queueSummary" style="font-size:12px;color:var(--text-faint);">REBUILD: 2 · REORGANIZE: 1 · STATS: 0</span>
        </div>
        <div class="selection-list" id="opsSelectionList">
          <!-- Filas generadas estáticamente con datos de ejemplo -->
          <div class="sel-row" data-index="0">
            <span class="name"><span class="tbl">Ventas.</span>IX_Pedidos_Fecha</span>
            <span style="font-size:12px;color:var(--text-faint);margin-right:4px;">Fragmentación: 38.4%</span>
            <select onchange="updateAction(this, 0)" style="background:var(--panel-2);border:1px solid var(--border);border-radius:5px;padding:4px 8px;color:var(--text);font-size:12px;font-family:var(--mono);">
              <option value="REBUILD" selected>REBUILD</option>
              <option value="REORGANIZE">REORGANIZE</option>
              <option value="UPDATE STATISTICS">UPDATE STATISTICS</option>
            </select>
            <span class="badge crit" id="badge-0">REBUILD</span>
            <button onclick="removeItem(0)" style="background:none;border:none;color:var(--text-faint);cursor:pointer;padding:4px;border-radius:4px;">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
          </div>
          <div class="sel-row" data-index="1">
            <span class="name"><span class="tbl">CRM.</span>IX_Clientes_Email</span>
            <span style="font-size:12px;color:var(--text-faint);margin-right:4px;">Fragmentación: 34.1%</span>
            <select onchange="updateAction(this, 1)" style="background:var(--panel-2);border:1px solid var(--border);border-radius:5px;padding:4px 8px;color:var(--text);font-size:12px;font-family:var(--mono);">
              <option value="REBUILD" selected>REBUILD</option>
              <option value="REORGANIZE">REORGANIZE</option>
              <option value="UPDATE STATISTICS">UPDATE STATISTICS</option>
            </select>
            <span class="badge crit" id="badge-1">REBUILD</span>
            <button onclick="removeItem(1)" style="background:none;border:none;color:var(--text-faint);cursor:pointer;padding:4px;border-radius:4px;">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
          </div>
          <div class="sel-row" data-index="2">
            <span class="name"><span class="tbl">Inventario.</span>IX_Inventario_SKU</span>
            <span style="font-size:12px;color:var(--text-faint);margin-right:4px;">Fragmentación: 22.7%</span>
            <select onchange="updateAction(this, 2)" style="background:var(--panel-2);border:1px solid var(--border);border-radius:5px;padding:4px 8px;color:var(--text);font-size:12px;font-family:var(--mono);">
              <option value="REBUILD">REBUILD</option>
              <option value="REORGANIZE" selected>REORGANIZE</option>
              <option value="UPDATE STATISTICS">UPDATE STATISTICS</option>
            </select>
            <span class="badge warn" id="badge-2">REORGANIZE</span>
            <button onclick="removeItem(2)" style="background:none;border:none;color:var(--text-faint);cursor:pointer;padding:4px;border-radius:4px;">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Tarjetas de acción (resumen) -->
      <div class="action-grid">
        <div class="action-card">
          <div class="action-card-icon" style="background:var(--warn-bg);color:var(--warn)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.65 6.35A8 8 0 106.35 17.65"/><path d="M21 4v6h-6M3 20v-6h6"/></svg>
          </div>
          <h4>REORGANIZE</h4>
          <p>Para fragmentación moderada (5–30%). Operación en línea: no bloquea la tabla.</p>
          <span class="tag" id="reorgCount">1 índice</span>
        </div>
        <div class="action-card">
          <div class="action-card-icon" style="background:var(--crit-bg);color:var(--crit)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 11-9-9"/><path d="M12 3v9l6 3"/></svg>
          </div>
          <h4>REBUILD</h4>
          <p>Para fragmentación alta (&gt;30%). Reconstruye el índice; puede ejecutarse en línea.</p>
          <span class="tag" id="rebuildCount">2 índices</span>
        </div>
        <div class="action-card">
          <div class="action-card-icon" style="background:var(--accent-bg);color:var(--accent)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M18.7 8l-5.1 5.1-2.8-2.8L7 14"/></svg>
          </div>
          <h4>UPDATE STATISTICS</h4>
          <p>Actualiza las estadísticas del índice para mejorar los planes de ejecución.</p>
          <span class="tag" id="statsCount">0 índices</span>
        </div>
      </div>

      <!-- Script generado dinámicamente (con JS) -->
      <div class="panel">
        <div class="panel-head">
          <span class="panel-title">Script T-SQL generado</span>
          <button class="btn btn-sm" onclick="copyScript()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
            Copiar script
          </button>
        </div>
        <div class="script-box" id="scriptBox">
          <span class="cm">-- Generado por IndexWatch · 17/06/2026 10:15</span>
          <span class="kw">ALTER INDEX</span> [IX_Pedidos_Fecha] <span class="kw">ON</span> [Ventas].[dbo].[Pedidos]
            <span class="kw">REBUILD WITH</span> (ONLINE = ON, FILLFACTOR = 90);
          <span class="cm">GO</span>

          <span class="kw">ALTER INDEX</span> [IX_Clientes_Email] <span class="kw">ON</span> [CRM].[dbo].[Clientes]
            <span class="kw">REBUILD WITH</span> (ONLINE = ON, FILLFACTOR = 90);
          <span class="cm">GO</span>

          <span class="kw">ALTER INDEX</span> [IX_Inventario_SKU] <span class="kw">ON</span> [Inventario].[dbo].[Stock]
            <span class="kw">REORGANIZE</span>;
          <span class="cm">GO</span>
        </div>
        <div class="save-bar">
          <button class="btn" onclick="showToast('Programado para horario valle (simulado)')">Programar para horario valle</button>
          <button class="btn btn-primary" onclick="showToast('Acción ejecutada sobre los índices en cola (simulado)')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 3l14 9-14 9V3z"/></svg>
            Ejecutar ahora
          </button>
        </div>
      </div>
    </section>

    <!-- ============================================================ -->
    <!-- PAGE 4 — CONFIGURACIÓN Y ALERTAS                              -->
    <!-- ============================================================ -->
    <section class="page" id="page-settings">
      <div class="page-head">
        <div>
          <div class="page-eyebrow">Preferencias</div>
          <div class="page-title">Configuración y alertas</div>
          <div class="page-sub">Define umbrales de mantenimiento y canales de notificación</div>
        </div>
      </div>

      <div class="settings-grid">
        <div class="panel">
          <div class="panel-head"><span class="panel-title">Umbrales de fragmentación</span></div>

          <div class="field-group">
            <label class="field-label">Umbral para REORGANIZE</label>
            <div class="field-row">
              <input type="range" min="1" max="50" value="20" id="reorgThreshold" oninput="updateThreshold('reorg')">
              <span class="field-value" id="reorgVal">20%</span>
            </div>
            <div class="field-hint">Por debajo de este umbral, el índice se considera saludable.</div>
          </div>

          <div class="field-group">
            <label class="field-label">Umbral para REBUILD</label>
            <div class="field-row">
              <input type="range" min="20" max="80" value="40" id="rebuildThreshold" oninput="updateThreshold('rebuild')">
              <span class="field-value" id="rebuildVal">40%</span>
            </div>
            <div class="field-hint">Por encima de este umbral, se genera una alerta crítica.</div>
          </div>

          <div class="threshold-track-wrap">
            <div class="threshold-bar"></div>
            <div class="threshold-marks">
              <span>0%</span><span>Saludable</span><span>Recomendado</span><span>Crítico</span><span>100%</span>
            </div>
          </div>

          <div class="save-bar">
            <button class="btn btn-primary" onclick="showToast('Umbrales guardados correctamente')">Guardar umbrales</button>
          </div>
        </div>

        <div class="panel">
          <div class="panel-head"><span class="panel-title">Alertas del sistema</span></div>
          <div class="toggle-row">
            <div>
              <div class="lbl-main">Alertas por correo</div>
              <div class="lbl-sub">Recibe un resumen diario de índices críticos</div>
            </div>
            <label class="switch"><input type="checkbox" checked><span class="track"></span></label>
          </div>
          <div class="toggle-row">
            <div>
              <div class="lbl-main">Alertas en tiempo real</div>
              <div class="lbl-sub">Notifica al instante cuando un índice supera el umbral crítico</div>
            </div>
            <label class="switch"><input type="checkbox" checked><span class="track"></span></label>
          </div>
          <div class="toggle-row">
            <div>
              <div class="lbl-main">Resumen semanal</div>
              <div class="lbl-sub">Reporte de tendencias enviado todos los lunes</div>
            </div>
            <label class="switch"><input type="checkbox"><span class="track"></span></label>
          </div>
        </div>
      </div>

      <div class="panel" style="margin-top:16px;">
        <div class="panel-head">
          <span class="panel-title">Integración con WhatsApp</span>
          <span class="badge ok">Conectado</span>
        </div>
        <div class="field-group">
          <label class="field-label">Número de WhatsApp para alertas</label>
          <div class="wa-input-row">
            <input type="text" value="+51 987 654 321" id="waNumber">
            <button class="btn" onclick="showToast('Número actualizado')">Actualizar</button>
          </div>
          <div class="field-hint">Las alertas críticas se enviarán a este número en tiempo real.</div>
        </div>

        <div class="field-group">
          <label class="field-label">Comandos disponibles por WhatsApp</label>
          <div class="wa-cmd-list">
            <div class="wa-cmd"><code>/estado</code><span>Resumen de índices críticos y recomendados</span></div>
            <div class="wa-cmd"><code>/rebuild [índice]</code><span>Ejecuta REBUILD sobre un índice específico</span></div>
            <div class="wa-cmd"><code>/reorganize [índice]</code><span>Ejecuta REORGANIZE sobre un índice específico</span></div>
            <div class="wa-cmd"><code>/silenciar 1h</code><span>Pausa las alertas durante el tiempo indicado</span></div>
          </div>
        </div>

        <div class="toggle-row" style="border-top:1px solid var(--border-soft);padding-top:16px;">
          <div>
            <div class="lbl-main">Permitir comandos de ejecución por WhatsApp</div>
            <div class="lbl-sub">Si está desactivado, solo se podrán consultar estados, no ejecutar acciones</div>
          </div>
          <label class="switch"><input type="checkbox" checked><span class="track"></span></label>
        </div>
      </div>
    </section>

  </main>
</div>

<!-- ===================== DETAIL DRAWER ===================== -->
<div class="drawer-overlay" id="drawerOverlay" onclick="closeDrawer()"></div>
<div class="drawer" id="drawer">
  <div class="drawer-head">
    <button class="drawer-close" onclick="closeDrawer()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
    </button>
    <div class="drawer-eyebrow" id="drawerTable">Ventas.dbo.Pedidos</div>
    <div class="drawer-title" id="drawerIndexName">IX_Pedidos_Fecha</div>
  </div>
  <div class="drawer-body">

    <div class="drawer-section">
      <div class="drawer-section-title">Información general</div>
      <div class="info-grid">
        <div class="info-item"><div class="lbl">Fragmentación</div><div class="val" id="drawerFrag" style="color:var(--crit)">38.4%</div></div>
        <div class="info-item"><div class="lbl">Tamaño</div><div class="val" id="drawerSize">412 MB</div></div>
        <div class="info-item"><div class="lbl">Última reorganización</div><div class="val" id="drawerLastReorg">02/05/2026</div></div>
        <div class="info-item"><div class="lbl">Acción recomendada</div><div class="val" id="drawerAction" style="color:var(--crit)">REBUILD</div></div>
      </div>
    </div>

    <div class="drawer-section">
      <div class="drawer-section-title">Historial de fragmentación</div>
      <div class="chart-box">
        <svg viewBox="0 0 420 140" width="100%" height="140">
          <line x1="0" y1="35" x2="420" y2="35" stroke="#1F2733" stroke-width="1"/>
          <line x1="0" y1="70" x2="420" y2="70" stroke="#1F2733" stroke-width="1"/>
          <line x1="0" y1="105" x2="420" y2="105" stroke="#1F2733" stroke-width="1"/>
          <text x="4" y="31" font-family="JetBrains Mono" font-size="9" fill="#5A6374">40%</text>
          <text x="4" y="66" font-family="JetBrains Mono" font-size="9" fill="#5A6374">20%</text>
          <text x="4" y="101" font-family="JetBrains Mono" font-size="9" fill="#5A6374">0%</text>
          <polyline points="30,118 90,112 150,98 210,88 270,62 330,44 390,20"
            fill="none" stroke="#E8543E" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
          <g fill="#E8543E">
            <circle cx="30" cy="118" r="3"/><circle cx="90" cy="112" r="3"/><circle cx="150" cy="98" r="3"/>
            <circle cx="210" cy="88" r="3"/><circle cx="270" cy="62" r="3"/><circle cx="330" cy="44" r="3"/>
            <circle cx="390" cy="20" r="3.5"/>
          </g>
          <text x="30" y="135" font-family="Inter" font-size="9" fill="#5A6374" text-anchor="middle">Ene</text>
          <text x="90" y="135" font-family="Inter" font-size="9" fill="#5A6374" text-anchor="middle">Feb</text>
          <text x="150" y="135" font-family="Inter" font-size="9" fill="#5A6374" text-anchor="middle">Mar</text>
          <text x="210" y="135" font-family="Inter" font-size="9" fill="#5A6374" text-anchor="middle">Abr</text>
          <text x="270" y="135" font-family="Inter" font-size="9" fill="#5A6374" text-anchor="middle">May</text>
          <text x="330" y="135" font-family="Inter" font-size="9" fill="#5A6374" text-anchor="middle">Jun</text>
          <text x="390" y="135" font-family="Inter" font-size="9" fill="#5A6374" text-anchor="middle">Hoy</text>
        </svg>
      </div>
    </div>

    <div class="drawer-section">
      <div class="drawer-section-title">Estadísticas de uso</div>
      <div class="usage-bars">
        <div class="usage-row">
          <span class="usage-label">user_seeks</span>
          <div class="usage-track"><div class="usage-fill" style="width:88%;background:var(--accent)"></div></div>
          <span class="usage-val">128,402</span>
        </div>
        <div class="usage-row">
          <span class="usage-label">user_scans</span>
          <div class="usage-track"><div class="usage-fill" style="width:24%;background:var(--accent)"></div></div>
          <span class="usage-val">3,118</span>
        </div>
        <div class="usage-row">
          <span class="usage-label">user_lookups</span>
          <div class="usage-track"><div class="usage-fill" style="width:46%;background:var(--accent)"></div></div>
          <span class="usage-val">19,775</span>
        </div>
      </div>
      <div class="field-hint" style="margin-top:10px;">Alto número de seeks indica que este índice es usado frecuentemente por el optimizador: no es candidato a eliminación.</div>
    </div>

    <div class="drawer-section">
      <div class="drawer-section-title">Acciones</div>
      <div class="drawer-actions">
        <button class="btn btn-primary" onclick="closeDrawer();goToPage('actions');">Enviar a operaciones</button>
        <button class="btn">REBUILD Índice</button>
        <button class="btn">REORGANIZE Índice</button>
      </div>
    </div>

  </div>
</div>

<!-- ===================== TOAST ===================== -->
<div class="toast" id="toast">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>
  <span id="toastMsg">Acción completada</span>
</div>

<script src="{{ asset('js/dashboard.js') }}"></script>

</body>
</html>
