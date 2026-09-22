<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\LocationAlias;
use App\Models\User;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds with clearly designated sample test locations.
     */
    public function run(): void
    {
        $admin = User::first() ?? User::factory()->admin()->create();

        $sampleLocations = [
            [
                'code' => 'G2',
                'official_name' => 'GAMA FARM 2',
                'type' => Location::TYPE_FARM,
                'latitude' => 14.8450000,
                'longitude' => 120.8120000,
                'address' => 'Sample Test Facility, Farm 2 Site Road',
                'barangay' => 'San Juan',
                'municipality' => 'Malolos City',
                'province' => 'Bulacan',
                'status' => Location::STATUS_ACTIVE,
                'notes' => 'Sample test master data for GAMA Farm 2 facility.',
                'aliases' => ['G2', 'GAMA 2', 'FARM 2', 'GAMA FARM2', 'G2 FARM', 'GAMA FARM 2'],
            ],
            [
                'code' => 'G1',
                'official_name' => 'GAMA FARM 1',
                'type' => Location::TYPE_FARM,
                'latitude' => 14.8620000,
                'longitude' => 120.8350000,
                'address' => 'Sample Test Facility, Farm 1 Site Road',
                'barangay' => 'Banga',
                'municipality' => 'Plaridel',
                'province' => 'Bulacan',
                'status' => Location::STATUS_ACTIVE,
                'notes' => 'Sample test master data for GAMA Farm 1 facility.',
                'aliases' => ['G1', 'GAMA 1', 'FARM 1', 'G1 FARM'],
            ],
            [
                'code' => 'CWH',
                'official_name' => 'CENTRAL WAREHOUSE',
                'type' => Location::TYPE_WAREHOUSE,
                'latitude' => 14.6500000,
                'longitude' => 121.0500000,
                'address' => 'Sample Test Facility, Central Logistics Complex',
                'barangay' => 'Bagong Silang',
                'municipality' => 'Quezon City',
                'province' => 'Metro Manila',
                'status' => Location::STATUS_ACTIVE,
                'notes' => 'Central storage depot for spare parts and grains.',
                'aliases' => ['CWH', 'WAREHOUSE', 'MAIN BODEGA', 'CENTRAL WH', 'WH'],
            ],
            [
                'code' => 'GHO',
                'official_name' => 'GAMA HEAD OFFICE',
                'type' => Location::TYPE_OFFICE,
                'latitude' => 14.5800000,
                'longitude' => 121.0600000,
                'address' => 'Sample Test Facility, Corporate Office Tower',
                'barangay' => 'San Antonio',
                'municipality' => 'Pasig City',
                'province' => 'Metro Manila',
                'status' => Location::STATUS_ACTIVE,
                'notes' => 'Main corporate administrative headquarters.',
                'aliases' => ['HO', 'HEAD OFFICE', 'OFFICE', 'MAIN OFFICE'],
            ],
        ];

        foreach ($sampleLocations as $data) {
            $aliases = $data['aliases'];
            unset($data['aliases']);

            $data['created_by'] = $admin->id;
            $data['updated_by'] = $admin->id;

            $loc = Location::firstOrCreate(['code' => $data['code']], $data);

            foreach ($aliases as $alias) {
                LocationAlias::firstOrCreate([
                    'location_id' => $loc->id,
                    'alias' => $alias,
                ]);
            }
        }
    }
}
