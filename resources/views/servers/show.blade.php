<x-app-layout>
    @push('styles')
        <style>
            .servers-page { padding: 28px; color: var(--text); }
            .list { display: flex; flex-wrap: wrap; gap: 10px; }
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