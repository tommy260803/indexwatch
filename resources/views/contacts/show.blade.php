<x-app-layout>
    @push('styles')
        <style>
            .contact-show-page { padding: 28px; color: var(--text); }
        </style>
    @endpush

    <div class="contact-show-page">
        <div class="page-shell">
            <div class="page-header">
                <div>
                    <div class="eyebrow">Detalle del contacto</div>
                    <h1>{{ $contact->name }}</h1>
                    <p>Información completa del contacto y sus asociaciones.</p>
                </div>
                <div class="header-actions">
                    <a class="ghost-button" href="{{ route('contacts.index') }}">Volver a la lista</a>
                    <a class="primary-button" href="{{ route('contacts.edit', $contact) }}">Editar</a>
                </div>
            </div>

            <div class="content-grid">
                <div class="detail-card">
                    <h2>Información básica</h2>
                    <div class="detail-row">
                        <div class="detail-label">Nombre</div>
                        <div class="detail-value">{{ $contact->name }}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Teléfono</div>
                        <div class="detail-value">{{ $contact->phone_number }}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Rol</div>
                        <div class="detail-value">
                            <span class="badge {{ $contact->role->value }}">
                                <span class="dot" style="background: currentColor"></span>
                                {{ $contact->role->value === 'dba' ? 'DBA' : ($contact->role->value === 'approver' ? 'Aprobador' : 'Visualizador') }}
                            </span>
                        </div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Estado</div>
                        <div class="detail-value">
                            <span class="badge {{ $contact->active ? 'active' : 'inactive' }}">
                                <span class="dot" style="background: currentColor"></span>
                                {{ $contact->active ? 'Activo' : 'Inactivo' }}
                            </span>
                        </div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Fecha de activación</div>
                        <div class="detail-value">{{ $contact->allowed_since?->format('d/m/Y H:i') ?? 'No especificada' }}</div>
                    </div>
                </div>

                <div class="detail-card">
                    <h2>Vinculaciones</h2>
                    <div class="detail-row">
                        <div class="detail-label">Usuario interno</div>
                        <div class="detail-value">{{ $contact->user ? $contact->user->name . ' (' . $contact->user->email . ')' : 'Sin vincular' }}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Servidores</div>
                    </div>
                    @if($contact->servers->count() > 0)
                        <div class="server-list">
                            @foreach($contact->servers as $server)
                                <div class="server-item">
                                    <strong>{{ $server->name }}</strong>
                                    <div class="muted" style="font-size:13px;">{{ $server->host }}:{{ $server->port }} / {{ $server->database_name }}</div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="muted">Sin servidores asignados</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
