<?php

namespace App\Models;

use App\Traits\HasUserScope;
use Illuminate\Database\Eloquent\Model;

/**
 * A browser/device that accepted push notifications (Web Push subscription).
 */
class PushSubscription extends Model
{
    use HasUserScope;

    protected $fillable = ['user_id', 'endpoint', 'p256dh', 'auth'];
}
