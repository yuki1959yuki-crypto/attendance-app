<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stamp_correction_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->datetime('clock_in_time');
            $table->datetime('clock_out_time')->nullable();
            $table->string('comment');
            $table->integer('status')->default(0);
            // 0: pending, 1: approved, 2: rejected
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stamp_correction_requests');
    }
};
