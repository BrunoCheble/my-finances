<?php

namespace App\Models;

use App\Traits\HasUserScope;
use Illuminate\Database\Eloquent\Model;

/**
 * A movement created, changed or deleted in a month whose balance was already confirmed.
 */
class MovementChange extends Model
{
    use HasUserScope;

    protected $fillable = ['wallet_id', 'month', 'movement_id', 'action', 'description', 'date', 'old_amount', 'new_amount'];
}
