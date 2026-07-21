<x-app-layout>
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
            <button class="btn btn-primary" onclick="saveThresholds()">Guardar umbrales</button>
          </div>
        </div>

        <div class="panel">
          <div class="panel-head"><span class="panel-title">Alertas del sistema</span></div>
          <div class="toggle-row">
            <div>
              <div class="lbl-main">Alertas por correo</div>
              <div class="lbl-sub">Recibe un resumen diario de índices críticos</div>
            </div>
            <label class="switch"><input type="checkbox" checked id="toggleEmail" onchange="saveNotification('email', this.checked)"><span class="track"></span></label>
          </div>
          <div class="toggle-row">
            <div>
              <div class="lbl-main">Alertas en tiempo real</div>
              <div class="lbl-sub">Notifica al instante cuando un índice supera el umbral crítico</div>
            </div>
            <label class="switch"><input type="checkbox" checked id="toggleRealtime" onchange="saveNotification('realtime', this.checked)"><span class="track"></span></label>
          </div>
          <div class="toggle-row">
            <div>
              <div class="lbl-main">Resumen semanal</div>
              <div class="lbl-sub">Reporte de tendencias enviado todos los lunes</div>
            </div>
            <label class="switch"><input type="checkbox" id="toggleWeekly" onchange="saveNotification('weekly', this.checked)"><span class="track"></span></label>
          </div>
        </div>
      </div>

      <div class="panel" style="margin-top:16px;">
        <div class="panel-head">
          <span class="panel-title">Integración con WhatsApp</span>
          <span class="badge ok" id="waStatus">Conectado</span>
        </div>
        <div class="field-group">
          <label class="field-label">Número de WhatsApp para alertas</label>
          <div class="wa-input-row">
            <input type="text" value="" id="waNumber" placeholder="Cargando...">
            <button class="btn" onclick="saveWhatsappNumber()">Actualizar</button>
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
          <label class="switch"><input type="checkbox" checked id="toggleWaCommands" onchange="saveNotification('whatsapp_commands', this.checked)"><span class="track"></span></label>
        </div>
      </div>
    </section>

    <script>
    async function fetchSettings() {
      try {
        const res = await fetch('/api/settings');
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();

        const reorg = document.getElementById('reorgThreshold');
        const rebuild = document.getElementById('rebuildThreshold');
        if (reorg) { reorg.value = data.thresholds.warning; updateThreshold('reorg'); }
        if (rebuild) { rebuild.value = data.thresholds.critical; updateThreshold('rebuild'); }

        const waNumber = document.getElementById('waNumber');
        if (waNumber && data.contacts && data.contacts.length) {
          waNumber.value = data.contacts[0].phone_number || '';
        }

        if (data.notifications) {
          const email = document.getElementById('toggleEmail');
          const realtime = document.getElementById('toggleRealtime');
          const weekly = document.getElementById('toggleWeekly');
          const waCmd = document.getElementById('toggleWaCommands');
          if (email) email.checked = data.notifications.email;
          if (realtime) realtime.checked = data.notifications.realtime;
          if (weekly) weekly.checked = data.notifications.weekly;
          if (waCmd) waCmd.checked = data.notifications.whatsapp_commands;
        }
      } catch (e) {
        console.error('Error fetching settings:', e);
      }
    }

    function updateThreshold(type) {
      if (type === 'reorg') {
        document.getElementById('reorgVal').textContent = document.getElementById('reorgThreshold').value + '%';
      } else {
        document.getElementById('rebuildVal').textContent = document.getElementById('rebuildThreshold').value + '%';
      }
    }

    async function saveThresholds() {
      const warning = document.getElementById('reorgThreshold').value;
      const critical = document.getElementById('rebuildThreshold').value;
      try {
        const res = await fetch('/api/settings/thresholds', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
          },
          body: JSON.stringify({ warning: parseFloat(warning), critical: parseFloat(critical) }),
        });
        if (res.ok) {
          showToast('Umbrales guardados correctamente');
        } else {
          showToast('Error al guardar umbrales');
        }
      } catch (e) {
        showToast('Error de conexión');
      }
    }

    async function saveWhatsappNumber() {
      const phone = document.getElementById('waNumber').value;
      if (!phone) return showToast('Ingresa un número');
      try {
        const res = await fetch('/api/settings/whatsapp', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
          },
          body: JSON.stringify({ phone_number: phone }),
        });
        if (res.ok) {
          showToast('Número actualizado correctamente');
        } else {
          showToast('Error al actualizar número');
        }
      } catch (e) {
        showToast('Error de conexión');
      }
    }

    async function saveNotification(key, value) {
      const toggles = {
        email: document.getElementById('toggleEmail')?.checked ?? true,
        realtime: document.getElementById('toggleRealtime')?.checked ?? true,
        weekly: document.getElementById('toggleWeekly')?.checked ?? false,
        whatsapp_commands: document.getElementById('toggleWaCommands')?.checked ?? true,
      };
      toggles[key] = value;

      try {
        const res = await fetch('/api/settings/notifications', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
          },
          body: JSON.stringify(toggles),
        });
        if (res.ok) {
          showToast('Preferencias guardadas');
        } else {
          showToast('Error al guardar preferencias');
        }
      } catch (e) {
        showToast('Error de conexión');
      }
    }

    document.addEventListener('DOMContentLoaded', fetchSettings);
    </script>
</x-app-layout>
