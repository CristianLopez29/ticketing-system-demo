<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('event_id')->constrained();
            $table->foreignId('seat_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('status'); // pending_payment, paid, cancelled
            $table->integer('price_amount');
            $table->string('price_currency');
            $table->timestamp('expires_at');
            $table->timestamps();

            // Index for finding expired reservations
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
