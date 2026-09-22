<?php

namespace Database\Seeders;

use App\Models\VehicleType;
use Illuminate\Database\Seeder;

class VehicleTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'BH', 'name' => 'BACKHOE'],
            ['code' => 'DT', 'name' => 'DUMP TRUCK'],
            ['code' => 'EX', 'name' => 'EXCAVATOR'],
            ['code' => 'WL', 'name' => 'WHEEL LOADER'],
            ['code' => 'GR', 'name' => 'GRADER'],
            ['code' => 'CO', 'name' => 'COMPACTOR'],
            ['code' => 'WT', 'name' => 'WATER TRUCK'],
            ['code' => 'FL', 'name' => 'FORKLIFT'],
            ['code' => 'PU', 'name' => 'PICK-UP'],
            ['code' => 'SV', 'name' => 'SERVICE VEHICLE'],
            ['code' => 'BU', 'name' => 'BUS'],
            ['code' => 'CR', 'name' => 'CRANE'],
            ['code' => 'SC', 'name' => 'SCRAPER'],
            ['code' => 'TK', 'name' => 'TANKER TRUCK'],
        ];

        foreach ($types as $type) {
            VehicleType::firstOrCreate(['code' => $type['code']], $type);
        }
    }
}
