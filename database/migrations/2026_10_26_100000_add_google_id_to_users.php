<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // the Google account linked to the user (its "sub"), for "Sign in with Google"
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'google_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('google_id')->nullable()->unique()->after('email');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'google_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique(['google_id']);
                $table->dropColumn('google_id');
            });
        }
    }
};
