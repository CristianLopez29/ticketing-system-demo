<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds soft-deletes to users and changes user_id foreign keys
 * in tickets, reservations and season_tickets from cascadeOnDelete
 * to nullOnDelete, also making those columns nullable.
 *
 * NOTE: MySQL requires DROPping a FK before modifying the column it
 * references or before re-creating it with different behaviour.
 * Laravel's default FK name pattern: {table}_{column}_foreign
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Add deleted_at to users
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });

        // 2. tickets.user_id → nullOnDelete + nullable
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        // 3. reservations.user_id → nullOnDelete + nullable
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        // 4. season_tickets.user_id → nullOnDelete + nullable
        Schema::table('season_tickets', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Reverse season_tickets
        Schema::table('season_tickets', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // Reverse reservations
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // Reverse tickets
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // Remove soft deletes from users
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
