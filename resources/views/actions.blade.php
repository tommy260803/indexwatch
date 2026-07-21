<x-app-layout>
    <section class="page active">
      <div class="page-head">
        <div>
          <div class="page-eyebrow">Mantenimiento</div>
          <div class="page-title">Centro de operaciones</div>
          <div class="page-sub">Gestiona los índices en cola y asigna acciones personalizadas</div>
        </div>
      </div>

      <div class="panel" style="margin-bottom:20px;">
        <div class="panel-head">
          <span class="panel-title">Índices en cola (<span id="queueCount">0</span>)</span>
          <span id="queueSummary" style="font-size:12px;color:var(--text-faint);"></span>
        </div>
        <div class="selection-list" id="opsSelectionList">
          <div class="loading-state"><span>Cargando cola de mantenimiento...</span></div>
        </div>
      </div>

      <div class="action-grid">
        <div class="action-card">
          <div class="action-card-icon" style="background:var(--warn-bg);color:var(--warn)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.65 6.35A8 8 0 106.35 17.65"/><path d="M21 4v6h-6M3 20v-6h6"/></svg>
          </div>
          <h4>REORGANIZE</h4>
          <p>Para fragmentación moderada (5–30%). Operación en línea: no bloquea la tabla.</p>
          <span class="tag" id="reorgCount">0 índices</span>
        </div>
        <div class="action-card">
          <div class="action-card-icon" style="background:var(--crit-bg);color:var(--crit)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 11-9-9"/><path d="M12 3v9l6 3"/></svg>
          </div>
          <h4>REBUILD</h4>
          <p>Para fragmentación alta (&gt;30%). Reconstruye el índice; puede ejecutarse en línea.</p>
          <span class="tag" id="rebuildCount">0 índices</span>
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

      <div class="panel">
        <div class="panel-head">
          <span class="panel-title">Script T-SQL generado</span>
          <button class="btn btn-sm" onclick="copyScript()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
            Copiar script
          </button>
        </div>
        <div class="script-box" id="scriptBox">
          <span class="cm">-- Generado por IndexWatch · {{ now()->format('d/m/Y H:i') }}</span>
        </div>
        <div class="save-bar">
          <button class="btn" id="btnSchedule" onclick="scheduleActions()">Programar para horario valle</button>
          <button class="btn btn-primary" id="btnExecute" onclick="executeActions()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 3l14 9-14 9V3z"/></svg>
            Ejecutar ahora
          </button>
        </div>
      </div>
    </section>

    <script>
    let actionQueue = [];
    let actionScripts = [];

    function escapeHtml(str) {
      if (!str) return '';
      const div = document.createElement('div');
      div.textContent = str;
      return div.innerHTML;
    }

    async function fetchActionsData() {
      try {
        const res = await fetch('/api/maintenance-actions/data', { credentials: 'same-origin' });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        actionQueue = data.queue || [];
        actionScripts = data.scripts || [];
        renderQueue(data.queue, data.summary);
        renderScripts(data.scripts);
      } catch (e) {
        console.error('Error fetching actions data:', e);
        document.getElementById('opsSelectionList').innerHTML = '<div class="empty-state">Error al cargar datos</div>';
      }
    }

    function renderQueue(queue, summary) {
      const list = document.getElementById('opsSelectionList');
      const countEl = document.getElementById('queueCount');
      const summaryEl = document.getElementById('queueSummary');
      document.getElementById('rebuildCount').textContent = summary.rebuild + ' índices';
      document.getElementById('reorgCount').textContent = summary.reorganize + ' índices';
      document.getElementById('statsCount').textContent = summary.stats + ' índices';
      countEl.textContent = summary.total;
      summaryEl.textContent = `REBUILD: ${summary.rebuild} · REORGANIZE: ${summary.reorganize} · STATS: ${summary.stats}`;

      if (!queue.length) {
        list.innerHTML = '<div class="empty-state">No hay acciones pendientes en la cola</div>';
        document.getElementById('btnExecute').disabled = true;
        document.getElementById('btnSchedule').disabled = true;
        return;
      }

      document.getElementById('btnExecute').disabled = false;
      document.getElementById('btnSchedule').disabled = false;
      list.innerHTML = '';

      queue.forEach((item, i) => {
        const badge = item.action_type === 'REBUILD' ? 'crit' : item.action_type === 'REORGANIZE' ? 'warn' : 'ok';
        const frag = item.fragmentation_percent;
        const fragColor = frag > 30 ? 'var(--crit)' : frag >= 5 ? 'var(--warn)' : 'var(--ok)';
        const row = document.createElement('div');
        row.className = 'sel-row';
        row.dataset.id = item.id;
        row.innerHTML = `
          <span class="name"><span class="tbl">${escapeHtml(item.schema_name)}.</span>${escapeHtml(item.table_name)}</span>
          <span style="font-size:12px;color:var(--text-faint);margin-right:4px;">Frag: <span style="color:${fragColor}">${frag.toFixed(1)}%</span></span>
          <select onchange="updateActionType(${item.id}, this.value)" style="background:var(--panel-2);border:1px solid var(--border);border-radius:5px;padding:4px 8px;color:var(--text);font-size:12px;font-family:var(--mono);">
            <option value="REBUILD" ${item.action_type === 'REBUILD' ? 'selected' : ''}>REBUILD</option>
            <option value="REORGANIZE" ${item.action_type === 'REORGANIZE' ? 'selected' : ''}>REORGANIZE</option>
            <option value="UPDATE STATISTICS" ${item.action_type === 'UPDATE STATISTICS' ? 'selected' : ''}>UPDATE STATISTICS</option>
          </select>
          <span class="badge ${badge}" id="badge-${item.id}">${escapeHtml(item.action_type)}</span>
          <button onclick="removeItem(${item.id})" style="background:none;border:none;color:var(--text-faint);cursor:pointer;padding:4px;border-radius:4px;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M18 6L6 18M6 6l12 12"/></svg>
          </button>`;
        list.appendChild(row);
      });
    }

    function renderScripts(scripts) {
      const box = document.getElementById('scriptBox');
      if (!scripts || !scripts.length) {
        box.innerHTML = '<span class="cm">-- No hay scripts generados</span>';
        return;
      }
      let html = `<span class="cm">-- Generado por IndexWatch · {{ now()->format('d/m/Y H:i') }}</span>\n`;
      scripts.forEach((sql, i) => {
        html += `\n${escapeHtml(sql)}\n<span class="cm">GO</span>\n`;
      });
      box.innerHTML = html;
    }

    function updateActionType(id, newType) {
      const item = actionQueue.find(x => x.id === id);
      if (item) item.action_type = newType;
      const badge = document.getElementById('badge-' + id);
      if (badge) {
        badge.textContent = newType;
        badge.className = 'badge ' + (newType === 'REBUILD' ? 'crit' : newType === 'REORGANIZE' ? 'warn' : 'ok');
      }

      fetch('/api/maintenance-actions/' + id + '/type', {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        credentials: 'same-origin',
        body: JSON.stringify({ action_type: newType })
      }).then(r => {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      }).then(data => {
        const updated = data.data;
        const qi = actionQueue.find(x => x.id === id);
        if (qi) qi.sql_script = updated.sql_script;
        actionScripts = actionQueue.map(item => item.sql_script).filter(Boolean);
        renderScripts(actionScripts);
        showToast('Tipo de acción actualizado');
      }).catch(e => {
        console.error('Error updating action type:', e);
        showToast('Error al actualizar tipo de acción');
      });
    }

    async function removeItem(id) {
      const row = document.querySelector('.sel-row[data-id="' + id + '"]');
      if (row) { row.style.opacity = '0.5'; row.style.pointerEvents = 'none'; }

      try {
        const res = await fetch('/api/maintenance-actions/' + id, {
          method: 'DELETE',
          headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
          credentials: 'same-origin'
        });
        if (!res.ok) {
          const err = await res.json().catch(() => ({}));
          throw new Error(err.message || 'HTTP ' + res.status);
        }
        actionQueue = actionQueue.filter(x => x.id !== id);
        if (row) row.remove();
        actionScripts = actionQueue.map(item => item.sql_script).filter(Boolean);
        document.getElementById('queueCount').textContent = actionQueue.length;
        renderScripts(actionScripts);
        showToast('Acción cancelada');
      } catch (e) {
        console.error('Error cancelling action:', e);
        if (row) { row.style.opacity = '1'; row.style.pointerEvents = 'auto'; }
        showToast('Error al cancelar: ' + e.message);
      }
    }

    function copyScript() {
      const text = document.getElementById('scriptBox').innerText;
      navigator.clipboard.writeText(text).then(() => showToast('Script copiado al portapapeles'));
    }

    function scheduleActions() {
      if (!actionQueue.length) return showToast('No hay acciones en la cola');

      const existing = document.getElementById('scheduleModal');
      if (existing) existing.remove();

      const actionIds = actionQueue.map(x => x.id);
      const tomorrow = new Date();
      tomorrow.setDate(tomorrow.getDate() + 1);
      tomorrow.setHours(2, 0, 0, 0);
      const defaultVal = tomorrow.toISOString().slice(0, 16);

      const overlay = document.createElement('div');
      overlay.id = 'scheduleModal';
      overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;display:flex;align-items:center;justify-content:center;';
      overlay.innerHTML = `
        <div style="background:var(--panel);border:1px solid var(--border);border-radius:12px;padding:24px;max-width:400px;width:90%;">
          <div style="font-size:16px;font-weight:600;color:var(--text);margin-bottom:4px;">Programar mantenimiento</div>
          <div style="font-size:13px;color:var(--text-faint);margin-bottom:16px;">${actionQueue.length} acción(es) se ejecutarán en el horario seleccionado</div>
          <label style="font-size:12px;color:var(--text-dim);display:block;margin-bottom:4px;">Fecha y hora de ejecución</label>
          <input type="datetime-local" id="scheduleDateTime" value="${defaultVal}" style="width:100%;background:var(--panel-2);border:1px solid var(--border);border-radius:8px;padding:10px 12px;color:var(--text);font-size:14px;margin-bottom:16px;">
          <div style="display:flex;gap:8px;justify-content:flex-end;">
            <button onclick="document.getElementById('scheduleModal').remove()" style="background:var(--panel-2);border:1px solid var(--border);border-radius:8px;padding:8px 16px;color:var(--text);cursor:pointer;font-size:13px;">Cancelar</button>
            <button onclick="confirmSchedule()" style="background:var(--accent);color:#fff;border:none;border-radius:8px;padding:8px 16px;cursor:pointer;font-size:13px;font-weight:500;">Programar</button>
          </div>
        </div>`;
      document.body.appendChild(overlay);
      overlay.addEventListener('click', e => { if (e.target === overlay) overlay.remove(); });
    }

    async function confirmSchedule() {
      const input = document.getElementById('scheduleDateTime');
      const scheduledFor = input?.value;
      if (!scheduledFor) return showToast('Selecciona una fecha y hora');

      const modal = document.getElementById('scheduleModal');
      const btn = modal?.querySelector('button:last-child');
      if (btn) { btn.disabled = true; btn.textContent = 'Programando...'; }

      const actionIds = actionQueue.map(x => x.id);

      try {
        const res = await fetch('/api/maintenance-actions/schedule', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
          credentials: 'same-origin',
          body: JSON.stringify({ action_ids: actionIds, scheduled_for: scheduledFor })
        });
        if (!res.ok) {
          const err = await res.json().catch(() => ({}));
          throw new Error(err.message || 'HTTP ' + res.status);
        }
        const data = await res.json();
        showToast(data.message);
        if (modal) modal.remove();
        fetchActionsData();
      } catch (e) {
        console.error('Error scheduling actions:', e);
        showToast('Error al programar: ' + e.message);
        if (btn) { btn.disabled = false; btn.textContent = 'Programar'; }
      }
    }

    async function executeActions() {
      if (!actionQueue.length) return showToast('No hay acciones en la cola');

      if (!confirm('¿Ejecutar ' + actionQueue.length + ' acción(es) de mantenimiento ahora? Esta operación se ejecutará en los servidores de producción.')) return;

      const actionIds = actionQueue.map(x => x.id);
      const btn = document.getElementById('btnExecute');
      if (btn) { btn.disabled = true; btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg> Ejecutando...'; }

      try {
        const res = await fetch('/api/maintenance-actions/execute', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
          credentials: 'same-origin',
          body: JSON.stringify({ action_ids: actionIds })
        });
        if (!res.ok) {
          const err = await res.json().catch(() => ({}));
          throw new Error(err.message || 'HTTP ' + res.status);
        }
        const data = await res.json();
        showToast(data.message);
        fetchActionsData();
      } catch (e) {
        console.error('Error executing actions:', e);
        showToast('Error al ejecutar: ' + e.message);
      } finally {
        if (btn) { btn.disabled = false; btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M5 3l14 9-14 9V3z"/></svg> Ejecutar ahora'; }
      }
    }

    document.addEventListener('DOMContentLoaded', fetchActionsData);
    </script>
</x-app-layout>
