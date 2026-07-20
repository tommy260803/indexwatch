<?php

namespace Tests\Unit;

use App\Models\MaintenanceWindow;
use App\Models\Server;
use App\Services\Maintenance\MaintenanceWindowResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MaintenanceWindowResolverTest extends TestCase
{
    use RefreshDatabase;

    private MaintenanceWindowResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new MaintenanceWindowResolver;
    }

    // ------------------------------------------------------------------ helpers

    private function createServer(string $tz = 'America/Lima'): Server
    {
        return Server::factory()->create(['timezone' => $tz]);
    }

    private function createWindow(
        Server $server,
        int $dayOfWeek,
        string $start,
        string $end,
        bool $active = true,
        ?string $tz = null,
    ): MaintenanceWindow {
        return $server->maintenanceWindows()->create([
            'day_of_week' => $dayOfWeek,
            'start_time' => $start,
            'end_time' => $end,
            'active' => $active,
            'timezone' => $tz,
        ]);
    }

    // -------------------------------------------------------- resolveNextWindow

    #[Test]
    public function returns_now_when_inside_active_window(): void
    {
        $server = $this->createServer();
        $now = CarbonImmutable::create(2026, 7, 19, 10, 30, 0, 'America/Lima');
        // Sunday = 0
        $this->createWindow($server, 0, '09:00', '12:00');

        $result = $this->resolver->resolveNextWindow($server, $now);

        $this->assertTrue($result->eq($now));
    }

    #[Test]
    public function returns_window_start_when_before_window_today(): void
    {
        $server = $this->createServer();
        $now = CarbonImmutable::create(2026, 7, 19, 8, 0, 0, 'America/Lima');
        $this->createWindow($server, 0, '09:00', '12:00');

        $result = $this->resolver->resolveNextWindow($server, $now);

        $this->assertEquals('09:00', $result->format('H:i'));
    }

    #[Test]
    public function skips_past_windows_and_returns_next_one_today(): void
    {
        $server = $this->createServer();
        $now = CarbonImmutable::create(2026, 7, 19, 14, 0, 0, 'America/Lima');
        // Morning window already passed
        $this->createWindow($server, 0, '08:00', '10:00');
        // Afternoon window still ahead
        $this->createWindow($server, 0, '15:00', '18:00');

        $result = $this->resolver->resolveNextWindow($server, $now);

        $this->assertEquals('15:00', $result->format('H:i'));
    }

    #[Test]
    public function finds_next_day_when_no_more_windows_today(): void
    {
        $server = $this->createServer();
        // Sunday 20:00 — all Sunday windows are past
        $now = CarbonImmutable::create(2026, 7, 19, 20, 0, 0, 'America/Lima');
        $this->createWindow($server, 0, '08:00', '10:00');
        // Monday window at 09:00
        $this->createWindow($server, 1, '09:00', '12:00');

        $result = $this->resolver->resolveNextWindow($server, $now);

        $this->assertEquals('2026-07-20 09:00', $result->format('Y-m-d H:i'));
    }

    #[Test]
    public function returns_null_when_no_windows_configured(): void
    {
        $server = $this->createServer();
        $now = CarbonImmutable::create(2026, 7, 19, 10, 0, 0, 'America/Lima');

        $result = $this->resolver->resolveNextWindow($server, $now);

        $this->assertNull($result);
    }

    #[Test]
    public function returns_null_when_all_windows_inactive(): void
    {
        $server = $this->createServer();
        $now = CarbonImmutable::create(2026, 7, 19, 10, 0, 0, 'America/Lima');
        $this->createWindow($server, 0, '09:00', '12:00', active: false);

        $result = $this->resolver->resolveNextWindow($server, $now);

        $this->assertNull($result);
    }

    #[Test]
    public function handles_midnight_crossing_window(): void
    {
        $server = $this->createServer();
        // 23:00 – 02:00 window (crosses midnight)
        $this->createWindow($server, 0, '23:00', '02:00');

        // Inside the window at 23:30 Sunday
        $at = CarbonImmutable::create(2026, 7, 19, 23, 30, 0, 'America/Lima');
        $this->assertTrue($this->resolver->isWithinWindow($server, $at));

        // Still inside at 00:30 Monday (same window)
        $at2 = CarbonImmutable::create(2026, 7, 20, 0, 30, 0, 'America/Lima');
        $this->assertTrue($this->resolver->isWithinWindow($server, $at2));

        // Outside at 02:01
        $at3 = CarbonImmutable::create(2026, 7, 20, 2, 1, 0, 'America/Lima');
        $this->assertFalse($this->resolver->isWithinWindow($server, $at3));
    }

    #[Test]
    public function resolve_next_window_with_midnight_crossing(): void
    {
        $server = $this->createServer();
        $this->createWindow($server, 0, '23:00', '02:00');

        // Before the window at 20:00 Sunday
        $at = CarbonImmutable::create(2026, 7, 19, 20, 0, 0, 'America/Lima');
        $result = $this->resolver->resolveNextWindow($server, $at);

        $this->assertEquals('23:00', $result->format('H:i'));
    }

    #[Test]
    public function respects_window_timezone_over_server_timezone(): void
    {
        $server = $this->createServer('America/Lima'); // UTC-5
        $this->createWindow($server, 0, '09:00', '12:00', tz: 'America/New_York'); // UTC-4 / UTC-5

        // 08:00 Lima = 09:00 New York (during standard time they differ by 1h)
        // Using July so both are DST: Lima UTC-5, NY UTC-4 → 1h difference
        $at = CarbonImmutable::create(2026, 7, 19, 8, 30, 0, 'America/Lima');

        // In NY timezone it's 09:30 → inside the 09:00-12:00 window
        $this->assertTrue($this->resolver->isWithinWindow($server, $at));
    }

    #[Test]
    public function is_within_window_returns_false_outside(): void
    {
        $server = $this->createServer();
        $this->createWindow($server, 0, '09:00', '12:00');

        $at = CarbonImmutable::create(2026, 7, 19, 13, 0, 0, 'America/Lima');
        $this->assertFalse($this->resolver->isWithinWindow($server, $at));
    }

    #[Test]
    public function is_within_window_returns_false_when_no_windows(): void
    {
        $server = $this->createServer();
        $at = CarbonImmutable::create(2026, 7, 19, 10, 0, 0, 'America/Lima');

        $this->assertFalse($this->resolver->isWithinWindow($server, $at));
    }

    #[Test]
    public function find_next_day_wraps_around_week(): void
    {
        $server = $this->createServer();
        // Saturday 23:00 — no more windows until Monday
        $now = CarbonImmutable::create(2026, 7, 25, 23, 0, 0, 'America/Lima'); // Saturday
        // Only Monday window
        $this->createWindow($server, 1, '08:00', '10:00');

        $result = $this->resolver->resolveNextWindow($server, $now);

        // Should find Monday July 27
        $this->assertEquals('2026-07-27', $result->format('Y-m-d'));
        $this->assertEquals('08:00', $result->format('H:i'));
    }

    #[Test]
    public function resolve_immediate_or_next_returns_now_inside_window(): void
    {
        $server = $this->createServer();
        $now = CarbonImmutable::create(2026, 7, 19, 10, 0, 0, 'America/Lima');
        $this->createWindow($server, 0, '09:00', '12:00');

        $result = $this->resolver->resolveImmediateOrNext($server, $now);

        $this->assertTrue($result->eq($now));
    }

    #[Test]
    public function resolve_immediate_or_next_returns_next_when_outside(): void
    {
        $server = $this->createServer();
        $now = CarbonImmutable::create(2026, 7, 19, 8, 0, 0, 'America/Lima');
        $this->createWindow($server, 0, '09:00', '12:00');

        $result = $this->resolver->resolveImmediateOrNext($server, $now);

        $this->assertEquals('09:00', $result->format('H:i'));
    }

    #[Test]
    public function window_end_equals_start_does_not_count_as_active(): void
    {
        $server = $this->createServer();
        // Degenerate window: 10:00 – 10:00 (zero-length)
        $this->createWindow($server, 0, '10:00', '10:00');

        $at = CarbonImmutable::create(2026, 7, 19, 10, 0, 0, 'America/Lima');
        $this->assertFalse($this->resolver->isWithinWindow($server, $at));
    }
}
