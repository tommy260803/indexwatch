@php
    $selectedUsers = old('user_id', $contact->user_id ?? null);
    $selectedServers = old('servers', $contact->exists ? $contact->servers->pluck('id')->all() : []);
    $currentRole = old('role', $contact->role instanceof \App\Enums\ContactRole ? $contact->role->value : ($contact->role ?? 'viewer'));
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
            <a class="ghost-button" href="{{ route('contacts.index') }}">Volver</a>
            <button class="primary-button" type="submit">{{ $submitLabel }}</button>
        </div>
    </div>

    <div class="form-grid">
        <section class="section-card">
            <h2>Información básica</h2>
            <div class="field-grid two-cols">
                <label class="field">
                    <span>Nombre</span>
                    <input type="text" name="name" value="{{ old('name', $contact->name) }}" placeholder="Juan Pérez" required>
                    @error('name')<small>{{ $message }}</small>@enderror
                </label>
                <label class="field">
                    <span>Número de teléfono (E.164)</span>
                    <input type="text" name="phone_number" value="{{ old('phone_number', $contact->phone_number) }}" placeholder="+51999999999" required>
                    @error('phone_number')<small>{{ $message }}</small>@enderror
                </label>
                <label class="field">
                    <span>Rol</span>
                    <select name="role" required>
                        @foreach(\App\Enums\ContactRole::cases() as $role)
                            <option value="{{ $role->value }}" @selected($currentRole === $role->value)>{{ $role->value === 'dba' ? 'DBA' : ($role->value === 'approver' ? 'Aprobador' : 'Visualizador') }}</option>
                        @endforeach
                    </select>
                    @error('role')<small>{{ $message }}</small>@enderror
                </label>
                <label class="field">
                    <span>Usuario interno (opcional)</span>
                    <select name="user_id">
                        <option value="">Sin vincular</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" @selected($selectedUsers === $user->id)>{{ $user->name }} ({{ $user->email }})</option>
                        @endforeach
                    </select>
                    @error('user_id')<small>{{ $message }}</small>@enderror
                </label>
            </div>
        </section>

        <section class="section-card">
            <h2>Estado y fechas</h2>
            <div class="field-grid two-cols">
                <label class="toggle-field">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" name="active" value="1" @checked(old('active', $contact->active ?? true))>
                    <span>
                        <strong>Activo</strong>
                        <small>Permitir aprobaciones y alertas</small>
                    </span>
                </label>
                <label class="field">
                    <span>Fecha de activación (opcional)</span>
                    <input type="datetime-local" name="allowed_since" value="{{ old('allowed_since', $contact->allowed_since?->format('Y-m-d\TH:i')) }}">
                    @error('allowed_since')<small>{{ $message }}</small>@enderror
                </label>
            </div>
        </section>

        <section class="section-card section-wide">
            <h2>Servidores asignados</h2>
            <p class="section-help">Relaciona el contacto con servidores para recibir alertas específicas. Opcional.</p>
            <label class="field">
                <span>Servidores</span>
                <select name="servers[]" multiple size="6">
                    @foreach($servers as $server)
                        <option value="{{ $server->id }}" @selected(in_array($server->id, $selectedServers, false))>{{ $server->name }} ({{ $server->host }}:{{ $server->port }})</option>
                    @endforeach
                </select>
                @error('servers')<small>{{ $message }}</small>@enderror
                @error('servers.*')<small>{{ $message }}</small>@enderror
            </label>
        </section>
    </div>
</form>
