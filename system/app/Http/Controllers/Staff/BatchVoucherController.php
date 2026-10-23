<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\IssueStaffVoucherBatchRequest;
use App\Http\Requests\Staff\PrintStaffVoucherBatchRequest;
use App\Models\Auth\StaffActivationVoucher;
use App\Models\Auth\StaffVoucherBatchRowReceipt;
use App\Models\User;
use App\Reports\Services\VoucherSlipPrintService;
use App\Staffing\Actions\IssueStaffActivationVoucher;
use App\Staffing\Mail\StaffActivationInviteMail;
use App\Staffing\Support\StaffVoucherTargetAuthority;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class BatchVoucherController extends Controller
{
    public function store(IssueStaffVoucherBatchRequest $request, IssueStaffActivationVoucher $issue, StaffVoucherTargetAuthority $authority): JsonResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        $operationId = $data['operation_id'];
        $purpose = $data['purpose'] ?? 'activation';
        $mode = $data['mode'] ?? 'standard_24h';
        $timezone = $data['timezone'] ?? config('app.timezone', 'UTC');
        $payloadRows = [];
        $authorizedTargets = [];
        foreach ($data['targets'] as $targetRow) {
            $target = User::query()->with('staffProfile')->findOrFail($targetRow['id']);
            if (! $authority->permits($actor, $target)) {
                abort(403, 'One or more selected staff accounts are outside your current voucher authority.');
            }
            $authorizedTargets[(int) $target->id] = $target;
        }

        foreach ($data['targets'] as $targetRow) {
            $target = $authorizedTargets[(int) $targetRow['id']];
            $institutionId = (int) $target->staffProfile->institution_id;
            $payloadHash = hash('sha256', json_encode([
                'target_id' => $target->id,
                'institution_id' => $institutionId,
                'purpose' => $purpose,
                'mode' => $mode,
                'custom_expiry' => $data['custom_expiry'] ?? null,
                'timezone' => $timezone,
                'send_email' => (bool) ($data['send_email'] ?? false),
                'replace' => (bool) ($data['replace'] ?? false),
            ], JSON_THROW_ON_ERROR));

            try {
                $row = DB::transaction(function () use ($actor, $target, $institutionId, $operationId, $payloadHash, $purpose, $mode, $data, $timezone, $issue, $authority): array {
                    $lockedTarget = User::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
                    $lockedProfile = $lockedTarget->staffProfile()->lockForUpdate()->first();
                    if ($lockedProfile === null || ! $lockedProfile->isActive() || (int) $lockedProfile->institution_id !== $institutionId) {
                        throw new RuntimeException('The target institution or active staff state changed. Refresh the authorized recipient list.');
                    }
                    $lockedTarget->setRelation('staffProfile', $lockedProfile);
                    if (! $authority->permits($actor, $lockedTarget)) {
                        throw new RuntimeException('Voucher authority changed. Refresh the authorized recipient list.');
                    }
                    $existing = StaffVoucherBatchRowReceipt::query()
                        ->where('actor_user_id', $actor->id)
                        ->where('operation_id', $operationId)
                        ->where('target_user_id', $target->id)
                        ->lockForUpdate()
                        ->first();
                    if ($existing !== null) {
                        if ((int) $existing->institution_id !== $institutionId || ! hash_equals($existing->payload_hash, $payloadHash)) {
                            return ['user_id' => $target->id, 'status' => 'conflict', 'message' => 'This operation ID was already used with different institution or voucher details. Start a new explicit replacement operation.'];
                        }

                        return ['user_id' => $target->id, 'status' => 'already_issued', 'public_reference' => $existing->public_reference, 'message' => 'This row already completed. Its one-time secret cannot be recovered; choose explicit replacement if the reveal was lost.'];
                    }

                    $voucher = $issue->handle(
                        $actor,
                        $lockedTarget,
                        $purpose,
                        $mode,
                        $data['custom_expiry'] ?? null,
                        $timezone,
                        (bool) ($data['replace'] ?? false),
                    );
                    StaffVoucherBatchRowReceipt::query()->create([
                        'actor_user_id' => $actor->id,
                        'operation_id' => $operationId,
                        'target_user_id' => $target->id,
                        'institution_id' => $institutionId,
                        'payload_hash' => $payloadHash,
                        'public_reference' => $voucher['public_reference'],
                        'outcome' => 'issued',
                        'created_at' => now(),
                    ]);

                    return [
                        'user_id' => $target->id,
                        'status' => 'issued',
                        ...$voucher,
                        'expires_at_display' => $voucher['expires_at'] === null
                            ? 'No calendar expiry · Single-use · Revocable'
                            : Carbon::parse($voucher['expires_at'])->setTimezone($timezone)->format('M j, Y g:i A T (e)'),
                        'print_hash' => hash_hmac('sha256', implode(':', [(string) $target->id, $voucher['public_reference'], $voucher['token'], $voucher['fallback']]), (string) config('app.key')),
                    ];
                });

                if ($row['status'] === 'issued' && (bool) ($data['send_email'] ?? false)) {
                    $row['email_queued'] = false;
                    if ($target->email === null || trim($target->email) === '') {
                        $row['email_blocked'] = true;
                        $row['message'] = 'Voucher issued; no email address is on file. Use the private printed slip or have the recipient supply an address during setup.';
                    } else {
                        try {
                            Mail::to($target->email)->queue(new StaffActivationInviteMail(
                                $target,
                                $row['qr_url'],
                                $row['public_reference'],
                                $row['fallback'],
                                $row['expires_at'],
                                $timezone,
                            ));
                            $row['email_queued'] = true;
                            $row['email_message'] = 'Invitation queued for delivery; successful delivery is not confirmed.';
                        } catch (Throwable) {
                            $row['email_queue_failed'] = true;
                            $row['message'] = 'Voucher issued, but the invitation could not be queued. Keep the printed slip private and use an authorized replacement if needed.';
                        }
                    }
                }
                $payloadRows[] = $row;
            } catch (RuntimeException $exception) {
                $replaceRequired = str_contains(strtolower($exception->getMessage()), 'prior voucher exists');
                $payloadRows[] = [
                    'user_id' => $target->id,
                    'status' => $replaceRequired ? 'replace_required' : 'failed',
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return response()->json(['operation_id' => $operationId, 'rows' => $payloadRows])->header('Cache-Control', 'no-store, private');
    }

    public function print(PrintStaffVoucherBatchRequest $request, VoucherSlipPrintService $printService, StaffVoucherTargetAuthority $authority): Response
    {
        $actor = $request->user();
        $cards = [];
        foreach ($request->validated('cards') as $card) {
            $target = User::query()->with(['staffProfile.department', 'roles'])->findOrFail($card['user_id']);
            abort_unless($authority->permits($actor, $target), 403, 'The selected voucher cards are outside your current print authority.');
            $expectedHash = hash_hmac('sha256', implode(':', [(string) $target->id, $card['reference'], $card['token'], $card['fallback']]), (string) config('app.key'));
            abort_unless(hash_equals($expectedHash, $card['hash']), 403, 'A voucher print authorization ticket is invalid.');

            $voucher = StaffActivationVoucher::query()
                ->where('public_reference', $card['reference'])
                ->where('user_id', $target->id)
                ->firstOrFail();
            abort_unless($voucher->isValid() && hash_equals($voucher->token_digest, hash('sha256', $card['token'])), 403, 'A selected voucher is no longer valid.');
            $cards[] = ['user' => $target, 'voucher' => $voucher, 'token' => $card['token'], 'fallback' => $card['fallback']];
        }

        return $printService->renderBatchResponse(
            $cards,
            $request,
            $request->input('format', 'html'),
            $request->input('layout', 'grouped'),
        );
    }
}
