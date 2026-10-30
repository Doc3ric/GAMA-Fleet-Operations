<?php

namespace Database\Factories;

use App\Models\FleetDispatch;
use App\Models\MileageReconciliation;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MileageReconciliation>
 */
class MileageReconciliationFactory extends Factory
{
    protected $model = MileageReconciliation::class;

    public function definition(): array
    {
        $startOdo = 50000.0;
        $endOdo = 50125.0;
        $actual = 125.0;
        $planned = 120.0;
        $variance = 5.0;
        $variancePct = 4.17;

        return [
            'reconciliation_number' => 'REC-'.fake()->unique()->numerify('2026####'),
            'dispatch_id' => FleetDispatch::factory(),
            'vehicle_id' => Vehicle::factory(),
            'trip_date' => Carbon::today(),
            'is_gps_vehicle' => false,
            'planned_distance_km' => $planned,
            'starting_odometer' => $startOdo,
            'ending_odometer' => $endOdo,
            'actual_odometer_km' => $actual,
            'gps_reported_km' => null,
            'odometer_gps_difference_km' => null,
            'variance_km' => $variance,
            'variance_percentage' => $variancePct,
            'status' => MileageReconciliation::STATUS_DRAFT,
            'has_exceptions' => false,
            'notes' => fake()->sentence(),
            'submitted_by' => null,
            'submitted_at' => null,
            'reviewer_id' => null,
            'reviewed_at' => null,
            'reviewer_remarks' => null,
            'created_by' => User::factory(),
        ];
    }

    public function gps(float $gpsDistance = 124.0): static
    {
        return $this->state(fn (array $attrs) => [
            'is_gps_vehicle' => true,
            'gps_reported_km' => $gpsDistance,
            'odometer_gps_difference_km' => round(abs(($attrs['actual_odometer_km'] ?? 125.0) - $gpsDistance), 2),
        ]);
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status' => MileageReconciliation::STATUS_SUBMITTED,
            'submitted_by' => User::factory(),
            'submitted_at' => Carbon::now(),
        ]);
    }

    public function forReview(): static
    {
        return $this->state(fn () => [
            'status' => MileageReconciliation::STATUS_FOR_REVIEW,
            'submitted_by' => User::factory(),
            'submitted_at' => Carbon::now(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => MileageReconciliation::STATUS_APPROVED,
            'submitted_by' => User::factory(),
            'submitted_at' => Carbon::now()->subHours(2),
            'reviewer_id' => User::factory(),
            'reviewed_at' => Carbon::now(),
            'reviewer_remarks' => 'Verified and approved.',
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => MileageReconciliation::STATUS_REJECTED,
            'submitted_by' => User::factory(),
            'submitted_at' => Carbon::now()->subHours(2),
            'reviewer_id' => User::factory(),
            'reviewed_at' => Carbon::now(),
            'reviewer_remarks' => 'Odometer reading unclear. Please re-upload photo.',
        ]);
    }

    public function withException(): static
    {
        return $this->state(fn () => [
            'has_exceptions' => true,
        ]);
    }
}
