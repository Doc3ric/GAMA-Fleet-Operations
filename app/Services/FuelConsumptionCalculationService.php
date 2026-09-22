<?php

namespace App\Services;

use InvalidArgumentException;

class FuelConsumptionCalculationService
{
    /**
     * Threshold under which a test distance is flagged with a non-blocking warning.
     */
    public const SHORT_DISTANCE_THRESHOLD_KM = 20.0;

    /**
     * Calculate distance travelled from start and end odometer readings.
     *
     * @throws InvalidArgumentException
     */
    public function calculateDistance(float $startOdometer, float $endOdometer): float
    {
        if ($endOdometer < $startOdometer) {
            throw new InvalidArgumentException('End odometer cannot be lower than start odometer.');
        }

        return round($endOdometer - $startOdometer, 2);
    }

    /**
     * Calculate average fuel consumption in KM/L.
     *
     * @throws InvalidArgumentException
     */
    public function calculateAverageConsumption(float $distanceTravelled, float $fuelConsumedLiters): float
    {
        if ($fuelConsumedLiters <= 0) {
            throw new InvalidArgumentException('Fuel consumed must be greater than zero.');
        }

        if ($distanceTravelled < 0) {
            throw new InvalidArgumentException('Distance travelled cannot be negative.');
        }

        // Full-tank method: Distance / Refilled liters to full tank
        return round($distanceTravelled / $fuelConsumedLiters, 2);
    }

    /**
     * Calculate distance and average consumption together from raw test inputs.
     *
     * @return array{distance_travelled: float, average_fuel_consumption: float}
     *
     * @throws InvalidArgumentException
     */
    public function calculateFromReadings(float $startOdometer, float $endOdometer, float $fuelConsumedLiters): array
    {
        $distance = $this->calculateDistance($startOdometer, $endOdometer);
        $consumption = $this->calculateAverageConsumption($distance, $fuelConsumedLiters);

        return [
            'distance_travelled' => $distance,
            'average_fuel_consumption' => $consumption,
        ];
    }

    /**
     * Determine if the test distance is unusually short.
     */
    public function isShortDistance(float $distance): bool
    {
        return $distance > 0 && $distance < self::SHORT_DISTANCE_THRESHOLD_KM;
    }

    /**
     * Non-blocking warning message for short distance tests.
     */
    public function getShortDistanceWarningMessage(): string
    {
        return "Short test distance. The calculated fuel consumption may not represent the vehicle's typical fuel economy.";
    }

    /**
     * Format distance in kilometers.
     */
    public function formatDistance(?float $distance): string
    {
        if ($distance === null) {
            return '—';
        }

        return number_format($distance, 2).' km';
    }

    /**
     * Format fuel consumed in liters.
     */
    public function formatFuel(?float $fuel): string
    {
        if ($fuel === null) {
            return '—';
        }

        return number_format($fuel, 3).' L';
    }

    /**
     * Format average fuel consumption in km/L.
     */
    public function formatKmL(?float $kmL): string
    {
        if ($kmL === null) {
            return '—';
        }

        return number_format($kmL, 2).' km/L';
    }
}
