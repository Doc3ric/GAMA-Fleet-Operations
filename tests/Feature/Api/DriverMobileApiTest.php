<?php

namespace Tests\Feature\Api;

use App\Models\DriverTrip;
use App\Models\DriverVehicleAssignment;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DriverMobileApiTest extends TestCase
{
    use RefreshDatabase;

    private User $driver;

    private User $secondDriver;

    private User $operator;

    private User $admin;

    private Vehicle $vehicle;

    private DriverVehicleAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();

        $vehicleType = VehicleType::create(['code' => 'DT', 'name' => 'DUMP TRUCK']);

        $this->admin = User::factory()->admin()->create(['email' => 'admin@test.com', 'password' => bcrypt('secret123')]);
        $this->operator = User::factory()->operator()->create(['email' => 'operator@test.com', 'password' => bcrypt('secret123')]);
        $this->driver = User::factory()->driver()->create(['email' => 'driver@test.com', 'password' => bcrypt('secret123')]);
        $this->secondDriver = User::factory()->driver()->create(['email' => 'driver2@test.com', 'password' => bcrypt('secret123')]);

        $this->vehicle = Vehicle::create([
            'equipment_code' => 'DT-01',
            'vehicle_type_id' => $vehicleType->id,
            'model' => 'ISUZU GIGA',
            'plate_number' => 'ABC-1234',
            'created_by' => $this->admin->id,
        ]);

        $this->assignment = DriverVehicleAssignment::create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'assigned_from' => Carbon::today()->toDateString(),
            'assigned_until' => null,
            'created_by' => $this->admin->id,
        ]);
    }

    /** 1. Driver can log in through API */
    public function test_driver_can_login_through_api(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'driver@test.com',
            'password' => 'secret123',
            'device_name' => 'Samsung Galaxy S23',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email', 'role'],
                    'token',
                ],
                'message',
            ]);

        $this->assertEquals('driver', $response->json('data.user.role'));
        $this->assertNotEmpty($response->json('data.token'));
    }

    /** 2. Non-driver cannot log in through driver API */
    public function test_non_driver_cannot_login_through_driver_api(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'operator@test.com',
            'password' => 'secret123',
        ]);

        $response->assertForbidden()
            ->assertJson(['message' => 'Access denied. Only driver accounts can authenticate on the mobile API.']);
    }

    /** 3. Invalid credentials rejected */
    public function test_invalid_credentials_are_rejected(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'driver@test.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    /** 4. Protected endpoint requires Sanctum authentication */
    public function test_protected_endpoints_require_sanctum_authentication(): void
    {
        $this->getJson('/api/v1/profile')->assertUnauthorized();
        $this->getJson('/api/v1/assignment')->assertUnauthorized();
        $this->getJson('/api/v1/trips')->assertUnauthorized();
        $this->postJson('/api/v1/trips', [])->assertUnauthorized();
        $this->postJson('/api/v1/sync', [])->assertUnauthorized();
        $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
    }

    /** 5. Driver can retrieve own profile and logout */
    public function test_driver_can_retrieve_own_profile_and_logout(): void
    {
        Sanctum::actingAs($this->driver, ['role:driver']);

        $profileResponse = $this->getJson('/api/v1/profile');
        $profileResponse->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $this->driver->id,
                    'name' => $this->driver->name,
                    'email' => $this->driver->email,
                    'role' => 'driver',
                ],
            ]);

        $logoutResponse = $this->postJson('/api/v1/auth/logout');
        $logoutResponse->assertOk()
            ->assertJson(['message' => 'Logged out successfully.']);
    }

    /** 6. Driver can retrieve own active assignment */
    public function test_driver_can_retrieve_own_active_assignment(): void
    {
        Sanctum::actingAs($this->driver, ['role:driver']);

        $response = $this->getJson('/api/v1/assignment');

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'assignment_id' => $this->assignment->id,
                    'vehicle_id' => $this->vehicle->id,
                    'equipment_code' => 'DT-01',
                    'model' => 'ISUZU GIGA',
                    'plate_number' => 'ABC-1234',
                    'vehicle_type' => 'DUMP TRUCK',
                ],
            ]);
    }

    /** 7. Driver cannot retrieve another driver's assignment */
    public function test_driver_cannot_retrieve_another_drivers_assignment(): void
    {
        Sanctum::actingAs($this->secondDriver, ['role:driver']);

        $response = $this->getJson('/api/v1/assignment');

        $response->assertOk()
            ->assertJson([
                'data' => null,
                'message' => 'No vehicle is currently assigned.',
            ]);
    }

    /** 8. Driver can start a trip */
    public function test_driver_can_start_a_trip(): void
    {
        Sanctum::actingAs($this->driver, ['role:driver']);

        $clientId = (string) Str::uuid();

        $response = $this->postJson('/api/v1/trips', [
            'client_id' => $clientId,
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => Carbon::today()->toDateString(),
            'time_in' => '08:00:00',
            'origin_latitude' => 14.5995123,
            'origin_longitude' => 120.9842221,
            'origin_accuracy' => 5.5,
            'origin_address' => 'Manila Port',
            'remarks' => 'Morning run',
        ]);

        $response->assertCreated()
            ->assertJson([
                'data' => [
                    'client_id' => $clientId,
                    'driver' => [
                        'id' => $this->driver->id,
                    ],
                    'vehicle' => [
                        'id' => $this->vehicle->id,
                        'equipment_code' => 'DT-01',
                    ],
                    'status' => 'IN_PROGRESS',
                    'origin' => [
                        'latitude' => 14.5995123,
                        'longitude' => 120.9842221,
                        'address' => 'Manila Port',
                    ],
                    'destination' => [
                        'latitude' => null,
                        'longitude' => null,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('driver_trips', [
            'client_id' => $clientId,
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'IN_PROGRESS',
        ]);
    }

    /** 9. Driver cannot start on another vehicle */
    public function test_driver_cannot_start_on_unassigned_vehicle(): void
    {
        Sanctum::actingAs($this->driver, ['role:driver']);

        $type = VehicleType::where('code', 'DT')->first();
        $otherVehicle = Vehicle::create([
            'equipment_code' => 'DT-99',
            'vehicle_type_id' => $type->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->postJson('/api/v1/trips', [
            'client_id' => (string) Str::uuid(),
            'vehicle_id' => $otherVehicle->id,
            'trip_date' => Carbon::today()->toDateString(),
            'time_in' => '08:00:00',
            'origin_latitude' => 14.5995,
            'origin_longitude' => 120.9842,
        ]);

        $response->assertUnprocessable()
            ->assertJson(['message' => "Driver #{$this->driver->id} is not assigned to vehicle #{$otherVehicle->id}."]);
    }

    /** 10. Driver cannot start when no assignment exists */
    public function test_driver_cannot_start_when_no_assignment_exists(): void
    {
        Sanctum::actingAs($this->secondDriver, ['role:driver']);

        $response = $this->postJson('/api/v1/trips', [
            'client_id' => (string) Str::uuid(),
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => Carbon::today()->toDateString(),
            'time_in' => '08:00:00',
            'origin_latitude' => 14.5995,
            'origin_longitude' => 120.9842,
        ]);

        $response->assertUnprocessable()
            ->assertJson(['message' => "Driver #{$this->secondDriver->id} is not assigned to vehicle #{$this->vehicle->id}."]);
    }

    /** 11. Driver cannot start a second active trip */
    public function test_driver_cannot_start_a_second_active_trip(): void
    {
        Sanctum::actingAs($this->driver, ['role:driver']);

        // First trip
        $this->postJson('/api/v1/trips', [
            'client_id' => (string) Str::uuid(),
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => Carbon::today()->toDateString(),
            'time_in' => '08:00:00',
            'origin_latitude' => 14.5995,
            'origin_longitude' => 120.9842,
        ])->assertCreated();

        // Second trip while first is still in progress
        $secondResponse = $this->postJson('/api/v1/trips', [
            'client_id' => (string) Str::uuid(),
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => Carbon::today()->toDateString(),
            'time_in' => '08:30:00',
            'origin_latitude' => 14.6000,
            'origin_longitude' => 120.9850,
        ]);

        $secondResponse->assertStatus(409)
            ->assertJson(['message' => "Driver #{$this->driver->id} already has an active trip in progress."]);
    }

    /** 12. Client ID is idempotent */
    public function test_client_id_is_idempotent(): void
    {
        Sanctum::actingAs($this->driver, ['role:driver']);

        $clientId = (string) Str::uuid();

        $firstResponse = $this->postJson('/api/v1/trips', [
            'client_id' => $clientId,
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => Carbon::today()->toDateString(),
            'time_in' => '08:00:00',
            'origin_latitude' => 14.5995,
            'origin_longitude' => 120.9842,
        ]);

        $firstResponse->assertCreated();
        $tripId = $firstResponse->json('data.id');

        // Retry same client ID
        $retryResponse = $this->postJson('/api/v1/trips', [
            'client_id' => $clientId,
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => Carbon::today()->toDateString(),
            'time_in' => '08:00:00',
            'origin_latitude' => 14.5995,
            'origin_longitude' => 120.9842,
        ]);

        $retryResponse->assertOk()
            ->assertJson([
                'data' => ['id' => $tripId],
                'message' => 'Trip already exists (idempotent request).',
            ]);

        $this->assertEquals(1, DriverTrip::where('client_id', $clientId)->count());
    }

    /** 13. Client ID cannot be abused by another driver */
    public function test_client_id_cannot_be_abused_by_another_driver(): void
    {
        Sanctum::actingAs($this->driver, ['role:driver']);

        $clientId = (string) Str::uuid();

        $this->postJson('/api/v1/trips', [
            'client_id' => $clientId,
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => Carbon::today()->toDateString(),
            'time_in' => '08:00:00',
            'origin_latitude' => 14.5995,
            'origin_longitude' => 120.9842,
        ])->assertCreated();

        // Second driver attempts to start with same client ID
        Sanctum::actingAs($this->secondDriver, ['role:driver']);

        $response = $this->postJson('/api/v1/trips', [
            'client_id' => $clientId,
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => Carbon::today()->toDateString(),
            'time_in' => '08:00:00',
            'origin_latitude' => 14.5995,
            'origin_longitude' => 120.9842,
        ]);

        $response->assertStatus(409)
            ->assertJson(['message' => "client_id '{$clientId}' has already been used by another driver."]);
    }

    /** 14. Driver can end own trip */
    public function test_driver_can_end_own_trip(): void
    {
        Sanctum::actingAs($this->driver, ['role:driver']);

        $startResponse = $this->postJson('/api/v1/trips', [
            'client_id' => (string) Str::uuid(),
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => Carbon::today()->toDateString(),
            'time_in' => '08:00:00',
            'origin_latitude' => 14.5995,
            'origin_longitude' => 120.9842,
        ]);

        $tripId = $startResponse->json('data.id');

        $endResponse = $this->patchJson("/api/v1/trips/{$tripId}/end", [
            'time_out' => '09:30:00',
            'destination_latitude' => 14.5547,
            'destination_longitude' => 121.0244,
            'destination_accuracy' => 4.2,
            'destination_address' => 'Makati Site',
            'remarks' => 'Completed normally',
        ]);

        $endResponse->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $tripId,
                    'status' => 'COMPLETED',
                    'time_out' => '09:30:00',
                    'destination' => [
                        'latitude' => 14.5547,
                        'longitude' => 121.0244,
                        'address' => 'Makati Site',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('driver_trips', [
            'id' => $tripId,
            'status' => 'COMPLETED',
            'time_out' => '09:30:00',
        ]);
    }

    /** 15. Driver cannot end another driver's trip */
    public function test_driver_cannot_end_another_drivers_trip(): void
    {
        Sanctum::actingAs($this->driver, ['role:driver']);

        $startResponse = $this->postJson('/api/v1/trips', [
            'client_id' => (string) Str::uuid(),
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => Carbon::today()->toDateString(),
            'time_in' => '08:00:00',
            'origin_latitude' => 14.5995,
            'origin_longitude' => 120.9842,
        ]);

        $tripId = $startResponse->json('data.id');

        // Second driver attempts to end
        Sanctum::actingAs($this->secondDriver, ['role:driver']);

        $endResponse = $this->patchJson("/api/v1/trips/{$tripId}/end", [
            'time_out' => '09:30:00',
            'destination_latitude' => 14.5547,
            'destination_longitude' => 121.0244,
        ]);

        $endResponse->assertForbidden();
    }

    /** 16. Completed trip cannot be ended again */
    public function test_completed_trip_cannot_be_ended_again(): void
    {
        Sanctum::actingAs($this->driver, ['role:driver']);

        $tripResponse = $this->postJson('/api/v1/trips', [
            'client_id' => (string) Str::uuid(),
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => Carbon::today()->toDateString(),
            'time_in' => '08:00:00',
            'origin_latitude' => 14.5995,
            'origin_longitude' => 120.9842,
        ]);

        $tripId = $tripResponse->json('data.id');

        $this->patchJson("/api/v1/trips/{$tripId}/end", [
            'time_out' => '09:30:00',
            'destination_latitude' => 14.5547,
            'destination_longitude' => 121.0244,
        ])->assertOk();

        // Second end attempt
        $secondEnd = $this->patchJson("/api/v1/trips/{$tripId}/end", [
            'time_out' => '09:45:00',
            'destination_latitude' => 14.5550,
            'destination_longitude' => 121.0250,
        ]);

        $secondEnd->assertUnprocessable()
            ->assertJson(['message' => "Trip cannot be ended because its current status is 'COMPLETED'."]);
    }

    /** 17. Driver can cancel own active trip */
    public function test_driver_can_cancel_own_active_trip(): void
    {
        Sanctum::actingAs($this->driver, ['role:driver']);

        $tripResponse = $this->postJson('/api/v1/trips', [
            'client_id' => (string) Str::uuid(),
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => Carbon::today()->toDateString(),
            'time_in' => '08:00:00',
            'origin_latitude' => 14.5995,
            'origin_longitude' => 120.9842,
        ]);

        $tripId = $tripResponse->json('data.id');

        $cancelResponse = $this->postJson("/api/v1/trips/{$tripId}/cancel", [
            'remarks' => 'Dispatch recalled vehicle',
        ]);

        $cancelResponse->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $tripId,
                    'status' => 'CANCELLED',
                    'remarks' => 'Dispatch recalled vehicle',
                ],
            ]);

        $this->assertDatabaseHas('driver_trips', [
            'id' => $tripId,
            'status' => 'CANCELLED',
        ]);
    }

    /** 18. Driver cannot cancel another driver's trip */
    public function test_driver_cannot_cancel_another_drivers_trip(): void
    {
        Sanctum::actingAs($this->driver, ['role:driver']);

        $tripResponse = $this->postJson('/api/v1/trips', [
            'client_id' => (string) Str::uuid(),
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => Carbon::today()->toDateString(),
            'time_in' => '08:00:00',
            'origin_latitude' => 14.5995,
            'origin_longitude' => 120.9842,
        ]);

        $tripId = $tripResponse->json('data.id');

        Sanctum::actingAs($this->secondDriver, ['role:driver']);

        $cancelResponse = $this->postJson("/api/v1/trips/{$tripId}/cancel", [
            'remarks' => 'Attempted hijack',
        ]);

        $cancelResponse->assertForbidden();
    }

    /** 19. Trip history only returns authenticated driver's records */
    public function test_trip_history_only_returns_authenticated_drivers_records(): void
    {
        // Driver 1 trip
        Sanctum::actingAs($this->driver, ['role:driver']);
        $this->postJson('/api/v1/trips', [
            'client_id' => (string) Str::uuid(),
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => Carbon::today()->toDateString(),
            'time_in' => '08:00:00',
            'origin_latitude' => 14.5995,
            'origin_longitude' => 120.9842,
        ]);

        // Driver 2 trip
        $assignment2 = DriverVehicleAssignment::create([
            'driver_id' => $this->secondDriver->id,
            'vehicle_id' => $this->vehicle->id,
            'assigned_from' => Carbon::today()->toDateString(),
            'assigned_until' => null,
            'created_by' => $this->admin->id,
        ]);

        Sanctum::actingAs($this->secondDriver, ['role:driver']);
        $this->postJson('/api/v1/trips', [
            'client_id' => (string) Str::uuid(),
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => Carbon::today()->toDateString(),
            'time_in' => '09:00:00',
            'origin_latitude' => 14.6000,
            'origin_longitude' => 120.9850,
        ]);

        // Query driver 2's trips
        $response = $this->getJson('/api/v1/trips');
        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($this->secondDriver->id, $response->json('data.0.driver.id'));

        // Query single trip: driver 2 can't view driver 1's trip
        $driver1Trip = DriverTrip::where('driver_id', $this->driver->id)->first();
        $forbiddenResponse = $this->getJson("/api/v1/trips/{$driver1Trip->id}");
        $forbiddenResponse->assertForbidden();
    }

    /** 20. Date and status filters work */
    public function test_date_and_status_filters_work(): void
    {
        Sanctum::actingAs($this->driver, ['role:driver']);

        $tripYesterday = DriverTrip::create([
            'client_id' => (string) Str::uuid(),
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => Carbon::yesterday()->toDateString(),
            'time_in' => '08:00:00',
            'time_out' => '09:00:00',
            'origin_latitude' => 14.5,
            'origin_longitude' => 120.9,
            'destination_latitude' => 14.6,
            'destination_longitude' => 121.0,
            'status' => 'COMPLETED',
        ]);

        $tripToday = DriverTrip::create([
            'client_id' => (string) Str::uuid(),
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => Carbon::today()->toDateString(),
            'time_in' => '10:00:00',
            'origin_latitude' => 14.5,
            'origin_longitude' => 120.9,
            'status' => 'IN_PROGRESS',
        ]);

        // Filter by date
        $responseDate = $this->getJson('/api/v1/trips?date='.Carbon::yesterday()->toDateString());
        $responseDate->assertOk();
        $this->assertCount(1, $responseDate->json('data'));
        $this->assertEquals($tripYesterday->id, $responseDate->json('data.0.id'));

        // Filter by status
        $responseStatus = $this->getJson('/api/v1/trips?status=IN_PROGRESS');
        $responseStatus->assertOk();
        $this->assertCount(1, $responseStatus->json('data'));
        $this->assertEquals($tripToday->id, $responseStatus->json('data.0.id'));
    }

    /** 21. Sync does not create duplicates and processes batch offline records */
    public function test_sync_does_not_create_duplicates_and_processes_batch(): void
    {
        Sanctum::actingAs($this->driver, ['role:driver']);

        $clientUuid1 = (string) Str::uuid();
        $clientUuid2 = (string) Str::uuid();

        $payload = [
            'trips' => [
                [
                    'client_id' => $clientUuid1,
                    'vehicle_id' => $this->vehicle->id,
                    'trip_date' => Carbon::today()->toDateString(),
                    'time_in' => '07:00:00',
                    'time_out' => '08:30:00',
                    'origin_latitude' => 14.5995,
                    'origin_longitude' => 120.9842,
                    'destination_latitude' => 14.5547,
                    'destination_longitude' => 121.0244,
                    'remarks' => 'Completed offline trip',
                ],
                [
                    'client_id' => $clientUuid2,
                    'vehicle_id' => $this->vehicle->id,
                    'trip_date' => Carbon::today()->toDateString(),
                    'time_in' => '09:00:00',
                    'origin_latitude' => 14.5547,
                    'origin_longitude' => 121.0244,
                ],
            ],
        ];

        $syncResponse = $this->postJson('/api/v1/sync', $payload);
        $syncResponse->assertOk()
            ->assertJson([
                'data' => [
                    'processed' => 2,
                    'failed' => 0,
                ],
            ]);

        $this->assertEquals('COMPLETED', DriverTrip::where('client_id', $clientUuid1)->value('status'));
        $this->assertEquals('IN_PROGRESS', DriverTrip::where('client_id', $clientUuid2)->value('status'));

        // Resend same payload: must be idempotent and not create duplicate trips
        $retrySync = $this->postJson('/api/v1/sync', $payload);
        $retrySync->assertOk()
            ->assertJson([
                'data' => [
                    'processed' => 2,
                    'failed' => 0,
                ],
            ]);

        $this->assertEquals(2, DriverTrip::count());
    }

    /** 22. Destination remains nullable while IN_PROGRESS */
    public function test_destination_remains_nullable_while_in_progress(): void
    {
        Sanctum::actingAs($this->driver, ['role:driver']);

        $response = $this->postJson('/api/v1/trips', [
            'client_id' => (string) Str::uuid(),
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => Carbon::today()->toDateString(),
            'time_in' => '08:00:00',
            'origin_latitude' => 14.5995,
            'origin_longitude' => 120.9842,
        ]);

        $response->assertCreated();
        $this->assertNull($response->json('data.destination.latitude'));
        $this->assertNull($response->json('data.destination.longitude'));
        $this->assertNull($response->json('data.time_out'));
    }

    /** 23. Completed trip requires destination data */
    public function test_completed_trip_requires_destination_data(): void
    {
        Sanctum::actingAs($this->driver, ['role:driver']);

        $tripResponse = $this->postJson('/api/v1/trips', [
            'client_id' => (string) Str::uuid(),
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => Carbon::today()->toDateString(),
            'time_in' => '08:00:00',
            'origin_latitude' => 14.5995,
            'origin_longitude' => 120.9842,
        ]);

        $tripId = $tripResponse->json('data.id');

        $invalidEnd = $this->patchJson("/api/v1/trips/{$tripId}/end", [
            'remarks' => 'Trying to complete without destination',
        ]);

        $invalidEnd->assertUnprocessable()
            ->assertJsonValidationErrors(['time_out', 'destination_latitude', 'destination_longitude']);
    }
}
