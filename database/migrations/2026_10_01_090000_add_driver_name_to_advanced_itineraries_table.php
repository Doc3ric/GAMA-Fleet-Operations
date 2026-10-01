<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advanced_itineraries', function (Blueprint $table) {
            $table->string('driver_name', 255)->nullable()->after('vehicle_id');
        });
    }

    public function down(): void
    {
        Schema::table('advanced_itineraries', function (Blueprint $table) {
            $table->dropColumn('driver_name');
        });
    }
};
