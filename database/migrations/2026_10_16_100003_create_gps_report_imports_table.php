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
        Schema::create('gps_report_imports', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->integer('total_rows')->default(0);
            $table->integer('matched_rows')->default(0);
            $table->integer('unmatched_rows')->default(0);
            $table->integer('duplicate_rows')->default(0);
            $table->string('status', 20)->default('COMMITTED'); // 'PREVIEW', 'COMMITTED', 'FAILED'
            $table->json('import_summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gps_report_imports');
    }
};
