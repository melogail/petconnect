<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Options\BuildClientOptions;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Enum labels, validation bounds and locales, fetched once at app launch.
 * Public: a guest browsing the feed needs the listing-type labels too.
 */
class OptionController extends Controller
{
    public function index(BuildClientOptions $buildClientOptions): JsonResponse
    {
        return response()->json($buildClientOptions->handle());
    }
}
