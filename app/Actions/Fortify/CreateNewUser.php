<?php

namespace App\Actions\Fortify;

use App\Actions\Users\RegisterUser;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Fortify's entry point for registration: validate the raw input, then hand the
 * write to the application's own Action.
 *
 * The validation cannot move into a Form Request — Fortify's CreatesNewUsers
 * contract hands this an array, not a request — so it stays here, built from
 * the same Concerns the profile form uses so the two cannot drift.
 *
 * Everything after the validator is Actions\Users\RegisterUser's, including the
 * bounded retry on a `media_directory_name` collision. What is *not* here, and
 * deliberately: dispatching Registered and sending the verification email.
 * Fortify's RegisteredUserController dispatches Registered around this call and
 * Laravel's SendEmailVerificationNotification listener turns that into the
 * mail, so doing either here would double it. See RegisterUser's docblock.
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private readonly RegisterUser $registerUser) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return $this->registerUser->handle([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);
    }
}
