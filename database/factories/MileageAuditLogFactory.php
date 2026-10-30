<?php

namespace Database\Factories;

use App\Models\FleetDispatch;
use App\Models\MileageAuditLog;
use App\Models\MileageReconciliation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MileageAuditLog>
 */
class MileageAuditLogFactory extends Factory
{
    protected $model = MileageAuditLog::class;

    public function definition(): array
    {
        return [
            'mileage_reconciliation_id' => MileageReconciliation::factory(),
            'dispatch_id' => FleetDispatch::factory(),
            'user_id' => User::factory(),
            'action' => MileageAuditLog::ACTION_SUBMITTED,
            'old_values' => ['status' => 'DRAFT'],
            'new_values' => ['status' => 'SUBMITTED'],
            'remarks' => 'Submitted reconciliation for manager review.',
            'ip_address' => '127.0.0.1',
        ];
    }
}
