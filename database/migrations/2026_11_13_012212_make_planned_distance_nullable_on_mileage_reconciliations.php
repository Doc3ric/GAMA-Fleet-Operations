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
            $table->decimal('planned_distance_km', 10, 2)->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('mileage_reconciliations')->whereNull('planned_distance_km')->exists()) {
            throw new RuntimeException('Cannot reverse this migration while reconciliations have an unknown planned distance. Resolve those records before retrying.');
        }

        Schema::table('mileage_reconciliations', function (Blueprint $table) {
            $table->decimal('planned_distance_km', 10, 2)->default(0.00)->nullable(false)->change();
        });
    }
};
