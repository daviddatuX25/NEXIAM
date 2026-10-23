<?php

namespace App\Testing\Queries;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Examination\Support\CreateExamSessionEvaluator;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionBenchmarkActivation;
use App\Models\Admission\ApplicantAccount;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Admission\ProgramSelection;
use App\Models\Admission\RecordThread;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Communications\PublicPolicy;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Department;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\Foundation\Room;
use App\Models\Foundation\ScoreConversionRow;
use App\Models\Foundation\SeatingArrangement;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\Models\StaffProfile;
use App\Models\User;
use App\Testing\Actions\DispatchPracticeReminder;
use App\Testing\Actions\PreparePracticeExchange;
use App\Testing\Actions\RegisterPracticeInquiryActors;
use App\Testing\PracticeFixtureSet;
use App\Testing\PracticeScenarioCatalog;
use App\Testing\PracticeWorkspace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class PracticePrerequisites
{
    public function __construct(private CapabilityAuthorizer $authorizer, private PracticeScenarioCatalog $catalog) {}

    public function context(User $actor, bool $lock = false): array
    {
        abort_unless(app()->environment(['local', 'testing']), 403);
        abort_unless($this->authorizer->decideFresh($actor, CapabilityCatalog::DEMO_STAGE_CONTROL)->allowed, 403);
        $scope = $actor->fresh()->actorScope();
        $institution = $this->rows(Institution::query()->whereKey($scope->institutionId), $lock)->first();
        $cycle = $this->rows(AcademicCycle::query()->where('institution_id', $scope->institutionId)->where('code', $scope->activeCycleCode)->where('is_active', true), $lock)->first();
        abort_unless($institution && $cycle, 403, 'A current assigned institution and academic cycle are required.');

        return [$institution, $cycle];
    }

    public function selected(User $actor, array $input, bool $lock = false): ?PracticeFixtureSet
    {
        [$institution, $cycle] = $this->context($actor, $lock);
        if (! isset($input['set_id'])) {
            return null;
        }
        $set = $this->rows(PracticeFixtureSet::query()->whereKey($input['set_id']), $lock)->first();
        abort_unless($set && (int) $set->created_by_user_id === (int) $actor->id && (int) $set->institution_id === (int) $institution->id && (int) $set->academic_cycle_id === (int) $cycle->id, 404);
        abort_unless($set->purpose === $input['purpose'] && $set->scenario === $input['scenario'], 404);

        return $set;
    }

    public function snapshot(User $actor, array $input, bool $lock = false): array
    {
        [$institution, $cycle] = $this->context($actor, $lock);
        $set = $this->selected($actor, $input, $lock);
        $action = $input['action'] ?? 'prepare';
        $checks = [];
        $resources = ['institution' => $institution->getAttributes(), 'cycle' => $cycle->getAttributes()];
        $resources['operator-authority'] = $this->authority([(int) $actor->id], $lock);
        $materials = [];
        $bindings = [];
        $add = static function (string $key, string $label, bool $available, string $reason, bool $blocking = true) use (&$checks): void {
            $checks[] = ['key' => $key, 'label' => $label, 'status' => $available ? 'available' : 'missing', 'reason' => $reason, 'blocking' => $blocking];
        };
        $add('context', 'Current institution and academic cycle', true, $institution->name.' / '.$cycle->staffLabel());
        if ($set !== null) {
            $resources['set'] = ['id' => $set->id, 'status' => $set->status, 'revision' => $set->revision, 'actor_bindings' => $set->actor_bindings, 'material_snapshot' => $set->material_snapshot];
            $resources['assigned-authority'] = $this->authority(array_values($set->actor_bindings ?? []), $lock);
            $add('active-set', 'Active owned practice set', $set->status === 'active', $set->status === 'active' ? 'Owned active set.' : 'Retired history cannot be prepared or changed.');
            $ownedCases = $this->rows(AdmissionAttempt::query()->where('practice_fixture_set_id', $set->id)->orderBy('id'), $lock);
            $resources['owned-identities'] = $this->rows(ApplicantIdentity::query()->where('practice_fixture_set_id', $set->id)->select(['id', 'institution_id', 'practice_fixture_set_id', 'email', 'updated_at'])->orderBy('id'), $lock)->map->getAttributes()->all();
            $resources['owned-accounts'] = $this->rows(ApplicantAccount::query()->whereIn('applicant_identity_id', $ownedCases->pluck('applicant_identity_id'))->select(['id', 'applicant_identity_id', 'user_id'])->orderBy('id'), $lock)->map->getAttributes()->all();
            $resources['cases'] = $ownedCases->map->getAttributes()->all();
            $resources['owned-sessions'] = $this->rows(ExamSession::query()->where('practice_fixture_set_id', $set->id)->orderBy('id'), $lock)->map->getAttributes()->all();
            if ($action === 'pair') {
                $caseIds = $ownedCases->modelKeys();
                $resources['result-documents'] = $this->rows(ResultDocument::query()->whereIn('admission_attempt_id', $caseIds)->select(['id', 'admission_attempt_id', 'status', 'version', 'checksum', 'updated_at'])->orderBy('id'), $lock)->map->getAttributes()->all();
                $resources['result-releases'] = $this->rows(ResultRelease::query()->whereIn('admission_attempt_id', $caseIds)->orderBy('id'), $lock)->map->getAttributes()->all();
                $resources['selections'] = $this->rows(ProgramSelection::query()->whereIn('admission_attempt_id', $caseIds)->orderBy('id'), $lock)->map->getAttributes()->all();
                $resources['follow-ups'] = $this->rows(ProgramFollowUp::query()->whereIn('admission_attempt_id', $caseIds)->orderBy('id'), $lock)->map->getAttributes()->all();
                $resources['threads'] = $this->rows(RecordThread::query()->whereIn('admission_attempt_id', $caseIds)->select(['id', 'admission_attempt_id', 'program_id', 'thread_type', 'status', 'created_by_user_id', 'updated_at'])->orderBy('id'), $lock)->map->getAttributes()->all();
            }
        } elseif ($action !== 'prepare') {
            $add('owned-set', 'Selected owned practice set', false, 'Select an active practice set.');
        }
        if (in_array($action, ['retire', 'time', 'reminder', 'pair'], true)) {
            if ($set !== null && in_array($action, ['time', 'reminder'], true)) {
                $session = $this->rows(ExamSession::query()->whereKey($input['session_id'] ?? 0)->where('practice_fixture_set_id', $set->id)->where('academic_cycle_id', $cycle->id), $lock)->first();
                $add('session', 'Selected practice session', $session !== null && ! in_array($session->status, ['cancelled', 'completed'], true), 'The exact owned non-completed session is required.');
                if ($session !== null) {
                    $resources['session'] = $session->getAttributes();
                    $resources['assignments'] = $this->rows($session->assignments()->getQuery(), $lock)->map->getAttributes()->all();
                }
            }
            if ($set !== null && $action === 'reminder') {
                $checks = [...$checks, ...app(DispatchPracticeReminder::class)->prerequisites($set, $input)];
                $resources['mail-transport'] = ['mailer' => config('mail.default'), 'smtp_host' => config('mail.mailers.smtp.host'), 'smtp_port' => config('mail.mailers.smtp.port')];
            }
            if ($set !== null && $action === 'pair') {
                $checks = [...$checks, ...app(PreparePracticeExchange::class)->prerequisites($set, $input)];
                $resources['cases'] = $this->rows(AdmissionAttempt::query()->where('practice_fixture_set_id', $set->id), $lock)->map->getAttributes()->all();
            }

            return ['context' => ['institution_id' => $institution->id, 'academic_cycle_id' => $cycle->id], 'prerequisites' => $checks, 'resources' => $resources, 'actor_bindings' => $set?->actor_bindings ?? [], 'material_snapshot' => $set?->material_snapshot ?? []];

        }

        $staff = $this->rows(User::query()->whereHas('staffProfile', fn (Builder $query) => $query->where('institution_id', $institution->id)), $lock);
        $registrarCapabilities = [CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW];
        if ($this->catalog->needsExam($input['scenario'])) {
            $registrarCapabilities[] = CapabilityCatalog::REGISTRAR_APPLICATIONS_ACCEPT;
        }
        if ($input['purpose'] === 'research' && $input['scenario'] === 'registrar') {
            $registrarCapabilities[] = CapabilityCatalog::REGISTRAR_APPLICATIONS_CORRECT;
        }
        $roles = ['registrar' => $registrarCapabilities];
        $caseProgramCodes = collect($this->catalog->builders($input['scenario']))->flatMap(static function (string $builder): array {
            $stage = strtolower(substr(class_basename($builder), 4, -5));

            return collect(config('seed.pipeline_cohorts.stages.'.$stage, []))->flatMap(static fn (array $profile): array => (array) ($profile['programs'] ?? [$profile['default_program'] ?? $profile['final_program'] ?? 'BSIT']))->all();
        })->unique()->values()->all();
        if ($this->catalog->needsExam($input['scenario']) || in_array($input['scenario'], ['staff-accounts-delegation', 'account-technical-support'], true)) {
            $roles['guidance'] = [CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE];
            if ($this->catalog->needsResults($input['scenario'])) {
                $roles['guidance'][] = CapabilityCatalog::GUIDANCE_SCORING_MANUAL;
                $roles['guidance'][] = CapabilityCatalog::GUIDANCE_RESULTS_PREPARE;
            }
        }
        if (in_array($input['scenario'], ['academic-leader', 'case-coordination', 'consultation-referral', 'enrollment-verification', 'guidance'], true)) {
            $roles['academic-leader'] = [CapabilityCatalog::PROGRAM_HEAD_VIEW, CapabilityCatalog::PROGRAM_HEAD_MESSAGE];
        }
        foreach ($roles as $role => $capabilities) {
            $assigned = $staff->first(fn (User $user): bool => $user->actorScope()->office === ($role === 'academic-leader' ? 'program' : $role)
                && collect($capabilities)->every(fn (string $capability): bool => $this->authorizer->decideFresh($user, $capability, ['institution_id' => $institution->id, 'cycle' => $cycle->code])->allowed)
                && ($role !== 'academic-leader' || array_intersect($user->actorScope()->programCodes, $caseProgramCodes) !== [])
                && ($input['scenario'] !== 'student' || ! in_array($role, ['registrar', 'guidance'], true) || app(RegisterPracticeInquiryActors::class)->staffEligible($user, $role, (int) $institution->id, (int) $cycle->id)));
            $add('actor-'.$role, 'Assigned '.$role.' account', $assigned !== null, $assigned ? 'Existing current-scope account; sign in normally.' : 'No current permitted account exists; preparation does not repair authority.');
            if ($assigned) {
                $bindings[$role] = (int) $assigned->id;
                $resources['actor-'.$role] = ['id' => $assigned->id, 'scope' => $assigned->actorScope()->toArray(), 'capabilities' => $capabilities, 'allowed' => true];
            }
        }
        $resources['assigned-authority'] = $this->authority(array_values($bindings), $lock);
        if ($input['scenario'] === 'student') {
            $checks = [...$checks, ...app(RegisterPracticeInquiryActors::class)->prerequisitesFor($bindings, (int) $institution->id, (int) $cycle->id)];
            $registrar = isset($bindings['registrar']) ? User::query()->find($bindings['registrar']) : null;
            $canPrepareNotice = $registrar !== null && $this->authorizer->decideFresh($registrar, CapabilityCatalog::REGISTRAR_APPLICATIONS_CORRECT, ['institution_id' => $institution->id, 'cycle' => $cycle->code])->allowed;
            $add('student-notice', 'Secondary applicant portal-notice case', $canPrepareNotice, $canPrepareNotice ? 'One fresh secondary review case receives a native correction notice; primary result-capable applicant stays separate.' : 'The bound Registrar lacks current correction authority; the notice task remains unavailable without permission repair.', false);
        }
        $accountOnly = in_array($input['scenario'], ['staff-accounts-delegation', 'account-technical-support'], true);
        if (! $accountOnly) {
            $window = $this->rows(ApplicationWindow::query()->where('academic_cycle_id', $cycle->id)->where('status', 'published')->where('opens_at', '<=', now())->where('closes_at', '>=', now())->orderBy('id'), $lock)->first();
            $policy = PublicPolicy::designatedIntake((int) $institution->getKey(), lockForUpdate: $lock);
            $revision = $policy?->publishedRevision;
            $programs = $this->rows(Program::query()->where('institution_id', $institution->id)->active()->admissionOffered()->orderBy('id'), $lock);
            $resources['starting-profiles'] = collect($this->catalog->builders($input['scenario']))->mapWithKeys(static function (string $builder): array {
                $stage = strtolower(substr(class_basename($builder), 4, -5));

                return [$stage => array_map(static fn (array $profile): array => array_intersect_key($profile, array_flip(['first_name', 'strand', 'programs', 'default_program', 'final_program', 'status', 'submitted', 'scored', 'score'])), config('seed.pipeline_cohorts.stages.'.$stage, []))];
            })->all();
            $add('starting-profiles', 'Defined initial case profiles', collect($resources['starting-profiles'])->every(static fn (array $profiles): bool => $profiles !== []), 'Every requested starting stage requires defined bounded profiles.');
            $requiredCodes = collect($this->catalog->builders($input['scenario']))->flatMap(function (string $builder): array {
                $stage = strtolower(substr(class_basename($builder), 4, -5));

                return collect(config('seed.pipeline_cohorts.stages.'.$stage, []))
                    ->flatMap(static fn (array $profile): array => (array) ($profile['programs'] ?? [$profile['default_program'] ?? $profile['final_program'] ?? 'BSIT']))->all();
            })->unique()->values()->all();
            $add('starting-programs', 'Offered programs for the defined starting cases', collect($requiredCodes)->diff($programs->pluck('code'))->isEmpty(), 'Every defined starting-case preference must remain currently offered; preparation never changes the program catalog.');
            $open = $cycle->opens_on && $cycle->closes_on && $cycle->opens_on->toDateString() <= now()->toDateString() && $cycle->closes_on->toDateString() >= now()->toDateString();
            $add('admission-window', 'Published open application window', $window !== null && $open, 'Ordinary intake must be currently open; its dates are never rewritten.');
            $add('privacy', 'Designated published PublicPolicy revision', $policy !== null && $revision !== null, 'Use the current institution-owned immutable published revision; no legacy active-policy fallback is available.');
            $add('programs', 'Active offered programs', $programs->isNotEmpty(), 'Use existing institution-owned program offerings.');
            $resources += [
                'window' => $window?->getAttributes(), 'privacy' => $policy?->getAttributes(),
                'privacy_revision' => $revision?->getAttributes(), 'programs' => $programs->map->getAttributes()->all(),
            ];
            $materials += [
                'application_window_id' => $window?->id,
                'public_policy_id' => $policy?->id,
                'public_policy_revision_id' => $revision?->id,
                'public_policy_revision_digest' => $revision?->contentDigest(),
                'program_ids' => $programs->modelKeys(),
            ];
        }
        if ($this->catalog->needsExam($input['scenario'])) {
            $candidates = app(InstitutionalOmrBookletQuery::class)->eligible($lock);
            $selectedBookletId = $input['booklet_version_id'] ?? $set?->material_snapshot['booklet_version_id'] ?? null;
            $booklet = $selectedBookletId !== null ? $candidates->firstWhere('id', (int) $selectedBookletId) : ($candidates->count() === 1 ? $candidates->first() : null);
            if (isset($input['booklet_version_id']) && $booklet === null) {
                throw ValidationException::withMessages(['booklet_version_id' => 'The selected booklet is not an active finalized non-synthetic institutional binding.']);
            }
            $rooms = $this->rows(Room::query()->where('institution_id', $institution->id)->where('is_active', true)->orderBy('id'), $lock);
            $layouts = $this->rows(SeatingArrangement::query()->where('is_active', true)->orderBy('id'), $lock);
            $layoutRooms = DB::table('seating_arrangement_rooms')->whereIn('seating_arrangement_id', $layouts->modelKeys())->orderBy('seating_arrangement_id')->orderBy('room_id');
            $resources['layout-rooms'] = ($lock ? $layoutRooms->lockForUpdate() : $layoutRooms)->get()->map(static fn ($row): array => (array) $row)->all();
            $resources['rooms'] = $rooms->map->getAttributes()->all();
            $resources['layouts'] = $layouts->map->getAttributes()->all();
            $resources['room-sessions'] = $this->rows(ExamSession::query()->where('academic_cycle_id', $cycle->id)->whereIn('room_id', $rooms->modelKeys())->orderBy('id'), $lock)->map->getAttributes()->all();
            $sheet = $booklet ? $this->rows(OmrAnswerSheetTemplateVersion::query()->whereKey($booklet->template_version_id), $lock)->first() : null;
            $maximumCases = max([1, ...array_map('count', array_values($resources['starting-profiles'] ?? []))]);
            $eligibleRooms = $rooms->filter(fn (Room $candidate): bool => $candidate->isExamRoom() && $candidate->capacity >= $maximumCases
                && (! $institution->usesSeatingArrangements() || ($candidate->allowsSeatingArrangement() && $layouts->contains(fn (SeatingArrangement $layout): bool => $layout->desk_count >= $maximumCases && $layout->desk_count <= $candidate->capacity && ($layout->availability_mode === SeatingArrangement::AVAILABILITY_ALL_ROOMS || $layout->rooms()->whereKey($candidate->id)->exists())))));
            $liveStages = collect(array_keys($resources['starting-profiles'] ?? []))->filter(static fn (string $stage): bool => in_array($stage, ['conduct', 'grading', 'consultation', 'program', 'enrollment'], true))->count();
            $requiredRooms = max(1, $liveStages);
            $add('practice-room-count', 'Rooms for the defined starting sessions', $eligibleRooms->count() >= $requiredRooms, 'This scenario needs '.$requiredRooms.' suitable configured room(s) for its overlapping practice starting sessions; no room or capacity is created.');
            $room = $eligibleRooms->first();
            $seating = $room ? $layouts->first(fn (SeatingArrangement $layout): bool => $layout->desk_count >= $maximumCases && $layout->desk_count <= $room->capacity && ($layout->availability_mode === SeatingArrangement::AVAILABILITY_ALL_ROOMS || $layout->rooms()->whereKey($room->id)->exists())) : null;
            $add('exam-material', 'Active finalized institutional examination materials', $booklet !== null && $sheet !== null, $booklet !== null ? 'Selected active finalized booklet #'.$booklet->id.' and its actual answer-sheet version.' : ($candidates->count() > 1 ? 'Choose one eligible institutional booklet for this fresh preparation or successor.' : 'Requires an active finalized non-synthetic institutional answer sheet and booklet.'));
            $add('room', 'Existing active examination room', $room !== null, 'Preparation never creates or changes room capacity.');
            $add('seating', 'Configured seating arrangement', ! $institution->usesSeatingArrangements() || $seating !== null, 'The ordinary seating policy and room eligibility remain enforced.');
            $checks[] = ['key' => 'material-ownership', 'label' => 'Institutional catalog ownership provenance', 'status' => 'unverified', 'reason' => 'The native single-institution OMR catalog has no institution/cycle ownership field. Actual selected versions are frozen; office ownership evidence is not invented.', 'blocking' => false];
            $resources += ['eligible-booklets' => $candidates->map->getAttributes()->all(), 'room' => $room?->getAttributes(), 'booklet' => $booklet?->getAttributes(), 'answer-sheet' => $sheet?->getAttributes(), 'seating' => $seating?->getAttributes()];
            $materials += ['booklet_version_id' => $booklet?->id, 'room_id' => $room?->id, 'seating_arrangement_id' => $seating?->id];
            $materials += ['room_candidate_ids' => $rooms->modelKeys(), 'seating_candidate_ids' => $layouts->modelKeys()];
        }
        if ($this->catalog->needsResults($input['scenario'])) {
            $areas = $this->rows(AptitudeArea::query()->active()->orderBy('id'), $lock);
            $conversions = $this->rows(ScoreConversionRow::query()->whereIn('aptitude_area_id', $areas->modelKeys())->orderBy('id'), $lock);
            $template = $this->rows(ResultSheetTemplate::query()->where('is_active', true)->orderByDesc('id'), $lock)->first();
            $activation = $this->rows(AdmissionBenchmarkActivation::query()->where('institution_id', $institution->id)->where('academic_cycle_id', $cycle->id)->whereNull('superseded_at')->orderByDesc('sequence'), $lock)->first();
            $add('scoring', 'Active aptitude and conversion configuration', $areas->isNotEmpty() && $areas->every(fn (AptitudeArea $area): bool => $conversions->contains(fn (ScoreConversionRow $row): bool => (int) $row->aptitude_area_id === (int) $area->id && $row->min_score <= $area->max_items && $row->max_score >= 0)), 'Ordinary scoring validates actual configured ranges; no conversion table is seeded.');
            $readableTemplate = $template !== null && ($template->mode !== ResultSheetTemplate::MODE_DOCX || ($template->document_path !== null && Storage::disk('local')->exists($template->document_path)));
            $resources['result-template-source'] = $readableTemplate && $template->mode === ResultSheetTemplate::MODE_DOCX
                ? hash_file('sha256', Storage::disk('local')->path($template->document_path))
                : null;
            $add('result-template', 'Current result document template', $readableTemplate, 'Use the actual readable current authored result template.');
            $add('benchmark', 'Current approved benchmark binding', $activation !== null, $activation ? 'Current cycle activation exists; model output remains separately unverified.' : 'No current benchmark activation exists.', false);
            $checks[] = ['key' => 'physical-paper', 'label' => 'Physical print and handover', 'status' => 'unverified', 'reason' => 'Use ordinary printing and attributable physical confirmation; preparation creates no print or handover receipt.', 'blocking' => false];
            $checks[] = ['key' => 'model-output', 'label' => 'Actual approved model result', 'status' => 'unverified', 'reason' => 'No model/provider run or performance certification is performed by preparation.', 'blocking' => false];
            $resources += ['areas' => $areas->map->getAttributes()->all(), 'conversions' => $conversions->map->getAttributes()->all(), 'result-template' => $template?->getAttributes(), 'benchmark' => $activation?->getAttributes()];
            $materials += ['aptitude_area_ids' => $areas->modelKeys(), 'result_template_id' => $template?->id, 'benchmark_activation_id' => $activation?->id];
        }
        if ($input['purpose'] === 'research') {
            $checks[] = ['key' => 'study-approval', 'label' => 'Research protocol and collection approval', 'status' => 'unverified', 'reason' => 'Institutional approval, pilot and participant recruitment are external to this software preparation.', 'blocking' => false];
        }

        return ['context' => ['institution_id' => $institution->id, 'academic_cycle_id' => $cycle->id], 'prerequisites' => $checks, 'resources' => $resources, 'actor_bindings' => $bindings, 'material_snapshot' => $materials];
    }

    public function reason(array $snapshot): ?string
    {
        foreach ($snapshot['prerequisites'] as $check) {
            if (($check['blocking'] ?? true) && $check['status'] !== 'available') {
                return $check['reason'];
            }
        }

        return null;
    }

    public function publicChecks(array $snapshot): array
    {
        return array_map(static fn (array $check): array => array_intersect_key($check, array_flip(['key', 'label', 'status', 'reason'])), $snapshot['prerequisites']);
    }

    public function withTimeline(User $actor, array $input, array $snapshot, bool $lock = false): array
    {
        $set = $this->selected($actor, $input, $lock);
        $session = $set === null ? null : $this->rows(ExamSession::query()->whereKey($input['session_id'])->where('practice_fixture_set_id', $set->id), $lock)->first();
        $room = $session === null ? null : $this->rows(Room::query()->whereKey($session->room_id), $lock)->first();
        $snapshot['resources']['timeline-room'] = $room?->getAttributes();
        $snapshot['resources']['timeline-neighbours'] = $session === null ? [] : $this->rows(ExamSession::query()->where('practice_fixture_set_id', $set->id)->where('room_id', $session->room_id)->where('id', '!=', $session->id)->orderBy('id'), $lock)->map->getAttributes()->all();
        $assignedCount = $session?->assignments()->where('status', ExamAssignment::STATUS_ASSIGNED)->count() ?? 0;
        $roomAllowed = $set !== null && $room !== null && (int) $room->institution_id === (int) $set->institution_id
            && $room->is_active && $room->isExamRoom() && $room->capacity > 0 && $room->capacity >= $assignedCount;
        $snapshot['prerequisites'][] = ['key' => 'timeline-room', 'label' => 'Current owned active examination room and capacity', 'status' => $roomAllowed ? 'available' : 'missing', 'reason' => $roomAllowed ? 'The actual room remains owned, active and suitable for the assigned roster.' : 'The selected session needs an active institution-owned examination room with sufficient actual capacity.', 'blocking' => true];
        if ($roomAllowed && $session !== null) {
            $cycle = $this->rows(AcademicCycle::query()->whereKey($set->academic_cycle_id), $lock)->firstOrFail();
            $preflight = app(PracticeWorkspace::class)->run($set, fn () => app(CreateExamSessionEvaluator::class)->evaluate(
                $cycle, $room, CarbonImmutable::parse($snapshot['timeline']['starts_at']), CarbonImmutable::parse($snapshot['timeline']['ends_at']), $session->code, (int) $session->id,
            ));
            foreach ($preflight->checks as $check) {
                $snapshot['prerequisites'][] = ['key' => 'timeline-'.$check['key'], 'label' => $check['label'], 'status' => $check['status'] === 'pass' ? 'available' : 'missing', 'reason' => $check['detail'], 'blocking' => true];
            }
        }

        return $snapshot;
    }

    public function digest(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }

    private function authority(array $userIds, bool $lock): array
    {
        sort($userIds);
        $profiles = $this->rows(StaffProfile::query()->whereIn('user_id', $userIds)->orderBy('user_id'), $lock)->map(static fn ($profile): array => array_intersect_key($profile->getAttributes(), array_flip(['user_id', 'institution_id', 'office', 'department_id', 'program_codes', 'active_cycle_code', 'status', 'deactivated_at'])))->all();
        $rolesQuery = DB::table(config('permission.table_names.model_has_roles'))->where('model_type', (new User)->getMorphClass())->whereIn('model_id', $userIds)->orderBy('model_id')->orderBy('role_id');
        $roles = ($lock ? $rolesQuery->lockForUpdate() : $rolesQuery)->get();
        $roleIds = $roles->pluck('role_id')->unique()->values()->all();
        $directQuery = DB::table(config('permission.table_names.model_has_permissions'))->where('model_type', (new User)->getMorphClass())->whereIn('model_id', $userIds)->orderBy('model_id')->orderBy('permission_id');
        $direct = ($lock ? $directQuery->lockForUpdate() : $directQuery)->get();
        $roleQuery = DB::table(config('permission.table_names.role_has_permissions'))->whereIn('role_id', $roleIds)->orderBy('role_id')->orderBy('permission_id');
        $permissions = ($lock ? $roleQuery->lockForUpdate() : $roleQuery)->get();
        $departments = $this->rows(Department::query()->whereIn('dean_user_id', $userIds)->orderBy('id'), $lock)->map->getAttributes()->all();

        return ['profiles' => $profiles, 'roles' => $roles->map(static fn ($row): array => (array) $row)->all(), 'direct' => $direct->map(static fn ($row): array => (array) $row)->all(), 'role_grants' => $permissions->map(static fn ($row): array => (array) $row)->all(), 'departments' => $departments];
    }

    private function rows(Builder $query, bool $lock): Collection
    {
        return ($lock ? $query->lockForUpdate() : $query)->get();
    }
}
