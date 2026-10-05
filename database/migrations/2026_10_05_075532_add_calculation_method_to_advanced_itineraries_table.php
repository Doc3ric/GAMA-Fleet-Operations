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
            $table->string('calculation_method', 20)->default('divide')->after('fuel_liters_required');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('advanced_itineraries', function (Blueprint $table) {
            $table->dropColumn('calculation_method');
        });
    }
};
