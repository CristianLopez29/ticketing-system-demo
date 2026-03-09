<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->foreignId('previous_season_id')->nullable()->constrained('seasons')->nullOnDelete();
            $table->timestamp('renewal_start_date')->nullable();
            $table->timestamp('renewal_end_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->dropForeign(['previous_season_id']);
            $table->dropColumn('previous_season_id');
            $table->dropColumn('renewal_start_date');
            $table->dropColumn('renewal_end_date');
        });
    }
};
