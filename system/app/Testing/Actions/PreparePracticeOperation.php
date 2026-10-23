<?php

namespace App\Testing\Actions;

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Examination\ExamSession;
use App\Models\User;
use App\Testing\PracticeFixtureSet;
use App\Testing\PracticePreparationOperation;
use App\Testing\PracticeScenarioCatalog;
use App\Testing\PracticeWorkspace;
use App\Testing\Queries\PracticePreparationQuery;
use App\Testing\Queries\PracticePrerequisites;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class PreparePracticeOperation
{
    public function __construct(
        private PracticePrerequisites $prerequisites,
        private PracticeScenarioCatalog $catalog,
        private PracticeWorkspace $workspace,
        private RecordAuditEvent $audit,
    ) {}

    public function preview(User $actor, array $input): array
    {
        $this->validate($input);
        $snapshot = $this->prerequisites->snapshot($actor, $input);
        if ($input['action'] === 'time') {
            $reference = CarbonImmutable::now();
            $minutes = match ($input['preset']) {
                '72h-reminder' => 4320, '24h-reminder' => 1440, '2h-reminder' => 120, '1h-reminder' => 60,
                'soon' => 45, 'door-open' => 20, 'live' => -30, 'ended' => -155,
            };
            $snapshot['timeline'] = ['reference' => $reference->toIso8601String(), 'starts_at' => $reference->addMinutes($minutes)->toIso8601String(), 'ends_at' => $reference->addMinutes($minutes)->addHours(2)->toIso8601String()];
            $snapshot = $this->prerequisites->withTimeline($actor, $input, $snapshot);
        }
        $reason = $this->prerequisites->reason($snapshot);
        $operation = PracticePreparationOperation::query()->create([
            'id' => (string) Str::uuid(), 'actor_user_id' => $actor->id,
            'practice_fixture_set_id' => $input['set_id'] ?? null,
            'purpose' => $input['purpose'], 'scenario' => $input['scenario'], 'action' => $input['action'],
            'parameters' => $input, 'prerequisite_snapshot' => $snapshot,
            'prerequisite_digest' => $this->prerequisites->digest($snapshot),
            'expected_revision' => $snapshot['resources']['set']['revision'] ?? null,
            'expires_at' => now()->addMinutes(10), 'status' => 'preview', 'result' => [],
        ]);

        return [
            'operation' => ['id' => $operation->id, 'action' => $operation->action, 'expires_at' => $operation->expires_at->toIso8601String(), 'confirm_url' => route('demo.preparation.confirm', ['operation' => $operation->id], absolute: false)],
            'impact' => [
                'creates' => in_array($input['action'], ['prepare', 'replace'], true) ? [
                    'One fresh '.$input['purpose'].' set for '.$input['scenario'],
                    ...collect($snapshot['resources']['starting-profiles'] ?? [])->map(static fn (array $profiles, string $stage): string => count($profiles).' fresh fictional '.$stage.' starting cases and their secure applicant accounts')->values()->all(),
                ] : ($input['action'] === 'pair' ? ['One native permitted same-case exchange, only when eligible'] : ($input['action'] === 'reminder' ? ['Policy-permitted reminder intents for practice session '.$input['session_id'].' in the '.$input['window'].' window, restricted to its exact owned assignments'] : [])),
                'replaces' => $input['action'] === 'replace' ? ['The selected set is retired and retained; a fresh successor is created'] : ($input['action'] === 'retire' ? ['Selected set availability only; all history is retained'] : ($input['action'] === 'time' ? ['Session '.$input['session_id'].' starts '.$snapshot['timeline']['starts_at'].' and ends '.$snapshot['timeline']['ends_at'].'. Status and policy unchanged; no reminder delivery.'] : [])),
                'preserves' => $this->catalog->preserved(),
            ],
            'prerequisites' => $this->prerequisites->publicChecks($snapshot), 'can_confirm' => $reason === null, 'reason' => $reason,
        ];
    }

    public function confirm(User $actor, string $operationId): array
    {
        $this->prerequisites->context($actor);
        abort_unless(Str::isUuid($operationId), 404);
        $outcome = DB::transaction(function () use ($actor, $operationId): array {
            User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $operation = PracticePreparationOperation::query()->whereKey($operationId)->lockForUpdate()->firstOrFail();
            abort_unless((int) $operation->actor_user_id === (int) $actor->id, 404);
            $input = $operation->parameters;
            $this->validate($input);
            $this->prerequisites->context($actor, true);
            $set = $this->prerequisites->selected($actor, $input, true);
            if ($operation->status === 'completed') {
                return $operation->result;
            }
            abort_if($operation->expires_at->isPast(), 409, 'This preview expired. Review a fresh preview.');
            abort_if($set !== null && (int) $set->revision !== (int) $operation->expected_revision, 409, 'The practice set changed. Review a fresh preview.');
            try {
                $snapshot = $this->prerequisites->snapshot($actor, $input, true);
            } catch (ValidationException) {
                abort(409, 'The selected material or exact preparation inputs changed. Review a fresh preview.');
            }
            if (isset($operation->prerequisite_snapshot['timeline'])) {
                $snapshot['timeline'] = $operation->prerequisite_snapshot['timeline'];
                $snapshot = $this->prerequisites->withTimeline($actor, $input, $snapshot, true);
            }
            abort_unless(hash_equals($operation->prerequisite_digest, $this->prerequisites->digest($snapshot)), 409, 'Authority, materials or the selected state changed. Review a fresh preview.');
            $reason = $this->prerequisites->reason($snapshot);
            if ($reason !== null) {
                throw ValidationException::withMessages(['prerequisites' => $reason]);
            }
            try {
                $result = match ($input['action']) {
                    'prepare', 'replace' => $this->prepare($actor, $input, $snapshot, $set),
                    'retire' => $this->retire($set),
                    'time' => $this->time($actor, $set, $input, $snapshot['timeline']['reference']),
                    'reminder' => app(DispatchPracticeReminder::class)->handle($set, $input),
                    'pair' => app(PreparePracticeExchange::class)->handle($set, $input),
                };
            } catch (RuntimeException $exception) {
                throw ValidationException::withMessages(['prerequisites' => $exception->getMessage()]);
            }
            $setId = $result['set_id'] ?? $set?->id;
            if (! in_array($input['action'], ['prepare', 'replace', 'retire'], true)) {
                $set->increment('revision');
            }
            $outcome = ['message' => match ($input['action']) {
                'prepare', 'replace' => 'Fresh practice starting state prepared. Physical, model and research gates remain separate.',
                'retire' => 'Practice set retired. History, outcomes and artifacts are retained.',
                'time' => 'Selected practice session timeline updated. No reminders were sent.',
                'reminder' => 'Scoped reminder operation recorded. Delivery status follows actual delivery evidence.',
                'pair' => 'Permitted same-case exchange prepared.',
            }, 'set_id' => $setId, 'result' => $result];
            $this->audit->handle('practice.preparation_completed', ['operation_id' => $operation->id, 'set_id' => $setId, 'action' => $input['action'], 'purpose' => $input['purpose'], 'scenario' => $input['scenario'], 'result' => $result], 'user:'.$actor->id);
            $operation->forceFill(['status' => 'completed', 'confirmed_at' => now(), 'result' => $outcome])->save();

            return $outcome;
        }, 3);
        $operation = PracticePreparationOperation::query()->findOrFail($operationId);
        $selection = ['purpose' => $operation->purpose, 'scenario' => $operation->scenario];
        if ($outcome['set_id'] !== null) {
            $selection['set_id'] = $outcome['set_id'];
        }

        return $outcome + ['preparation' => app(PracticePreparationQuery::class)->get($actor, $selection)];
    }

    private function prepare(User $actor, array $input, array $snapshot, ?PracticeFixtureSet $predecessor): array
    {
        if ($input['action'] === 'replace') {
            $this->retire($predecessor);
        }
        $set = PracticeFixtureSet::query()->create([
            'id' => (string) Str::uuid(), 'label' => ucfirst($input['purpose']).' · '.str_replace('-', ' ', $input['scenario']).' · '.now()->format('M j H:i'),
            'institution_id' => $snapshot['context']['institution_id'], 'academic_cycle_id' => $snapshot['context']['academic_cycle_id'],
            'scenario' => $input['scenario'], 'purpose' => $input['purpose'], 'status' => 'active', 'revision' => 1,
            'created_by_user_id' => $actor->id, 'actor_bindings' => $snapshot['actor_bindings'],
            'material_snapshot' => $snapshot['material_snapshot'], 'credentials' => [], 'predecessor_set_id' => $predecessor?->id,
        ]);
        $stages = $this->workspace->run($set, function () use ($set, $input): array {
            $results = [];
            foreach ($this->catalog->builders($input['scenario']) as $builder) {
                $set->refresh();
                $stage = app($builder)->forSet($set)->seed();
                $results[] = [
                    'stage' => $stage['stage'] ?? class_basename($builder),
                    'count' => $stage['count'] ?? 0,
                    'attempt_ids' => collect($stage['attempts'] ?? [])->map(static fn ($attempt): int => (int) (is_object($attempt) ? $attempt->id : $attempt))->all(),
                    'session_id' => isset($stage['session']) ? (int) $stage['session']->id : null,
                    'grading_id' => isset($stage['grading']) ? (int) $stage['grading']->id : null,
                    'document_ids' => collect($stage['documents'] ?? [])->map(static fn ($document): int => (int) (is_object($document) ? $document->id : $document))->all(),
                    'gates' => $stage['gates'] ?? [],
                ];
            }
            $this->designateCases($set->fresh());
            if ($input['scenario'] === 'student') {
                app(RegisterPracticeInquiryActors::class)->register($set->fresh());
            } else {
                $freshSet = $set->fresh();
                foreach ($freshSet->credentials ?? [] as $identityId => $credentials) {
                    $identity = ApplicantIdentity::query()->whereKey($identityId)->where('practice_fixture_set_id', $freshSet->id)->lockForUpdate()->firstOrFail();
                    if ($identity->applicantAccount()->exists() || User::query()->where('email', $identity->email)->exists()) {
                        throw new RuntimeException('Fresh fixture account provisioning cannot adopt an existing account.');
                    }
                    $user = app(AuthenticateApplicantWithAccessKey::class)->handle($credentials['access_key'], $credentials['birthdate']);
                    if ($user->staffProfile()->exists() || (int) $user->applicantAccount?->applicant_identity_id !== (int) $identity->id) {
                        throw new RuntimeException('Fresh fixture account provisioning did not preserve the owned identity.');
                    }
                }
            }

            return $results;
        });

        return ['set_id' => $set->id, 'stages' => $stages];
    }

    private function designateCases(PracticeFixtureSet $set): void
    {
        $snapshot = $set->material_snapshot ?? [];
        $stages = $snapshot['stages'] ?? [];
        $reviewIds = $stages['review']['attempt_ids'] ?? [];
        $resultIds = $stages['program']['attempt_ids'] ?? $stages['consultation']['attempt_ids'] ?? $stages['enrollment']['attempt_ids'] ?? [];
        $results = AdmissionAttempt::query()->where('practice_fixture_set_id', $set->id)->whereIn('id', $resultIds)->orderBy('id')->get();
        $primary = $results->first(static fn ($attempt): bool => $attempt->current_result_document_id !== null);
        $academic = User::query()->find($set->actor_bindings['academic-leader'] ?? null);
        $programCodes = $academic?->actorScope()->programCodes ?? [];
        $pair = $results->first(static fn ($attempt): bool => array_intersect($programCodes, array_filter([$attempt->program_preference_1_code, $attempt->program_preference_2_code, $attempt->program_preference_3_code])) !== []);
        $reviewCases = AdmissionAttempt::query()->where('practice_fixture_set_id', $set->id)->whereIn('id', $reviewIds)->orderBy('id')->get();
        $pending = $reviewCases->firstWhere('status', 'pending');
        $readCase = $reviewCases->first(static fn ($attempt): bool => $attempt->status !== 'pending')
            ?? $reviewCases->first(static fn ($attempt): bool => $pending === null || (int) $attempt->id !== (int) $pending->id);
        $snapshot['designated_cases'] = array_filter([
            'student_primary' => $set->scenario === 'student' ? $primary?->id : null,
            'student_notice' => $set->scenario === 'student' ? ($reviewIds[0] ?? null) : null,
            'registrar_read' => $set->scenario === 'registrar' ? $readCase?->id : null,
            'registrar_correction' => $set->scenario === 'registrar' ? $pending?->id : null,
            'registrar_progressed' => $set->scenario === 'registrar' ? ($stages['enrollment']['attempt_ids'][0] ?? null) : null,
            'pair' => $pair?->id ?? $primary?->id,
        ], static fn ($id): bool => $id !== null);
        $set->update(['material_snapshot' => $snapshot]);
    }

    private function retire(PracticeFixtureSet $set): array
    {
        abort_unless($set->status === 'active', 409, 'This set is already retired.');
        $set->forceFill(['status' => 'retired', 'retired_at' => now(), 'revision' => $set->revision + 1])->save();

        return ['set_id' => $set->id, 'status' => 'retired', 'history_retained' => true];
    }

    private function time(User $actor, PracticeFixtureSet $set, array $input, string $reference): array
    {
        $session = ExamSession::query()->whereKey($input['session_id'])->where('practice_fixture_set_id', $set->id)->lockForUpdate()->firstOrFail();
        $result = $this->workspace->run($set, fn (): array => app(WarpDemoSessionTimeline::class)->handle($session, $input['preset'], $actor, $set, CarbonImmutable::parse($reference)));

        return ['set_id' => $set->id, 'session_id' => $session->id, 'preset' => $result['stage'], 'starts_at' => $result['session']->starts_at?->toIso8601String(), 'ends_at' => $result['session']->effective_end_at?->toIso8601String()];
    }

    private function validate(array $input): void
    {
        $unknown = array_diff(array_keys($input), ['purpose', 'scenario', 'action', 'set_id', 'session_id', 'window', 'preset', 'booklet_version_id']);
        if ($unknown !== [] || ! is_string($input['purpose'] ?? null) || ! is_string($input['scenario'] ?? null)
            || ! $this->catalog->valid($input['purpose'], $input['scenario']) || ! in_array($input['action'] ?? '', PracticeScenarioCatalog::ACTIONS, true)) {
            throw ValidationException::withMessages(['operation' => 'Choose a fixed preparation scenario and action. Unknown inputs are not permitted.']);
        }
        $allowed = ['purpose', 'scenario', 'action', ...match ($input['action']) {
            'prepare' => ['booklet_version_id'],
            'replace' => ['set_id', 'booklet_version_id'],
            'retire', 'pair' => ['set_id'],
            'time' => ['set_id', 'session_id', 'preset'],
            'reminder' => ['set_id', 'session_id', 'window'],
        }];
        if (array_diff(array_keys($input), $allowed) !== [] || ($input['action'] !== 'prepare' && ! isset($input['set_id']))) {
            throw ValidationException::withMessages(['operation' => 'Supply only the exact inputs for this action and select an owned set.']);
        }
        if (isset($input['set_id']) && (! is_string($input['set_id']) || ! Str::isUuid($input['set_id']))) {
            throw ValidationException::withMessages(['set_id' => 'Choose a valid owned practice set identity.']);
        }
        if (isset($input['booklet_version_id']) && (filter_var($input['booklet_version_id'], FILTER_VALIDATE_INT) === false || (int) $input['booklet_version_id'] < 1 || ! $this->catalog->needsExam($input['scenario']))) {
            throw ValidationException::withMessages(['booklet_version_id' => 'Choose an eligible institutional booklet for an exam-dependent prepare or replacement operation.']);
        }
        if (isset($input['session_id']) && (filter_var($input['session_id'], FILTER_VALIDATE_INT) === false || (int) $input['session_id'] < 1)) {
            throw ValidationException::withMessages(['session_id' => 'Choose a valid owned practice session identity.']);
        }
        if (in_array($input['action'], ['time', 'reminder'], true) && ! isset($input['session_id'])) {
            throw ValidationException::withMessages(['session_id' => 'Choose the exact owned practice session.']);
        }
        if ($input['action'] === 'time' && ! in_array($input['preset'] ?? '', array_column(app(WarpDemoSessionTimeline::class)->options(), 'key'), true)) {
            throw ValidationException::withMessages(['preset' => 'Choose a supported timeline preset.']);
        }
        if ($input['action'] === 'reminder' && ! in_array($input['window'] ?? '', ['72h', '24h', '2h', '1h'], true)) {
            throw ValidationException::withMessages(['window' => 'Choose a supported reminder window.']);
        }
    }
}
