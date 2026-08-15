<?php

namespace App\Providers;

use App\Ai\Adapters\Fake\FakeAiGateway;
use App\Ai\Adapters\LaravelAi\LaravelAiGateway;
use App\Ai\Adapters\Local\LocalKnowledgeIndexPort;
use App\Ai\Contracts\AiGateway;
use App\Ai\Contracts\KnowledgeIndexPort;
use App\Application\Capabilities\CapabilityDefinitions;
use App\Application\Capabilities\CapabilityRegistry;
use App\Assessment\Omr\FakeOmrDetection;
use App\Assessment\Omr\OmrCheckerDetection;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\OmrDetectionPort;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CapabilityRegistry::class, function (): CapabilityRegistry {
            return new CapabilityRegistry(CapabilityDefinitions::all());
        });

        $this->app->singleton(AiGateway::class, function (): AiGateway {
            $driver = (string) config('nexi.ai.gateway', 'fake');

            return match ($driver) {
                'laravel' => new LaravelAiGateway,
                default => new FakeAiGateway,
            };
        });

        $this->app->singleton(KnowledgeIndexPort::class, LocalKnowledgeIndexPort::class);

        $this->app->bind(\App\Printing\PrintRenderer::class, \App\Printing\DomPdfPrintRenderer::class);

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
                    && (string) config('omr.omrchecker.contract_version') === OmrDetectionRequest::ENVELOPE_VERSION
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
        $trusted = trim((string) env('TRUSTED_PROXIES', '*'));
        if ($trusted !== '') {
            TrustProxies::at($trusted);
        }
    }
}
