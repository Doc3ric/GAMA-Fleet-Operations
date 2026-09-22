<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_consumption_tests', function (Blueprint $table) {
            $table->id();
            $table->date('test_date');
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('driver_name', 100)->nullable();
            $table->decimal('start_odometer', 10, 2);
            $table->decimal('end_odometer', 10, 2);
            $table->decimal('distance_travelled', 10, 2);
            $table->decimal('fuel_consumed_liters', 10, 3);
            $table->decimal('average_fuel_consumption', 10, 2);
            $table->string('test_route', 255)->nullable();
            $table->text('remarks')->nullable();
            $table->string('attested_by', 100)->nullable();
            $table->string('requested_by', 100)->nullable();
            $table->string('start_odometer_image', 255)->nullable();
            $table->string('end_odometer_image', 255)->nullable();
            $table->string('fuel_receipt_image', 255)->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('test_date');
            $table->index('vehicle_id');
            $table->index('driver_id');
            $table->index('distance_travelled');
            $table->index('average_fuel_consumption');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_consumption_tests');
    }
};
