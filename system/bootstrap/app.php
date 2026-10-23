<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\ApplicantOfficeInquiryResponse;
use App\Http\Middleware\EnsureActiveStaffSession;
use App\Http\Middleware\EnsureCapability;
use App\Http\Middleware\EnsureDemoEnvironment;
use App\Http\Middleware\EnsureStaffSurface;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SelectPracticeWorkspace;
use App\Http\Middleware\TrimRequestStrings;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AddSecurityHeaders::class);
        $noticeAuthoringRequest = static function (Request $request): bool {
            if (! in_array($request->method(), ['POST', 'PUT', 'DELETE'], true)) {
                return false;
            }
            $path = $request->path();
            $isImportMultipart = $request->isMethod('POST') && str_ends_with($path, '/import')
                && str_starts_with(strtolower($request->header('Content-Type', '')), 'multipart/form-data');

            return ($request->isJson() || $isImportMultipart)
                && preg_match('#^setup/notifications/(?:admissions|examination|results)/templates/[a-z0-9-]+/(?:drafts(?:/[0-9a-fA-F-]{36})?|import|import-review|preview|publish|test-mail)$#D', $path) === 1;
        };
        $middleware->trimStrings(except: [$noticeAuthoringRequest]);
        $middleware->replace(TrimStrings::class, TrimRequestStrings::class);
        $middleware->convertEmptyStringsToNull(except: [$noticeAuthoringRequest]);

        $middleware->validateCsrfTokens(except: [
            'tam-answers/*',
            'tam-answers',
            'tam-responses/reset',
            'staff/tam-responses/reset',
        ]);
        $middleware->encryptCookies(except: [
            'nexiam_applicant_submitted',
        ]);
        $middleware->web(append: [
            EnsureActiveStaffSession::class,
            SelectPracticeWorkspace::class,
            HandleInertiaRequests::class,
        ]);
        $middleware->web(prepend: [
            ApplicantOfficeInquiryResponse::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request): string => (
            $request->is('applicant/*') || $request->is('applicant')
                ? route('applicant.login')
                : route('login')
        ));
        $middleware->redirectUsersTo('/staff');

        $middleware->alias([
            'capability' => EnsureCapability::class,
            'staff.surface' => EnsureStaffSurface::class,
        ]);

        $middleware->priority([
            EnsureDemoEnvironment::class,
            SubstituteBindings::class,
            Authenticate::class,
            EnsureActiveStaffSession::class,
            EnsureCapability::class,
        ]);

        $trustedEnv = (string) env('TRUSTED_PROXIES', '');
        if ($trustedEnv === '*' || $trustedEnv === '**') {
            $middleware->trustProxies(at: '*');
        } else {
            $proxies = array_values(array_filter(array_map('trim', explode(',', $trustedEnv)), static fn (string $p): bool => $p !== ''));
            $middleware->trustProxies(at: ! empty($proxies) ? $proxies : [
                '127.0.0.1/32',
                '::1/128',
                '10.0.0.0/8',
                '172.16.0.0/12',
                '192.168.0.0/16',
            ]);
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
