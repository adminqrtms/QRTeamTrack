<?php

namespace Tests\Feature;

use App\Models\Alarm;
use App\Models\Attendance;
use App\Models\Location;
use App\Models\Report;
use App\Models\Schedule;
use App\Models\User;
use App\Services\DutyLog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IncidentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Location $location;
    private User $admin;
    private User $resident;
    private User $juan;
    private User $pedro;

    protected function setUp(): void
    {
        parent::setUp();
        $this->location = Location::create(['location_name' => 'Station 1', 'barangay' => 'Poblacion', 'latitude' => 7.2, 'longitude' => 124.24]);
        // The very first user becomes an admin (see User::booted).
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password']);
        $this->resident = User::create(['name' => 'Maria Reyes', 'email' => 'maria@example.com', 'password' => 'password', 'role' => 'resident', 'location_id' => $this->location->id]);
        $this->juan = User::create(['name' => 'Juan Cruz', 'email' => 'juan@example.com', 'password' => 'password', 'role' => 'personnel', 'hourly_rate' => 60]);
        $this->pedro = User::create(['name' => 'Pedro Santos', 'email' => 'pedro@example.com', 'password' => 'password', 'role' => 'personnel', 'hourly_rate' => 60]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function onDuty(User $personnel): void
    {
        Attendance::create([
            'user_id' => $personnel->id, 'location_id' => $this->location->id,
            'date' => now()->toDateString(), 'time_in' => now()->format('H:i:s'), 'status' => 'On Time',
        ]);
    }

    private function report(): Report
    {
        return Report::create([
            'user_id' => $this->resident->id, 'title' => 'Flood near the bridge', 'description' => 'Water rising',
            'status' => 'pending', 'location_id' => $this->location->id,
        ]);
    }

    private function alarm(): Alarm
    {
        return Alarm::create([
            'user_id' => $this->resident->id, 'location_id' => $this->location->id,
            'latitude' => 7.2, 'longitude' => 124.24, 'status' => 'triggered',
        ]);
    }

    // ---- Team response ----

    public function test_on_duty_personnel_join_as_a_team(): void
    {
        $report = $this->report();
        $this->onDuty($this->juan);
        $this->onDuty($this->pedro);

        Sanctum::actingAs($this->juan);
        $this->postJson("/api/reports/{$report->id}/join")->assertOk();
        Sanctum::actingAs($this->pedro);
        $this->postJson("/api/reports/{$report->id}/join")->assertOk();
        $this->postJson("/api/reports/{$report->id}/join")->assertOk(); // joining twice is harmless

        $report->refresh();
        $this->assertSame('ongoing', $report->status);
        $this->assertSame(['Juan Cruz', 'Pedro Santos'], $report->responders->pluck('name')->all());

        // The resident's app sees who responded
        Sanctum::actingAs($this->resident);
        $this->assertSame(['Juan Cruz', 'Pedro Santos'],
            array_column($this->getJson("/api/reports/{$report->id}")->json('data.responders'), 'name'));
    }

    public function test_personnel_not_on_duty_cannot_join(): void
    {
        $alarm = $this->alarm();

        Sanctum::actingAs($this->juan);
        $this->postJson("/api/alarms/{$alarm->id}/join")->assertForbidden();

        $this->onDuty($this->juan);
        $this->postJson("/api/alarms/{$alarm->id}/join")->assertOk();
        $this->assertSame('responding', $alarm->fresh()->status);

        Sanctum::actingAs($this->resident);
        $this->postJson("/api/alarms/{$alarm->id}/join")->assertForbidden();
    }

    // ---- Mutual resolution ----

    public function test_responder_alone_cannot_resolve_a_report(): void
    {
        $report = $this->report();
        Sanctum::actingAs($this->juan);

        $this->putJson("/api/reports/{$report->id}", ['status' => 'resolved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'awaiting_confirmation');

        $report->refresh();
        $this->assertSame('awaiting_confirmation', $report->status);
        $this->assertSame($this->juan->id, (int) $report->resolution_requested_by);
        $this->assertNull($report->resolved_at);
        $this->assertTrue($report->hasResponder($this->juan));
    }

    public function test_resident_confirms_then_it_is_resolved(): void
    {
        $report = $this->report();
        Sanctum::actingAs($this->juan);
        $this->putJson("/api/reports/{$report->id}", ['status' => 'resolved']);

        Sanctum::actingAs($this->resident);
        $this->postJson("/api/reports/{$report->id}/resolution", ['resolved' => true])->assertOk();

        $report->refresh();
        $this->assertSame('resolved', $report->status);
        $this->assertSame('confirmed', $report->resolution_method);
        $this->assertNotNull($report->resolved_at);
    }

    public function test_resident_says_not_yet_resolved(): void
    {
        $alarm = $this->alarm();
        $this->onDuty($this->juan);
        Sanctum::actingAs($this->juan);
        $this->postJson("/api/alarms/{$alarm->id}/join");
        $this->putJson("/api/alarms/{$alarm->id}", ['status' => 'resolved'])->assertJsonPath('data.status', 'awaiting_confirmation');

        Sanctum::actingAs($this->resident);
        $this->postJson("/api/alarms/{$alarm->id}/resolution", ['resolved' => false])->assertOk();

        $alarm->refresh();
        $this->assertSame('responding', $alarm->status);
        $this->assertNull($alarm->resolution_requested_at);
        $this->assertNull($alarm->resolution_method);
    }

    public function test_only_the_reporting_resident_can_confirm(): void
    {
        $report = $this->report();
        Sanctum::actingAs($this->juan);
        $this->putJson("/api/reports/{$report->id}", ['status' => 'resolved']);

        // The responder can't confirm their own "resolved"
        $this->postJson("/api/reports/{$report->id}/resolution", ['resolved' => true])->assertForbidden();

        $other = User::create(['name' => 'Other', 'email' => 'o@example.com', 'password' => 'password', 'role' => 'resident']);
        Sanctum::actingAs($other);
        $this->postJson("/api/reports/{$report->id}/resolution", ['resolved' => true])->assertForbidden();

        $this->assertSame('awaiting_confirmation', $report->fresh()->status);
    }

    public function test_no_answer_within_24_hours_resolves_automatically(): void
    {
        Carbon::setTestNow('2026-11-15 08:00:00');
        $report = $this->report();
        Sanctum::actingAs($this->juan);
        $this->putJson("/api/reports/{$report->id}", ['status' => 'resolved']);

        Carbon::setTestNow('2026-11-16 07:59:00');
        $this->getJson('/api/reports');
        $this->assertSame('awaiting_confirmation', $report->fresh()->status);

        Carbon::setTestNow('2026-11-16 08:01:00');
        $this->getJson('/api/reports');
        $report->refresh();
        $this->assertSame('resolved', $report->status);
        $this->assertSame('auto', $report->resolution_method);
        $this->assertSame('2026-11-16 08:00:00', $report->resolved_at->format('Y-m-d H:i:s'));
    }

    public function test_admin_resolve_also_waits_for_the_resident(): void
    {
        $report = $this->report();

        $this->actingAs($this->admin)
            ->patch("/admin/reports/{$report->id}/status", ['status' => 'resolved'])
            ->assertRedirect();

        $this->assertSame('awaiting_confirmation', $report->fresh()->status);
    }

    // ---- Time in rule and paid hours ----

    private function shiftToday(string $start, string $end): void
    {
        Schedule::create([
            'personnel_id' => $this->juan->id, 'location_id' => $this->location->id,
            'schedule_date_start' => now()->toDateString(), 'schedule_date_end' => now()->toDateString(),
            'start_time' => $start, 'end_time' => $end, 'status' => 'active',
        ]);
    }

    public function test_cannot_time_in_after_the_shift_ended(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-15 16:00:00', 'Asia/Manila'));
        $this->shiftToday('15:00', '16:00');
        Sanctum::actingAs($this->juan);

        $this->postJson('/api/personnel/attendance/toggle', ['latitude' => 7.2, 'longitude' => 124.24])
            ->assertForbidden()
            ->assertJsonPath('message', 'Time in denied: your duty today ended at 4:00 PM.');

        Carbon::setTestNow(Carbon::parse('2026-11-15 15:59:00', 'Asia/Manila'));
        $this->postJson('/api/personnel/attendance/toggle', ['latitude' => 7.2, 'longitude' => 124.24])->assertOk();
    }

    public function test_only_time_inside_the_shift_is_paid(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-15 18:00:00', 'Asia/Manila'));
        $this->shiftToday('15:00', '17:00');
        Attendance::create(['user_id' => $this->juan->id, 'location_id' => $this->location->id, 'date' => '2026-11-15',
            'time_in' => '14:50:00', 'time_out' => '17:20:00', 'status' => 'On Time']);

        $row = DutyLog::rows($this->juan, now()->startOfDay(), now()->endOfDay())->sole();
        $this->assertSame('03:00 PM', $row->shift_start->format('h:i A'));
        $this->assertSame('05:00 PM', $row->shift_end->format('h:i A'));
        $this->assertSame(2.5, $row->actual_hours);
        $this->assertSame(2.0, $row->paid_hours);
        $this->assertSame(120.0, $row->pay);

        // Late in, early out: 3:05 - 4:56 = 1.85 hrs
        Attendance::query()->update(['time_in' => '15:05:00', 'time_out' => '16:56:00']);
        $this->assertSame(1.85, DutyLog::rows($this->juan, now()->startOfDay(), now()->endOfDay())->sole()->paid_hours);
    }

    // ---- Web pages ----

    public function test_personnel_log_page_and_print(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-15 18:00:00', 'Asia/Manila'));
        $this->shiftToday('15:00', '17:00');
        Attendance::create(['user_id' => $this->juan->id, 'location_id' => $this->location->id, 'date' => '2026-11-15',
            'time_in' => '15:05:00', 'time_out' => '16:56:00', 'status' => 'On Time']);

        $report = $this->report();
        $report->addResponder($this->juan);
        $report->addResponder($this->pedro);
        $report->forceFill(['status' => 'resolved', 'resolved_at' => now(), 'resolution_method' => 'confirmed'])->save();

        $this->actingAs($this->admin)->get("/admin/personnels/{$this->juan->id}/attendance?month=2026-11")
            ->assertOk()
            ->assertSee('03:00 PM – 05:00 PM')
            ->assertSee('03:05 PM')
            ->assertSee('04:56 PM')
            ->assertSee('1.85')
            ->assertSee('Flood near the bridge')
            ->assertSee('with Pedro Santos')
            ->assertSee('confirmed by resident');

        $this->actingAs($this->admin)
            ->get("/admin/personnels/{$this->juan->id}/attendance/print?mode=range&from=2026-11-01&to=2026-11-15")
            ->assertOk()
            ->assertSee('Personnel Duty Log')
            ->assertSee('Nov 1, 2026 – Nov 15, 2026')
            ->assertSee('₱111.00')
            ->assertSee('Approved by');
    }

    public function test_resident_page_lists_who_responded(): void
    {
        $report = $this->report();
        $report->addResponder($this->juan);
        $alarm = $this->alarm();
        $alarm->addResponder($this->pedro);

        $this->actingAs($this->admin)->get("/admin/residents/{$this->resident->id}")
            ->assertOk()
            ->assertSee('Flood near the bridge')
            ->assertSee('Juan Cruz')
            ->assertSee('Pedro Santos');
    }

    public function test_reports_tab_includes_sos_with_filters(): void
    {
        $report = $this->report();
        $alarm = $this->alarm();
        $alarm->addResponder($this->juan);
        $alarm->forceFill(['status' => 'resolved', 'resolved_at' => now(), 'resolution_method' => 'confirmed'])->save();

        $this->actingAs($this->admin)->get('/admin/reports')
            ->assertOk()
            ->assertSee('#INC-' . $report->id)
            ->assertSee('#SOS-' . $alarm->id)
            ->assertSee('Juan Cruz');

        $this->actingAs($this->admin)->get('/admin/reports?type=sos')
            ->assertSee('#SOS-' . $alarm->id)
            ->assertDontSee('#INC-' . $report->id);

        $this->actingAs($this->admin)->get('/admin/reports?status=resolved')
            ->assertSee('#SOS-' . $alarm->id)
            ->assertDontSee('#INC-' . $report->id);

        $this->actingAs($this->admin)->get("/admin/sos/{$alarm->id}")
            ->assertOk()
            ->assertSee('Maria Reyes')
            ->assertSee('Juan Cruz')
            ->assertSee('confirmed by the resident');
    }

    public function test_personnel_keeps_seeing_incidents_they_responded_to(): void
    {
        $mine = $this->report();
        $mine->addResponder($this->juan);
        $mine->forceFill(['status' => 'resolved', 'resolved_at' => now(), 'resolution_method' => 'confirmed'])->save();
        $other = $this->report(); // at the station, but Juan never responded

        // Off duty: only the incidents Juan responded to, resolved ones included
        Sanctum::actingAs($this->juan);
        $this->assertSame([$mine->id], array_column($this->getJson('/api/reports')->json('data'), 'id'));

        // On duty: everything at the station
        $this->onDuty($this->juan);
        $ids = array_column($this->getJson('/api/reports')->json('data'), 'id');
        sort($ids);
        $this->assertSame([$mine->id, $other->id], $ids);
    }

    public function test_menu_says_personnel_logs(): void
    {
        $this->actingAs($this->admin)->get('/admin/audit-logs')
            ->assertOk()
            ->assertSee('Personnel Logs')
            ->assertDontSee('Audit Logs');
    }
}
