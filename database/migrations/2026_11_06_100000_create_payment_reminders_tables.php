<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // a payment that comes every month on the same day, notified by push on that morning
        if (! Schema::hasTable('payment_reminders')) Schema::create('payment_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('name');
            $table->unsignedTinyInteger('day');
            $table->decimal('amount', 15, 2)->nullable();
            $table->date('last_sent_on')->nullable();
            $table->timestamps();
        });

        // a browser/device that accepted notifications
        if (! Schema::hasTable('push_subscriptions')) Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('endpoint', 500)->unique();
            $table->string('p256dh');
            $table->string('auth');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('payment_reminders');
    }
};
