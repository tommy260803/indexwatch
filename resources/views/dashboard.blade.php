<x-app-layout>
    <!-- ============================================================ -->
    <!-- PAGE 1 — DASHBOARD                                            -->
    <!-- ============================================================ -->
    <section class="page active">
      <div class="page-head">
        <div>
          <div class="page-eyebrow">Resumen ejecutivo</div>
          <div class="page-title">Panel de control</div>
          <div class="page-sub">Estado de fragmentación de índices en todas las bases monitoreadas</div>
        </div>
        <a class="btn btn-primary" href="{{ route('indices') }}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 21l-4.3-4.3M19 11a8 8 0 11-16 0 8 8 0 0116 0z"/></svg>
          Revisar índices
        </a>
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
            <a class="panel-link" href="{{ route('settings') }}">Configurar</a>
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
</x-app-layout>
