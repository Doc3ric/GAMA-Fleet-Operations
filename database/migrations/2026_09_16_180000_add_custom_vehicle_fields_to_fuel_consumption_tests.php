<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel_consumption_tests', function (Blueprint $table) {
            $table->unsignedBigInteger('vehicle_id')->nullable()->change();
            $table->string('custom_equipment_code', 100)->nullable()->after('vehicle_id');
            $table->string('custom_plate_number', 50)->nullable()->after('custom_equipment_code');
            $table->string('custom_model', 100)->nullable()->after('custom_plate_number');
        });
    }

    public function down(): void
    {
        Schema::table('fuel_consumption_tests', function (Blueprint $table) {
            $table->dropColumn(['custom_equipment_code', 'custom_plate_number', 'custom_model']);
            $table->unsignedBigInteger('vehicle_id')->nullable(false)->change();
        });
    }
};
