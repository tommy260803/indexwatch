<x-app-layout>
    <section class="page active">
        <div class="page-head">
            <div>
                <div class="page-eyebrow">Exportaciones</div>
                <div class="page-title">Reportes</div>
                <div class="page-sub">Genera reportes de monitoreo bajo demanda con datos históricos reales</div>
            </div>
        </div>

        <div class="panel" style="margin-top:16px;">
            <div class="panel-head">
                <span class="panel-title">Solicitar Reporte</span>
            </div>
            <div style="display:flex;gap:12px;padding:0 0 16px;flex-wrap:wrap;align-items:end;">
                <div>
                    <label style="font-size:11px;color:var(--text-faint);display:block;">Servidor</label>
                    <select id="reportServer" style="background:var(--panel-2);border:1px solid var(--border);border-radius:5px;padding:4px 8px;color:var(--text);font-size:12px;">
                        <option value="">Todos los servidores</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:11px;color:var(--text-faint);display:block;">Formato</label>
                    <select id="reportFormat" style="background:var(--panel-2);border:1px solid var(--border);border-radius:5px;padding:4px 8px;color:var(--text);font-size:12px;">
                        <option value="html">HTML</option>
                        <option value="csv">CSV</option>
                    </select>
                </div>
                <button onclick="generateReport()" class="btn btn-primary" style="font-size:12px;padding:4px 12px;">Generar Reporte</button>
            </div>
        </div>

        <div class="panel" style="margin-top:16px;">
            <div class="panel-head">
                <span class="panel-title">Reportes Generados</span>
            </div>
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="border-bottom:1px solid var(--border);text-align:left;">
                        <th style="padding:8px;font-size:11px;color:var(--text-faint);">Servidor</th>
                        <th style="padding:8px;font-size:11px;color:var(--text-faint);">Formato</th>
                        <th style="padding:8px;font-size:11px;color:var(--text-faint);">Estado</th>
                        <th style="padding:8px;font-size:11px;color:var(--text-faint);">Generado</th>
                        <th style="padding:8px;font-size:11px;color:var(--text-faint);">Expira</th>
                        <th style="padding:8px;font-size:11px;color:var(--text-faint);">Acción</th>
                    </tr>
                </thead>
                <tbody id="reportTableBody">
                    <tr><td colspan="6" style="padding:20px;text-align:center;color:var(--text-faint);">Cargando reportes...</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            loadReports();
            // Load servers for filter
            fetch('/api/dashboard/data').then(r => r.json()).then(d => {
                const sel = document.getElementById('reportServer');
                (d.servers || []).forEach(s => {
                    sel.innerHTML += `<option value="${s.id}">${s.name}</option>`;
                });
            });
        });

        async function generateReport() {
            const serverId = document.getElementById('reportServer').value;
            const format = document.getElementById('reportFormat').value;
            try {
                const res = await fetch('/api/reports', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                    body: JSON.stringify({server_id: serverId || null, filters: {}, format: format})
                });
                const data = await res.json();
                if (data.data) {
                    loadReports();
                    alert('Reporte solicitado. Recarga en unos segundos.');
                }
            } catch(e) {
                alert('Error al generar reporte');
            }
        }

        async function loadReports() {
            try {
                const res = await fetch('/api/reports?per_page=20');
                const data = await res.json();
                const tbody = document.getElementById('reportTableBody');
                if (!data.data?.length) {
                    tbody.innerHTML = '<tr><td colspan="6" style="padding:20px;text-align:center;">Sin reportes</td></tr>';
                    return;
                }
                tbody.innerHTML = data.data.map(r => `
                    <tr style="border-bottom:1px solid var(--border);font-size:13px;">
                        <td style="padding:8px;">${r.server?.name || 'Todos'}</td>
                        <td style="padding:8px;">${r.format}</td>
                        <td style="padding:8px;"><span class="badge ${r.status === 'completed' ? 'ok' : r.status === 'failed' ? 'crit' : 'warn'}">${r.status}</span></td>
                        <td style="padding:8px;font-size:11px;color:var(--text-faint);">${r.created_at || ''}</td>
                        <td style="padding:8px;font-size:11px;color:var(--text-faint);">${r.expires_at || ''}</td>
                        <td style="padding:8px;">
                            ${r.status === 'completed' ? `<a href="/api/reports/${r.id}/download" class="btn btn-primary" style="font-size:11px;padding:2px 8px;">Descargar</a>` : '—'}
                        </td>
                    </tr>`).join('');
            } catch(e) {
                document.getElementById('reportTableBody').innerHTML = '<tr><td colspan="6" style="padding:20px;text-align:center;color:var(--crit);">Error al cargar</td></tr>';
            }
        }
    </script>
</x-app-layout>