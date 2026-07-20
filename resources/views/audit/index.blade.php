<x-app-layout>
    <section class="page active">
        <div class="page-head">
            <div>
                <div class="page-eyebrow">Seguridad y trazabilidad</div>
                <div class="page-title">Registro de Auditoría</div>
                <div class="page-sub">Historial inmutable de autorizaciones, ejecuciones, fallos y descartes</div>
            </div>
        </div>

        <div class="panel" style="margin-top:16px;">
            <div class="panel-head">
                <span class="panel-title">Filtros</span>
            </div>
            <div style="display:flex;gap:12px;padding:0 0 16px;flex-wrap:wrap;">
                <select id="auditFilterAction" style="background:var(--panel-2);border:1px solid var(--border);border-radius:5px;padding:4px 8px;color:var(--text);font-size:12px;">
                    <option value="">Todas las acciones</option>
                    <option value="approved">Aprobado</option>
                    <option value="executed">Ejecutado</option>
                    <option value="failed">Falló</option>
                    <option value="scheduled">Programado</option>
                    <option value="cancelled">Cancelado</option>
                </select>
                <select id="auditFilterSource" style="background:var(--panel-2);border:1px solid var(--border);border-radius:5px;padding:4px 8px;color:var(--text);font-size:12px;">
                    <option value="">Todas las fuentes</option>
                    <option value="webhook">WhatsApp</option>
                    <option value="job">Job</option>
                    <option value="dashboard">Dashboard</option>
                    <option value="scheduler">Scheduler</option>
                </select>
                <input id="auditFilterDateFrom" type="date" style="background:var(--panel-2);border:1px solid var(--border);border-radius:5px;padding:4px 8px;color:var(--text);font-size:12px;" placeholder="Desde">
                <input id="auditFilterDateTo" type="date" style="background:var(--panel-2);border:1px solid var(--border);border-radius:5px;padding:4px 8px;color:var(--text);font-size:12px;" placeholder="Hasta">
                <button onclick="loadAuditLogs()" class="btn btn-primary" style="font-size:12px;padding:4px 12px;">Filtrar</button>
            </div>
        </div>

        <div class="panel" style="margin-top:16px;">
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="border-bottom:1px solid var(--border);text-align:left;">
                        <th style="padding:8px;font-size:11px;color:var(--text-faint);">Fecha/Hora</th>
                        <th style="padding:8px;font-size:11px;color:var(--text-faint);">Servidor</th>
                        <th style="padding:8px;font-size:11px;color:var(--text-faint);">Actor</th>
                        <th style="padding:8px;font-size:11px;color:var(--text-faint);">Origen</th>
                        <th style="padding:8px;font-size:11px;color:var(--text-faint);">Acción</th>
                        <th style="padding:8px;font-size:11px;color:var(--text-faint);">Descripción</th>
                    </tr>
                </thead>
                <tbody id="auditLogsBody">
                    <tr><td colspan="6" style="padding:20px;text-align:center;color:var(--text-faint);">Cargando registros de auditoría...</td></tr>
                </tbody>
            </table>
            <div id="auditPagination" style="display:flex;gap:8px;justify-content:center;padding:16px 0;"></div>
        </div>
    </section>

    <script>
        let auditPage = 1;
        async function loadAuditLogs() {
            const params = new URLSearchParams();
            params.set('page', auditPage);
            const action = document.getElementById('auditFilterAction')?.value;
            const source = document.getElementById('auditFilterSource')?.value;
            const from = document.getElementById('auditFilterDateFrom')?.value;
            const to = document.getElementById('auditFilterDateTo')?.value;
            if (action) params.set('action', action);
            if (source) params.set('source', source);
            if (from) params.set('date_from', from);
            if (to) params.set('date_to', to);

            try {
                const res = await fetch('/api/audit-logs?' + params.toString());
                const data = await res.json();
                const tbody = document.getElementById('auditLogsBody');
                if (!data.data?.length) {
                    tbody.innerHTML = '<tr><td colspan="6" style="padding:20px;text-align:center;">Sin registros</td></tr>';
                    return;
                }
                tbody.innerHTML = data.data.map(log => `
                    <tr style="border-bottom:1px solid var(--border);font-size:13px;">
                        <td style="padding:8px;color:var(--text-faint);font-size:11px;">${log.created_at || ''}</td>
                        <td style="padding:8px;">${log.server?.name || 'N/A'}</td>
                        <td style="padding:8px;">${log.actor_type || ''}</td>
                        <td style="padding:8px;">${log.source || ''}</td>
                        <td style="padding:8px;">${log.action || ''}</td>
                        <td style="padding:8px;max-width:300px;overflow:hidden;text-overflow:ellipsis;">${log.description || ''}</td>
                    </tr>`).join('');
                // Pagination
                let pages = '';
                for (let i = 1; i <= data.last_page; i++) {
                    pages += `<button onclick="auditPage=${i};loadAuditLogs()" style="background:${i===auditPage?'var(--accent)':'var(--panel-2)'};border:1px solid var(--border);border-radius:5px;padding:4px 10px;color:var(--text);cursor:pointer;font-size:12px;">${i}</button>`;
                }
                document.getElementById('auditPagination').innerHTML = pages;
            } catch(e) {
                document.getElementById('auditLogsBody').innerHTML = '<tr><td colspan="6" style="padding:20px;text-align:center;color:var(--crit);">Error al cargar</td></tr>';
            }
        }
        document.addEventListener('DOMContentLoaded', loadAuditLogs);
    </script>
</x-app-layout>