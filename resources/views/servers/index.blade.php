<x-app-layout>
    @push('styles')
        <style>
            .servers-page {
                padding: 28px;
                color: #e7ebf3;
            }
            .page-shell {
                background: linear-gradient(180deg, rgba(18,24,33,.98), rgba(14,19,26,.98));
                border: 1px solid rgba(145, 158, 171, .18);
                border-radius: 28px;
                box-shadow: 0 28px 80px rgba(0, 0, 0, .35);
                overflow: hidden;
            }
            .page-header {
                display:flex; align-items:flex-start; justify-content:space-between; gap:20px;
                padding: 28px;
                border-bottom: 1px solid rgba(145, 158, 171, .14);
                background: radial-gradient(circle at top left, rgba(96, 165, 250, .12), transparent 45%);
            }
            .eyebrow {
                text-transform: uppercase;
                letter-spacing: .14em;
                font-size: 11px;
                color: #7fb4ff;
                margin-bottom: 10px;
            }
            .page-header h1 { margin: 0; font-size: 30px; line-height: 1.1; }
            .page-header p { margin: 10px 0 0; color: #9aa6b6; max-width: 72ch; }
            .header-actions { display:flex; gap:12px; flex-wrap:wrap; }
            .primary-button, .ghost-button, .danger-button {
                display:inline-flex; align-items:center; justify-content:center; gap:8px;
                border-radius: 16px; padding: 13px 18px; border:1px solid transparent;
                font-weight: 700; text-decoration:none; cursor:pointer;
            }
            .primary-button { background: linear-gradient(135deg, #5fa3ff, #4d7dff); color: white; }
            .ghost-button { background: rgba(255,255,255,.04); color: #edf2f7; border-color: rgba(145,158,171,.18); }
            .danger-button { background: rgba(239, 68, 68, .12); color: #ffb4b4; border-color: rgba(239,68,68,.2); }
            .stats-grid {
                display:grid; gap:16px; grid-template-columns: repeat(4, minmax(0, 1fr));
                padding: 0 28px 28px;
            }
            .metric-card {
                background: rgba(255,255,255,.03);
                border: 1px solid rgba(145, 158, 171, .14);
                border-radius: 24px;
                padding: 20px;
            }
            .metric-card .label { color:#8d98a8; font-size: 13px; }
            .metric-card .value { font-size: 32px; font-weight: 800; margin-top: 8px; }
            .metric-card .hint { color:#9aa6b6; font-size: 13px; margin-top: 6px; }
            .toolbar {
                padding: 0 28px 24px;
                display:flex; gap:16px; align-items:center; justify-content:space-between; flex-wrap:wrap;
            }
            .search-form {
                display:flex; gap:12px; flex-wrap:wrap; align-items:center; width:100%;
            }
            .search-input, .search-select {
                border-radius: 16px; border:1px solid rgba(145,158,171,.2);
                background: rgba(8,12,18,.92); color:#f1f5f9; padding:14px 16px; outline:none;
            }
            .search-input { min-width: 280px; flex: 1 1 320px; }
            .search-select { min-width: 180px; }
            .table-wrap {
                padding: 0 28px 28px;
                overflow-x:auto;
            }
            table {
                width:100%; border-collapse: collapse; min-width: 1100px;
                background: rgba(255,255,255,.03); border: 1px solid rgba(145,158,171,.14); border-radius: 24px; overflow:hidden;
            }
            th, td {
                padding: 16px 18px; border-bottom: 1px solid rgba(145,158,171,.10); vertical-align: top;
            }
            th { color:#c7d0dc; font-size: 12px; letter-spacing:.04em; text-transform: uppercase; text-align:left; background: rgba(255,255,255,.02); }
            tr:hover td { background: rgba(255,255,255,.02); }
            .badge {
                display:inline-flex; align-items:center; gap:8px; padding:8px 10px; border-radius:999px;
                border:1px solid rgba(145,158,171,.16); font-size: 12px; font-weight:700;
            }
            .badge .dot { width:8px; height:8px; border-radius:50%; }
            .badge.active { color:#8ef0bb; background: rgba(34,197,94,.12); }
            .badge.inactive { color:#f6cb7f; background: rgba(245,158,11,.12); }
            .badge.maintenance { color:#8fd3ff; background: rgba(59,130,246,.12); }
            .badge.ok { color:#8ef0bb; background: rgba(34,197,94,.12); }
            .badge.warn { color:#f6cb7f; background: rgba(245,158,11,.12); }
            .badge.crit { color:#ff9999; background: rgba(239,68,68,.12); }
            .row-actions { display:flex; gap:10px; flex-wrap:wrap; }
            .muted { color:#9aa6b6; }
            .empty-state {
                padding: 60px 28px 80px;
                text-align:center;
                color:#9aa6b6;
            }
            .pagination-wrap { padding: 0 28px 28px; }
            @media (max-width: 1100px) {
                .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            }
        </style>
    @endpush

    <div class="servers-page">
        <div class="page-shell">
            <div class="page-header">
                <div>
                    <div class="eyebrow">Infraestructura SQL Server</div>
                    <h1>Servidores</h1>
                    <p>Administra altas, ediciones y bajas lógicas sin perder historial. Los campos calculados se muestran en modo solo lectura.</p>
                </div>
                <div class="header-actions">
                    <a class="primary-button" href="{{ route('servers.create') }}">Nuevo servidor</a>
                </div>
            </div>

            <div class="stats-grid">
                <div class="metric-card"><div class="label">Total</div><div class="value">{{ $totalServers }}</div><div class="hint">Servidores registrados</div></div>
                <div class="metric-card"><div class="label">Activos</div><div class="value">{{ $activeServers }}</div><div class="hint">Monitoreo habilitado</div></div>
                <div class="metric-card"><div class="label">Mantenimiento</div><div class="value">{{ $maintenanceServers }}</div><div class="hint">Pausados temporalmente</div></div>
                <div class="metric-card"><div class="label">Contactos</div><div class="value">{{ $contactsCount }}</div><div class="hint">Asignados a servidores</div></div>
            </div>

            <div class="toolbar">
                <form method="GET" action="{{ route('servers.index') }}" class="search-form">
                    <input class="search-input" type="search" name="q" value="{{ $search }}" placeholder="Buscar por nombre, host, base de datos o usuario">
                    <select class="search-select" name="status">
                        <option value="">Todos los estados</option>
                        @foreach(\App\Enums\ServerStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected($selectedStatus === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                    <button class="ghost-button" type="submit">Filtrar</button>
                    <a class="ghost-button" href="{{ route('servers.index') }}">Limpiar</a>
                </form>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Servidor</th>
                            <th>Conexión</th>
                            <th>Estado</th>
                            <th>Salud</th>
                            <th>Último escaneo</th>
                            <th>Errores</th>
                            <th>Relaciones</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($servers as $server)
                            <tr>
                                <td>
                                    <div style="font-weight:800">{{ $server->name }}</div>
                                    <div class="muted">{{ $server->database_name }}</div>
                                </td>
                                <td>
                                    <div>{{ $server->host }}:{{ $server->port }}</div>
                                    <div class="muted">{{ $server->username }}</div>
                                </td>
                                <td>
                                    <span class="badge {{ $server->status->value ?? $server->status }}">
                                        <span class="dot" style="background: currentColor"></span>
                                        {{ $server->status?->label() ?? ucfirst((string) $server->status) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ ($server->health_score ?? 0) >= 70 ? 'ok' : (($server->health_score ?? 0) >= 50 ? 'warn' : 'crit') }}">
                                        <span class="dot" style="background: currentColor"></span>
                                        {{ $server->health_score !== null ? $server->health_score.'%' : 'Sin dato' }}
                                    </span>
                                </td>
                                <td>
                                    <div>{{ $server->last_scanned_at?->format('d/m/Y H:i') ?? 'Nunca' }}</div>
                                    <div class="muted">{{ $server->last_scan_status?->label() ?? 'Pendiente' }}</div>
                                </td>
                                <td>
                                    <div class="muted">{{ $server->sanitized_last_scan_error ?? 'Sin errores' }}</div>
                                </td>
                                <td>
                                    <div>{{ $server->sql_indexes_count }} índices</div>
                                    <div class="muted">{{ $server->alerts_count }} alertas · {{ $server->contacts->count() }} contactos</div>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <a class="ghost-button" href="{{ route('servers.show', $server) }}">Ver</a>
                                        <a class="ghost-button" href="{{ route('servers.edit', $server) }}">Editar</a>
                                        <form method="POST" action="{{ route('servers.destroy', $server) }}" onsubmit="return confirm('¿Eliminar lógicamente este servidor?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="danger-button" type="submit">Eliminar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">No hay servidores que coincidan con el filtro actual.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pagination-wrap">
                {{ $servers->links() }}
            </div>
        </div>
    </div>
</x-app-layout>