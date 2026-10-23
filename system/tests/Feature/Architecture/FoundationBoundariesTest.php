<?php

use App\Ai\Conversation\XiamConversationSnapshotQuery;
use App\Http\Controllers\Admission\ApplicantApplyController;
use App\Http\Controllers\Examination\ExamSessionController;
use App\Http\Controllers\Foundation\DocsStateController;
use App\Http\Controllers\Knowledge\KnowledgeSourceController;
use App\Http\Controllers\Release\ReleaseController;
use App\Http\Controllers\Xiam\XiamConversationController;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

arch('controllers do not open database transactions directly')
    ->expect('App\Http\Controllers')
    ->not->toUse('Illuminate\Support\Facades\DB')
    ->ignoring([
        'App\Http\Controllers\HealthController',
        'App\Http\Controllers\Foundation\DocsStateController',
    ]);

arch('foundation models do not depend on HTTP request types')
    ->expect('App\Models')
    ->not->toUse('Illuminate\Http');

arch('application code does not reference a Legacy namespace')
    ->expect('App')
    ->not->toUse('Legacy');

test('docs state controller exception is local/testing-only while production controllers delegate DB access', function (): void {
    $testingDocsRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (IlluminateRoute $route): bool => str_starts_with($route->getActionName(), DocsStateController::class))
        ->values();

    expect($testingDocsRoutes)->toHaveCount(2)
        ->and($testingDocsRoutes->map(fn (IlluminateRoute $route): ?string => $route->getName())->sort()->values()->all())
        ->toBe(['capstone.docs.state', 'capstone.docs.state.store'])
        ->and($testingDocsRoutes->map(fn (IlluminateRoute $route): string => $route->uri())->unique()->values()->all())
        ->toBe(['capstone/docs/state']);

    $originalRoutes = Route::getRoutes();
    $originalEnv = (string) app()->environment();

    try {
        app()->detectEnvironment(fn (): string => 'production');
        Route::setRoutes(new RouteCollection);
        require base_path('routes/web.php');

        $productionDocsRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (IlluminateRoute $route): bool => str_starts_with($route->getActionName(), DocsStateController::class));

        expect($productionDocsRoutes)->toBeEmpty();
    } finally {
        app()->detectEnvironment(fn (): string => $originalEnv);
        Route::setRoutes($originalRoutes);
    }

    expect([
        XiamConversationController::class,
        ApplicantApplyController::class,
        ExamSessionController::class,
        KnowledgeSourceController::class,
        ReleaseController::class,
    ])->not->toUse(DB::class);

    expect(XiamConversationController::class)
        ->toUse(XiamConversationSnapshotQuery::class);
});
