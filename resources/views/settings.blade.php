<x-app-layout>
    <!-- ============================================================ -->
    <!-- PAGE 4 — CONFIGURACIÓN Y ALERTAS                              -->
    <!-- ============================================================ -->
    <section class="page active">
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
</x-app-layout>
