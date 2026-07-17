<x-app-layout>
    <!-- ============================================================ -->
    <!-- PAGE 3 — CENTRO DE OPERACIONES (MAQUETADO ESTÁTICO)          -->
    <!-- ============================================================ -->
    <section class="page active">
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
</x-app-layout>
