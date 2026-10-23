<?php

namespace App\Http\Controllers\Walkthrough;

use App\Http\Controllers\Controller;
use App\Http\Requests\Walkthrough\StoreWalkthroughProgressRequest;
use App\Models\User;
use App\Walkthrough\Actions\StoreWalkthroughProgress;
use Illuminate\Http\JsonResponse;

class WalkthroughProgressController extends Controller
{
    public function store(
        StoreWalkthroughProgressRequest $request,
        StoreWalkthroughProgress $action,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $result = $action->handle(
            $user,
            (string) $request->session()->get('auth.surface'),
            $request->validated(),
        );

        return response()->json($result);
    }
}
