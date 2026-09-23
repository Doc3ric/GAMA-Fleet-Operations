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
        Schema::table('advanced_itineraries', function (Blueprint $table) {
            $table->unsignedInteger('total_duration_minutes')->nullable()->after('status');
        });

        Schema::table('advanced_itinerary_legs', function (Blueprint $table) {
            $table->unsignedInteger('duration_origin_to_start_minutes')->nullable()->after('distance_origin_to_start');
            $table->unsignedInteger('duration_start_to_dest_minutes')->nullable()->after('distance_start_to_dest');
            $table->unsignedInteger('total_duration_minutes')->nullable()->after('total_distance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('advanced_itinerary_legs', function (Blueprint $table) {
            $table->dropColumn([
                'duration_origin_to_start_minutes',
                'duration_start_to_dest_minutes',
                'total_duration_minutes',
            ]);
        });

        Schema::table('advanced_itineraries', function (Blueprint $table) {
            $table->dropColumn('total_duration_minutes');
        });
    }
};
