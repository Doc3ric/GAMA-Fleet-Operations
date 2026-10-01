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
            $table->decimal('start_odo', 12, 2)->nullable()->after('destination');
            $table->decimal('end_odo', 12, 2)->nullable()->after('start_odo');
        });

        Schema::table('advanced_itinerary_legs', function (Blueprint $table) {
            $table->decimal('start_odo', 12, 2)->nullable()->after('destination_location_id');
            $table->decimal('end_odo', 12, 2)->nullable()->after('start_odo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('advanced_itinerary_legs', function (Blueprint $table) {
            $table->dropColumn(['start_odo', 'end_odo']);
        });

        Schema::table('advanced_itineraries', function (Blueprint $table) {
            $table->dropColumn(['start_odo', 'end_odo']);
        });
    }
};
