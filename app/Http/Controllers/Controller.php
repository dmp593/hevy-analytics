<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;

abstract class Controller
{
    /**
     * Guard against IDOR: ensure a route-bound model belongs to the current user.
     * Call this in every action that receives a model via route-model binding.
     */
    protected function authorizeOwner(Model $model): void
    {
        abort_unless(
            isset($model->user_id) && $model->user_id === request()->user()?->id,
            403,
        );
    }
}
