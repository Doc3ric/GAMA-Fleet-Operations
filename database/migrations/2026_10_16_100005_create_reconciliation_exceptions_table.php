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
        Schema::create('reconciliation_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mileage_reconciliation_id')->constrained('mileage_reconciliations')->cascadeOnDelete();
            $table->foreignId('dispatch_id')->nullable()->constrained('fleet_dispatches')->cascadeOnDelete();
            $table->string('exception_type', 50)->index();
            $table->string('severity', 20)->default('WARNING')->index(); // 'INFO', 'WARNING', 'DANGER'
            $table->text('description');
            $table->string('status', 20)->default('OPEN')->index(); // 'OPEN', 'RESOLVED', 'WAIVED'
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->text('resolution_remarks')->nullable();
            $table->timestamps();

            $table->index(['mileage_reconciliation_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reconciliation_exceptions');
    }
};
