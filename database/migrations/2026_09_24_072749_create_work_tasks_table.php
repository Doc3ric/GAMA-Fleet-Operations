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
        Schema::create('work_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('category', 100)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->string('priority', 20)->default('normal')->index();
            $table->date('due_date')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->text('next_action')->nullable();
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('user_id');
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'due_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_tasks');
    }
};
