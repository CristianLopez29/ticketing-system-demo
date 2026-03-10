<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->string('row');
            $table->integer('number');
            $table->integer('price_amount'); // cents
            $table->string('price_currency');
            $table->unsignedBigInteger('reserved_by_user_id')->nullable();

            $table->unique(['event_id', 'row', 'number']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seats');
    }
};
