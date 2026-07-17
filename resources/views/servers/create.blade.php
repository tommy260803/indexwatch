<x-app-layout>
    @push('styles')
        <style>
            .servers-page {
                padding: 28px;
                color: #e7ebf3;
            }
            .server-card {
                background: linear-gradient(180deg, rgba(18,24,33,.98), rgba(14,19,26,.98));
                border: 1px solid rgba(145, 158, 171, .18);
                border-radius: 28px;
                box-shadow: 0 28px 80px rgba(0, 0, 0, .35);
                overflow: hidden;
            }
            .form-header, .page-header {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 20px;
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
            .form-header h1, .page-header h1 {
                margin: 0;
                font-size: 30px;
                line-height: 1.1;
            }
            .form-header p, .page-header p {
                margin: 10px 0 0;
                color: #9aa6b6;
                max-width: 72ch;
            }
            .header-actions { display:flex; gap:12px; flex-wrap:wrap; }
            .primary-button, .ghost-button, .danger-button {
                display:inline-flex; align-items:center; justify-content:center; gap:8px;
                border-radius: 16px; padding: 13px 18px; border:1px solid transparent;
                font-weight: 700; text-decoration:none; cursor:pointer;
            }
            .primary-button { background: linear-gradient(135deg, #5fa3ff, #4d7dff); color: white; }
            .ghost-button { background: rgba(255,255,255,.04); color: #edf2f7; border-color: rgba(145,158,171,.18); }
            .danger-button { background: rgba(239, 68, 68, .12); color: #ffb4b4; border-color: rgba(239,68,68,.2); }
            .form-grid, .dashboard-grid { display:grid; gap:20px; padding:28px; }
            .section-card, .metric-card, .info-card {
                background: rgba(255,255,255,.03);
                border: 1px solid rgba(145, 158, 171, .14);
                border-radius: 24px;
                padding: 22px;
            }
            .section-card h2 { margin: 0 0 6px; font-size: 18px; }
            .section-help { margin: 0 0 18px; color: #8d98a8; font-size: 13px; }
            .field-grid { display:grid; gap:16px; }
            .field-grid.two-cols { grid-template-columns: repeat(2, minmax(0,1fr)); }
            .field-grid.three-cols { grid-template-columns: repeat(2, minmax(0,1fr)); }
            .section-wide { grid-column: 1 / -1; }
            .field { display:grid; gap:9px; }
            .field span { font-size: 13px; color: #b9c2cf; font-weight: 600; }
            .field input, .field select {
                width:100%; border-radius:16px; border:1px solid rgba(145,158,171,.2);
                background: rgba(8, 12, 18, .92); color:#f1f5f9; padding:14px 16px;
                outline:none; transition: border-color .2s, box-shadow .2s;
            }
            .field input:focus, .field select:focus {
                border-color:#5fa3ff; box-shadow:0 0 0 3px rgba(95,163,255,.16);
            }
            .field small { color:#ff9ca3; }
            .connection-grid { display:grid; gap:16px; grid-template-columns: repeat(3, minmax(0,1fr)); }
            .toggle-field {
                display:flex; gap:14px; align-items:flex-start; padding:16px; border-radius:18px;
                background: rgba(8,12,18,.72); border:1px solid rgba(145,158,171,.16);
            }
            .toggle-field input[type="checkbox"] { width:18px; height:18px; margin-top:2px; accent-color:#5fa3ff; }
            .toggle-field strong { display:block; margin-bottom:4px; }
            .toggle-field small { color:#98a5b8; display:block; }
            @media (max-width: 1100px) {
                .field-grid.two-cols, .field-grid.three-cols, .connection-grid { grid-template-columns: 1fr; }
            }
        </style>
    @endpush

    <div class="servers-page">
        @include('servers.partials.form', [
            'server' => $server,
            'contacts' => $contacts,
            'timezones' => $timezones,
            'formAction' => route('servers.store'),
            'httpMethod' => 'POST',
            'eyebrow' => 'Catálogo de servidores',
            'title' => 'Nuevo servidor SQL Server',
            'subtitle' => 'Registra la conexión, umbrales, opciones estructuradas y contactos relacionados. La contraseña se cifra automáticamente.',
            'submitLabel' => 'Crear servidor',
        ])
    </div>
</x-app-layout>