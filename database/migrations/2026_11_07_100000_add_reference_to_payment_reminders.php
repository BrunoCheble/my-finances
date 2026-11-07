<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // text found in the description of the movement that pays the reminder
        if (! Schema::hasColumn('payment_reminders', 'reference')) {
            Schema::table('payment_reminders', fn (Blueprint $table) => $table->string('reference')->nullable()->after('amount'));
        }
        // month (Y-m) marked as paid by hand
        if (! Schema::hasColumn('payment_reminders', 'paid_month')) {
            Schema::table('payment_reminders', fn (Blueprint $table) => $table->string('paid_month', 7)->nullable()->after('reference'));
        }
    }

    public function down(): void
    {
        Schema::table('payment_reminders', fn (Blueprint $table) => $table->dropColumn(['reference', 'paid_month']));
    }
};
