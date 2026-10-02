<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_sessions', function (Blueprint $table) {
            $table->id();

            // Shift yang melakukan inventory
            $table->string('shift_type', 20);

            // Tanggal operasional inventory
            $table->date('session_date');

            // User yang membuka session
            $table->foreignId('opened_by')
                ->constrained('users')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // User yang menutup session
            $table->foreignId('closed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->dateTime('opened_at')->nullable();
            $table->dateTime('closed_at')->nullable();

            // open / closed
            $table->string('status', 20)->default('open');

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['session_date', 'shift_type']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_sessions');
    }
};