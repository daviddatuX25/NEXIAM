<?php

namespace App\Http\Controllers\Examination;

use App\Examination\Queries\SessionSearchSuggestionQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Examination\SessionSearchSuggestionRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class SessionSearchSuggestionController extends Controller
{
    public function index(SessionSearchSuggestionRequest $request, SessionSearchSuggestionQuery $query): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            return response()->json(['message' => 'Search access is unavailable.'], 403)
                ->header('Cache-Control', 'private, no-store');
        }

        try {
            $data = $query->handle($actor, $request->validated(), $request->headers->get('X-Request-ID'));
        } catch (AuthorizationException) {
            return response()->json(['message' => 'Search access is unavailable.'], 403)
                ->header('Cache-Control', 'private, no-store');
        }

        return response()->json($data)->header('Cache-Control', 'private, no-store');
    }
}
