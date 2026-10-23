<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\BatchProvisionStaffRequest;
use App\Http\Requests\Staff\TransitionStaffCycleDispositionRequest;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\StaffProfile;
use App\Models\User;
use App\Staffing\Actions\BatchProvisionStaff;
use App\Staffing\Actions\TransitionStaffCycleDisposition;
use App\Staffing\Support\StaffRoleCeiling;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class StaffCycleDispositionController extends Controller
{
    public function index(Request $request, StaffRoleCeiling $ceiling): Response
    {
        $actor = $request->user();
        abort_unless($actor !== null, 403);
        abort_unless($ceiling->canManagePersonnel($actor), 403);

        $institutionId = (int) ($actor->staffProfile?->institution_id ?? 0);
        $cycles = AcademicCycle::query()
            ->where('institution_id', $institutionId)
            ->orderByDesc('is_active')
            ->orderByDesc('school_year')
            ->orderByDesc('semester')
            ->get(['id', 'code', 'name', 'school_year', 'semester', 'is_active']);

        $activeCycle = $cycles->firstWhere('is_active', true) ?? $cycles->first();
        $targetCycleCode = $request->input('target_cycle', $activeCycle?->code);

        $programs = Program::query()
            ->active()
            ->where('institution_id', $institutionId)
            ->with('department:id,code,name')
            ->orderBy('code')
            ->get(['id', 'department_id', 'code', 'name']);

        $usersQuery = User::query()
            ->whereHas('staffProfile', fn ($q) => $q
                ->where('institution_id', $institutionId)
                ->where('status', StaffProfile::STATUS_ACTIVE)
            )
            ->with(['roles', 'staffProfile.department']);

        if (! $ceiling->isFullHr($actor)) {
            $actorOffice = $actor->staffProfile?->office;
            $usersQuery->whereHas('staffProfile', fn ($q) => $q->where('office', $actorOffice));
        }

        $personnel = $usersQuery->get()->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'office' => $user->staffProfile?->office,
            'roles' => $user->roles->pluck('name')->all(),
            'program_codes' => $user->staffProfile?->program_codes ?? [],
            'department' => $user->staffProfile?->department?->name,
            'active_cycle_code' => $user->staffProfile?->active_cycle_code,
            'can_manage' => $ceiling->canManageUser($actor, $user),
        ]);

        return Inertia::render('Staff/Personnel/Rollover', [
            'cycles' => $cycles,
            'target_cycle_code' => $targetCycleCode,
            'personnel' => $personnel,
            'programs' => $programs,
            'can_full_hr' => $ceiling->isFullHr($actor),
            'actor_office' => $actor->staffProfile?->office,
        ]);
    }

    public function transition(
        TransitionStaffCycleDispositionRequest $request,
        TransitionStaffCycleDisposition $action,
    ): JsonResponse {
        $actor = $request->user();
        abort_unless($actor !== null, 403);

        $data = $request->validated();
        try {
            $result = $action->handle(
                actor: $actor,
                destinationCycleCode: $data['destination_cycle_code'],
                dispositions: $data['dispositions'],
            );
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            $message = $exception->getMessage();
            foreach ($errors as $messages) {
                if ($messages !== []) {
                    $message = $messages[0];
                    break;
                }
            }

            return response()->json([
                'message' => $message,
                'errors' => $errors,
            ], 422)->header('Cache-Control', 'no-store, private');
        } catch (AuthorizationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 403)->header('Cache-Control', 'no-store, private');
        } catch (ConflictHttpException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409)->header('Cache-Control', 'no-store, private');
        }

        return response()->json($result)->header('Cache-Control', 'no-store, private');
    }

    public function fastProvision(
        BatchProvisionStaffRequest $request,
        BatchProvisionStaff $action,
    ): JsonResponse {
        $actor = $request->user();
        abort_unless($actor !== null, 403);

        $data = $request->validated();
        try {
            $result = $action->handle(
                actor: $actor,
                destinationCycleCode: $data['destination_cycle_code'],
                voucherMode: $data['voucher_mode'],
                operationId: $data['operation_id'],
                recipients: $data['recipients'],
            );
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => $exception->errors(),
            ], 422)->header('Cache-Control', 'no-store, private');
        } catch (AuthorizationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 403)->header('Cache-Control', 'no-store, private');
        }

        return response()->json($result)->header('Cache-Control', 'no-store, private');
    }
}
