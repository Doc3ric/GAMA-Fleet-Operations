<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_name', 100);
            $table->string('imei', 50)->nullable()->index();
            $table->string('model', 50)->nullable();
            $table->date('activated_date')->nullable();
            $table->date('sales_time')->nullable();
            $table->string('sim', 50)->nullable();
            $table->date('expiration_date')->nullable()->index();
            $table->string('raw_expiration', 100)->nullable();
            $table->string('group_name', 100)->nullable()->index();
            $table->string('iccid', 50)->nullable();
            $table->string('imsi', 50)->nullable();
            $table->decimal('mileage', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('device_name');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
