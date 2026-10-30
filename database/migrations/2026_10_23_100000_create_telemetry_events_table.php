<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telemetry_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->string('imei', 32)->index();
            $table->string('event_type', 20)->index(); // 'idling', 'parking', 'moving', 'offline', 'unknown'
            $table->timestamp('start_time')->index();
            $table->timestamp('end_time')->nullable()->index();
            $table->unsignedInteger('duration_seconds')->default(0)->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_ongoing')->default(true)->index();
            $table->string('data_quality', 20)->default('good'); // 'good', 'stale', 'intermittent', 'suspect'
            $table->string('source', 30)->default('live_sync'); // 'live_sync', 'track_reconstruction', 'manual'
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'start_time']);
            $table->index(['imei', 'start_time']);
            $table->index(['event_type', 'start_time']);
            $table->index(['is_ongoing', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemetry_events');
    }
};
