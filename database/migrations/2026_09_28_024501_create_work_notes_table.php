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
        Schema::create('work_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 255);
            $table->text('content')->nullable();
            $table->boolean('is_pinned')->default(false)->index();
            $table->string('color', 20)->default('slate');
            $table->timestamps();

            $table->index('user_id');
            $table->index(['user_id', 'is_pinned']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_notes');
    }
};
