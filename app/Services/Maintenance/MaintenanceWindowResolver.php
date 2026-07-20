<?php

namespace App\Services\Maintenance;

use App\Models\MaintenanceWindow;
use App\Models\Server;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

class MaintenanceWindowResolver
{
    public function resolveNextWindow(Server $server, ?CarbonImmutable $from = null): ?CarbonImmutable
    {
        $from = $from ?? CarbonImmutable::now($server->timezone);
        $dayOfWeek = (int) $from->format('w'); // 0=Sunday ... 6=Saturday

        $windows = MaintenanceWindow::query()
            ->where('server_id', $server->id)
            ->where('active', true)
            ->where('day_of_week', $dayOfWeek)
            ->orderBy('start_time')
            ->get();

        if ($windows->isEmpty()) {
            return $this->findNextDayWithWindow($server, $from);
        }

        foreach ($windows as $window) {
            $start = $this->makeWindowStart($from, $window);
            $end   = $this->makeWindowEnd($from, $window);

            // If window hasn't started yet today, use it
            if ($from->lt($start)) {
                return $start;
            }

            // If window is active now, use now
            if ($from->gte($start) && $from->lt($end)) {
                return $from;
            }
        }

        // No more windows today, check tomorrow
        return $this->findNextDayWithWindow($server, $from->addDay()->startOfDay());
    }

    private function findNextDayWithWindow(Server $server, CarbonImmutable $from): ?CarbonImmutable
    {
        // Check up to 7 days ahead
        for ($i = 1; $i <= 7; $i++) {
            $candidate = $from->copy()->addDays($i)->startOfDay();
            $dayOfWeek = (int) $candidate->format('w');

            $window = MaintenanceWindow::query()
                ->where('server_id', $server->id)
                ->where('active', true)
                ->where('day_of_week', $dayOfWeek)
                ->orderBy('start_time')
                ->first();

            if ($window) {
                return $this->makeWindowStart($candidate, $window);
            }
        }

        return null;
    }

    private function makeWindowStart(CarbonImmutable $date, MaintenanceWindow $window): CarbonImmutable
    {
        return $date->setTimeFrom($window->start_time);
    }

    private function makeWindowEnd(CarbonImmutable $date, MaintenanceWindow $window): CarbonImmutable
    {
        $end = $date->setTimeFrom($window->end_time);

        // Handle windows that cross midnight (e.g., 22:00 - 04:00)
        if ($end->lte($date->setTimeFrom($window->start_time))) {
            $end = $end->addDay();
        }

        return $end;
    }

    public function isWithinWindow(Server $server, ?CarbonImmutable $at = null): bool
    {
        $at = $at ?? CarbonImmutable::now($server->timezone);
        $dayOfWeek = (int) $at->format('w');

        $windows = MaintenanceWindow::query()
            ->where('server_id', $server->id)
            ->where('active', true)
            ->where('day_of_week', $dayOfWeek)
            ->get();

        foreach ($windows as $window) {
            $start = $this->makeWindowStart($at, $window);
            $end   = $this->makeWindowEnd($at, $window);

            if ($at->gte($start) && $at->lt($end)) {
                return true;
            }
        }

        return false;
    }
}