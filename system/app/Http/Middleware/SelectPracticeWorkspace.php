<?php

namespace App\Http\Middleware;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Testing\PracticeFixtureSet;
use App\Testing\PracticeRecordGuard;
use App\Testing\PracticeWorkspace;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class SelectPracticeWorkspace
{
    public function __construct(private readonly PracticeWorkspace $workspace) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $this->select($request, $next);
        } finally {
            $this->workspace->set(null);
        }
    }

    private function select(Request $request, Closure $next): Response
    {
        $this->workspace->set(null);
        if ($request->routeIs('demo.research.index', 'demo.research.instrument', 'logout') || $request->user() === null) {
            return $next($request);
        }
        if ($request->routeIs('demo.preparation.preview', 'demo.preparation.confirm')) {
            return $next($request);
        }
        $explicit = $request->query->has('practice_set');
        $selector = $request->query('practice_set');
        if ($selector === 'leave') {
            abort_unless($request->isMethod('GET'), 422);
            $request->session()->forget(['practice_fixture_set_id', 'practice_fixture_set_actor_id']);

            return redirect($request->url());
        }
        $identity = $request->user()->applicantAccount?->identity;
        if ($identity !== null) {
            $ownSet = $identity->practice_fixture_set_id;
            abort_if($explicit && $selector !== $ownSet, 403);
            $selector = $ownSet;
        } elseif (! $explicit) {
            if ((int) $request->session()->get('practice_fixture_set_actor_id') !== (int) $request->user()->getKey()) {
                $request->session()->forget(['practice_fixture_set_id', 'practice_fixture_set_actor_id']);
            }
            $selector = $request->session()->get('practice_fixture_set_id');
        }
        if ($selector === null || $selector === '') {
            foreach ($request->route()?->parameters() ?? [] as $resource) {
                if ($resource instanceof Model) {
                    $this->assertResource($resource, null);
                }
            }
            $this->assertInputs($request, null);

            return $next($request);
        }
        abort_unless(is_string($selector) && Str::isUuid($selector), 422);
        abort_unless(app()->environment(['local', 'testing']) && Schema::hasTable('practice_fixture_sets'), 403);
        $set = PracticeFixtureSet::query()->findOrFail($selector);
        $cycle = AcademicCycle::query()->find($set->academic_cycle_id);
        $scope = $request->user()->actorScope();
        $allowed = $identity !== null
            ? $identity->practice_fixture_set_id === $set->id && (int) $identity->institution_id === (int) $set->institution_id
            : $set->allowsActor($request->user()) && (int) $scope->institutionId === (int) $set->institution_id
                && $scope->activeCycleCode === $cycle?->code;
        abort_unless($allowed && $cycle?->is_active && (int) $cycle->institution_id === (int) $set->institution_id, 403);
        abort_unless($request->isMethodSafe() || $set->status === 'active', 409, 'This practice set is retired. Leave the practice workspace before continuing.');
        $this->workspace->set($set);
        if ($identity === null) {
            $request->session()->put('practice_fixture_set_id', $set->id);
            $request->session()->put('practice_fixture_set_actor_id', $request->user()->getKey());
        }
        foreach ($request->route()?->parameters() ?? [] as $resource) {
            if ($resource instanceof Model) {
                $this->assertResource($resource, $set->id);
            }
        }
        if (! $request->isMethodSafe()) {
            abort_if($request->is('setup/*', 'imports', 'imports/*', 'staff/personnel*', 'staff/profile*', 'staff/users*', 'staff/accounts*', 'staff/delegations*', 'omr/templates*', 'result-templates*', 'reports/templates*'), 403, 'Institutional configuration and genuine staff accounts are read-only in a practice workspace.');
            $this->assertInputs($request, $set->id);
        }

        return $next($request);
    }

    private function assertInputs(Request $request, ?string $setId): void
    {
        if ($request->isMethodSafe()) {
            return;
        }
        foreach (['admission_attempt_id' => AdmissionAttempt::class, 'exam_session_id' => ExamSession::class] as $key => $class) {
            if ($request->filled($key)) {
                $this->assertResource($class::query()->findOrFail($request->input($key)), $setId);
            }
        }
        foreach ((array) $request->input('attempts', []) as $attempt) {
            if (is_array($attempt) && isset($attempt['id'])) {
                $this->assertResource(AdmissionAttempt::query()->findOrFail($attempt['id']), $setId);
            }
        }
        $sessionClass = $request->is('consultation/*')
            ? ConsultationSession::class
            : ExamSession::class;
        foreach ((array) $request->input('session_ids', []) as $sessionId) {
            $this->assertResource($sessionClass::query()->findOrFail($sessionId), $setId);
        }
    }

    private function assertResource(Model $resource, ?string $setId): void
    {
        foreach (app(PracticeRecordGuard::class)->roots($resource) as $root) {
            abort_unless($root->practice_fixture_set_id === $setId, 404);
        }
    }
}
