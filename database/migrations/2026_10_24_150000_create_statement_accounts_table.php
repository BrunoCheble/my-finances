<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // remembers which wallet a bank account maps to; the account number
        // itself is not stored, only a hash and its last digits for display
        if (! Schema::hasTable('statement_accounts')) Schema::create('statement_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('bank');
            $table->string('account_hash', 64);
            $table->string('account_last4', 4);
            $table->foreignId('wallet_id')->constrained('wallets');
            $table->timestamps();
            $table->unique(['user_id', 'bank', 'account_hash']);
        });

        if (! Schema::hasColumn('statement_imports', 'account_last4')) {
            Schema::table('statement_imports', function (Blueprint $table) {
                $table->string('account_last4', 4)->nullable()->after('bank');
            });
        }
    }

    public function down(): void
    {
        Schema::table('statement_imports', function (Blueprint $table) {
            $table->dropColumn('account_last4');
        });
        Schema::dropIfExists('statement_accounts');
    }
};
