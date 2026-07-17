<x-app-layout>
    <!-- ============================================================ -->
    <!-- PAGE 2 — LISTA DE ÍNDICES                                     -->
    <!-- ============================================================ -->
    <section class="page active">
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
        <a class="btn btn-sm" href="{{ route('actions') }}">Ir a operaciones</a>
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
            <a class="btn btn-primary" href="{{ route('actions') }}">Enviar a operaciones</a>
            <button class="btn">REBUILD Índice</button>
            <button class="btn">REORGANIZE Índice</button>
          </div>
        </div>

      </div>
    </div>
</x-app-layout>
