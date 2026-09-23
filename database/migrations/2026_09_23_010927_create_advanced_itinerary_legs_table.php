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
        Schema::create('advanced_itinerary_legs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advanced_itinerary_id')->constrained('advanced_itineraries')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('origin_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('starting_point_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('destination_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->decimal('distance_origin_to_start', 8, 2)->nullable();
            $table->decimal('distance_start_to_dest', 8, 2)->nullable();
            $table->decimal('total_distance', 8, 2)->nullable();
            $table->string('routing_source', 30)->nullable();
            $table->string('purpose')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advanced_itinerary_legs');
    }
};
