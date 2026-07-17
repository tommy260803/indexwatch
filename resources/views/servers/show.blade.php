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
            .primary-button, .ghost-button {
                display:inline-flex; align-items:center; justify-content:center; gap:8px;
                border-radius: 16px; padding: 13px 18px; border:1px solid transparent;
                font-weight: 700; text-decoration:none; cursor:pointer;
            }
            .primary-button { background: linear-gradient(135deg, #5fa3ff, #4d7dff); color: white; }
            .ghost-button { background: rgba(255,255,255,.04); color: #edf2f7; border-color: rgba(145,158,171,.18); }
            .stats-grid {
                display:grid; gap:16px; grid-template-columns: repeat(4, minmax(0, 1fr));
                padding: 28px;
            }
            .info-card {
                background: rgba(255,255,255,.03);
                border: 1px solid rgba(145, 158, 171, .14);
                border-radius: 24px;
                padding: 20px;
            }
            .info-card .label { color:#8d98a8; font-size: 13px; }
            .info-card .value { font-size: 20px; font-weight: 800; margin-top: 8px; }
            .section-grid { display:grid; gap:20px; padding: 0 28px 28px; }
            .section-card {
                background: rgba(255,255,255,.03);
                border: 1px solid rgba(145, 158, 171, .14);
                border-radius: 24px;
                padding: 22px;
            }
            .section-card h2 { margin: 0 0 14px; font-size: 18px; }
            .detail-grid { display:grid; gap:16px; grid-template-columns: repeat(2, minmax(0,1fr)); }
            .field { padding:16px; border-radius:18px; background: rgba(8,12,18,.72); border:1px solid rgba(145,158,171,.16); }
            .field .label { color:#8d98a8; font-size: 13px; margin-bottom: 8px; }
            .field .value { font-weight:700; color:#f1f5f9; }
            .badge {
                display:inline-flex; align-items:center; gap:8px; padding:8px 10px; border-radius:999px;
                border:1px solid rgba(145,158,171,.16); font-size: 12px; font-weight:700;
            }
            .badge.active { color:#8ef0bb; background: rgba(34,197,94,.12); }
            .badge.inactive { color:#f6cb7f; background: rgba(245,158,11,.12); }
            .badge.maintenance { color:#8fd3ff; background: rgba(59,130,246,.12); }
            .badge.ok { color:#8ef0bb; background: rgba(34,197,94,.12); }
            .badge.warn { color:#f6cb7f; background: rgba(245,158,11,.12); }
            .badge.crit { color:#ff9999; background: rgba(239,68,68,.12); }
            .list { display:flex; flex-wrap:wrap; gap:10px; }
            .chip { padding: 8px 12px; border-radius: 999px; background: rgba(95,163,255,.10); border: 1px solid rgba(95,163,255,.18); }
            .muted { color:#9aa6b6; }
            @media (max-width: 1100px) {
                .stats-grid, .detail-grid { grid-template-columns: 1fr; }
            }
        </style>
    @endpush

    <div class="servers-page">
        <div class="page-shell">
            <div class="page-header">
                <div>
                    <div class="eyebrow">Servidor SQL Server</div>
                    <h1>{{ $server->name }}</h1>
                    <p>{{ $server->host }}:{{ $server->port }} · {{ $server->database_name }}</p>
                </div>
                <div class="header-actions">
                    <a class="ghost-button" href="{{ route('servers.index') }}">Volver</a>
                    <a class="primary-button" href="{{ route('servers.edit', $server) }}">Editar</a>
                </div>
            </div>

            <div class="stats-grid">
                <div class="info-card"><div class="label">Estado</div><div class="value"><span class="badge {{ $server->status->value ?? $server->status }}">{{ $server->status?->label() ?? ucfirst((string) $server->status) }}</span></div></div>
                <div class="info-card"><div class="label">Salud</div><div class="value"><span class="badge {{ ($server->health_score ?? 0) >= 70 ? 'ok' : (($server->health_score ?? 0) >= 50 ? 'warn' : 'crit') }}">{{ $server->health_score !== null ? $server->health_score.'%' : 'Sin dato' }}</span></div></div>
                <div class="info-card"><div class="label">Índices</div><div class="value">{{ $server->sqlIndexes->count() }}</div></div>
                <div class="info-card"><div class="label">Alertas</div><div class="value">{{ $server->alerts->count() }}</div></div>
            </div>

            <div class="section-grid">
                <section class="section-card">
                    <h2>Conexión</h2>
                    <div class="detail-grid">
                        <div class="field"><div class="label">Usuario</div><div class="value">{{ $server->username }}</div></div>
                        <div class="field"><div class="label">Zona horaria</div><div class="value">{{ $server->timezone }}</div></div>
                        <div class="field"><div class="label">Puerto</div><div class="value">{{ $server->port }}</div></div>
                        <div class="field"><div class="label">Connection options</div><div class="value muted">{{ json_encode($server->connection_options ?? [], JSON_UNESCAPED_UNICODE) }}</div></div>
                        <div class="field"><div class="label">Warning threshold</div><div class="value">{{ $server->warning_threshold }}%</div></div>
                        <div class="field"><div class="label">Critical threshold</div><div class="value">{{ $server->critical_threshold }}%</div></div>
                    </div>
                </section>

                <section class="section-card">
                    <h2>Monitoreo</h2>
                    <div class="detail-grid">
                        <div class="field"><div class="label">Último escaneo</div><div class="value">{{ $server->last_scanned_at?->format('d/m/Y H:i') ?? 'Nunca' }}</div></div>
                        <div class="field"><div class="label">Estado del escaneo</div><div class="value">{{ $server->last_scan_status?->label() ?? 'Pendiente' }}</div></div>
                        <div class="field"><div class="label">Error sanitizado</div><div class="value muted">{{ $server->sanitized_last_scan_error ?? 'Sin errores registrados' }}</div></div>
                        <div class="field"><div class="label">Stale threshold</div><div class="value">{{ $server->stats_stale_threshold }}%</div></div>
                        <div class="field"><div class="label">Minimum index pages</div><div class="value">{{ $server->minimum_index_pages }}</div></div>
                        <div class="field"><div class="label">Health updated</div><div class="value">{{ $server->health_score_updated_at?->format('d/m/Y H:i') ?? 'Sin dato' }}</div></div>
                    </div>
                </section>

                <section class="section-card">
                    <h2>Contactos vinculados</h2>
                    <div class="list">
                        @forelse($server->contacts as $contact)
                            <span class="chip">{{ $contact->getDisplayName() }} · {{ $contact->phone_number }}</span>
                        @empty
                            <span class="muted">No hay contactos asignados.</span>
                        @endforelse
                    </div>
                </section>

                <section class="section-card">
                    <h2>Relaciones y acciones</h2>
                    <div class="detail-grid">
                        <div class="field"><div class="label">SQL indexes</div><div class="value">{{ $server->sqlIndexes->count() }}</div></div>
                        <div class="field"><div class="label">Alerts</div><div class="value">{{ $server->alerts->count() }}</div></div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>