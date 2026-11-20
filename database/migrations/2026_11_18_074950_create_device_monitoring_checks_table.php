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
        Schema::create('device_monitoring_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('monitored_on');
            $table->dateTime('checked_at');
            $table->string('device_name', 100);
            $table->string('imei', 50)->nullable();
            $table->string('vehicle_label', 100)->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'monitored_on']);
            $table->index(['owner_id', 'monitored_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_monitoring_checks');
    }
};
