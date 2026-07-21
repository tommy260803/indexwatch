<x-app-layout>
    @push('styles')
        <style>
            .contact-form-page { padding: 28px; color: var(--text); }
            .page-shell {
                background: linear-gradient(180deg, var(--panel), var(--panel-2));
                border: 1px solid var(--border);
                border-radius: 28px;
                box-shadow: 0 28px 80px rgba(0, 0, 0, .35);
                overflow: hidden;
            }
            .server-card { padding: 0; }
            .form-header {
                display:flex; align-items:flex-start; justify-content:space-between; gap:20px;
                padding: 28px;
                border-bottom: 1px solid var(--border);
                background: radial-gradient(circle at top left, rgba(96, 165, 250, .12), transparent 45%);
            }
            .eyebrow { text-transform: uppercase; letter-spacing: .14em; font-size: 11px; color: var(--accent); margin-bottom: 10px; }
            .form-header h1 { margin: 0; font-size: 30px; line-height: 1.1; color: var(--text); }
            .form-header p { margin: 10px 0 0; color: var(--text-dim); max-width: 72ch; }
            .header-actions { display:flex; gap:12px; flex-wrap:wrap; }
            .primary-button, .ghost-button, .danger-button {
                display:inline-flex; align-items:center; justify-content:center; gap:8px;
                border-radius: 16px; padding: 13px 18px; border:1px solid transparent;
                font-weight: 700; text-decoration:none; cursor:pointer;
            }
            .primary-button { background: linear-gradient(135deg, #5fa3ff, #4d7dff); color: white; }
            .ghost-button { background: var(--panel-2); color: var(--text); border-color: var(--border); }
            .form-grid { padding: 28px; display:grid; gap:20px; }
            .section-card {
                background: var(--panel);
                border: 1px solid var(--border);
                border-radius: 24px;
                padding: 24px;
            }
            .section-card h2 { margin:0 0 16px; font-size:18px; color: var(--text); }
            .section-help { margin:0 0 16px; color: var(--text-dim); font-size:14px; }
            .field-grid { display:grid; gap:16px; }
            .field-grid.two-cols { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .field-grid.three-cols { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .field { display:flex; flex-direction:column; gap:8px; }
            .field span { color: var(--text-dim); font-size:14px; font-weight:600; }
            .field input, .field select, .field textarea {
                border-radius: 14px; border:1px solid var(--border);
                background: var(--panel-2); color: var(--text); padding:12px 14px; outline:none;
                transition: border-color 0.2s, box-shadow 0.2s;
            }
            .field input:focus, .field select:focus, .field textarea:focus {
                border-color: var(--accent);
                box-shadow: 0 0 0 4px var(--accent-soft);
            }
            .field small { color: var(--crit); font-size:13px; }
            .toggle-field { display:flex; align-items:center; gap:14px; padding:12px; border-radius:14px; background: var(--panel-2); border:1px solid var(--border); }
            .toggle-field input[type="checkbox"] { width:20px; height:20px; cursor:pointer; }
            .toggle-field span strong { display:block; color: var(--text); }
            .toggle-field span small { display:block; color: var(--text-dim); }
            .section-wide { grid-column: 1 / -1; }
            @media (max-width: 900px) {
                .field-grid.two-cols, .field-grid.three-cols { grid-template-columns: 1fr; }
            }
        </style>
    @endpush

    <div class="contact-form-page">
        <div class="page-shell">
            @include('contacts.partials.form', [
                'formAction' => route('contacts.store'),
                'httpMethod' => 'POST',
                'eyebrow' => 'Nuevo contacto',
                'title' => 'Crear contacto',
                'subtitle' => 'Registra un nuevo contacto para alertas y aprobaciones. El teléfono debe ser único y con formato E.164.',
                'submitLabel' => 'Crear contacto',
            ])
        </div>
    </div>
</x-app-layout>
