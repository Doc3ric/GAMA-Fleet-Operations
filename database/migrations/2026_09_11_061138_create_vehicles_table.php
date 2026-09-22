<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('equipment_code', 20)->unique();
            $table->foreignId('vehicle_type_id')->nullable()->constrained('vehicle_types')->nullOnDelete();
            $table->string('model', 100)->nullable();
            $table->string('plate_number', 30)->nullable();
            $table->date('date_acquired')->nullable();
            // Fuel: stored separately for clean filtering, displayed as "16–20 LIT/HR"
            $table->decimal('fuel_min', 8, 2)->nullable();
            $table->decimal('fuel_max', 8, 2)->nullable();
            $table->string('fuel_unit', 20)->nullable()->default('LIT/HR');
            // Status: stored separately, displayed as "0.8 / RUNNING"
            $table->decimal('status_value', 5, 2)->nullable();
            $table->string('status_label', 50)->nullable();
            $table->string('location', 150)->nullable();
            $table->string('project_code', 50)->nullable();
            $table->string('operator_driver', 100)->nullable();
            $table->string('helper', 100)->nullable();
            $table->enum('gps_status', ['YES', 'NO', 'EXPIRED', 'FOR_CHECKUP'])->default('NO');
            $table->string('image')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('vehicle_type_id');
            $table->index('gps_status');
            $table->index('location');
            $table->index('project_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
