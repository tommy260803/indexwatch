<?php

namespace App\Jobs;

use App\Models\Server;
use App\Services\Monitoring\ServerScanService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class ScanServerJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // El job puede fallar por red, SQL Server o bloqueo temporal.
    // Estos límites evitan reintentos infinitos y jobs colgados.
    public int $tries = 3;

    public int $timeout = 300;

    // La unicidad cubre el caso en que se dispare el mismo escaneo dos veces.
    // Así evitamos duplicar lecturas, alertas y escrituras sobre el mismo servidor.
    public int $uniqueFor = 600;

    public readonly string $correlationId;

    public function __construct(public readonly int $serverId, ?string $correlationId = null)
    {
        $this->correlationId = $correlationId ?? (string) Str::uuid();
        $this->onQueue('scans');
    }

    public function uniqueId(): string
    {
        return (string) $this->serverId;
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('indexwatch-scan-server-'.$this->serverId))
                ->releaseAfter(30)
                ->expireAfter($this->timeout + 60),
        ];
    }

    public function handle(ServerScanService $scanner): void
    {
        // Si el servidor fue eliminado o desactivado, salir temprano es más seguro
        // que intentar conectar y producir errores innecesarios.
        $server = Server::query()->find($this->serverId);

        if ($server === null || ! $server->isActive()) {
            return;
        }

        // Este servicio concentra toda la lógica pesada del escaneo.
        // El job solo orquesta y deja la ejecución real en un servicio testeable.
        $scanner->scan($server, $this->correlationId);
    }
}
