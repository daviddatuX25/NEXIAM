<?php

namespace App\Testing\Actions;

use App\Admission\Support\AttemptProgramFacts;
use App\Admission\Support\RecordThreadService;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\RecordThread;
use App\Models\Foundation\Program;
use App\Models\User;
use App\Testing\PracticeFixtureSet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PreparePracticeExchange
{
    public function __construct(private readonly RecordThreadService $threads) {}

    /** @return array{status:string,reason:string,url?:string} */
    public function projection(PracticeFixtureSet $set): array
    {
        $context = $this->context(PracticeFixtureSet::query()->findOrFail($set->id));
        $caseId = $context['attempt']->id ?? $set->material_snapshot['designated_cases']['pair'] ?? null;
        if ($caseId !== null && AdmissionAttempt::query()->whereKey($caseId)->where('practice_fixture_set_id', $set->id)->where('institution_id', $set->institution_id)->where('academic_cycle_id', $set->academic_cycle_id)->doesntExist()) {
            $caseId = null;
        }
        $metadata = ['case_id' => $caseId === null ? null : (int) $caseId, 'task_ids' => ['C5', 'D4']];
        if ($context['reason'] !== null) {
            return ['status' => 'missing', 'reason' => $context['reason']] + $metadata;
        }

        return [
            'status' => 'available',
            'reason' => $context['thread'] === null
                ? 'The current operator can prepare a native thread for both assigned parties; no thread exists yet.'
                : 'Both assigned parties may use the current native case thread.',
            'url' => route('consultation.applicants.show', [
                'attempt' => $caseId,
                'tab' => 'threads',
                ...($context['thread'] === null ? [] : ['thread_id' => $context['thread']->id]),
                'thread_type' => 'internal_ph_guidance',
                'program_code' => $context['program']->code,
            ], absolute: false),
        ] + $metadata;
    }

    /** @param array<string,mixed> $input
     * @return list<array{key:string,label:string,status:string,reason:string}>
     */
    public function prerequisites(PracticeFixtureSet $set, array $input = []): array
    {
        $context = $this->context(PracticeFixtureSet::query()->findOrFail($set->id));

        return [[
            'key' => 'paired-exchange', 'label' => 'Guidance and academic-leader case exchange',
            'status' => $context['reason'] === null ? 'available' : 'missing',
            'reason' => $context['reason'] ?? 'Both assigned parties may use the same native case thread. Registrar is excluded.',
        ]];
    }

    /** @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function handle(PracticeFixtureSet $set, array $input = []): array
    {
        return DB::transaction(function () use ($set): array {
            $set = PracticeFixtureSet::query()->whereKey($set->id)->lockForUpdate()->firstOrFail();
            $context = $this->context($set);
            if ($context['reason'] !== null) {
                throw ValidationException::withMessages(['pair' => $context['reason']]);
            }
            $attempt = AdmissionAttempt::query()->whereKey($context['attempt']->id)->lockForUpdate()->firstOrFail();
            $context = $this->context($set);
            if ($context['reason'] !== null || (int) $context['attempt']->id !== (int) $attempt->id) {
                throw ValidationException::withMessages(['pair' => $context['reason'] ?? 'The eligible case changed. Preview the pair again.']);
            }
            $thread = $context['thread'];
            if ($thread !== null && (! $this->threads->canSend($thread, $context['guidance'])
                || ! $this->threads->canSend($thread, $context['academic']))) {
                throw ValidationException::withMessages(['pair' => 'The native case boundary no longer permits both assigned parties.']);
            }

            return [
                'status' => 'available',
                'reason' => $thread === null
                    ? 'The assigned parties are ready to start a native case thread with actual content; preparation created no empty root or message.'
                    : 'The assigned Guidance and academic leader can use the current native case thread.',
                'admission_attempt_id' => (string) $attempt->id,
                'thread_id' => $thread === null ? null : (string) $thread->id,
                'program_id' => (string) $context['program']->id,
                'guidance_user_id' => (int) $context['guidance']->id,
                'academic_user_id' => (int) $context['academic']->id,
                'url' => route('consultation.applicants.show', [
                    'attempt' => $attempt->id,
                    'tab' => 'threads',
                    ...($thread === null ? [] : ['thread_id' => $thread->id]),
                    'thread_type' => 'internal_ph_guidance',
                    'program_code' => $context['program']->code,
                ], absolute: false),
            ];
        });
    }

    /** @return array<string,mixed> */
    private function context(PracticeFixtureSet $set): array
    {
        $blocked = fn (string $reason): array => ['reason' => $reason];
        if (! app()->environment(['local', 'testing']) || $set->status !== 'active') {
            return $blocked('Pairing requires an active practice set in local/testing.');
        }
        $guidance = User::query()->with('staffProfile')->find($set->actor_bindings['guidance'] ?? null);
        $academic = User::query()->with('staffProfile')->find($set->actor_bindings['academic-leader'] ?? null);
        $creator = User::query()->find($set->created_by_user_id);
        if ($guidance === null || $academic === null || $guidance->id === $academic->id
            || $guidance->staffProfile?->office !== 'guidance' || $academic->staffProfile?->office !== 'program') {
            return $blocked('The set needs distinct assigned Guidance and Program Head or Dean accounts; Registrar cannot enter this private exchange.');
        }
        $attempts = AdmissionAttempt::query()->where('practice_fixture_set_id', $set->id)
            ->where('institution_id', $set->institution_id)->where('academic_cycle_id', $set->academic_cycle_id)
            ->whereNull('archived_at')->whereHas('identity', fn ($query) => $query
            ->where('practice_fixture_set_id', $set->id)->where('institution_id', $set->institution_id))
            ->orderBy('id')->get();
        $designatedId = $set->material_snapshot['designated_cases']['pair'] ?? null;
        if ($designatedId !== null) {
            $attempts = $attempts->filter(static fn (AdmissionAttempt $attempt): bool => (int) $attempt->id === (int) $designatedId);
        }
        $reason = 'No current owned case is available for this set.';
        foreach ($attempts as $attempt) {
            if (! AttemptProgramFacts::isAccepted($attempt) || ! AttemptProgramFacts::isResultReleased($attempt)) {
                $reason = 'The owned case must be accepted and have current released results before academic participation.';

                continue;
            }
            $programIds = collect(AttemptProgramFacts::openFollowUps($attempt))->pluck('program_id');
            $selection = AttemptProgramFacts::currentSelection($attempt);
            if ($selection !== null) {
                $programIds->prepend($selection->program_id);
            }
            if ($programIds->isEmpty()) {
                $reason = 'The owned case has no current program selection or targeted open follow-up.';
            }
            foreach ($programIds->unique() as $programId) {
                $program = Program::query()->whereKey($programId)->where('institution_id', $set->institution_id)->where('is_active', true)->first();
                if ($program === null || ! $this->threads->hasCaseRelationship($attempt, $programId)) {
                    $reason = 'The current case/program relationship is unavailable.';

                    continue;
                }
                if (! $this->threads->staffCanParticipate($guidance, $attempt, $programId, RecordThread::TYPE_INTERNAL_PH_GUIDANCE)) {
                    $reason = 'Assigned Guidance lacks current thread capability, active office or institution/cycle scope.';

                    continue;
                }
                if (! $this->threads->staffCanParticipate($academic, $attempt, $programId, RecordThread::TYPE_INTERNAL_PH_GUIDANCE)) {
                    $reason = 'Assigned Program Head or Dean lacks current thread capability or active program/institution/cycle scope.';

                    continue;
                }
                $thread = RecordThread::query()->where('admission_attempt_id', $attempt->id)->where('program_id', $programId)
                    ->where('thread_type', RecordThread::TYPE_INTERNAL_PH_GUIDANCE)->where('status', RecordThread::STATUS_OPEN)
                    ->orderBy('id')->first();
                if ($thread === null && ($creator === null
                    || ! $this->threads->staffCanParticipate($creator, $attempt, $programId, RecordThread::TYPE_INTERNAL_PH_GUIDANCE))) {
                    $reason = 'No permitted native thread exists. Its assigned participant must open it through the ordinary case workspace; preparation cannot impersonate that account.';

                    continue;
                }

                return compact('attempt', 'program', 'guidance', 'academic', 'creator', 'thread') + ['reason' => null];
            }
        }

        return $blocked($reason);
    }
}
