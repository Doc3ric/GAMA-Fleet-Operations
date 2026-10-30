<?php

namespace Tests\Unit;

use App\Models\MileageReconciliation;
use PHPUnit\Framework\TestCase;

class MileageCalculationTest extends TestCase
{
    public function test_actual_odometer_calculation(): void
    {
        $reconciliation = new MileageReconciliation([
            'starting_odometer' => 10500.0,
            'ending_odometer' => 10650.5,
            'planned_distance_km' => 150.0,
        ]);

        $reconciliation->computeCalculations();

        $this->assertEquals(150.5, $reconciliation->actual_odometer_km);
    }

    public function test_mileage_variance_and_percentage_calculation(): void
    {
        $reconciliation = new MileageReconciliation([
            'starting_odometer' => 20000.0,
            'ending_odometer' => 20120.0,
            'planned_distance_km' => 100.0,
        ]);

        $reconciliation->computeCalculations();

        $this->assertEquals(120.0, $reconciliation->actual_odometer_km);
        $this->assertEquals(20.0, $reconciliation->variance_km);
        $this->assertEquals(20.0, $reconciliation->variance_percentage);
    }

    public function test_zero_planned_distance_safe_handling_never_divides_by_zero(): void
    {
        $reconciliation = new MileageReconciliation([
            'starting_odometer' => 5000.0,
            'ending_odometer' => 5050.0,
            'planned_distance_km' => 0.0,
        ]);

        $reconciliation->computeCalculations();

        $this->assertEquals(50.0, $reconciliation->actual_odometer_km);
        $this->assertEquals(50.0, $reconciliation->variance_km);
        $this->assertNull($reconciliation->variance_percentage);
    }

    public function test_missing_or_negative_planned_distance(): void
    {
        $reconciliation = new MileageReconciliation([
            'starting_odometer' => 100.0,
            'ending_odometer' => 200.0,
            'planned_distance_km' => null,
        ]);

        $reconciliation->computeCalculations();

        $this->assertEquals(100.0, $reconciliation->actual_odometer_km);
        $this->assertNull($reconciliation->variance_percentage);
    }

    public function test_negative_odometer_difference_calculated_correctly(): void
    {
        $reconciliation = new MileageReconciliation([
            'starting_odometer' => 50200.0,
            'ending_odometer' => 50100.0,
            'planned_distance_km' => 100.0,
        ]);

        $reconciliation->computeCalculations();

        $this->assertEquals(-100.0, $reconciliation->actual_odometer_km);
        $this->assertEquals(-200.0, $reconciliation->variance_km);
        $this->assertEquals(-200.0, $reconciliation->variance_percentage);
    }

    public function test_odometer_vs_gps_difference_calculation(): void
    {
        $reconciliation = new MileageReconciliation([
            'starting_odometer' => 1000.0,
            'ending_odometer' => 1100.0,
            'planned_distance_km' => 100.0,
            'gps_reported_km' => 105.2,
        ]);

        $reconciliation->computeCalculations();

        $this->assertEquals(100.0, $reconciliation->actual_odometer_km);
        $this->assertEquals(5.2, $reconciliation->odometer_gps_difference_km);
    }
}
