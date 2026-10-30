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
        Schema::create('mileage_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mileage_reconciliation_id')->constrained('mileage_reconciliations')->cascadeOnDelete();
            $table->string('evidence_type', 50)->default('odometer_photo'); // 'odometer_photo', 'verified_reading_form', 'gps_report', 'other'
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('evidence_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mileage_evidences');
    }
};
