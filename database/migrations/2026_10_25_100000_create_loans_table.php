<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // money lent to someone: not a wallet, just the person and the state of the debt
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('person');
            $table->string('status')->default('open'); // open | settled | written_off
            $table->decimal('written_off_amount', 15, 2)->default(0);
            $table->date('closed_on')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::table('financial_movements', function (Blueprint $table) {
            $table->foreignId('loan_id')->nullable()->after('category_id')->constrained('loans')->nullOnDelete();
        });

        Schema::table('statement_import_lines', function (Blueprint $table) {
            $table->string('loan_person')->nullable()->after('transfer_wallet_id');
        });
    }

    public function down(): void
    {
        Schema::table('statement_import_lines', fn (Blueprint $table) => $table->dropColumn('loan_person'));
        Schema::table('financial_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('loan_id');
        });
        Schema::dropIfExists('loans');
    }
};
