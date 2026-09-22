<?php

namespace Database\Seeders;

use App\Models\DriverTrip;
use App\Models\DriverVehicleAssignment;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DriverSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@gama.com'],
            ['name' => 'GPS Monitoring Specialist', 'role' => User::ROLE_ADMIN, 'password' => Hash::make('password')]
        );

        $driver = User::firstOrCreate(
            ['email' => 'driver@gama.com'],
            [
                'name' => 'Juan Dela Cruz',
                'role' => User::ROLE_DRIVER,
                'password' => Hash::make('password'),
            ]
        );

        $vehicleType = VehicleType::firstOrCreate(
            ['code' => 'DT'],
            ['name' => 'DUMP TRUCK']
        );

        $vehicle = Vehicle::firstOrCreate(
            ['equipment_code' => 'DT-01'],
            [
                'vehicle_type_id' => $vehicleType->id,
                'model' => 'ISUZU GIGA 10W',
                'plate_number' => 'NBD-5421',
                'location' => 'Main Yard',
                'gps_status' => 'YES',
                'created_by' => $admin->id,
            ]
        );

        $assignment = DriverVehicleAssignment::firstOrCreate(
            [
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
                'assigned_from' => now()->toDateString(),
            ],
            [
                'assigned_until' => null,
                'notes' => 'Primary route dump truck',
                'created_by' => $admin->id,
            ]
        );

        // Seed one completed historical trip
        DriverTrip::firstOrCreate(
            ['client_id' => '00000000-0000-4000-8000-000000000001'],
            [
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
                'driver_vehicle_assignment_id' => $assignment->id,
                'trip_date' => now()->subDay()->toDateString(),
                'time_in' => '07:30:00',
                'time_out' => '09:15:00',
                'origin_latitude' => 14.5547,
                'origin_longitude' => 121.0244,
                'origin_accuracy' => 5.0,
                'origin_address' => 'Makati Warehouse, Metro Manila',
                'destination_latitude' => 14.6507,
                'destination_longitude' => 121.0365,
                'destination_accuracy' => 6.2,
                'destination_address' => 'Quezon City Plant, Metro Manila',
                'status' => DriverTrip::STATUS_COMPLETED,
                'remarks' => 'Completed batch delivery',
            ]
        );
    }
}
