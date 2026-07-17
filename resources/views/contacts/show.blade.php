<x-app-layout>
    @push('styles')
        <style>
            .contact-show-page { padding: 28px; color: #e7ebf3; }
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
            .eyebrow { text-transform: uppercase; letter-spacing: .14em; font-size: 11px; color: #7fb4ff; margin-bottom: 10px; }
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
            .content-grid { padding: 28px; display:grid; gap:20px; }
            .detail-card {
                background: rgba(255,255,255,.03);
                border: 1px solid rgba(145, 158, 171, .14);
                border-radius: 24px;
                padding: 24px;
            }
            .detail-card h2 { margin:0 0 16px; font-size:18px; }
            .detail-row { display:flex; gap:16px; margin-bottom:16px; align-items:center; }
            .detail-label { color:#9aa6b6; font-size:14px; min-width:150px; }
            .detail-value { color:#e7ebf3; font-weight:600; }
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
            .server-list { display:flex; flex-direction:column; gap:10px; }
            .server-item {
                background: rgba(255,255,255,.02);
                border:1px solid rgba(145,158,171,.1);
                border-radius:14px;
                padding:12px 16px;
            }
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
                                    <div style="color:#9aa6b6; font-size:13px;">{{ $server->host }}:{{ $server->port }} / {{ $server->database_name }}</div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div style="color:#9aa6b6;">Sin servidores asignados</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
