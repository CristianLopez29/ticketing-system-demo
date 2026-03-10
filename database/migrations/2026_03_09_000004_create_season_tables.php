<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create Seasons table
        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();
        });

        // 2. Add season_id to events
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('season_id')->nullable()->constrained()->nullOnDelete();
        });

        // 3. Create Season Tickets (Abonos) table
        Schema::create('season_tickets', function (Blueprint $table) {
            $table->string('id')->primary(); // UUID
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('row');
            $table->integer('number');
            $table->integer('price_amount');
            $table->string('price_currency');
            $table->string('status'); // pending_payment, paid, cancelled, renewed
            $table->timestamp('expires_at')->nullable(); // For payment deadline
            $table->timestamps();

            // Ensure unique seat per season
            $table->unique(['season_id', 'row', 'number']);
        });

        // 4. Add season_ticket_id to tickets table to link individual tickets to the season ticket
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('season_ticket_id')->nullable();
            $table->foreign('season_ticket_id')->references('id')->on('season_tickets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['season_ticket_id']);
            $table->dropColumn('season_ticket_id');
        });

        Schema::dropIfExists('season_tickets');

        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['season_id']);
            $table->dropColumn('season_id');
        });

        Schema::dropIfExists('seasons');
    }
};
