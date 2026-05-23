<?php

namespace App\Http\Controllers\Web;

use App\Enums\ReportReason;
use App\Http\Controllers\Controller;
use App\Http\Resources\Pet\PetCardResource;
use App\Models\Pet;

class HomeController extends Controller
{
    public function index()
    {
        $pets = PetCardResource::collection(
            Pet::available()
                ->with([
                    'category',
                    'breed',
                    'user',
                    'comments' => fn ($query) => $query
                        ->whereNull('parent_id')
                        ->with([
                            'user',
                            'replies' => fn ($replyQuery) => $replyQuery
                                ->with('user')
                                ->withReportedByCurrentUser(),
                        ])
                        ->withReportedByCurrentUser()
                        ->latest(),
                ])
                ->withCount(['likes', 'comments'])
                ->orderBy('created_at', 'desc')
                ->paginate(12)
        );

        if (request()->wantsJson()) {
            return $pets;
        }

        return inertia('Home', [
            'pets' => $pets,
            'reportReasons' => ReportReason::options(),
        ]);
    }
}
