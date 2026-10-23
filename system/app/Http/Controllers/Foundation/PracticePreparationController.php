<?php

namespace App\Http\Controllers\Foundation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Demo\PracticePreparationRequest;
use App\Testing\Actions\PreparePracticeOperation;
use App\Testing\Queries\PracticePreparationQuery;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

final class PracticePreparationController extends Controller
{
    public function index(PracticePreparationRequest $request, PracticePreparationQuery $query): JsonResponse|Response
    {
        $preparation = $query->get($request->user(), $request->validated());

        return $request->expectsJson() ? response()->json($preparation) : Inertia::render('Foundation/PracticePreparation', $preparation);
    }

    public function preview(PracticePreparationRequest $request, PreparePracticeOperation $operations): JsonResponse
    {
        return response()->json($operations->preview($request->user(), $request->validated()));
    }

    public function confirm(PracticePreparationRequest $request, string $operation, PreparePracticeOperation $operations): JsonResponse
    {
        return response()->json($operations->confirm($request->user(), $operation));
    }
}
