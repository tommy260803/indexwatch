<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServerRequest;
use App\Http\Requests\UpdateServerRequest;
use App\Models\Contact;
use App\Models\Server;
use App\Services\SqlServer\SqlServerConnectionFactory;
use App\Services\SqlServer\SqlServerErrorSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ServerController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->trim()->toString();

        $query = Server::query()
            ->with(['contacts'])
            ->withCount(['sqlIndexes', 'alerts'])
            ->latest();

        if ($search = trim((string) $request->string('q'))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('host', 'like', "%{$search}%")
                    ->orWhere('database_name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        $servers = $query->paginate(10)->withQueryString();

        $servers->getCollection()->transform(function (Server $server): Server {
            $server->setAttribute('sanitized_last_scan_error', $this->sanitizeScanError($server->last_scan_error));
            return $server;
        });

        return view('servers.index', [
            'servers' => $servers,
            'totalServers' => Server::count(),
            'activeServers' => Server::where('status', 'active')->count(),
            'maintenanceServers' => Server::where('status', 'maintenance')->count(),
            'contactsCount' => Contact::count(),
            'search' => $request->string('q')->toString(),
            'selectedStatus' => $status,
        ]);
    }

    public function create(): View
    {
        return view('servers.create', [
            'server' => new Server(),
            'contacts' => Contact::query()->orderBy('name')->get(),
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function store(StoreServerRequest $request): RedirectResponse
    {
        $server = Server::create($this->payloadFromRequest($request->validated()));
        $server->contacts()->sync($request->validated('contacts', []));

        return redirect()
            ->route('servers.show', $server)
            ->with('status', 'Servidor creado correctamente.');
    }

    public function show(Server $server): View
    {
        $server->load(['contacts', 'sqlIndexes', 'alerts']);
        $server->setAttribute('sanitized_last_scan_error', $this->sanitizeScanError($server->last_scan_error));

        return view('servers.show', [
            'server' => $server,
        ]);
    }

    public function edit(Server $server): View
    {
        return view('servers.edit', [
            'server' => $server->load('contacts'),
            'contacts' => Contact::query()->orderBy('name')->get(),
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function update(UpdateServerRequest $request, Server $server): RedirectResponse
    {
        $server->fill($this->payloadFromRequest($request->validated(), true));
        $server->save();
        $server->contacts()->sync($request->validated('contacts', []));

        return redirect()
            ->route('servers.show', $server)
            ->with('status', 'Servidor actualizado correctamente.');
    }

    public function destroy(Server $server): RedirectResponse
    {
        $server->delete();

        return redirect()
            ->route('servers.index')
            ->with('status', 'Servidor eliminado lógicamente.');
    }

    /**
     * API: Test SQL Server connection without exposing secrets.
     */
    public function testConnection(
        Server $server,
        SqlServerConnectionFactory $connections,
        SqlServerErrorSanitizer $errors,
    ): JsonResponse {
        Gate::authorize('testConnection', $server);

        try {
            $connection = $connections->connect($server);
            $connections->disconnect($server);

            return response()->json([
                'status' => 'success',
                'server' => $server->name,
                'host' => $server->host,
                'database' => $server->database_name,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'error' => $errors->sanitize($e, $server),
            ], 422);
        }
    }

    protected function payloadFromRequest(array $data, bool $updating = false): array
    {
        $payload = Arr::only($data, [
            'name',
            'host',
            'port',
            'database_name',
            'username',
            'password',
            'status',
            'warning_threshold',
            'critical_threshold',
            'stats_stale_threshold',
            'minimum_index_pages',
            'timezone',
        ]);

        $payload['connection_options'] = $this->normalizeConnectionOptions($data['connection_options'] ?? null);

        if (! array_key_exists('stats_stale_threshold', $payload) || $payload['stats_stale_threshold'] === null) {
            $payload['stats_stale_threshold'] = 20.00;
        }

        if (! array_key_exists('minimum_index_pages', $payload) || $payload['minimum_index_pages'] === null) {
            $payload['minimum_index_pages'] = 1000;
        }

        if ($updating && array_key_exists('password', $payload) && blank($payload['password'] ?? null)) {
            unset($payload['password']);
        }

        if (! $updating && blank($payload['password'] ?? null)) {
            unset($payload['password']);
        }

        return $payload;
    }

    protected function normalizeConnectionOptions(mixed $options): ?array
    {
        if (! is_array($options)) {
            return null;
        }

        $allowed = [
            'encrypt' => filter_var($options['encrypt'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'trust_server_certificate' => filter_var($options['trust_server_certificate'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'timeout' => isset($options['timeout']) && $options['timeout'] !== '' ? (int) $options['timeout'] : null,
        ];

        $filtered = array_filter($allowed, static fn (mixed $value): bool => $value !== null);

        return $filtered === [] ? null : $filtered;
    }

    protected function sanitizeScanError(?string $error): ?string
    {
        if ($error === null || trim($error) === '') {
            return null;
        }

        $sanitized = Str::of(strip_tags($error))->squish()->limit(180);

        return (string) $sanitized;
    }
}