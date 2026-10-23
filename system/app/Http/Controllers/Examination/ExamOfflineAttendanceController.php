<?php

namespace App\Http\Controllers\Examination;

use App\Examination\Actions\SyncOfflineAttendance;
use App\Http\Controllers\Controller;
use App\Http\Requests\Examination\SyncOfflineAttendanceRequest;
use App\Models\Examination\ExamSession;
use DomainException;
use Illuminate\Http\JsonResponse;

class ExamOfflineAttendanceController extends Controller
{
    public function __invoke(SyncOfflineAttendanceRequest $request, ExamSession $session, SyncOfflineAttendance $sync): JsonResponse
    {
        try {
            return response()->json($sync->handle($session, $request->user(), $request->validated()));
        } catch (DomainException $exception) {
            return response()->json(['status' => 'conflict', 'message' => $exception->getMessage()], 409);
        }
    }
}
