<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // also notify the day before
        if (! Schema::hasColumn('payment_reminders', 'notify_before')) {
            Schema::table('payment_reminders', fn (Blueprint $table) => $table->boolean('notify_before')->default(false)->after('reference'));
        }
    }

    public function down(): void
    {
        Schema::table('payment_reminders', fn (Blueprint $table) => $table->dropColumn('notify_before'));
    }
};
