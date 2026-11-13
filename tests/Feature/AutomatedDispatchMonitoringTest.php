<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DispatchMonitorEvent;
use App\Models\FleetDispatch;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\DispatchAlertNotification;
use App\Services\DispatchGpsTrackingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AutomatedDispatchMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $operator;

    protected Vehicle $gpsVehicle;

    protected Device $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['email' => 'admin_monitor@gama.com']);
        $this->operator = User::factory()->operator()->create(['email' => 'operator_monitor@gama.com']);

        $this->gpsVehicle = Vehicle::factory()->create([
            'equipment_code' => 'BT7',
            'plate_number' => 'NBC-7777',
            'gps_status' => 'YES',
            'created_by' => $this->admin->id,
        ]);

        $this->device = Device::factory()->create([
            'vehicle_id' => $this->gpsVehicle->id,
            'imei' => '868120050012345',
            'device_name' => 'TrackSolid BT7',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_store_dispatch_with_multistop_waypoints_and_coordinates(): void
    {
        $payload = [
            'vehicle_id' => $this->gpsVehicle->id,
            'driver_name' => 'Juan Dela Cruz',
            'trip_date' => Carbon::today()->format('Y-m-d'),
            'starting_point' => 'Cagayan de Oro Main Yard',
            'origin_latitude' => 8.4822,
            'origin_longitude' => 124.6472,
            'destination' => 'Iligan City Distribution Depot',
            'destination_latitude' => 8.2280,
            'destination_longitude' => 124.2452,
            'planned_distance_km' => 88.5,
            'planned_departure_time' => Carbon::now()->addHour()->format('Y-m-d\TH:i'),
            'waypoints' => [
                [
                    'name' => 'Tagoloan Warehouse Stop',
                    'address' => 'Tagoloan Port Area',
                    'lat' => 8.5414,
                    'lng' => 124.7547,
                ],
            ],
            'create_reconciliation' => 1,
            'starting_odometer' => 12000.0,
            'ending_odometer' => 12088.5,
        ];

        $response = $this->actingAs($this->operator)
            ->post(route('fleet-dispatches.store'), $payload);

        $response->assertRedirect(route('fleet-dispatches.index'));
        $this->assertDatabaseHas('fleet_dispatches', [
            'vehicle_id' => $this->gpsVehicle->id,
            'starting_point' => 'Cagayan de Oro Main Yard',
            'destination' => 'Iligan City Distribution Depot',
            'planned_distance_km' => 88.5,
        ]);

        $dispatch = FleetDispatch::where('vehicle_id', $this->gpsVehicle->id)->first();
        $this->assertNotNull($dispatch);
        $this->assertNotNull($dispatch->reconciliation);
        $this->assertEquals(8.4822, $dispatch->origin_latitude);
        $this->assertEquals(124.6472, $dispatch->origin_longitude);

        $routeData = $dispatch->planned_route_data;
        $this->assertIsArray($routeData);
        $this->assertNotEmpty($routeData['intermediate_destinations']);
    }

    public function test_calculate_route_api_endpoint(): void
    {
        $points = [
            ['name' => 'Cagayan de Oro', 'lat' => 8.4822, 'lng' => 124.6472],
            ['name' => 'Tagoloan', 'lat' => 8.5414, 'lng' => 124.7547],
            ['name' => 'Iligan City', 'lat' => 8.2280, 'lng' => 124.2452],
        ];

        $response = $this->actingAs($this->operator)
            ->postJson(route('fleet-dispatches.calculate-route'), ['points' => $points]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'total_distance_km',
                'total_duration_minutes',
                'formatted_total_duration',
                'legs',
                'geometry',
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertGreaterThan(0, $response->json('total_distance_km'));
    }

    public function test_detects_possible_and_confirmed_route_deviations(): void
    {
        Notification::fake();

        $now = Carbon::parse('2026-11-13 10:00:00');

        // Route: Straight line from (8.48, 124.64) to (8.54, 124.75), trip departed 10 min ago (plenty of time remaining)
        $dispatch = FleetDispatch::factory()->create([
            'vehicle_id' => $this->gpsVehicle->id,
            'origin_latitude' => 8.4800,
            'origin_longitude' => 124.6400,
            'destination_latitude' => 8.5400,
            'destination_longitude' => 124.7500,
            'planned_departure_time' => $now->copy()->subMinutes(10),
            'actual_departure_time' => $now->copy()->subMinutes(10),
            'planned_distance_km' => 80.0,
            'created_by' => $this->admin->id,
        ]);

        $trackingService = app(DispatchGpsTrackingService::class);

        // Ping 1: Far outside corridor (e.g. 5km away at 8.60, 124.60)
        $deviantPing1 = [
            'lat' => 8.6000,
            'lng' => 124.6000,
            'speed' => 45.0,
            'gpsTime' => $now->toDateTimeString(),
        ];

        $telemetryBatch1 = [
            'locations' => [$this->device->imei => $deviantPing1],
            'failed_imeis' => [],
            'status' => 'success',
        ];

        $events1 = $trackingService->evaluateDispatch($dispatch, $telemetryBatch1, $now);

        // First ping outside: must be POSSIBLE route deviation
        $this->assertNotEmpty($events1);
        $event1 = $events1[0];
        $this->assertEquals(DispatchMonitorEvent::EVENT_ROUTE_DEVIATION, $event1->event_type);
        $this->assertEquals(DispatchMonitorEvent::STATUS_POSSIBLE, $event1->status);
        $this->assertEquals(FleetDispatch::MONITORING_POSSIBLE_EXCEPTION, $dispatch->fresh()->monitoring_status);

        // Ping 2: Consecutive reading still outside corridor -> must escalate to CONFIRMED route deviation
        $nowLater = $now->copy()->addMinutes(3);
        $deviantPing2 = [
            'lat' => 8.6050,
            'lng' => 124.6020,
            'speed' => 48.0,
            'gpsTime' => $nowLater->toDateTimeString(),
        ];

        $telemetryBatch2 = [
            'locations' => [$this->device->imei => $deviantPing2],
            'failed_imeis' => [],
            'status' => 'success',
        ];

        $events2 = $trackingService->evaluateDispatch($dispatch, $telemetryBatch2, $nowLater);

        $dispatch->refresh();
        $this->assertEquals(FleetDispatch::MONITORING_CONFIRMED_EXCEPTION, $dispatch->monitoring_status);

        $confirmedEvent = $dispatch->activeMonitorEvents()
            ->where('event_type', DispatchMonitorEvent::EVENT_ROUTE_DEVIATION)
            ->first();
        $this->assertNotNull($confirmedEvent);
        $this->assertEquals(DispatchMonitorEvent::STATUS_CONFIRMED, $confirmedEvent->status);
        $this->assertEquals(DispatchMonitorEvent::SEVERITY_DANGER, $confirmedEvent->severity);

        // Verification of In-App Notification: Sent exclusively via database channel
        Notification::assertSentTo(
            [$this->admin, $this->operator],
            DispatchAlertNotification::class,
            function (DispatchAlertNotification $notification, array $channels) {
                return $channels === ['database'];
            }
        );
    }

    public function test_detects_missed_departure_and_auto_departure_upon_movement(): void
    {
        $now = Carbon::parse('2026-11-13 09:30:00');

        $dispatch = FleetDispatch::factory()->create([
            'vehicle_id' => $this->gpsVehicle->id,
            'origin_latitude' => 8.4822,
            'origin_longitude' => 124.6472,
            'planned_departure_time' => Carbon::parse('2026-11-13 08:00:00'), // 90 min ago
            'actual_departure_time' => null,
            'grace_period_minutes' => 15,
            'created_by' => $this->admin->id,
        ]);

        $trackingService = app(DispatchGpsTrackingService::class);

        // Scenario A: Vehicle still stationary at yard
        $stationaryPing = [
            'lat' => 8.4822,
            'lng' => 124.6472,
            'speed' => 0.0,
            'gpsTime' => $now->toDateTimeString(),
        ];

        $batch1 = [
            'locations' => [$this->device->imei => $stationaryPing],
            'failed_imeis' => [],
            'status' => 'success',
        ];

        $events = $trackingService->evaluateDispatch($dispatch, $batch1, $now);
        $this->assertNotEmpty($events);
        $this->assertEquals(DispatchMonitorEvent::EVENT_MISSED_DEPARTURE, $events[0]->event_type);
        $this->assertEquals(DispatchMonitorEvent::STATUS_CONFIRMED, $events[0]->status);

        // Scenario B: Vehicle starts moving away (>600m from origin at 35 km/h) -> auto-records departure
        $movementTime = $now->copy()->addMinutes(5);
        $movingPing = [
            'lat' => 8.5200, // ~4.5 km away
            'lng' => 124.6800,
            'speed' => 38.0,
            'gpsTime' => $movementTime->toDateTimeString(),
        ];

        $batch2 = [
            'locations' => [$this->device->imei => $movingPing],
            'failed_imeis' => [],
            'status' => 'success',
        ];

        $trackingService->evaluateDispatch($dispatch, $batch2, $movementTime);

        $dispatch->refresh();
        $this->assertNotNull($dispatch->actual_departure_time);
        $this->assertEquals(FleetDispatch::SOURCE_GPS_MOVEMENT, $dispatch->departure_source);
    }

    public function test_distinguishes_authorized_stop_from_unexpected_stop(): void
    {
        $now = Carbon::parse('2026-11-13 11:00:00');

        $dispatch = FleetDispatch::factory()->create([
            'vehicle_id' => $this->gpsVehicle->id,
            'origin_latitude' => 8.4800,
            'origin_longitude' => 124.6400,
            'destination_latitude' => 8.5400,
            'destination_longitude' => 124.7500,
            'planned_departure_time' => $now->copy()->subHours(2),
            'actual_departure_time' => $now->copy()->subHours(2),
            'planned_route' => json_encode([
                'waypoints' => [
                    ['name' => 'Origin', 'lat' => 8.4800, 'lng' => 124.6400],
                    ['name' => 'Customer Site Stop', 'lat' => 8.5000, 'lng' => 124.6800],
                    ['name' => 'Dest', 'lat' => 8.5400, 'lng' => 124.7500],
                ],
            ]),
            'created_by' => $this->admin->id,
        ]);

        $trackingService = app(DispatchGpsTrackingService::class);

        // Case 1: Stopped (speed = 0) within 200m of authorized waypoint (8.5000, 124.6800) -> NO ALERT
        $authorizedStopPing = [
            'lat' => 8.5010,
            'lng' => 124.6805,
            'speed' => 0.0,
            'gpsTime' => $now->toDateTimeString(),
        ];

        $batch1 = [
            'locations' => [$this->device->imei => $authorizedStopPing],
            'failed_imeis' => [],
            'status' => 'success',
        ];

        $eventsAuthorized = $trackingService->evaluateDispatch($dispatch, $batch1, $now);
        $stopEvents1 = array_filter($eventsAuthorized, fn ($e) => $e->event_type === DispatchMonitorEvent::EVENT_UNEXPECTED_STOP);
        $this->assertEmpty($stopEvents1);

        // Case 2: Stopped at unauthorized location for > 20 min -> TRIGGERS UNEXPECTED STOP
        // Pre-create initial stop event to simulate 25 minutes of stoppage
        $existingEvent = DispatchMonitorEvent::create([
            'dispatch_id' => $dispatch->id,
            'vehicle_id' => $this->gpsVehicle->id,
            'event_type' => DispatchMonitorEvent::EVENT_UNEXPECTED_STOP,
            'severity' => DispatchMonitorEvent::SEVERITY_WARNING,
            'status' => DispatchMonitorEvent::STATUS_POSSIBLE,
            'latitude' => 8.4200,
            'longitude' => 124.5500,
            'speed_kmh' => 0.0,
            'duration_minutes' => 25,
            'description' => 'Vehicle stopped outside approved area',
            'created_at' => $now->copy()->subMinutes(25),
        ]);

        $unauthorizedStopPing = [
            'lat' => 8.4200, // 10 km away from any waypoint
            'lng' => 124.5500,
            'speed' => 0.0,
            'gpsTime' => $now->toDateTimeString(),
        ];

        $batch2 = [
            'locations' => [$this->device->imei => $unauthorizedStopPing],
            'failed_imeis' => [],
            'status' => 'success',
        ];

        $eventsUnauthorized = $trackingService->evaluateDispatch($dispatch, $batch2, $now);
        $dispatch->refresh();
        $this->assertTrue(
            $dispatch->activeMonitorEvents()->where('event_type', DispatchMonitorEvent::EVENT_UNEXPECTED_STOP)->exists()
        );
    }

    public function test_flags_gps_unavailable_when_device_offline_or_stale(): void
    {
        $now = Carbon::parse('2026-11-13 14:00:00');

        $dispatch = FleetDispatch::factory()->create([
            'vehicle_id' => $this->gpsVehicle->id,
            'trip_date' => $now->toDateString(),
            'created_by' => $this->admin->id,
        ]);

        $trackingService = app(DispatchGpsTrackingService::class);

        // Stale ping: 90 minutes old (> 60m threshold)
        $stalePing = [
            'lat' => 8.4822,
            'lng' => 124.6472,
            'speed' => 0.0,
            'gpsTime' => $now->copy()->subMinutes(90)->toDateTimeString(),
        ];

        $batch = [
            'locations' => [$this->device->imei => $stalePing],
            'failed_imeis' => [],
            'status' => 'success',
        ];

        $events = $trackingService->evaluateDispatch($dispatch, $batch, $now);

        $dispatch->refresh();
        $this->assertEquals(FleetDispatch::MONITORING_GPS_UNAVAILABLE, $dispatch->monitoring_status);
        $this->assertNotEmpty($events);
        $this->assertEquals(DispatchMonitorEvent::EVENT_GPS_UNAVAILABLE, $events[0]->event_type);
    }

    public function test_duplicate_notification_cooldown_suppresses_repeat_alerts(): void
    {
        Notification::fake();

        $now = Carbon::parse('2026-11-13 10:00:00');
        Carbon::setTestNow($now);

        $dispatch = FleetDispatch::factory()->create([
            'vehicle_id' => $this->gpsVehicle->id,
            'origin_latitude' => 8.4800,
            'origin_longitude' => 124.6400,
            'destination_latitude' => 8.5400,
            'destination_longitude' => 124.7500,
            'planned_departure_time' => $now->copy()->subMinutes(15),
            'actual_departure_time' => $now->copy()->subMinutes(15),
            'planned_distance_km' => 80.0,
            'created_by' => $this->admin->id,
        ]);

        $trackingService = app(DispatchGpsTrackingService::class);

        $ping1 = [
            'lat' => 8.6500,
            'lng' => 124.6000,
            'speed' => 45.0,
            'gpsTime' => $now->toDateTimeString(),
        ];

        $batch1 = [
            'locations' => [$this->device->imei => $ping1],
            'failed_imeis' => [],
            'status' => 'success',
        ];

        // First execution -> emits initial POSSIBLE notification
        $trackingService->evaluateDispatch($dispatch, $batch1, $now);
        Notification::assertSentToTimes($this->admin, DispatchAlertNotification::class, 1);

        // Second execution (2 min later) -> escalates to CONFIRMED -> emits escalation notification (total = 2)
        $t2 = $now->copy()->addMinutes(2);
        Carbon::setTestNow($t2);
        $trackingService->evaluateDispatch($dispatch, $batch1, $t2);
        Notification::assertSentToTimes($this->admin, DispatchAlertNotification::class, 2);

        // Third execution (7 min later, already CONFIRMED) -> suppressed by 30-min duplicate cooldown (total remains 2)
        $t3 = $now->copy()->addMinutes(7);
        Carbon::setTestNow($t3);
        $trackingService->evaluateDispatch($dispatch, $batch1, $t3);
        Notification::assertSentToTimes($this->admin, DispatchAlertNotification::class, 2);

        Carbon::setTestNow(); // reset
    }

    public function test_console_command_runs_monitoring_sweep(): void
    {
        FleetDispatch::factory()->create([
            'vehicle_id' => $this->gpsVehicle->id,
            'trip_date' => Carbon::today()->toDateString(),
            'created_by' => $this->admin->id,
        ]);

        $this->artisan('fleet:monitor-dispatches')
            ->expectsOutputToContain('Starting Automated Fleet Dispatch Monitoring sweep')
            ->expectsOutputToContain('Completed monitoring sweep')
            ->assertSuccessful();
    }

    public function test_live_track_api_returns_route_and_telemetry(): void
    {
        $dispatch = FleetDispatch::factory()->create([
            'vehicle_id' => $this->gpsVehicle->id,
            'origin_latitude' => 8.4822,
            'origin_longitude' => 124.6472,
            'destination_latitude' => 8.5414,
            'destination_longitude' => 124.7547,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->operator)
            ->getJson(route('fleet-dispatches.live-track', $dispatch));

        $response->assertOk()
            ->assertJsonStructure([
                'dispatch' => [
                    'id',
                    'trip_number',
                    'vehicle_code',
                    'starting_point',
                    'destination',
                    'monitoring_status',
                ],
                'waypoints',
            ]);
    }

    public function test_operator_can_resolve_monitoring_event(): void
    {
        $dispatch = FleetDispatch::factory()->create([
            'vehicle_id' => $this->gpsVehicle->id,
            'monitoring_status' => FleetDispatch::MONITORING_CONFIRMED_EXCEPTION,
            'created_by' => $this->admin->id,
        ]);

        $event = DispatchMonitorEvent::create([
            'dispatch_id' => $dispatch->id,
            'vehicle_id' => $this->gpsVehicle->id,
            'event_type' => DispatchMonitorEvent::EVENT_ROUTE_DEVIATION,
            'severity' => DispatchMonitorEvent::SEVERITY_DANGER,
            'status' => DispatchMonitorEvent::STATUS_CONFIRMED,
            'description' => 'Vehicle deviated from corridor',
        ]);

        $response = $this->actingAs($this->operator)
            ->post(route('fleet-dispatches.events.resolve', $event), [
                'remarks' => 'Detour was authorized due to road construction on coastal highway.',
            ]);

        $response->assertRedirect();
        $event->refresh();
        $this->assertTrue($event->isResolved());
        $this->assertEquals($this->operator->id, $event->resolved_by);
        $this->assertStringContainsString('road construction', $event->resolution_remarks);

        $dispatch->refresh();
        $this->assertEquals(FleetDispatch::MONITORING_NORMAL, $dispatch->monitoring_status);
    }

    public function test_in_app_notification_feed_and_mark_as_read(): void
    {
        $dispatch = FleetDispatch::factory()->create([
            'vehicle_id' => $this->gpsVehicle->id,
            'created_by' => $this->admin->id,
        ]);

        $event = DispatchMonitorEvent::create([
            'dispatch_id' => $dispatch->id,
            'vehicle_id' => $this->gpsVehicle->id,
            'event_type' => DispatchMonitorEvent::EVENT_ROUTE_DEVIATION,
            'severity' => DispatchMonitorEvent::SEVERITY_DANGER,
            'status' => DispatchMonitorEvent::STATUS_CONFIRMED,
            'description' => 'Vehicle deviated 1.2km outside corridor',
        ]);

        $this->admin->notify(new DispatchAlertNotification($event, 'Route Deviation Alert'));

        // Check feed
        $feedRes = $this->actingAs($this->admin)->getJson(route('notifications.feed'));
        $feedRes->assertOk();
        $this->assertEquals(1, $feedRes->json('unread_count'));
        $notifId = $feedRes->json('notifications.0.id');

        // Mark as read
        $markRes = $this->actingAs($this->admin)->postJson(route('notifications.mark-as-read', $notifId));
        $markRes->assertOk();

        // Feed should now report 0 unread
        $feedRes2 = $this->actingAs($this->admin)->getJson(route('notifications.feed'));
        $this->assertEquals(0, $feedRes2->json('unread_count'));
    }
}
