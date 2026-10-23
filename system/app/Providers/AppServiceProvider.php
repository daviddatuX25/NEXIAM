<?php

namespace App\Providers;

use App\Admission\Actions\ObserveApplicationOpeningAvailability;
use App\Admission\Support\ApplicantOfficeInquiryAccess;
use App\Ai\Adapters\Fake\FakeAiGateway;
use App\Ai\Adapters\LaravelAi\LaravelAiGateway;
use App\Ai\Adapters\Local\LocalKnowledgeIndexPort;
use App\Ai\Connectors\ConnectorCatalog;
use App\Ai\Contracts\AiGateway;
use App\Ai\Contracts\KnowledgeIndexPort;
use App\Ai\Gateway\AssistantToolCatalog;
use App\Assessment\Omr\Diagnostics\OmrPhysicalChallengeRenderer;
use App\Assessment\Omr\Diagnostics\OmrPhysicalChallengeRendererPort;
use App\Assessment\Omr\FakeOmrDetection;
use App\Assessment\Omr\OmrCaptureAnalysisPipeline;
use App\Assessment\Omr\OmrCaptureAnalysisPort;
use App\Assessment\Omr\OmrCaptureOrientationPort;
use App\Assessment\Omr\OmrCheckerDetection;
use App\Assessment\Omr\OmrDetectionPort;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\OmrLayoutVerificationPort;
use App\Assessment\Omr\Verification\OmrPhysicalQrOrientation;
use App\Assessment\Omr\Verification\OmrVerificationRunService;
use App\Jobs\Admission\ReconcileApplicationOpeningAlertsJob;
use App\Models\Communications\PublicPolicy;
use App\Models\Communications\PublicPolicyRevision;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Printing\DomPdfPrintRenderer;
use App\Printing\PrintRenderer;
use App\ResultSheet\Rendering\DocxToPdfConverter;
use App\ResultSheet\Rendering\LibreOfficeConverter;
use App\Testing\PracticeRecordGuard;
use App\Testing\PracticeWorkspace;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(PracticeWorkspace::class);

        $this->app->singleton(AssistantToolCatalog::class, function (): AssistantToolCatalog {
            return new AssistantToolCatalog;
        });

        $this->app->singleton(ConnectorCatalog::class, function (): ConnectorCatalog {
            /** @var array<string, array<string, mixed>> $profiles */
            $profiles = config('xiam.connectors.allowlist', []);

            return new ConnectorCatalog($profiles);
        });

        $this->app->singleton(AiGateway::class, function (): AiGateway {
            $driver = (string) config('xiam.ai.gateway', 'fake');

            return match ($driver) {
                'laravel' => new LaravelAiGateway,
                default => new FakeAiGateway,
            };
        });

        $this->app->singleton(KnowledgeIndexPort::class, LocalKnowledgeIndexPort::class);

        $this->app->bind(PrintRenderer::class, DomPdfPrintRenderer::class);
        $this->app->bind(DocxToPdfConverter::class, LibreOfficeConverter::class);
        $this->app->bind(OmrPhysicalChallengeRendererPort::class, OmrPhysicalChallengeRenderer::class);
        $this->app->singleton(OmrLayoutVerificationPort::class, function (): OmrLayoutVerificationPort {
            $adapter = $this->app->make(OmrDetectionPort::class);
            if (! $adapter instanceof OmrLayoutVerificationPort) {
                throw new RuntimeException('The configured OMR detection adapter does not provide layout verification.');
            }

            return $adapter;
        });
        $this->app->bind(OmrCaptureAnalysisPort::class, OmrCaptureAnalysisPipeline::class);
        $this->app->bind(OmrCaptureOrientationPort::class, OmrPhysicalQrOrientation::class);
        $this->app->bind(OmrVerificationRunService::class, fn (): OmrVerificationRunService => OmrVerificationRunService::make());

        $this->app->singleton(OmrDetectionPort::class, function (): OmrDetectionPort {
            $driver = (string) config('omr.provider.driver', 'fake');

            if ($driver === 'fake') {
                if (! (bool) config('omr.provider.allow_fake', false) || $this->app->environment('production')) {
                    throw new RuntimeException('The fake OMR provider is not permitted in this environment.');
                }

                return new FakeOmrDetection;
            }

            if ($driver === 'omrchecker') {
                $configured = (bool) config('omr.omrchecker.enabled', false)
                    && strlen((string) config('omr.omrchecker.signing_secret')) >= 32
                    && in_array((string) config('omr.omrchecker.contract_version'), ['nexiam.omr.detection.v3', OmrDetectionRequest::ENVELOPE_VERSION], true)
                    && (string) config('omr.omrchecker.engine_name') === 'OMRChecker'
                    && (string) config('omr.omrchecker.engine_commit') !== ''
                    && (string) config('omr.omrchecker.patch_identity') !== '';

                if (! $configured) {
                    throw new RuntimeException('The OMRChecker provider is incomplete or disabled.');
                }

                return new OmrCheckerDetection;
            }

            throw new RuntimeException("Unknown OMR detection provider [{$driver}].");
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen('eloquent.saving: *', function (string $event, array $models): void {
            app(PracticeRecordGuard::class)->saving($models[0]);
        });
        $wakeOpeningAlerts = static function (string $event, array $models): void {
            $model = $models[0] ?? null;
            if (! config('application-opening-alerts.enabled', false)
                || ! ($model instanceof AcademicCycle
                    || $model instanceof ApplicationWindow
                    || $model instanceof PublicPolicy
                    || $model instanceof PublicPolicyRevision)) {
                return;
            }
            app(ObserveApplicationOpeningAvailability::class)->observeSourceChange($model);
            DB::afterCommit(static function (): void {
                ReconcileApplicationOpeningAlertsJob::dispatch();
            });
        };
        Event::listen('eloquent.saved: *', $wakeOpeningAlerts);
        Event::listen('eloquent.deleted: *', $wakeOpeningAlerts);
        $hotFile = config('app.vite_hot_file');
        if (is_string($hotFile) && $hotFile !== '') {
            Vite::useHotFile($hotFile);
        }

        RateLimiter::for('search-suggestions', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user() !== null ? 'user:'.$request->user()->getAuthIdentifier() : 'ip:'.$request->ip()));
        RateLimiter::for('consultation-lookup', function (Request $request) {
            if ($request->routeIs('consultation.index') && ! $request->filled('q')) {
                return Limit::none();
            }

            return Limit::perMinute(60)
                ->by($request->user() !== null ? 'user:'.$request->user()->getAuthIdentifier() : 'ip:'.$request->ip());
        });
        RateLimiter::for('xiam-turns', fn (Request $request) => Limit::perMinute(30)
            ->by($request->user() !== null ? 'user:'.$request->user()->getAuthIdentifier() : 'ip:'.$request->ip()));
        RateLimiter::for('intake-apply', fn (Request $request) => Limit::perMinute(10)->by('ip:'.$request->ip()));
        RateLimiter::for('application-opening-signup', static function (Request $request): array {
            $value = $request->input('email');
            $address = is_string($value) ? mb_strtolower(trim($value)) : '';
            $addressKey = hash_hmac('sha256', $address, (string) config('app.key'));

            return [
                Limit::perMinute(6)->by('opening-signup-minute:'.$request->ip()),
                Limit::perHour(30)->by('opening-signup-hour:'.$request->ip()),
                Limit::perHour(3)->by('opening-address:'.$addressKey),
            ];
        });
        RateLimiter::for('application-opening-token', static fn (Request $request): array => [
            Limit::perMinute(30)->by('opening-token-ip:'.$request->ip()),
            Limit::perMinute(10)->by('opening-token:'.hash('sha256', (string) $request->route('token', ''))),
        ]);

        $deviceInquiryKey = static function (Request $request): string {
            if ($request->user() !== null) {
                return 'user:'.$request->user()->getAuthIdentifier();
            }
            $rawToken = (string) ($request->cookie(ApplicantOfficeInquiryAccess::DEVICE_COOKIE)
                ?? $request->header(ApplicantOfficeInquiryAccess::DEVICE_HEADER, ''));
            $tokenPart = $rawToken !== '' ? substr(hash('sha256', $rawToken), 0, 16) : 'anon';

            return 'ip:'.$request->ip().':token:'.$tokenPart;
        };
        RateLimiter::for('public-inquiry-create', static fn (Request $request): array => [
            Limit::perMinutes(15, 3)->by('15m:'.$deviceInquiryKey($request)),
            Limit::perDay(5)->by('1d:'.$deviceInquiryKey($request)),
        ]);
        RateLimiter::for('public-inquiry-message', static fn (Request $request): Limit => Limit::perMinutes(15, 10)
            ->by($deviceInquiryKey($request)));
        RateLimiter::for('public-inquiry-snapshot', static fn (Request $request): Limit => Limit::perMinute(30)
            ->by($deviceInquiryKey($request)));
    }
}
