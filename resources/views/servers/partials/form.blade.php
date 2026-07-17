@php
    $connectionOptions = old('connection_options', $server->connection_options ?? []);
    $selectedContacts = old('contacts', $server->exists ? $server->contacts->pluck('id')->all() : []);
    $currentStatus = old('status', $server->status instanceof \App\Enums\ServerStatus ? $server->status->value : ($server->status ?? 'active'));
@endphp

<form method="POST" action="{{ $formAction }}" class="server-card server-form">
    @csrf
    @if($httpMethod !== 'POST')
        @method($httpMethod)
    @endif

    <div class="form-header">
        <div>
            <div class="eyebrow">{{ $eyebrow }}</div>
            <h1>{{ $title }}</h1>
            <p>{{ $subtitle }}</p>
        </div>
        <div class="header-actions">
            <a class="ghost-button" href="{{ route('servers.index') }}">Volver</a>
            <button class="primary-button" type="submit">{{ $submitLabel }}</button>
        </div>
    </div>

    <div class="form-grid">
        <section class="section-card">
            <h2>Identidad del servidor</h2>
            <div class="field-grid two-cols">
                <label class="field">
                    <span>Nombre</span>
                    <input type="text" name="name" value="{{ old('name', $server->name) }}" placeholder="SQL01-PROD" required>
                    @error('name')<small>{{ $message }}</small>@enderror
                </label>
                <label class="field">
                    <span>Host</span>
                    <input type="text" name="host" value="{{ old('host', $server->host) }}" placeholder="10.0.0.12 o db01.midominio.local" required>
                    @error('host')<small>{{ $message }}</small>@enderror
                </label>
                <label class="field">
                    <span>Puerto</span>
                    <input type="number" name="port" value="{{ old('port', $server->port ?? 1433) }}" min="1" max="65535" required>
                    @error('port')<small>{{ $message }}</small>@enderror
                </label>
                <label class="field">
                    <span>Zona horaria</span>
                    <select name="timezone" required>
                        @foreach($timezones as $timezone)
                            <option value="{{ $timezone }}" @selected(old('timezone', $server->timezone ?? 'America/Lima') === $timezone)>{{ $timezone }}</option>
                        @endforeach
                    </select>
                    @error('timezone')<small>{{ $message }}</small>@enderror
                </label>
            </div>
        </section>

        <section class="section-card">
            <h2>Credenciales y estado</h2>
            <div class="field-grid two-cols">
                <label class="field">
                    <span>Base de datos</span>
                    <input type="text" name="database_name" value="{{ old('database_name', $server->database_name) }}" placeholder="IndexWatch" required>
                    @error('database_name')<small>{{ $message }}</small>@enderror
                </label>
                <label class="field">
                    <span>Usuario</span>
                    <input type="text" name="username" value="{{ old('username', $server->username) }}" placeholder="sa" required>
                    @error('username')<small>{{ $message }}</small>@enderror
                </label>
                <label class="field">
                    <span>Contraseña {{ $server->exists ? '(dejar en blanco para conservar)' : '' }}</span>
                    <input type="password" name="password" value="" placeholder="••••••••" {{ $server->exists ? '' : 'required' }}>
                    @error('password')<small>{{ $message }}</small>@enderror
                </label>
                <label class="field">
                    <span>Estado</span>
                    <select name="status" required>
                        @foreach(\App\Enums\ServerStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected($currentStatus === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                    @error('status')<small>{{ $message }}</small>@enderror
                </label>
            </div>
        </section>

        <section class="section-card">
            <h2>Umbrales y límites</h2>
            <div class="field-grid three-cols">
                <label class="field">
                    <span>Warning (%)</span>
                    <input type="number" step="0.01" min="0" max="100" name="warning_threshold" value="{{ old('warning_threshold', $server->warning_threshold ?? 5.00) }}" required>
                    @error('warning_threshold')<small>{{ $message }}</small>@enderror
                </label>
                <label class="field">
                    <span>Critical (%)</span>
                    <input type="number" step="0.01" min="0" max="100" name="critical_threshold" value="{{ old('critical_threshold', $server->critical_threshold ?? 30.00) }}" required>
                    @error('critical_threshold')<small>{{ $message }}</small>@enderror
                </label>
                <label class="field">
                    <span>Stale stats (%)</span>
                    <input type="number" step="0.01" min="0" max="100" name="stats_stale_threshold" value="{{ old('stats_stale_threshold', $server->stats_stale_threshold ?? 20.00) }}">
                    @error('stats_stale_threshold')<small>{{ $message }}</small>@enderror
                </label>
                <label class="field">
                    <span>Minimum index pages</span>
                    <input type="number" step="1" min="0" name="minimum_index_pages" value="{{ old('minimum_index_pages', $server->minimum_index_pages ?? 1000) }}">
                    @error('minimum_index_pages')<small>{{ $message }}</small>@enderror
                </label>
            </div>
        </section>

        <section class="section-card section-wide">
            <h2>Connection options</h2>
            <p class="section-help">Solo se admiten claves controladas por el formulario. No se aceptan claves arbitrarias.</p>
            <div class="connection-grid">
                <label class="toggle-field">
                    <input type="hidden" name="connection_options[encrypt]" value="0">
                    <input type="checkbox" name="connection_options[encrypt]" value="1" @checked((bool) data_get($connectionOptions, 'encrypt', false))>
                    <span>
                        <strong>Encrypt</strong>
                        <small>Forzar cifrado del canal de conexión</small>
                    </span>
                </label>
                <label class="toggle-field">
                    <input type="hidden" name="connection_options[trust_server_certificate]" value="0">
                    <input type="checkbox" name="connection_options[trust_server_certificate]" value="1" @checked((bool) data_get($connectionOptions, 'trust_server_certificate', false))>
                    <span>
                        <strong>Trust server certificate</strong>
                        <small>Aceptar certificado del servidor SQL Server</small>
                    </span>
                </label>
                <label class="field">
                    <span>Connection timeout</span>
                    <input type="number" min="1" max="120" step="1" name="connection_options[timeout]" value="{{ old('connection_options.timeout', data_get($connectionOptions, 'timeout')) }}" placeholder="30">
                    @error('connection_options.timeout')<small>{{ $message }}</small>@enderror
                </label>
            </div>
            @error('connection_options')<small>{{ $message }}</small>@enderror
        </section>

        <section class="section-card section-wide">
            <h2>Contactos asignados</h2>
            <p class="section-help">Relaciona el servidor con contactos para alertas y operación. Opcional.</p>
            <label class="field">
                <span>Contacts</span>
                <select name="contacts[]" multiple size="6">
                    @foreach($contacts as $contact)
                        <option value="{{ $contact->id }}" @selected(in_array($contact->id, $selectedContacts, false))>{{ $contact->getDisplayName() }} - {{ $contact->phone_number }}</option>
                    @endforeach
                </select>
                @error('contacts')<small>{{ $message }}</small>@enderror
                @error('contacts.*')<small>{{ $message }}</small>@enderror
            </label>
        </section>
    </div>
</form>