<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a composite index on reservations(status, expires_at, id).
     *
     * This index optimises the cursor-based cleanup query:
     *
     *   SELECT * FROM reservations
     *   WHERE  status = 'pending_payment'
     *     AND  expires_at <= ?
     *     AND  (created_at > ? OR (created_at = ? AND id > ?))
     *   ORDER BY created_at ASC, id ASC
     *   LIMIT ?
     *
     * The (status, expires_at) prefix filters the eligible rows quickly,
     * while the id suffix helps the engine cover the ORDER BY without a
     * full table-scan.
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // Drop the narrower index added in the original migration to avoid
            // redundancy; the new composite index supersedes it.
            $table->dropIndex(['status', 'expires_at']);

            $table->index(['status', 'expires_at', 'id'], 'reservations_status_expires_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex('reservations_status_expires_id_idx');

            // Restore the original narrower index
            $table->index(['status', 'expires_at']);
        });
    }
};
