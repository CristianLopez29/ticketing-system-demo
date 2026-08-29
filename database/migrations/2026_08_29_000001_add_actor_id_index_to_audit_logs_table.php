<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // debt: audit_logs has no retention/pruning job yet — revisit once row count starts
    // affecting this index's usefulness or storage becomes a concern.
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('actor_id');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['actor_id']);
        });
    }
};
