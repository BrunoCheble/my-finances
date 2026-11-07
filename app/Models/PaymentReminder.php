<?php

namespace App\Models;

use App\Traits\HasUserScope;
use Illuminate\Database\Eloquent\Model;

/**
 * A payment due every month on the same day; a push notification is sent that morning.
 */
class PaymentReminder extends Model
{
    use HasUserScope;

    protected $fillable = ['name', 'day', 'amount', 'last_sent_on'];

    protected $casts = ['amount' => 'decimal:2', 'last_sent_on' => 'date'];
}
