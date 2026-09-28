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
        Schema::table('long_idling_records', function (Blueprint $table) {
            $table->string('driver_name')->nullable()->after('device_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('long_idling_records', function (Blueprint $table) {
            $table->dropColumn('driver_name');
        });
    }
};
