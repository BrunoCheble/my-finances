<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_movements', function (Blueprint $table) {
            $table->index(['date', 'wallet_id'], 'idx_financial_movements_date_wallet');
        });
    }

    public function down(): void
    {
        Schema::table('financial_movements', function (Blueprint $table) {
            $table->dropIndex('idx_financial_movements_date_wallet');
        });
    }

};
