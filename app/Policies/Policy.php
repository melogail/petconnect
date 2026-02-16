<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Nova;

abstract class Policy
{

    public function viewAny(Authenticatable $auth)
    {
        return Nova::whenServing(function (NovaRequest $request) use ($auth) {
            if ($auth instanceof Admin) {
                return true;
            }
        }, function () {
            return false;
        });
    }

    public function view($auth, $model)
    {
        return Nova::whenServing(function (NovaRequest $request) use ($auth, $model) {
            if ($auth instanceof Admin) {
                return true;
            }
        }, function () {
            return false;
        });
    }

    public function create($auth)
    {
        return Nova::whenServing(function (NovaRequest $request) use ($auth) {
            if ($auth instanceof Admin) {
                return true;
            }
        }, function () {
            return false;
        });
    }

    public function update($auth, $model)
    {
        return Nova::whenServing(function (NovaRequest $request) use ($auth, $model) {
            if ($auth instanceof Admin) {
                return true;
            }
        }, function () use ($auth, $model) {
            return $auth->id === $model->user_id;
        });
    }

    public function delete($auth, $model)
    {
        return Nova::whenServing(function (NovaRequest $request) use ($auth, $model) {
            if ($auth instanceof Admin) {
                return true;
            }
        }, function () use ($auth, $model) {
            return $auth->id === $model->user_id;
        });
    }

    public function restore($auth, $model)
    {
        return Nova::whenServing(function (NovaRequest $request) use ($auth, $model) {
            if ($auth instanceof Admin) {
                return true;
            }
        }, function () use ($auth, $model) {
            return $auth->id === $model->user_id;
        });
    }

    public function forceDelete($auth, $model)
    {
        return Nova::whenServing(function (NovaRequest $request) use ($auth, $model) {
            if ($auth instanceof Admin) {
                return true;
            }
        }, function () use ($auth, $model) {
            return $auth->id === $model->user_id;
        });
    }

}
