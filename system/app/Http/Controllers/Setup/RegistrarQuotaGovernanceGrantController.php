<?php

namespace App\Http\Controllers\Setup;

use App\Admission\Actions\ManageRegistrarQuotaGovernanceGrant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\GovernanceCommandRequest;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;
use RuntimeException;

final class RegistrarQuotaGovernanceGrantController extends Controller
{
    public function issue(GovernanceCommandRequest $request, Program $program, AcademicCycle $cycle, ManageRegistrarQuotaGovernanceGrant $action): JsonResponse|RedirectResponse
    {
        return $this->change($request, $program, $cycle, $action, 'issue');
    }

    public function revoke(GovernanceCommandRequest $request, Program $program, AcademicCycle $cycle, ManageRegistrarQuotaGovernanceGrant $action): JsonResponse|RedirectResponse
    {
        return $this->change($request, $program, $cycle, $action, 'revoke');
    }

    private function change(GovernanceCommandRequest $request, Program $program, AcademicCycle $cycle, ManageRegistrarQuotaGovernanceGrant $action, string $operation): JsonResponse|RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        abort_unless((int) $program->institution_id === (int) $cycle->institution_id, 404);
        $values = $request->validated();
        $allowed = ['recipient_id', 'expected_version', 'reason', 'idempotency_key'];
        if (array_diff(array_keys($values), $allowed) !== []) {
            return response()->json(['status' => 'invalid', 'message' => 'Unexpected request field.'], 422);
        }
        foreach ($allowed as $key) {
            if (! array_key_exists($key, $values)) {
                return response()->json(['status' => 'invalid', 'message' => "Missing required field: {$key}"], 422);
            }
        }
        $recipient = User::query()->findOrFail($values['recipient_id']);
        try {
            $grant = $action->{$operation}($actor, $recipient, $program, $cycle, $values['expected_version'], $values['reason'], $values['idempotency_key']);
        } catch (AuthorizationException $exception) {
            if ($request->header('X-Inertia')) {
                return back()->withErrors(['reason' => $exception->getMessage()]);
            }

            return response()->json(['status' => 'denied', 'message' => $exception->getMessage()], 403);
        } catch (InvalidArgumentException $exception) {
            if ($request->header('X-Inertia')) {
                return back()->withErrors(['reason' => $exception->getMessage()]);
            }

            return response()->json(['status' => 'invalid', 'message' => $exception->getMessage()], 422);
        } catch (RuntimeException $exception) {
            if ($request->header('X-Inertia')) {
                return back()->withErrors(['reason' => $exception->getMessage()]);
            }

            $message = strtolower($exception->getMessage());
            $conflict = str_contains($message, 'stale') || str_contains($message, 'conflict') || str_contains($message, 'already exists') || str_contains($message, 'different request payload') || str_contains($message, 'version');

            return response()->json(['status' => $conflict ? 'conflict' : 'invalid', 'message' => $exception->getMessage()], $conflict ? 409 : 422);
        }

        if ($request->header('X-Inertia')) {
            $operationLabel = $operation === 'revoke' ? 'revoked' : 'granted';

            return back()->with('status', "Registrar quota access {$operationLabel} successfully.");
        }

        return response()->json(['status' => 'success', 'grant' => $grant->toArray()]);
    }
}
