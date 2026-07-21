<x-app-layout>
    @push('styles')
        <style>
            .contact-form-page { padding: 28px; color: var(--text); }
            .server-card { padding: 0; }
            .form-header {
                display: flex; align-items: flex-start; justify-content: space-between; gap: 20px;
                padding: 28px;
                border-bottom: 1px solid var(--border);
                background: radial-gradient(circle at top left, rgba(96, 165, 250, .12), transparent 45%);
            }
            .form-header h1 { margin: 0; font-size: 30px; line-height: 1.1; color: var(--text); }
            .form-header p { margin: 10px 0 0; color: var(--text-dim); max-width: 72ch; }
            .form-grid { padding: 28px; display: grid; gap: 20px; }
            .section-help { margin: 0 0 16px; color: var(--text-dim); font-size: 14px; }
            .field-grid.two-cols { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .field-grid.three-cols { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .field { display: flex; flex-direction: column; gap: 8px; }
            .field span { color: var(--text-dim); font-size: 14px; font-weight: 600; }
            .field input, .field select, .field textarea {
                border-radius: 14px; border: 1px solid var(--border);
                background: var(--panel-2); color: var(--text); padding: 12px 14px; outline: none;
                transition: border-color 0.2s, box-shadow 0.2s;
            }
            .field input:focus, .field select:focus, .field textarea:focus {
                border-color: var(--accent);
                box-shadow: 0 0 0 4px var(--accent-soft);
            }
            .field small { color: var(--crit); font-size: 13px; }
            .toggle-field { display: flex; align-items: center; gap: 14px; padding: 12px; border-radius: 14px; background: var(--panel-2); border: 1px solid var(--border); }
            .toggle-field input[type="checkbox"] { width: 20px; height: 20px; cursor: pointer; }
            .toggle-field span strong { display: block; color: var(--text); }
            .toggle-field span small { display: block; color: var(--text-dim); }
            .section-wide { grid-column: 1 / -1; }
            @media (max-width: 900px) {
                .field-grid.two-cols, .field-grid.three-cols { grid-template-columns: 1fr; }
            }
        </style>
    @endpush

    <div class="contact-form-page">
        <div class="page-shell">
            @include('contacts.partials.form', [
                'formAction' => route('contacts.update', $contact),
                'httpMethod' => 'PUT',
                'eyebrow' => 'Editar contacto',
                'title' => 'Actualizar contacto',
                'subtitle' => 'Modifica los datos del contacto. El teléfono debe ser único y con formato E.164.',
                'submitLabel' => 'Guardar cambios',
            ])
        </div>
    </div>
</x-app-layout>
