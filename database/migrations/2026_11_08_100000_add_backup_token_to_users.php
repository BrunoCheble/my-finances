<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // sha256 of the token the scheduled backup uses (the token itself is shown once and never stored)
        if (! Schema::hasColumn('users', 'backup_token_hash')) {
            Schema::table('users', fn (Blueprint $table) => $table->string('backup_token_hash', 64)->nullable()->unique());
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('backup_token_hash'));
    }
};
