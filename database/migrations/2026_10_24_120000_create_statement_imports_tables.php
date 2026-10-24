<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statement_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('wallet_id')->constrained('wallets');
            $table->string('bank');
            $table->string('file_name');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->decimal('opening_balance', 15, 2)->nullable();
            $table->decimal('closing_balance', 15, 2)->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('statement_import_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('statement_import_id')->constrained('statement_imports')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->date('date');
            $table->date('value_date')->nullable();
            $table->string('description');
            $table->string('normalized_description');
            $table->decimal('amount', 15, 2);
            $table->decimal('bank_balance', 15, 2)->nullable();
            $table->boolean('balance_mismatch')->default(false);
            $table->string('type');
            $table->foreignId('category_id')->nullable()->constrained('financial_categories');
            $table->foreignId('transfer_wallet_id')->nullable()->constrained('wallets');
            $table->boolean('is_new')->default(true);
            $table->string('history_match')->nullable(); // exact | similar
            $table->string('history_description')->nullable();
            $table->unsignedInteger('history_count')->default(0);
            $table->date('history_last_date')->nullable();
            $table->foreignId('duplicate_movement_id')->nullable()->constrained('financial_movements')->nullOnDelete();
            $table->boolean('selected')->default(true);
            $table->foreignId('financial_movement_id')->nullable()->constrained('financial_movements')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statement_import_lines');
        Schema::dropIfExists('statement_imports');
    }
};
