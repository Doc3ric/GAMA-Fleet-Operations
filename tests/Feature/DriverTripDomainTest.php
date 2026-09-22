<?php

namespace Tests\Feature;

use App\Exceptions\DriverTripException;
use App\Models\DriverTrip;
use App\Models\DriverVehicleAssignment;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Policies\DriverTripPolicy;
use App\Policies\DriverVehicleAssignmentPolicy;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DriverTripDomainTest extends TestCase
{
    use RefreshDatabase;

    private User $driver;

    private User $secondDriver;

    private User $admin;

    private User $operator;

    private Vehicle $vehicle;

    private DriverVehicleAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();

        $vehicleType = VehicleType::create(['code' => 'DT', 'name' => 'DUMP TRUCK']);

        $this->admin = User::factory()->admin()->create(['email' => 'admin_test@gama.com']);
        $this->operator = User::factory()->operator()->create(['email' => 'operator_test@gama.com']);
        $this->driver = User::factory()->driver()->create(['email' => 'driver_test@gama.com']);
        $this->secondDriver = User::factory()->driver()->create(['email' => 'driver2_test@gama.com']);

        $this->vehicle = Vehicle::create([
            'equipment_code' => 'DT-10',
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

    /** 1. Driver can have vehicle assignment */
    public function test_driver_can_have_vehicle_assignment(): void
    {
        $this->assertDatabaseHas('driver_vehicle_assignments', [
            'id' => $this->assignment->id,
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $this->assertTrue($this->assignment->isActive());
        $this->assertEquals($this->driver->id, $this->assignment->driver->id);
        $this->assertEquals($this->vehicle->id, $this->assignment->vehicle->id);
        $this->assertTrue($this->driver->driverVehicleAssignments->contains($this->assignment));
        $this->assertTrue($this->vehicle->driverVehicleAssignments->contains($this->assignment));
    }

    /** 2. Trip can be created with origin data */
    public function test_trip_can_be_created(): void
    {
        $clientId = (string) Str::uuid();

        $trip = DriverTrip::startTrip([
            'client_id' => $clientId,
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => Carbon::today()->toDateString(),
            'time_in' => '08:15:00',
            'origin_latitude' => 14.5995123,
            'origin_longitude' => 120.9842221,
            'origin_accuracy' => 5.2,
            'origin_address' => 'Manila Port Area',
        ]);

        $this->assertNotNull($trip);
        $this->assertEquals($clientId, $trip->client_id);
        $this->assertEquals(DriverTrip::STATUS_IN_PROGRESS, $trip->status);
        $this->assertTrue($trip->isInProgress());
        $this->assertFalse($trip->isCompleted());
        $this->assertEquals($this->assignment->id, $trip->driver_vehicle_assignment_id);

        $this->assertDatabaseHas('driver_trips', [
            'id' => $trip->id,
            'client_id' => $clientId,
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'IN_PROGRESS',
        ]);
    }

    /** 3. Only one active trip per driver */
    public function test_only_one_active_trip_per_driver(): void
    {
        DriverTrip::startTrip([
            'client_id' => (string) Str::uuid(),
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => Carbon::today()->toDateString(),
            'time_in' => '08:00:00',
            'origin_latitude' => 14.5995,
            'origin_longitude' => 120.9842,
        ]);

        $this->expectException(DriverTripException::class);
        $this->expectExceptionMessage("Driver #{$this->driver->id} already has an active trip in progress.");

        DriverTrip::startTrip([
            'client_id' => (string) Str::uuid(),
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => Carbon::today()->toDateString(),
            'time_in' => '08:30:00',
            'origin_latitude' => 14.6000,
            'origin_longitude' => 120.9850,
        ]);
    }

    /** 4. Completed trip cannot be ended twice */
    public function test_completed_trip_cannot_be_ended_twice(): void
    {
        $trip = DriverTrip::startTrip([
            'client_id' => (string) Str::uuid(),
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => Carbon::today()->toDateString(),
            'time_in' => '08:00:00',
            'origin_latitude' => 14.5995,
            'origin_longitude' => 120.9842,
        ]);

        $trip->endTrip([
            'destination_latitude' => 14.5547,
            'destination_longitude' => 121.0244,
            'time_out' => '09:15:00',
        ], $this->driver);

        $this->assertTrue($trip->isCompleted());

        $this->expectException(DriverTripException::class);
        $this->expectExceptionMessage("Trip cannot be ended because its current status is 'COMPLETED'.");

        $trip->endTrip([
            'destination_latitude' => 14.5550,
            'destination_longitude' => 121.0250,
            'time_out' => '09:30:00',
        ], $this->driver);
    }

    /** 5. Driver cannot access or end another driver's trip */
    public function test_driver_cannot_end_another_drivers_trip(): void
    {
        $trip = DriverTrip::startTrip([
            'client_id' => (string) Str::uuid(),
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => Carbon::today()->toDateString(),
            'time_in' => '08:00:00',
            'origin_latitude' => 14.5995,
            'origin_longitude' => 120.9842,
        ]);

        $this->expectException(DriverTripException::class);
        $this->expectExceptionMessage("Driver is not authorized to modify another driver's trip.");

        $trip->endTrip([
            'destination_latitude' => 14.5547,
            'destination_longitude' => 121.0244,
            'time_out' => '09:15:00',
        ], $this->secondDriver);
    }

    /** Policy checks for driver access */
    public function test_policy_prevents_unauthorized_trip_access(): void
    {
        $policy = new DriverTripPolicy;

        $trip = DriverTrip::startTrip([
            'client_id' => (string) Str::uuid(),
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => Carbon::today()->toDateString(),
            'time_in' => '08:00:00',
            'origin_latitude' => 14.5995,
            'origin_longitude' => 120.9842,
        ]);

        // Owning driver can view and end
        $this->assertTrue($policy->view($this->driver, $trip));
        $this->assertTrue($policy->end($this->driver, $trip));

        // Another driver cannot view or end
        $this->assertFalse($policy->view($this->secondDriver, $trip));
        $this->assertFalse($policy->end($this->secondDriver, $trip));

        // Admin can view and end
        $this->assertTrue($policy->view($this->admin, $trip));
        $this->assertTrue($policy->end($this->admin, $trip));

        // Operator can view
        $this->assertTrue($policy->view($this->operator, $trip));
    }

    /** 6. client_id duplicate is rejected/deduplicated */
    public function test_client_id_duplicate_is_idempotently_returned(): void
    {
        $clientId = (string) Str::uuid();

        $firstTrip = DriverTrip::startTrip([
            'client_id' => $clientId,
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => Carbon::today()->toDateString(),
            'time_in' => '08:00:00',
            'origin_latitude' => 14.5995,
            'origin_longitude' => 120.9842,
        ]);

        // Duplicate submission by same driver with same client_id returns the existing trip
        $retryTrip = DriverTrip::startTrip([
            'client_id' => $clientId,
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => Carbon::today()->toDateString(),
            'time_in' => '08:00:00',
            'origin_latitude' => 14.5995,
            'origin_longitude' => 120.9842,
        ]);

        $this->assertEquals($firstTrip->id, $retryTrip->id);
        $this->assertEquals(1, DriverTrip::where('client_id', $clientId)->count());
    }

    public function test_client_id_cannot_be_reused_by_different_driver(): void
    {
        $clientId = (string) Str::uuid();

        DriverTrip::startTrip([
            'client_id' => $clientId,
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => Carbon::today()->toDateString(),
            'time_in' => '08:00:00',
            'origin_latitude' => 14.5995,
            'origin_longitude' => 120.9842,
        ]);

        $this->expectException(DriverTripException::class);
        $this->expectExceptionMessage("client_id '{$clientId}' has already been used by another driver.");

        DriverTrip::startTrip([
            'client_id' => $clientId,
            'driver_id' => $this->secondDriver->id,
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => Carbon::today()->toDateString(),
            'time_in' => '08:00:00',
            'origin_latitude' => 14.5995,
            'origin_longitude' => 120.9842,
        ]);
    }

    /** 7. Destination fields can remain null while trip is IN_PROGRESS */
    public function test_destination_fields_can_remain_null_while_trip_is_in_progress(): void
    {
        $trip = DriverTrip::startTrip([
            'client_id' => (string) Str::uuid(),
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => Carbon::today()->toDateString(),
            'time_in' => '08:00:00',
            'origin_latitude' => 14.5995,
            'origin_longitude' => 120.9842,
        ]);

        $this->assertNull($trip->destination_latitude);
        $this->assertNull($trip->destination_longitude);
        $this->assertNull($trip->destination_accuracy);
        $this->assertNull($trip->destination_address);
        $this->assertNull($trip->time_out);
        $this->assertEquals('IN_PROGRESS', $trip->status);
    }

    /** 8. Trip can become COMPLETED with destination data */
    public function test_trip_can_become_completed_with_destination_data(): void
    {
        $trip = DriverTrip::startTrip([
            'client_id' => (string) Str::uuid(),
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => Carbon::today()->toDateString(),
            'time_in' => '08:00:00',
            'origin_latitude' => 14.5995,
            'origin_longitude' => 120.9842,
            'origin_address' => 'Origin Depot',
        ]);

        $trip->endTrip([
            'destination_latitude' => 14.6760,
            'destination_longitude' => 121.0437,
            'destination_accuracy' => 4.8,
            'destination_address' => 'Quezon City Site',
            'time_out' => '10:45:00',
            'remarks' => 'Smooth delivery',
        ], $this->driver);

        $trip->refresh();

        $this->assertEquals('COMPLETED', $trip->status);
        $this->assertTrue($trip->isCompleted());
        $this->assertEquals(14.6760, $trip->destination_latitude);
        $this->assertEquals(121.0437, $trip->destination_longitude);
        $this->assertEquals(4.8, $trip->destination_accuracy);
        $this->assertEquals('Quezon City Site', $trip->destination_address);
        $this->assertEquals('10:45:00', $trip->time_out);
        $this->assertEquals('Smooth delivery', $trip->remarks);
    }

    /** Driver must have role=driver to start trip */
    public function test_non_driver_cannot_start_trip(): void
    {
        $this->expectException(DriverTripException::class);
        $this->expectExceptionMessage("User #{$this->operator->id} does not have the driver role.");

        DriverTrip::startTrip([
            'client_id' => (string) Str::uuid(),
            'driver_id' => $this->operator->id,
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => Carbon::today()->toDateString(),
            'time_in' => '08:00:00',
            'origin_latitude' => 14.5995,
            'origin_longitude' => 120.9842,
        ]);
    }

    /** Driver must be authorized to use assigned vehicle */
    public function test_driver_cannot_start_trip_on_unassigned_vehicle(): void
    {
        $vehicleType = VehicleType::where('code', 'DT')->first();
        $otherVehicle = Vehicle::create([
            'equipment_code' => 'DT-99',
            'vehicle_type_id' => $vehicleType->id,
            'created_by' => $this->admin->id,
        ]);

        $this->expectException(DriverTripException::class);
        $this->expectExceptionMessage("Driver #{$this->driver->id} is not assigned to vehicle #{$otherVehicle->id}.");

        DriverTrip::startTrip([
            'client_id' => (string) Str::uuid(),
            'driver_id' => $this->driver->id,
            'vehicle_id' => $otherVehicle->id,
            'trip_date' => Carbon::today()->toDateString(),
            'time_in' => '08:00:00',
            'origin_latitude' => 14.5995,
            'origin_longitude' => 120.9842,
        ]);
    }

    /** Missing origin data validation */
    public function test_origin_coordinates_are_required_to_start_trip(): void
    {
        $this->expectException(DriverTripException::class);
        $this->expectExceptionMessage("Origin field 'origin_latitude' is required to start a trip.");

        DriverTrip::startTrip([
            'client_id' => (string) Str::uuid(),
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => Carbon::today()->toDateString(),
            'time_in' => '08:00:00',
        ]);
    }

    /** Missing destination data validation */
    public function test_destination_data_is_required_to_end_trip(): void
    {
        $trip = DriverTrip::startTrip([
            'client_id' => (string) Str::uuid(),
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'trip_date' => Carbon::today()->toDateString(),
            'time_in' => '08:00:00',
            'origin_latitude' => 14.5995,
            'origin_longitude' => 120.9842,
        ]);

        $this->expectException(DriverTripException::class);
        $this->expectExceptionMessage("Destination field 'destination_latitude' is required to complete a trip.");

        $trip->endTrip([
            'time_out' => '09:00:00',
        ], $this->driver);
    }

    /** Conflicting assignment detection */
    public function test_conflicting_assignment_is_detected(): void
    {
        $today = Carbon::today()->toDateString();
        $tomorrow = Carbon::tomorrow()->toDateString();

        // Already assigned for today
        $hasConflict = DriverVehicleAssignment::hasConflict(
            $this->driver->id,
            $this->vehicle->id,
            $today,
            $tomorrow
        );

        $this->assertTrue($hasConflict);

        // Different vehicle and different driver in the past should not conflict with an ended range
        $anotherDriver = User::factory()->driver()->create();
        $vehicleType = VehicleType::where('code', 'DT')->first();
        $freeVehicle = Vehicle::create([
            'equipment_code' => 'DT-55',
            'vehicle_type_id' => $vehicleType->id,
            'created_by' => $this->admin->id,
        ]);

        $noConflict = DriverVehicleAssignment::hasConflict(
            $anotherDriver->id,
            $freeVehicle->id,
            $today
        );

        $this->assertFalse($noConflict);
    }

    /** Assignment policy checks */
    public function test_assignment_policy(): void
    {
        $policy = new DriverVehicleAssignmentPolicy;

        $this->assertTrue($policy->view($this->driver, $this->assignment));
        $this->assertFalse($policy->view($this->secondDriver, $this->assignment));
        $this->assertTrue($policy->view($this->admin, $this->assignment));
        $this->assertTrue($policy->view($this->operator, $this->assignment));

        $this->assertTrue($policy->create($this->admin));
        $this->assertTrue($policy->create($this->operator));
        $this->assertFalse($policy->create($this->driver));
    }
}
