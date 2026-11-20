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
        Schema::create('device_monitoring_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_monitoring_check_id')->constrained('device_monitoring_checks')->cascadeOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 20);
            $table->dateTime('performed_at');
            $table->timestamps();

            $table->index(['device_monitoring_check_id', 'performed_at'], 'monitoring_actions_check_time_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_monitoring_actions');
    }
};
