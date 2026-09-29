<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advanced_itineraries', function (Blueprint $table) {
            $table->string('destination', 255)->nullable()->after('title');
            $table->decimal('total_distance', 10, 2)->nullable()->after('destination');
        });
    }

    public function down(): void
    {
        Schema::table('advanced_itineraries', function (Blueprint $table) {
            $table->dropColumn(['destination', 'total_distance']);
        });
    }
};
