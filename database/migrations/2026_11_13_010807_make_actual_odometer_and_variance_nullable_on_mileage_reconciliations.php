<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mileage_reconciliations', function (Blueprint $table) {
            $table->decimal('actual_odometer_km', 10, 2)->nullable()->default(null)->change();
            $table->decimal('variance_km', 10, 2)->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $hasPendingMeasurements = DB::table('mileage_reconciliations')
            ->whereNull('actual_odometer_km')
            ->orWhereNull('variance_km')
            ->exists();

        if ($hasPendingMeasurements) {
            throw new RuntimeException('Cannot reverse this migration while reconciliations have pending measurements. Resolve those records before retrying.');
        }

        Schema::table('mileage_reconciliations', function (Blueprint $table) {
            $table->decimal('actual_odometer_km', 10, 2)->default(0.00)->nullable(false)->change();
            $table->decimal('variance_km', 10, 2)->default(0.00)->nullable(false)->change();
        });
    }
};
