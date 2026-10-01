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
        Schema::table('attendances', function (Blueprint $table) {
            $table->dateTime('scheduled_start_at')
                ->nullable()
                ->after('attendance_date');

            $table->dateTime('scheduled_end_at')
                ->nullable()
                ->after('scheduled_start_at');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn([
                'scheduled_start_at',
                'scheduled_end_at',
            ]);
        });
    }
};
