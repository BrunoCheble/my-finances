<?php
// app/Traits/HasUserScope.php
namespace App\Traits;

use App\Scopes\UserScope;

trait HasUserScope
{
    protected static function bootHasUserScope()
    {
        static::addGlobalScope(new UserScope);

        static::creating(function ($model) {
            if (auth()->check() && !$model->user_id) {
                $model->user_id = auth()->id();
            }
        });
    }
}
