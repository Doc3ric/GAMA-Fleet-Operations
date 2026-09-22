<?php

namespace Database\Seeders;

use App\Models\LongIdlingRecord;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(['email' => 'admin@gama.com'], ['name' => 'GPS Monitoring Specialist', 'password' => Hash::make('password')]);
        $devices = [
            ['name' => 'GAMA-001', 'imei' => '123456789012301', 'model' => 'Hikvision DS-MH2111'],
            ['name' => 'GAMA-002', 'imei' => '123456789012302', 'model' => 'Hikvision DS-MH2111'],
            ['name' => 'GAMA-003', 'imei' => '123456789012303', 'model' => 'Suntech ST600R'],
            ['name' => 'GAMA-004', 'imei' => '123456789012304', 'model' => 'Suntech ST600R'],
            ['name' => 'GAMA-005', 'imei' => '123456789012305', 'model' => 'Concox AT4'],
        ];
        $addresses = ['Brgy. Pinyahan, Quezon City', 'Magallanes Interchange, Makati City', 'Brgy. Baclaran, Paranaque City', 'EDSA-Kamuning, Quezon City', 'Brgy. Manggahan, Pasig City'];
        for ($day = 4; $day >= 0; $day--) {
            $date = now()->subDays($day)->format('Y-m-d');
            $report = Report::firstOrCreate(['report_date' => $date, 'created_by' => $user->id, 'report_type' => 'long_idling'], ['status' => $day === 0 ? 'draft' : 'completed']);
            foreach ($devices as $i => $d) {
                $sH = rand(7, 15);
                $sM = rand(0, 59);
                $dur = rand(45, 180);
                $eH = $sH + intdiv($sM + $dur, 60);
                $eM = ($sM + $dur) % 60;
                $st = sprintf('%02d:%02d', $sH, $sM);
                $en = sprintf('%02d:%02d', $eH, $eM);
                LongIdlingRecord::firstOrCreate(['report_id' => $report->id, 'imei' => $d['imei']], ['device_name' => $d['name'], 'model' => $d['model'], 'start_time' => $st, 'end_time' => $en, 'stay_time' => LongIdlingRecord::calculateStayTime($st, $en), 'latitude' => round(14.5 + (rand(0, 1000) / 10000), 7), 'longitude' => round(120.9 + (rand(0, 1000) / 10000), 7), 'address' => $addresses[$i % count($addresses)], 'sort_order' => $i]);
            }
        }
        $this->command->info('Seeded! Login: admin@gama.com / password');
    }
}
