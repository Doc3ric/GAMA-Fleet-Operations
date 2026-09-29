<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advanced_itineraries', function (Blueprint $table) {
            $table->decimal('fuel_liters_required', 10, 2)->nullable()->after('total_duration_minutes');
            $table->boolean('po_checked')->default(false)->after('fuel_liters_required');
            $table->timestamp('po_checked_at')->nullable()->after('po_checked');
            $table->foreignId('po_checked_by')->nullable()->after('po_checked_at')->constrained('users')->nullOnDelete();

            $table->index('po_checked');
        });
    }

    public function down(): void
    {
        Schema::table('advanced_itineraries', function (Blueprint $table) {
            $table->dropForeign(['po_checked_by']);
            $table->dropIndex(['po_checked']);
            $table->dropColumn([
                'fuel_liters_required',
                'po_checked',
                'po_checked_at',
                'po_checked_by',
            ]);
        });
    }
};
