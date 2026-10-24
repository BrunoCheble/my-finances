<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // names were unique across all users; two users could not have a "Supermarket" each
    public function up(): void
    {
        // only what is there: databases made by hand may not have the old index (or already have the new one)
        foreach (['financial_categories', 'wallets'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                if (Schema::hasIndex($name, "{$name}_name_unique")) {
                    $table->dropUnique(['name']);
                }
                if (! Schema::hasIndex($name, "{$name}_user_id_name_unique")) {
                    $table->unique(['user_id', 'name']);
                }
            });
        }
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
