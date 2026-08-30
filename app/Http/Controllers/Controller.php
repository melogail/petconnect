<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Authorization for every controller runs through $this->authorize(), so a
 * policy check is always in the same place: the controller action. Form
 * Requests validate and do not authorize.
 */
abstract class Controller
{
    use AuthorizesRequests;
}
