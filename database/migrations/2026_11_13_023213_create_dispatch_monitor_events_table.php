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
        Schema::create('dispatch_monitor_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatch_id')->constrained('fleet_dispatches')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->string('event_type', 50)->index(); // 'ROUTE_DEVIATION', 'MISSED_DEPARTURE', 'UNEXPECTED_STOP', 'DELAYED_ARRIVAL', 'GPS_UNAVAILABLE'
            $table->string('severity', 20)->default('WARNING')->index(); // 'INFO', 'WARNING', 'DANGER'
            $table->string('status', 30)->default('POSSIBLE')->index(); // 'POSSIBLE', 'CONFIRMED', 'RESOLVED', 'WAIVED'
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('speed_kmh', 6, 2)->nullable();
            $table->decimal('deviation_meters', 10, 2)->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_remarks')->nullable();
            $table->timestamps();

            $table->index(['dispatch_id', 'status']);
            $table->index(['vehicle_id', 'event_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dispatch_monitor_events');
    }
};
