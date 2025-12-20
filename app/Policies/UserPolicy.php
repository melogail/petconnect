<?php

namespace App\Policies;
use App\Models\User;
use App\Models\Admin;
use Laravel\Nova\Nova;
use Laravel\Nova\Http\Requests\NovaRequest;

class UserPolicy extends Policy
{
    public function update($auth, $model)
    {
        return Nova::whenServing(function (NovaRequest $request) use ($auth, $model) {
            if ($auth instanceof Admin) {
                return true;
            }
        }, function () use ($auth, $model) {
            return $auth->id === $model->id;
        });
    }

    public function delete($auth, $model)
    {
        return Nova::whenServing(function (NovaRequest $request) use ($auth, $model) {
            if ($auth instanceof Admin) {
                return true;
            }
        }, function () use ($auth, $model) {
            return $auth->id === $model->id;
        });
    }
}
