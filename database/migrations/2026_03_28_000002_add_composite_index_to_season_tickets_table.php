<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('season_tickets', function (Blueprint $table): void {
            // Composite index to speed up queries that filter by user and season,
            // used by findAllByUserAndSeason (subscription checks) and findAndLockBySeasonAndSeat
            // (renewal ownership verification inside transactions).
            $table->index(['user_id', 'season_id'], 'season_tickets_user_season_idx');
        });
    }

    public function down(): void
    {
        Schema::table('season_tickets', function (Blueprint $table): void {
            $table->dropIndex('season_tickets_user_season_idx');
        });
    }
};
