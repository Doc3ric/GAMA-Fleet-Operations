<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_type')->default('long_idling')->index();
            $table->date('report_date')->index();
            $table->enum('status', ['draft', 'completed'])->default('draft');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['report_type', 'report_date', 'created_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
