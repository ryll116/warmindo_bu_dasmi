<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->date('attendance_date');

            $table->dateTime('clock_in')->nullable();
            $table->dateTime('clock_out')->nullable();

            $table->string('status', 20)->default('present');
            $table->text('notes')->nullable();

            $table->timestamps();

            // Satu user hanya memiliki satu absensi per tanggal
            $table->unique(
                ['user_id', 'attendance_date'],
                'attendances_user_date_unique'
            );

            // Membantu filter laporan berdasarkan tanggal
            $table->index('attendance_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
