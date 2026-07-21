<x-app-layout>
    @push('styles')
        <style>
            .servers-page { padding: 28px; color: var(--text); }
            .server-card {
                background: linear-gradient(180deg, var(--panel), var(--panel-2));
                border: 1px solid var(--border);
                border-radius: 28px;
                box-shadow: 0 28px 80px rgba(0, 0, 0, .35);
                overflow: hidden;
            }
            .form-header {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 20px;
                padding: 28px;
                border-bottom: 1px solid var(--border);
                background: radial-gradient(circle at top left, rgba(96, 165, 250, .12), transparent 45%);
            }
            .form-header h1 { margin: 0; font-size: 30px; line-height: 1.1; color: var(--text); }
            .form-header p { margin: 10px 0 0; color: var(--text-dim); max-width: 72ch; }
            .form-grid, .dashboard-grid { display: grid; gap: 20px; padding: 28px; }
            .metric-card {
                background: var(--panel);
                border: 1px solid var(--border);
                border-radius: 24px;
                padding: 22px;
            }
            .section-help { margin: 0 0 18px; color: var(--text-dim); font-size: 13px; }
            .field-grid.two-cols { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .field-grid.three-cols { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .section-wide { grid-column: 1 / -1; }
            .field { display: grid; gap: 9px; }
            .field span { font-size: 13px; color: var(--text-dim); font-weight: 600; }
            .field input, .field select {
                width: 100%; border-radius: 16px; border: 1px solid var(--border);
                background: var(--panel-2); color: var(--text); padding: 14px 16px;
                outline: none; transition: border-color .2s, box-shadow .2s;
            }
            .field input:focus, .field select:focus {
                border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft);
            }
            .field small { color: var(--crit); }
            .toggle-field {
                display: flex; gap: 14px; align-items: flex-start; padding: 16px; border-radius: 18px;
                background: var(--panel-2); border: 1px solid var(--border);
            }
            .toggle-field input[type="checkbox"] { width: 18px; height: 18px; margin-top: 2px; accent-color: var(--accent); }
            .toggle-field strong { display: block; margin-bottom: 4px; color: var(--text); }
            .toggle-field small { color: var(--text-dim); display: block; }
            @media (max-width: 1100px) {
                .field-grid.two-cols, .field-grid.three-cols { grid-template-columns: 1fr; }
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