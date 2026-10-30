<?php

namespace Tests\Feature;

use App\Models\FleetDispatch;
use App\Models\MileageAuditLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\DispatchMonitoringService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispatchMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $operator;

    protected User $driver;

    protected Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['email' => 'dispatch_admin@gama.com']);
        $this->operator = User::factory()->operator()->create(['email' => 'dispatch_operator@gama.com']);
        $this->driver = User::factory()->driver()->create(['email' => 'dispatch_driver@gama.com']);

        $this->vehicle = Vehicle::factory()->create([
            'equipment_code' => 'BT-101',
            'plate_number' => 'NBC-1234',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_guest_cannot_access_dispatch_dashboard(): void
    {
        $this->get(route('fleet-dispatches.index'))->assertRedirect(route('login'));
    }

    public function test_authorized_user_can_view_dispatch_dashboard(): void
    {
        $this->actingAs($this->operator)
            ->get(route('fleet-dispatches.index'))
            ->assertOk()
            ->assertViewIs('fleet-dispatches.index')
            ->assertSee('Fleet Dispatch Monitoring')
            ->assertSee('On-Time Dispatch Rate');
    }

    public function test_create_dispatch_stores_record_and_initializes_reconciliation(): void
    {
        $plannedTime = Carbon::now()->addHours(2);

        $payload = [
            'vehicle_id' => $this->vehicle->id,
            'driver_name' => 'Carlos Santos',
            'trip_date' => Carbon::today()->format('Y-m-d'),
            'starting_point' => 'Central Yard',
            'destination' => 'Batangas Port Facility',
            'planned_route' => 'Direct Expressway',
            'planned_distance_km' => 110.5,
            'planned_departure_time' => $plannedTime->format('Y-m-d\TH:i'),
            'grace_period_minutes' => 15,
            'create_reconciliation' => '1',
            'starting_odometer' => 45000.0,
            'ending_odometer' => 45110.5,
        ];

        $response = $this->actingAs($this->operator)->post(route('fleet-dispatches.store'), $payload);

        $response->assertRedirect(route('fleet-dispatches.index'));
        $this->assertDatabaseHas('fleet_dispatches', [
            'vehicle_id' => $this->vehicle->id,
            'destination' => 'Batangas Port Facility',
            'driver_name' => 'Carlos Santos',
            'dispatch_status' => FleetDispatch::STATUS_PENDING,
        ]);

        $dispatch = FleetDispatch::first();
        $this->assertNotNull($dispatch->reconciliation);
        $this->assertEquals(110.5, $dispatch->reconciliation->planned_distance_km);
        $this->assertEquals(45000.0, $dispatch->reconciliation->starting_odometer);
    }

    public function test_record_actual_departure_calculates_on_time_within_grace_period(): void
    {
        $planned = Carbon::today()->setHour(8)->setMinute(0);
        $dispatch = FleetDispatch::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'planned_departure_time' => $planned,
            'actual_departure_time' => null,
            'dispatch_status' => FleetDispatch::STATUS_PENDING,
            'grace_period_minutes' => 15,
            'created_by' => $this->admin->id,
        ]);

        // Departed 10 minutes late (<= 15 min grace)
        $actual = $planned->copy()->addMinutes(10);

        $this->actingAs($this->operator)->post(route('fleet-dispatches.record-departure', $dispatch), [
            'actual_departure_time' => $actual->format('Y-m-d\TH:i'),
            'departure_source' => FleetDispatch::SOURCE_AUTHORIZED_DISPATCH,
        ])->assertRedirect();

        $dispatch->refresh();
        $this->assertEquals(FleetDispatch::STATUS_ON_TIME, $dispatch->dispatch_status);
        $this->assertEquals(10, $dispatch->delay_minutes);
        $this->assertEquals(FleetDispatch::SOURCE_AUTHORIZED_DISPATCH, $dispatch->departure_source);

        $this->assertDatabaseHas('mileage_audit_logs', [
            'dispatch_id' => $dispatch->id,
            'action' => MileageAuditLog::ACTION_UPDATED,
        ]);
    }

    public function test_record_actual_departure_delayed_when_exceeding_grace_period(): void
    {
        $planned = Carbon::today()->setHour(8)->setMinute(0);
        $dispatch = FleetDispatch::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'planned_departure_time' => $planned,
            'actual_departure_time' => null,
            'dispatch_status' => FleetDispatch::STATUS_PENDING,
            'grace_period_minutes' => 15,
            'created_by' => $this->admin->id,
        ]);

        // Departed 35 minutes late (> 15 min grace)
        $actual = $planned->copy()->addMinutes(35);

        $this->actingAs($this->operator)->post(route('fleet-dispatches.record-departure', $dispatch), [
            'actual_departure_time' => $actual->format('Y-m-d\TH:i'),
            'departure_source' => FleetDispatch::SOURCE_DRIVER_SUBMITTED,
        ])->assertRedirect();

        $dispatch->refresh();
        $this->assertEquals(FleetDispatch::STATUS_DELAYED, $dispatch->dispatch_status);
        $this->assertEquals(35, $dispatch->delay_minutes);
    }

    public function test_on_time_dispatch_rate_business_rules(): void
    {
        $service = app(DispatchMonitoringService::class);
        $now = Carbon::now();

        // 1. On-time departure (eligible, on-time)
        FleetDispatch::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'planned_departure_time' => $now->copy()->subHours(5),
            'actual_departure_time' => $now->copy()->subHours(5)->addMinutes(5),
            'dispatch_status' => FleetDispatch::STATUS_ON_TIME,
            'created_by' => $this->admin->id,
        ]);

        // 2. Delayed departure (eligible, delayed)
        FleetDispatch::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'planned_departure_time' => $now->copy()->subHours(4),
            'actual_departure_time' => $now->copy()->subHours(4)->addMinutes(45),
            'dispatch_status' => FleetDispatch::STATUS_DELAYED,
            'created_by' => $this->admin->id,
        ]);

        // 3. Past scheduled time with NO departure recorded (eligible, classified delayed)
        FleetDispatch::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'planned_departure_time' => $now->copy()->subHours(2),
            'actual_departure_time' => null,
            'dispatch_status' => FleetDispatch::STATUS_PENDING,
            'created_by' => $this->admin->id,
        ]);

        // 4. Future scheduled trip (pending, EXCLUDED from denominator)
        FleetDispatch::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'planned_departure_time' => $now->copy()->addHours(3),
            'actual_departure_time' => null,
            'dispatch_status' => FleetDispatch::STATUS_PENDING,
            'created_by' => $this->admin->id,
        ]);

        // 5. Cancelled trip (cancelled, EXCLUDED from denominator)
        FleetDispatch::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'planned_departure_time' => $now->copy()->subHours(1),
            'actual_departure_time' => null,
            'dispatch_status' => FleetDispatch::STATUS_CANCELLED,
            'created_by' => $this->admin->id,
        ]);

        $stats = $service->getStatistics();

        // Total: 5 trips
        // Cancelled: 1
        // Future Pending: 1
        // Total Eligible: 3 (1 on-time, 1 departed delayed, 1 past scheduled without departure)
        // On-Time: 1
        // Delayed: 2
        // On-Time Rate: 1 / 3 = 33.3%
        $this->assertEquals(5, $stats['total_trips']);
        $this->assertEquals(1, $stats['cancelled_count']);
        $this->assertEquals(1, $stats['pending_count']);
        $this->assertEquals(3, $stats['eligible_count']);
        $this->assertEquals(1, $stats['on_time_count']);
        $this->assertEquals(2, $stats['delayed_count']);
        $this->assertEquals(33.3, $stats['on_time_rate']);
    }

    public function test_export_dispatch_excel_and_pdf(): void
    {
        FleetDispatch::factory()->count(3)->create([
            'vehicle_id' => $this->vehicle->id,
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('fleet-dispatches.export-excel'))
            ->assertOk();

        $this->actingAs($this->admin)
            ->get(route('fleet-dispatches.export-pdf'))
            ->assertOk();
    }
}
