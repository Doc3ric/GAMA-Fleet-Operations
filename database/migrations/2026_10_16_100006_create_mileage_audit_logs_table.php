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
        Schema::create('mileage_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mileage_reconciliation_id')->nullable()->constrained('mileage_reconciliations')->cascadeOnDelete();
            $table->foreignId('dispatch_id')->nullable()->constrained('fleet_dispatches')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50)->index();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('remarks')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['mileage_reconciliation_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mileage_audit_logs');
    }
};
