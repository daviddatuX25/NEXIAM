<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\IssueStaffVoucherRequest;
use App\Models\Auth\StaffActivationVoucher;
use App\Models\User;
use App\Reports\Services\VoucherSlipPrintService;
use App\Staffing\Actions\IssueStaffActivationVoucher;
use App\Staffing\Actions\ReissueStaffActivationVoucher;
use App\Staffing\Actions\RevokeStaffActivationVoucher;
use App\Staffing\Mail\StaffActivationInviteMail;
use App\Staffing\Support\StaffVoucherTargetAuthority;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class VoucherController extends Controller
{
    public function store(IssueStaffVoucherRequest $request, User $user, IssueStaffActivationVoucher $issue, StaffVoucherTargetAuthority $authority): JsonResponse
    {
        return $this->issueFor($request, $user, $issue, $authority);
    }

    public function reissue(IssueStaffVoucherRequest $request, User $user, ReissueStaffActivationVoucher $reissue, StaffVoucherTargetAuthority $authority): JsonResponse
    {
        abort_unless($request->boolean('confirm_replace'), 422, 'Confirm that the previous voucher and any in-progress attempt will be invalidated before replacement.');

        return $this->issueFor($request, $user, $reissue, $authority);
    }

    public function destroy(Request $request, User $user, StaffActivationVoucher $voucher, RevokeStaffActivationVoucher $revoke, StaffVoucherTargetAuthority $authority): JsonResponse
    {
        abort_unless($authority->permits($request->user(), $user), 403, 'You are not authorized to revoke a voucher for this staff account.');
        abort_unless((int) $voucher->user_id === (int) $user->id, 404);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        try {
            $revoked = $revoke->handle($request->user(), $user, $voucher, $data['reason'] ?? null);
        } catch (RuntimeException $exception) {
            abort(403, $exception->getMessage());
        }

        return response()->json(['revoked' => $revoked->revoked_at !== null])->header('Cache-Control', 'no-store, private');
    }

    public function printSlip(Request $request, User $user, VoucherSlipPrintService $printService, StaffVoucherTargetAuthority $authority): Response
    {
        abort_unless($authority->permits($request->user(), $user), 403, 'You are not authorized to print a voucher for this staff account.');
        $data = $request->validate([
            'reference' => ['required', 'string', 'size:10'],
            'token' => ['required', 'string', 'regex:/^[a-f0-9]{32}$/D'],
            'hash' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/D'],
            'fallback' => ['required', 'string', 'regex:/^[A-Z2-7]{4}-[A-Z2-7]{4}$/D'],
            'format' => ['sometimes', 'string', Rule::in(['html', 'pdf'])],
            'embedded' => ['sometimes', 'boolean'],
        ]);
        $expectedHash = hash_hmac('sha256', implode(':', [(string) $user->id, $data['reference'], $data['token'], $data['fallback']]), (string) config('app.key'));
        abort_unless(hash_equals($expectedHash, $data['hash']), 403, 'Invalid print authorization ticket.');
        $voucher = StaffActivationVoucher::query()
            ->where('public_reference', $data['reference'])
            ->where('user_id', $user->id)
            ->firstOrFail();
        abort_unless($voucher->isValid(), 403, 'This voucher is expired, revoked, or already redeemed.');
        abort_unless(hash_equals($voucher->token_digest, hash('sha256', $data['token'])), 403, 'Token verification failed.');

        return $printService->renderResponse($user, $voucher, $data['token'], $request, $data['fallback']);
    }

    private function issueFor(IssueStaffVoucherRequest $request, User $user, IssueStaffActivationVoucher|ReissueStaffActivationVoucher $action, StaffVoucherTargetAuthority $authority): JsonResponse
    {
        abort_unless($authority->permits($request->user(), $user), 403, 'You are not authorized to issue a voucher for this staff account.');
        $data = $request->validated();
        $purpose = $data['purpose'] ?? 'activation';
        $mode = $data['mode'] ?? 'standard_24h';
        $timezone = $data['timezone'] ?? config('app.timezone', 'UTC');

        try {
            $voucher = $action->handle($request->user(), $user, $purpose, $mode, $data['custom_expiry'] ?? null, $timezone);
        } catch (RuntimeException $exception) {
            abort(403, $exception->getMessage());
        }
        $voucher['expires_at_display'] = $voucher['expires_at'] === null
            ? 'No calendar expiry · Single-use · Revocable'
            : Carbon::parse($voucher['expires_at'])->setTimezone($timezone)->format('M j, Y g:i A T (e)');

        if ((bool) ($data['send_email'] ?? false)) {
            $voucher['email_queued'] = false;
            if ($user->email === null || trim($user->email) === '') {
                $voucher['email_blocked'] = true;
                $voucher['delivery_message'] = 'No email address is on file. Use the private printed slip; the recipient can choose an address during setup.';
            } else {
                try {
                    Mail::to($user->email)->queue(new StaffActivationInviteMail(
                        $user,
                        $voucher['qr_url'],
                        $voucher['public_reference'],
                        $voucher['fallback'],
                        $voucher['expires_at'],
                        $timezone,
                    ));
                    $voucher['email_queued'] = true;
                    $voucher['delivery_message'] = 'Invitation queued for delivery; successful delivery is not confirmed.';
                } catch (Throwable) {
                    $voucher['email_queue_failed'] = true;
                    $voucher['delivery_message'] = 'Voucher issued, but the invitation could not be queued. Keep the printed slip private and use an authorized replacement if needed.';
                }
            }
        }
        $voucher['print_url'] = route('staff.personnel.vouchers.print', ['user' => $user->id]);
        $voucher['print_hash'] = hash_hmac('sha256', implode(':', [(string) $user->id, $voucher['public_reference'], $voucher['token'], $voucher['fallback']]), (string) config('app.key'));

        return response()->json($voucher)->header('Cache-Control', 'no-store, private');
    }
}
