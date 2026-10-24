<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // the closing balance the user confirmed for a wallet in a month
        if (! Schema::hasColumn('financial_balances', 'reconciled_balance')) {
            Schema::table('financial_balances', function (Blueprint $table) {
                $table->decimal('reconciled_balance', 15, 2)->nullable()->after('calculated_balance');
                $table->timestamp('reconciled_at')->nullable()->after('reconciled_balance');
                $table->string('reconciled_source')->nullable()->after('reconciled_at'); // manual | statement
            });
        }

        // changes made to movements of months that were already confirmed
        if (! Schema::hasTable('movement_changes')) Schema::create('movement_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->string('month', 7);
            $table->unsignedBigInteger('movement_id')->nullable();
            $table->string('action'); // created | updated | deleted
            $table->string('description');
            $table->date('date');
            $table->decimal('old_amount', 15, 2)->nullable();
            $table->decimal('new_amount', 15, 2)->nullable();
            $table->timestamps();
            $table->index(['wallet_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movement_changes');
        Schema::table('financial_balances', function (Blueprint $table) {
            $table->dropColumn(['reconciled_balance', 'reconciled_at', 'reconciled_source']);
        });
    }
};
