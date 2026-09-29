<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('user', 100)->nullable()->after('operator_driver');
            $table->decimal('average_fuel_consumption', 8, 2)->nullable()->after('fuel_unit');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['user', 'average_fuel_consumption']);
        });
    }
};
