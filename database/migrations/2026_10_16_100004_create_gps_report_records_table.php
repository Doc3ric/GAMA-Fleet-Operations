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
        Schema::create('gps_report_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gps_report_import_id')->constrained('gps_report_imports')->cascadeOnDelete();
            $table->string('device_name')->nullable();
            $table->string('imei', 50)->nullable()->index();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->dateTime('start_time')->nullable();
            $table->dateTime('end_time')->nullable();
            $table->decimal('run_distance_km', 10, 2)->nullable();
            $table->decimal('start_odometer', 10, 2)->nullable();
            $table->decimal('end_odometer', 10, 2)->nullable();
            $table->json('raw_data')->nullable();
            $table->string('match_status', 20)->default('UNMATCHED')->index(); // 'MATCHED', 'AMBIGUOUS', 'UNMATCHED', 'DUPLICATE'
            $table->foreignId('mileage_reconciliation_id')->nullable()->constrained('mileage_reconciliations')->nullOnDelete();
            $table->timestamps();

            $table->index(['vehicle_id', 'start_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gps_report_records');
    }
};
