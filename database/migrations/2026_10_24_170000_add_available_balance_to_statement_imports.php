<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statement_imports', function (Blueprint $table) {
            $table->decimal('available_balance', 15, 2)->nullable()->after('closing_balance');
        });
    }

    public function down(): void
    {
        Schema::table('statement_imports', function (Blueprint $table) {
            $table->dropColumn('available_balance');
        });
    }
};
