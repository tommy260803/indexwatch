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
        function escapeHtml(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadReports();
            fetch('/api/dashboard/data', { credentials: 'same-origin' }).then(r => r.json()).then(d => {
                const sel = document.getElementById('reportServer');
                (d.servers || []).forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s.id;
                    opt.textContent = s.name;
                    sel.appendChild(opt);
                });
            }).catch(() => {});
        });

        async function generateReport() {
            const serverId = document.getElementById('reportServer').value;
            const format = document.getElementById('reportFormat').value;
            const btn = event.target;
            btn.disabled = true;
            btn.textContent = 'Generando...';
            try {
                const res = await fetch('/api/reports', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({server_id: serverId || null, filters: {}, format: format})
                });
                const data = await res.json();
                if (data.data) {
                    loadReports();
                    showToast('Reporte solicitado. Recarga en unos segundos.');
                } else {
                    showToast('Error al generar reporte');
                }
            } catch(e) {
                showToast('Error al generar reporte');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Generar Reporte';
            }
        }

        async function loadReports() {
            try {
                const res = await fetch('/api/reports?per_page=20', { credentials: 'same-origin' });
                const data = await res.json();
                const tbody = document.getElementById('reportTableBody');
                if (!data.data?.length) {
                    tbody.innerHTML = '<tr><td colspan="6" class="empty-state">Sin reportes generados</td></tr>';
                    return;
                }
                tbody.innerHTML = data.data.map(r => {
                    const statusClass = r.status === 'completed' ? 'ok' : r.status === 'failed' ? 'crit' : 'warn';
                    const downloadLink = r.status === 'completed'
                        ? '<a href="/api/reports/' + r.id + '/download" class="btn btn-primary" style="font-size:11px;padding:2px 8px;">Descargar</a>'
                        : '—';
                    return '<tr style="border-bottom:1px solid var(--border);font-size:13px;">'
                        + '<td style="padding:8px;">' + escapeHtml(r.server?.name || 'Todos') + '</td>'
                        + '<td style="padding:8px;">' + escapeHtml(r.format) + '</td>'
                        + '<td style="padding:8px;"><span class="badge ' + statusClass + '">' + escapeHtml(r.status) + '</span></td>'
                        + '<td style="padding:8px;font-size:11px;color:var(--text-faint);">' + escapeHtml(r.created_at || '') + '</td>'
                        + '<td style="padding:8px;font-size:11px;color:var(--text-faint);">' + escapeHtml(r.expires_at || '') + '</td>'
                        + '<td style="padding:8px;">' + downloadLink + '</td>'
                        + '</tr>';
                }).join('');
            } catch(e) {
                document.getElementById('reportTableBody').innerHTML = '<tr><td colspan="6" style="padding:20px;text-align:center;color:var(--crit);">Error al cargar</td></tr>';
            }
        }
    </script>
</x-app-layout>