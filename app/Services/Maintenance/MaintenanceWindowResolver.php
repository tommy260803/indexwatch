<?php

namespace App\Services\Maintenance;

use App\Models\MaintenanceWindow;
use App\Models\Server;
use Carbon\CarbonImmutable;

class MaintenanceWindowResolver
{
    public function resolveNextWindow(Server $server, ?CarbonImmutable $from = null): ?CarbonImmutable
    {
        $from = $from ?? CarbonImmutable::now($server->timezone);
        $dayOfWeek = (int) $from->format('w'); // 0=Sunday ... 6=Saturday

        $windows = $this->getWindowsForDay($server, $dayOfWeek);

        if ($windows->isEmpty()) {
            return $this->findNextDayWithWindow($server, $from);
        }

        foreach ($windows as $window) {
            $start = $this->makeWindowStart($from, $window);
            $end = $this->makeWindowEnd($from, $window);

            // If window hasn't started yet today, use it
            if ($from->lt($start)) {
                return $start;
            }

            // If window is active now, use now
            if ($from->gte($start) && $from->lt($end)) {
                return $from;
            }
        }

        // No more windows today, check from tomorrow onward
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
        $at = $at ?? CarbonImmutable::now($server->timezone);
        $dayOfWeek = (int) $at->format('w');

        // Check today's windows
        if ($this->dayHasActiveWindow($server, $at, $at, $dayOfWeek)) {
            return true;
        }

        // Also check previous day's windows for early morning — a window stored
        // as Sunday 23:00–02:00 is still active at Monday 00:30.
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

        // Handle windows that cross midnight (e.g., 23:00–02:00).
        // Strictly less-than: if start == end it's a zero-length window, not midnight-crossing.
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
