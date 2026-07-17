<x-app-layout>
    @push('styles')
        <style>
            .contacts-page {
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
                display:grid; gap:16px; grid-template-columns: repeat(2, minmax(0, 1fr));
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
                width:100%; border-collapse: collapse; min-width: 900px;
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
            .badge.dba { color:#8fd3ff; background: rgba(59,130,246,.12); }
            .badge.approver { color:#a78bfa; background: rgba(124,58,237,.12); }
            .badge.viewer { color:#9aa6b6; background: rgba(145,158,171,.12); }
            .row-actions { display:flex; gap:10px; flex-wrap:wrap; }
            .muted { color:#9aa6b6; }
            .empty-state {
                padding: 60px 28px 80px;
                text-align:center;
                color:#9aa6b6;
            }
            .pagination-wrap { padding: 0 28px 28px; }
            @media (max-width: 900px) {
                .stats-grid { grid-template-columns: repeat(1, minmax(0, 1fr)); }
            }
        </style>
    @endpush

    <div class="contacts-page">
        <div class="page-shell">
            <div class="page-header">
                <div>
                    <div class="eyebrow">Gestión de contactos</div>
                    <h1>Contactos</h1>
                    <p>Administra contactos para alertas y aprobaciones vía WhatsApp. Incluye validación de formato E.164 y unicidad.</p>
                </div>
                <div class="header-actions">
                    <a class="primary-button" href="{{ route('contacts.create') }}">Nuevo contacto</a>
                </div>
            </div>

            <div class="stats-grid">
                <div class="metric-card"><div class="label">Total</div><div class="value">{{ $totalContacts }}</div><div class="hint">Contactos registrados</div></div>
                <div class="metric-card"><div class="label">Activos</div><div class="value">{{ $activeContacts }}</div><div class="hint">Con acceso autorizado</div></div>
            </div>

            <div class="toolbar">
                <form method="GET" action="{{ route('contacts.index') }}" class="search-form">
                    <input class="search-input" type="search" name="q" value="{{ $search }}" placeholder="Buscar por nombre o teléfono">
                    <select class="search-select" name="active">
                        <option value="">Todos los estados</option>
                        <option value="1" @selected($selectedActive === '1')>Activos</option>
                        <option value="0" @selected($selectedActive === '0')>Inactivos</option>
                    </select>
                    <select class="search-select" name="role">
                        <option value="">Todos los roles</option>
                        @foreach(\App\Enums\ContactRole::cases() as $role)
                            <option value="{{ $role->value }}" @selected($selectedRole === $role->value)>{{ $role->value === 'dba' ? 'DBA' : ($role->value === 'approver' ? 'Aprobador' : 'Visualizador') }}</option>
                        @endforeach
                    </select>
                    <button class="ghost-button" type="submit">Filtrar</button>
                    <a class="ghost-button" href="{{ route('contacts.index') }}">Limpiar</a>
                </form>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Contacto</th>
                            <th>Teléfono</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Vinculado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($contacts as $contact)
                            <tr>
                                <td>
                                    <div style="font-weight:800">{{ $contact->name }}</div>
                                    <div class="muted">Desde: {{ $contact->allowed_since?->format('d/m/Y H:i') ?? 'No especificada' }}</div>
                                </td>
                                <td>
                                    <div>{{ $contact->phone_number }}</div>
                                </td>
                                <td>
                                    <span class="badge {{ $contact->role->value }}">
                                        <span class="dot" style="background: currentColor"></span>
                                        {{ $contact->role->value === 'dba' ? 'DBA' : ($contact->role->value === 'approver' ? 'Aprobador' : 'Visualizador') }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $contact->active ? 'active' : 'inactive' }}">
                                        <span class="dot" style="background: currentColor"></span>
                                        {{ $contact->active ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td>
                                    <div>{{ $contact->user ? $contact->user->name : 'Sin usuario' }}</div>
                                    <div class="muted">{{ $contact->servers->count() }} servidor(es)</div>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <a class="ghost-button" href="{{ route('contacts.show', $contact) }}">Ver</a>
                                        <a class="ghost-button" href="{{ route('contacts.edit', $contact) }}">Editar</a>
                                        <form method="POST" action="{{ route('contacts.destroy', $contact) }}" onsubmit="return confirm('¿Eliminar lógicamente este contacto?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="danger-button" type="submit">Eliminar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">No hay contactos que coincidan con el filtro actual.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pagination-wrap">
                {{ $contacts->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
