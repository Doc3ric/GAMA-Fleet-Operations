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
        Schema::create('fleet_dispatches', function (Blueprint $table) {
            $table->id();
            $table->string('trip_number', 50)->unique();
            $table->foreignId('advanced_itinerary_id')->nullable()->constrained('advanced_itineraries')->nullOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->string('driver_name', 100)->nullable();
            $table->date('trip_date')->index();
            $table->string('starting_point', 200)->default('Main Yard');
            $table->string('destination', 255);
            $table->text('planned_route')->nullable();
            $table->decimal('planned_distance_km', 10, 2)->default(0.00);
            $table->dateTime('planned_departure_time')->index();
            $table->dateTime('actual_departure_time')->nullable()->index();
            $table->string('departure_source', 50)->nullable(); // 'authorized_dispatch', 'driver_submitted', 'gps_movement'
            $table->string('dispatch_status', 20)->default('PENDING')->index(); // 'PENDING', 'ON_TIME', 'DELAYED', 'CANCELLED'
            $table->integer('delay_minutes')->default(0);
            $table->integer('grace_period_minutes')->default(15);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['trip_date', 'dispatch_status']);
            $table->index(['vehicle_id', 'trip_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fleet_dispatches');
    }
};
