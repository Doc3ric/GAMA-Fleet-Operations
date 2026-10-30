<?php

namespace Database\Factories;

use App\Models\FleetDispatch;
use App\Models\MileageReconciliation;
use App\Models\ReconciliationException;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReconciliationException>
 */
class ReconciliationExceptionFactory extends Factory
{
    protected $model = ReconciliationException::class;

    public function definition(): array
    {
        return [
            'mileage_reconciliation_id' => MileageReconciliation::factory(),
            'dispatch_id' => FleetDispatch::factory(),
            'exception_type' => ReconciliationException::TYPE_VARIANCE_THRESHOLD,
            'severity' => ReconciliationException::SEVERITY_WARNING,
            'description' => 'Actual distance exceeded planned route by 25.0 km (20.8%).',
            'status' => ReconciliationException::STATUS_OPEN,
            'resolved_by' => null,
            'resolved_at' => null,
            'resolution_remarks' => null,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn () => [
            'status' => ReconciliationException::STATUS_RESOLVED,
            'resolved_by' => User::factory(),
            'resolved_at' => now(),
            'resolution_remarks' => 'Driver took confirmed detour due to road maintenance.',
        ]);
    }

    public function waived(): static
    {
        return $this->state(fn () => [
            'status' => ReconciliationException::STATUS_WAIVED,
            'resolved_by' => User::factory(),
            'resolved_at' => now(),
            'resolution_remarks' => 'Approved exception per manager instructions.',
        ]);
    }
}
