<?php

namespace App\Http\Controllers\Admission;

use App\Admission\Actions\CreateApplicationOpeningSubscription;
use App\Admission\Actions\ResolveApplicationOpeningToken;
use App\Admission\Queries\CurrentIntakeQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admission\ResolveApplicationOpeningTokenRequest;
use App\Http\Requests\Admission\SubscribeToApplicationOpeningRequest;
use App\Models\Admission\ApplicationOpeningSubscription;
use App\Models\Admission\ApplicationOpeningToken;
use App\Models\Foundation\Institution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class ApplicationOpeningAlertController extends Controller
{
    public function store(
        SubscribeToApplicationOpeningRequest $request,
        CreateApplicationOpeningSubscription $subscribe,
        CurrentIntakeQuery $currentIntake,
    ): JsonResponse {
        abort_unless(config('application-opening-alerts.enabled'), 503, 'Application opening alerts are not available yet.');
        $institution = Institution::current();
        $email = $request->validated('email');
        if ($currentIntake->forInstitution($institution) !== null) {
            $request->session()->put('application_opening_prefill', [
                'email' => $email,
                'expires_at' => now()->addMinutes((int) config('application-opening-alerts.token_handoff_minutes', 15))->timestamp,
            ]);

            return $this->noStore(response()->json([
                'status' => 'open',
                'message' => 'Applications are open. You can start your application now.',
                'apply_url' => '/apply',
            ]));
        }

        $subscribe->handle($institution, $email);

        return $this->noStore(response()->json([
            'status' => 'confirmation_sent',
            'message' => 'If this address can receive application-opening alerts, a confirmation email will be sent shortly.',
            'apply_url' => '/apply',
        ]));
    }

    public function show(string $token, Request $request, ResolveApplicationOpeningToken $resolve): HttpResponse
    {
        abort_unless(config('application-opening-alerts.enabled'), 503);
        $purpose = $request->query('purpose');
        $state = in_array($purpose, ['verify', 'unsubscribe'], true)
            ? $resolve->resolve($token, $purpose, Institution::current())
            : 'invalid';

        return $this->noStore(Inertia::render('Admission/OpeningAlert', [
            'state' => $state,
            'token' => $state === 'invalid' ? '' : $token,
            'purpose' => in_array($purpose, ['verify', 'unsubscribe'], true) ? $purpose : 'verify',
            'message' => $this->message($state),
        ])->toResponse($request));
    }

    public function resolve(
        ResolveApplicationOpeningTokenRequest $request,
        string $token,
        ResolveApplicationOpeningToken $resolve,
    ): HttpResponse {
        abort_unless(config('application-opening-alerts.enabled'), 503);
        $purpose = $request->validated('purpose');
        $state = $resolve->confirm($token, $purpose, Institution::current());
        if ($state === 'open') {
            $subscription = ApplicationOpeningToken::query()->where('token_hash', hash('sha256', $token))
                ->where('purpose', 'verify')->with('subscription')->first()?->subscription;
            if (is_string($subscription?->email)) {
                $request->session()->put('application_opening_prefill', [
                    'email' => $subscription->email,
                    'expires_at' => now()->addMinutes((int) config('application-opening-alerts.token_handoff_minutes', 15))->timestamp,
                ]);
            }
        }

        return $this->noStore(Inertia::render('Admission/OpeningAlert', [
            'state' => $state,
            'token' => '',
            'purpose' => $purpose,
            'message' => $this->message($state),
        ])->toResponse($request));
    }

    public function prefill(string $token, Request $request): RedirectResponse
    {
        abort_unless(config('application-opening-alerts.enabled'), 503);
        $tokenHash = hash('sha256', $token);
        $candidate = ApplicationOpeningToken::query()->where('token_hash', $tokenHash)->where('purpose', 'prefill')
            ->whereNull('consumed_at')->whereNull('superseded_at')->where('expires_at', '>', now())->first();
        abort_unless($candidate !== null, 404);

        $email = DB::transaction(function () use ($candidate, $tokenHash): ?string {
            $subscription = ApplicationOpeningSubscription::query()->whereKey($candidate->subscription_id)->lockForUpdate()->first();
            $current = ApplicationOpeningToken::query()->whereKey($candidate->id)
                ->where('token_hash', $tokenHash)->where('purpose', 'prefill')
                ->whereNull('consumed_at')->whereNull('superseded_at')->where('expires_at', '>', now())
                ->lockForUpdate()->first();
            if ($subscription === null || $current === null
                || (int) $current->generation !== (int) $subscription->generation
                || (int) $subscription->institution_id !== (int) Institution::current()->id
                || $subscription->state !== ApplicationOpeningSubscription::STATE_CONSUMED
                || $subscription->email === null) {
                return null;
            }

            return $subscription->email;
        });
        abort_unless(is_string($email), 404);
        $request->session()->put('application_opening_prefill', [
            'email' => $email,
            'expires_at' => now()->addMinutes((int) config('application-opening-alerts.token_handoff_minutes', 15))->timestamp,
        ]);

        return $this->noStore(redirect('/apply'));
    }

    private function noStore(HttpResponse $response): HttpResponse
    {
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }

    private function message(string $state): string
    {
        return match ($state) {
            'verify' => 'Confirm this email address to receive one email when applications next open.',
            'unsubscribe' => 'Confirm that you no longer want an application-opening email.',
            'confirmed' => 'Your email is confirmed. We will email you when applications next open.',
            'unsubscribed' => 'You are unsubscribed from application-opening alerts.',
            'open' => 'Applications are open now. Start your application; this alert does not submit it.',
            default => 'This link is invalid, expired, or already used.',
        };
    }
}
