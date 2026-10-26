<?php

namespace Tests\Feature;

use App\Support\Bangla;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The shop trades in Bangladesh, so all application-local date and time
 * behaviour must use the approved Asia/Dhaka timezone. Raw UTC storage is
 * still reported explicitly through Carbon/MySQL when needed.
 */
class ApplicationTimezoneTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_application_timezone_is_asia_dhaka(): void
    {
        $this->assertSame('Asia/Dhaka', config('app.timezone'));
        $this->assertSame('Asia/Dhaka', date_default_timezone_get());
    }

    public function test_now_reports_dhaka_wall_clock_time_for_a_frozen_utc_instant(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 26, 12, 30, 0, 'UTC'));

        $now = now();

        $this->assertSame('Asia/Dhaka', $now->timezoneName);
        $this->assertSame('2026-10-26 18:30:00', $now->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-26 12:30:00', $now->copy()->utc()->format('Y-m-d H:i:s'));
    }

    public function test_bangla_date_helper_uses_the_dhaka_calendar_date(): void
    {
        // 20:00 UTC on 31 October is 02:00 on 1 November in Dhaka.
        Carbon::setTestNow(Carbon::create(2026, 10, 31, 20, 0, 0, 'UTC'));

        $this->assertSame(Bangla::digits('01/11/2026'), Bangla::date(now()));
    }

    public function test_mysql_and_mariadb_connections_use_the_dhaka_session_offset(): void
    {
        $this->assertSame('+06:00', config('database.connections.mysql.timezone'));
        $this->assertSame('+06:00', config('database.connections.mariadb.timezone'));
    }

    public function test_mysql_session_timezone_matches_the_application_timezone(): void
    {
        $driver = DB::connection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('MySQL/MariaDB only; SQLite has no session timezone.');
        }

        $session = DB::selectOne('SELECT @@session.time_zone AS time_zone');

        $this->assertSame('+06:00', $session->time_zone);
    }
}
