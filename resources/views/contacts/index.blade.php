<x-app-layout>
    <div class="page-shell-content">
        <div class="page-shell">
            <x-page-header
                eyebrow="Gestión de contactos"
                title="Contactos"
                subtitle="Administra contactos para alertas y aprobaciones vía WhatsApp. Incluye validación de formato E.164 y unicidad.">
                <x-slot:actions>
                    <a class="primary-button" href="{{ route('contacts.create') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                        Nuevo contacto
                    </a>
                </x-slot:actions>
            </x-page-header>

            <div class="stats-grid two-cols">
                <x-metric-card label="Total" :value="$totalContacts" hint="Contactos registrados" />
                <x-metric-card label="Activos" :value="$activeContacts" hint="Con acceso autorizado" />
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
                            <option value="{{ $role->value }}" @selected($selectedRole === $role->value)>
                                {{ $role->value === 'dba' ? 'DBA' : ($role->value === 'approver' ? 'Aprobador' : 'Visualizador') }}
                            </option>
                        @endforeach
                    </select>
                    <button class="ghost-button" type="submit">Filtrar</button>
                    <a class="ghost-button" href="{{ route('contacts.index') }}">Limpiar</a>
                </form>
            </div>

            <div class="data-table-wrap">
                <table class="data-table">
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
                                    <x-badge :variant="$contact->role->value">
                                        {{ $contact->role->value === 'dba' ? 'DBA' : ($contact->role->value === 'approver' ? 'Aprobador' : 'Visualizador') }}
                                    </x-badge>
                                </td>
                                <td>
                                    <x-badge :variant="$contact->active ? 'active' : 'inactive'">
                                        {{ $contact->active ? 'Activo' : 'Inactivo' }}
                                    </x-badge>
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
                            <x-empty-state colspan="6" title="Sin contactos" message="No hay contactos que coincidan con el filtro actual." />
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pagination-wrap">
                {{ $contacts->links('vendor.pagination.indexwatch') }}
            </div>
        </div>
    </div>
</x-app-layout>
