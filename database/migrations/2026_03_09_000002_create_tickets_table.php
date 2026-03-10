<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seat_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('price_amount');
            $table->string('price_currency');
            $table->string('payment_reference');
            $table->timestamp('issued_at');
            $table->timestamps();

            $table->unique('seat_id'); // One ticket per seat
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
