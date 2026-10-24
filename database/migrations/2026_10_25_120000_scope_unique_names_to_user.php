<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // names were unique across all users; two users could not have a "Supermarket" each
    public function up(): void
    {
        Schema::table('financial_categories', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->unique(['user_id', 'name']);
        });
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->unique(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('financial_categories', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'name']);
            $table->unique('name');
        });
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'name']);
            $table->unique('name');
        });
    }
};
