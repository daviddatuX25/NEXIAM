<?php

namespace App\Providers;

use App\Models\Applicant;
use App\Models\Application;
use App\Models\AptitudeArea;
use App\Models\AuditLog;
use App\Models\ExamSession;
use App\Models\GradingSession;
use App\Models\RatingScale;
use App\Policies\ApplicationPolicy;
use App\Policies\AptitudeAreaPolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\ExamSessionPolicy;
use App\Policies\GradingSessionPolicy;
use App\Policies\NotificationPolicy;
use App\Policies\RatingScalePolicy;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(AptitudeArea::class, AptitudeAreaPolicy::class);
        Gate::policy(Application::class, ApplicationPolicy::class);
        Gate::policy(ExamSession::class, ExamSessionPolicy::class);
        Gate::policy(GradingSession::class, GradingSessionPolicy::class);
        Gate::policy(RatingScale::class, RatingScalePolicy::class);
        Gate::policy(DatabaseNotification::class, NotificationPolicy::class);

        RedirectIfAuthenticated::redirectUsing(fn () => route('dashboard'));

        RateLimiter::for('login', function (Request $request) {
            if (! config('auth.login_throttle_enabled', true)) {
                return Limit::none();
            }

            $attempts = (int) (config('demo.enabled') && config('demo.throttle_attempts') !== null
                ? config('demo.throttle_attempts')
                : config('auth.login_throttle_attempts', 5));

            // Demo mode: use a seconds-based window so rate-limiting can be
            // demonstrated live without waiting 15 minutes.
            if (config('demo.enabled') && config('demo.throttle_decay_seconds') !== null) {
                $seconds = (int) config('demo.throttle_decay_seconds');

                $decayMinutes = max(1, (int) ceil($seconds / 60));

                return Limit::perMinutes($decayMinutes, $attempts)
                    ->by($request->ip())
                    ->response(fn () => back()->withErrors([
                        'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
                    ]));
            }

            $decayMinutes = (int) config('auth.login_throttle_decay_minutes', 15);

            return Limit::perMinutes($decayMinutes, $attempts)
                ->by($request->ip())
                ->response(fn () => back()->withErrors([
                    'email' => "Too many login attempts. Please try again in {$decayMinutes} minutes.",
                ]));
        });

        // AI Companion chat rate limit: 10 requests per minute per applicant
        RateLimiter::for('ai-companion', function (Request $request) {
            /** @var Applicant|null $applicant */
            $applicant = $request->user();

            $key = $applicant
                ? 'ai-companion:'.$applicant->id
                : 'ai-companion:'.$request->ip();

            return Limit::perMinute(10)
                ->by($key)
                ->response(fn () => response()->json([
                    'message' => 'Too many requests. Please wait a moment.',
                    'retry_after' => 60,
                ], 429));
        });

        // AI Companion clear-history rate limit: 5 requests per minute
        RateLimiter::for('ai-companion-clear', function (Request $request) {
            return Limit::perMinute(5)
                ->by('ai-companion-clear:'.($request->user()?->id ?? $request->ip()))
                ->response(fn () => response()->json([
                    'message' => 'Too many requests. Please wait a moment.',
                    'retry_after' => 60,
                ], 429));
        });
    }
}
