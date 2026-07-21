<x-app-layout>
    <div class="page-shell-content">
        <div class="page-shell">
            <x-page-header
                eyebrow="Infraestructura SQL Server"
                title="Servidores"
                subtitle="Administra altas, ediciones y bajas lógicas sin perder historial. Los campos calculados se muestran en modo solo lectura.">
                <x-slot:actions>
                    <a class="primary-button" href="{{ route('servers.create') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                        Nuevo servidor
                    </a>
                </x-slot:actions>
            </x-page-header>

            <div class="stats-grid">
                <x-metric-card label="Total" :value="$totalServers" hint="Servidores registrados" />
                <x-metric-card label="Activos" :value="$activeServers" hint="Monitoreo habilitado" />
                <x-metric-card label="Mantenimiento" :value="$maintenanceServers" hint="Pausados temporalmente" />
                <x-metric-card label="Contactos" :value="$contactsCount" hint="Asignados a servidores" />
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

            <div class="data-table-wrap">
                <table class="data-table">
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
                                    <x-badge :variant="$server->status->value ?? $server->status">
                                        {{ $server->status?->label() ?? ucfirst((string) $server->status) }}
                                    </x-badge>
                                </td>
                                <td>
                                    @php
                                        $healthVariant = ($server->health_score ?? 0) >= 70 ? 'ok' : (($server->health_score ?? 0) >= 50 ? 'warn' : 'crit');
                                    @endphp
                                    <x-badge :variant="$healthVariant">
                                        {{ $server->health_score !== null ? $server->health_score.'%' : 'Sin dato' }}
                                    </x-badge>
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
                            <x-empty-state colspan="8" title="Sin servidores" message="No hay servidores que coincidan con el filtro actual." />
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pagination-wrap">
                {{ $servers->links('vendor.pagination.indexwatch') }}
            </div>
        </div>
    </div>
</x-app-layout>
