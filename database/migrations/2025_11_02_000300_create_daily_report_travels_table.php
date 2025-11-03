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
        Schema::create('daily_report_travels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_report_id')->constrained('daily_reports')->cascadeOnDelete();
            $table->enum('direction', ['outbound', 'return'])->index();
            $table->time('depart_time')->nullable();
            $table->time('arrive_time')->nullable();
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->boolean('use_highway')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_report_travels');
    }
};
