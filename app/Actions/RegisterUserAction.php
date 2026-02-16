<?php

namespace App\Actions;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class RegisterUserAction
{
    public function execute(Request $request): User
    {
        try {
            return $this->createUser($request);
        } catch (QueryException $e) {
            if ($this->isMediaDirectoryCollision($e)) {
                return $this->execute($request);
            }
            throw $e;
        }
    }

    public function createUser(Request $request)
    {
        return User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);
    }

    /**
     * Check if the exception is a media directory collision
     * @param QueryException $e
     * @return bool
     */
    public function isMediaDirectoryCollision(QueryException $e): bool
    {
        return $e->getCode() == '23000'
            && str_contains($e->getMessage(), 'media_directory_name');
    }
}
