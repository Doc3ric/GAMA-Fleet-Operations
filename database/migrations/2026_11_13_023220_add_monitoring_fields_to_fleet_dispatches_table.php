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
        Schema::table('fleet_dispatches', function (Blueprint $table) {
            $table->decimal('origin_latitude', 10, 7)->nullable()->after('starting_point');
            $table->decimal('origin_longitude', 10, 7)->nullable()->after('origin_latitude');
            $table->decimal('destination_latitude', 10, 7)->nullable()->after('destination');
            $table->decimal('destination_longitude', 10, 7)->nullable()->after('destination_latitude');
            $table->string('monitoring_status', 30)->default('NORMAL')->index()->after('dispatch_status'); // 'NORMAL', 'POSSIBLE_EXCEPTION', 'CONFIRMED_EXCEPTION', 'GPS_UNAVAILABLE'
            $table->dateTime('last_monitored_at')->nullable()->after('monitoring_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fleet_dispatches', function (Blueprint $table) {
            $table->dropColumn([
                'origin_latitude',
                'origin_longitude',
                'destination_latitude',
                'destination_longitude',
                'monitoring_status',
                'last_monitored_at',
            ]);
        });
    }
};
