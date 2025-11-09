<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('financial_balances', function (Blueprint $table) {
            $table->index('start_date', 'idx_financial_balances_start_date');
            $table->index('end_date', 'idx_financial_balances_end_date');
        });

        Schema::table('financial_movements', function (Blueprint $table) {
            $table->index('date', 'idx_financial_movements_date');
        });
    }

    public function down(): void
    {
        Schema::table('financial_balances', function (Blueprint $table) {
            $table->dropIndex('idx_financial_balances_start_date');
            $table->dropIndex('idx_financial_balances_end_date');
        });

        Schema::table('financial_movements', function (Blueprint $table) {
            $table->dropIndex('idx_financial_movements_date');
        });
    }
};
