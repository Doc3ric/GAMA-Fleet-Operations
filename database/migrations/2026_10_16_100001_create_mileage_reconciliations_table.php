<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mileage_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->string('reconciliation_number', 50)->unique();
            $table->foreignId('dispatch_id')->constrained('fleet_dispatches')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->date('trip_date')->index();
            $table->boolean('is_gps_vehicle')->default(false)->index();
            $table->decimal('planned_distance_km', 10, 2)->default(0.00);
            $table->decimal('starting_odometer', 10, 2)->nullable();
            $table->decimal('ending_odometer', 10, 2)->nullable();
            $table->decimal('actual_odometer_km', 10, 2)->default(0.00);
            $table->decimal('gps_reported_km', 10, 2)->nullable();
            $table->decimal('odometer_gps_difference_km', 10, 2)->nullable();
            $table->decimal('variance_km', 10, 2)->default(0.00);
            $table->decimal('variance_percentage', 8, 2)->nullable();
            $table->string('status', 20)->default('DRAFT')->index(); // 'DRAFT', 'SUBMITTED', 'FOR_REVIEW', 'APPROVED', 'REJECTED'
            $table->boolean('has_exceptions')->default(false)->index();
            $table->text('notes')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('submitted_at')->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('reviewer_remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['trip_date', 'status']);
            $table->index(['vehicle_id', 'trip_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mileage_reconciliations');
    }
};
