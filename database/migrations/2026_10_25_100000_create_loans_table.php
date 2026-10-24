<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // money lent to someone: not a wallet, just the person and the state of the debt
        // each step checks what is there: a run that failed halfway on MySQL leaves part of it behind
        if (! Schema::hasTable('loans')) Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('person');
            $table->string('status')->default('open'); // open | settled | written_off
            $table->decimal('written_off_amount', 15, 2)->default(0);
            $table->date('closed_on')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        if (! Schema::hasColumn('financial_movements', 'loan_id')) {
            Schema::table('financial_movements', function (Blueprint $table) {
                $table->foreignId('loan_id')->nullable()->after('category_id')->constrained('loans')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('statement_import_lines', 'loan_person')) {
            Schema::table('statement_import_lines', function (Blueprint $table) {
                $table->string('loan_person')->nullable()->after('transfer_wallet_id');
            });
        }
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
