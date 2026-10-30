<?php

namespace Database\Factories;

use App\Models\MileageEvidence;
use App\Models\MileageReconciliation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MileageEvidence>
 */
class MileageEvidenceFactory extends Factory
{
    protected $model = MileageEvidence::class;

    public function definition(): array
    {
        return [
            'mileage_reconciliation_id' => MileageReconciliation::factory(),
            'evidence_type' => MileageEvidence::TYPE_ODOMETER_PHOTO,
            'file_path' => 'reconciliation-evidence/odo-'.fake()->uuid().'.jpg',
            'original_filename' => 'odometer_photo.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1024 * 350,
            'verified_by' => null,
            'verified_at' => null,
            'notes' => fake()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    public function verified(): static
    {
        return $this->state(fn () => [
            'verified_by' => User::factory(),
            'verified_at' => now(),
        ]);
    }
}
