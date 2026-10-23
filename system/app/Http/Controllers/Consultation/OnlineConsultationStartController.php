<?php

declare(strict_types=1);

namespace App\Http\Controllers\Consultation;

use App\Consultation\Actions\StartOnlineConsultation;
use App\Http\Controllers\Controller;
use App\Models\Admission\AdmissionAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OnlineConsultationStartController extends Controller
{
    public function __invoke(Request $request, AdmissionAttempt $attempt, StartOnlineConsultation $action): JsonResponse
    {
        $validated = $request->validate([
            'idempotency_key' => ['required', 'uuid'],
        ]);

        return response()->json($action->handle($attempt, $request->user(), $validated['idempotency_key']));
    }
}
