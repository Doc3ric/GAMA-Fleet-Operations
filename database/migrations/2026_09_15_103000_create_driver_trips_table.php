<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_trips', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_id')->unique();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('driver_vehicle_assignment_id')->nullable()->constrained('driver_vehicle_assignments')->nullOnDelete();
            $table->date('trip_date')->index();
            $table->time('time_in');
            $table->time('time_out')->nullable();
            $table->decimal('origin_latitude', 10, 7);
            $table->decimal('origin_longitude', 10, 7);
            $table->decimal('origin_accuracy', 8, 2)->nullable();
            $table->text('origin_address')->nullable();
            $table->decimal('destination_latitude', 10, 7)->nullable();
            $table->decimal('destination_longitude', 10, 7)->nullable();
            $table->decimal('destination_accuracy', 8, 2)->nullable();
            $table->text('destination_address')->nullable();
            $table->string('status', 20)->default('IN_PROGRESS')->index();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'status']);
            $table->index(['vehicle_id', 'trip_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_trips');
    }
};
