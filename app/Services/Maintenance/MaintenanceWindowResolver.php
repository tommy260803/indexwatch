<?php

namespace App\Services\Maintenance;

use App\Models\MaintenanceWindow;
use App\Models\Server;
use Carbon\CarbonImmutable;

class MaintenanceWindowResolver
{
    public function resolveNextWindow(Server $server, ?CarbonImmutable $from = null): ?CarbonImmutable
    {
        // Resuelve la próxima ventana a partir del reloj del servidor, no del servidor web.
        // Eso evita errores cuando el monitoreo está en otra zona horaria.
        $from = $from ?? CarbonImmutable::now($server->timezone);
        $dayOfWeek = (int) $from->format('w'); // 0=Sunday ... 6=Saturday

        $windows = $this->getWindowsForDay($server, $dayOfWeek);

        if ($windows->isEmpty()) {
            return $this->findNextDayWithWindow($server, $from);
        }

        foreach ($windows as $window) {
            $start = $this->makeWindowStart($from, $window);
            $end = $this->makeWindowEnd($from, $window);

            // Si la ventana aún no empieza hoy, esa es la próxima ejecución válida.
            if ($from->lt($start)) {
                return $start;
            }

            // Si ya estamos dentro de la ventana, la acción puede ejecutarse ya.
            if ($from->gte($start) && $from->lt($end)) {
                return $from;
            }
        }

        // Si ya no queda ventana hoy, se busca desde el siguiente día.
        return $this->findNextDayWithWindow($server, $from);
    }

    public function resolveImmediateOrNext(Server $server, ?CarbonImmutable $from = null): ?CarbonImmutable
    {
        $from = $from ?? CarbonImmutable::now($server->timezone);

        if ($this->isWithinWindow($server, $from)) {
            return $from;
        }

        return $this->resolveNextWindow($server, $from);
    }

    public function isWithinWindow(Server $server, ?CarbonImmutable $at = null): bool
    {
        // Para ventanas que cruzan medianoche, no basta con mirar el día actual.
        // También hay que revisar el día anterior con el reloj actual.
        $at = $at ?? CarbonImmutable::now($server->timezone);
        $dayOfWeek = (int) $at->format('w');

        // Primer intento: ventanas del día actual.
        if ($this->dayHasActiveWindow($server, $at, $at, $dayOfWeek)) {
            return true;
        }

        // Segundo intento: una ventana que empezó ayer y sigue abierta hoy.
        $prevDay = $at->subDay();
        $prevDayOfWeek = (int) $prevDay->format('w');

        return $this->dayHasActiveWindow($server, $prevDay, $at, $prevDayOfWeek);
    }

    private function dayHasActiveWindow(Server $server, CarbonImmutable $dayAnchor, CarbonImmutable $compareAt, int $dayOfWeek): bool
    {
        $windows = $this->getWindowsForDay($server, $dayOfWeek);

        foreach ($windows as $window) {
            $start = $this->makeWindowStart($dayAnchor, $window);
            $end = $this->makeWindowEnd($dayAnchor, $window);

            if ($compareAt->gte($start) && $compareAt->lt($end)) {
                return true;
            }
        }

        return false;
    }

    private function getWindowsForDay(Server $server, int $dayOfWeek)
    {
        return MaintenanceWindow::query()
            ->where('server_id', $server->id)
            ->where('active', true)
            ->where('day_of_week', $dayOfWeek)
            ->orderBy('start_time')
            ->get();
    }

    private function findNextDayWithWindow(Server $server, CarbonImmutable $from): ?CarbonImmutable
    {
        for ($i = 1; $i <= 7; $i++) {
            $candidate = $from->copy()->addDays($i)->startOfDay();
            $dayOfWeek = (int) $candidate->format('w');

            $window = $this->getWindowsForDay($server, $dayOfWeek)->first();

            if ($window) {
                return $this->makeWindowStart($candidate, $window);
            }
        }

        return null;
    }

    private function makeWindowStart(CarbonImmutable $date, MaintenanceWindow $window): CarbonImmutable
    {
        $tz = $this->resolveTimezone($window);
        $dateInTz = $date->setTimezone($tz);

        return $dateInTz->setTimeFromTimeString($window->start_time);
    }

    private function makeWindowEnd(CarbonImmutable $date, MaintenanceWindow $window): CarbonImmutable
    {
        $tz = $this->resolveTimezone($window);
        $dateInTz = $date->setTimezone($tz);

        $start = $dateInTz->setTimeFromTimeString($window->start_time);
        $end = $dateInTz->setTimeFromTimeString($window->end_time);

        // Si el cierre queda antes que la apertura, la ventana cruza medianoche.
        // En ese caso se suma un día al cierre para conservar el intervalo correcto.
        if ($end->lt($start)) {
            $end = $end->addDay();
        }

        return $end;
    }

    private function resolveTimezone(MaintenanceWindow $window): string
    {
        return $window->timezone ?: ($window->server?->timezone ?? config('app.timezone', 'UTC'));
    }
}
