<?php

namespace Tests\Unit;

use App\Models\AdvancedItinerary;
use PHPUnit\Framework\TestCase;

class FuelPoThresholdRoundingTest extends TestCase
{
    /**
     * Test the user's specific case: 48.50 KM ÷ 1.60 KM/L = 30.3125 L
     * Rounded to 2 decimals gives 30.31 L.
     * Since decimal part .31 >= .10, it rounds up to 31.
     */
    public function test_user_example_48_point_5_km_with_1_point_6_km_per_liter(): void
    {
        $result = AdvancedItinerary::calculateFuelLiters(48.50, 1.60);

        $this->assertEquals(31.0, $result);
    }

    /**
     * Test the full user threshold rounding table (.00-.09 keeps integer, .10+ rounds up to next integer).
     */
    public function test_custom_threshold_rounding_table_rule(): void
    {
        // 30.01 -> 30
        $this->assertEquals(30.0, AdvancedItinerary::calculateFuelLiters(30.01, 1.0));

        // 30.09 -> 30
        $this->assertEquals(30.0, AdvancedItinerary::calculateFuelLiters(30.09, 1.0));

        // 30.10 -> 31
        $this->assertEquals(31.0, AdvancedItinerary::calculateFuelLiters(30.10, 1.0));

        // 30.20 -> 31
        $this->assertEquals(31.0, AdvancedItinerary::calculateFuelLiters(30.20, 1.0));

        // 30.31 -> 31
        $this->assertEquals(31.0, AdvancedItinerary::calculateFuelLiters(30.31, 1.0));

        // 30.99 -> 31
        $this->assertEquals(31.0, AdvancedItinerary::calculateFuelLiters(30.99, 1.0));

        // 31.05 -> 31
        $this->assertEquals(31.0, AdvancedItinerary::calculateFuelLiters(31.05, 1.0));

        // 31.10 -> 32
        $this->assertEquals(32.0, AdvancedItinerary::calculateFuelLiters(31.10, 1.0));
    }

    /**
     * Test edge cases: null, zero, and invalid values.
     */
    public function test_edge_cases(): void
    {
        $this->assertEquals(0.0, AdvancedItinerary::calculateFuelLiters(null, 1.6));
        $this->assertEquals(0.0, AdvancedItinerary::calculateFuelLiters(0.0, 1.6));
        $this->assertEquals(0.0, AdvancedItinerary::calculateFuelLiters(-5.0, 1.6));

        $this->assertNull(AdvancedItinerary::calculateFuelLiters(48.5, null));
        $this->assertNull(AdvancedItinerary::calculateFuelLiters(48.5, 0.0));
        $this->assertNull(AdvancedItinerary::calculateFuelLiters(48.5, -1.0));
    }
}
