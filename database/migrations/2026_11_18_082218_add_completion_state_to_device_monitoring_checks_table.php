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
        Schema::table('device_monitoring_checks', function (Blueprint $table) {
            $table->boolean('is_completed')->default(true);
            $table->dateTime('reopened_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_monitoring_checks', function (Blueprint $table) {
            $table->dropForeign(['reopened_by']);
            $table->dropColumn(['is_completed', 'reopened_at', 'reopened_by']);
        });
    }
};
