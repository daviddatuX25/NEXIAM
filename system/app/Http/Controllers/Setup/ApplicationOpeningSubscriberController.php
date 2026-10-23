<?php

namespace App\Http\Controllers\Setup;

use App\Admission\Queries\ListApplicationOpeningSubscribersQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\ListApplicationOpeningSubscribersRequest;
use App\Models\Foundation\Institution;
use Illuminate\Http\JsonResponse;

class ApplicationOpeningSubscriberController extends Controller
{
    public function index(
        ListApplicationOpeningSubscribersRequest $request,
        ListApplicationOpeningSubscribersQuery $query,
    ): JsonResponse {
        $rows = $query->handle($request->user(), Institution::current(), $request->validated());

        return response()->json([
            'data' => $rows->items(),
            'current_page' => $rows->currentPage(),
            'last_page' => $rows->lastPage(),
            'total' => $rows->total(),
        ])->header('Cache-Control', 'private, no-store');
    }
}
