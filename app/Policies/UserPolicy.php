<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\User;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Nova;

class UserPolicy extends Policy
{
    public function update(User $auth, $model): bool
    {
        return Nova::whenServing(function (NovaRequest $request) use ($auth) {
            if ($auth instanceof Admin) {
                return true;
            }
        }, function () use ($auth, $model) {
            return $auth->id === $model->id;
        });
    }

    public function delete(User $auth, $model): bool
    {
        return Nova::whenServing(function (NovaRequest $request) use ($auth) {
            if ($auth instanceof Admin) {
                return true;
            }
        }, function () use ($auth, $model) {
            return $auth->id === $model->id;
        });
    }
}
