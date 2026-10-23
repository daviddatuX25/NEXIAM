<?php

namespace App\Http\Controllers\Setup;

use App\Admission\Actions\CopyProgramAdvisingNoteToCycle;
use App\Admission\Actions\MarkProgramGovernanceDiscussionRead;
use App\Admission\Actions\OpenProgramGovernanceDiscussion;
use App\Admission\Actions\PublishProgramAdvisingNoteRevision;
use App\Admission\Actions\RecordProgramAdvisingNoteUse;
use App\Admission\Actions\RestoreProgramAdvisingNoteRevision;
use App\Admission\Actions\RetractProgramAdvisingNote;
use App\Admission\Actions\SaveProgramAdvisingNote;
use App\Admission\Actions\SendProgramGovernanceMessage;
use App\Admission\Actions\SetProgramGovernanceDiscussionState;
use App\Admission\Queries\ProgramGovernanceDiscussionQuery;
use App\AdmissionEngine\Actions\ActivateAdmissionBenchmarkSet;
use App\AdmissionEngine\Actions\BenchmarkExecutionContext;
use App\AdmissionEngine\Actions\CutoverProgramBenchmarkDraftSchema;
use App\AdmissionEngine\Actions\PublishProgramBenchmarkSet;
use App\AdmissionEngine\Actions\RebaseProgramBenchmarkDraft;
use App\AdmissionEngine\Actions\RecoverHistoricalProgramBenchmarkDraft;
use App\AdmissionEngine\Actions\RestoreProgramBenchmarkDraftRevision;
use App\AdmissionEngine\Actions\SaveProgramBenchmarkDraft;
use App\AdmissionEngine\Actions\StartProgramBenchmarkDraft;
use App\AdmissionEngine\DTOs\AdmissionInputPayload;
use App\AdmissionEngine\ProgramBenchmarkPreviewQuery;
use App\AdmissionEngine\Support\BenchmarkInputSchema;
use App\Authorization\CapabilityCatalog;
use App\Authorization\GovernanceAuthorizer;
use App\Consultation\Queries\ProgramAdvisingNoteQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\ActivateBenchmarkSetRequest;
use App\Http\Requests\Setup\GovernanceCommandRequest;
use App\Http\Requests\Setup\ProgramAdvisingNoteCommandRequest;
use App\Http\Requests\Setup\ProgramGovernanceReadRequest;
use App\Http\Requests\Setup\RecoverBenchmarkDraftRequest;
use App\Http\Requests\Setup\SandboxPreviewRequest;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionBenchmarkActivation;
use App\Models\Admission\AdmissionBenchmarkDraft;
use App\Models\Admission\AdmissionBenchmarkExemplar;
use App\Models\Admission\AdmissionBenchmarkRevision;
use App\Models\Admission\AdmissionBenchmarkSet;
use App\Models\Admission\ProgramAdvisingNote;
use App\Models\Admission\ProgramAdvisingNotePublication;
use App\Models\Admission\ProgramAdvisingNoteRevision;
use App\Models\Admission\ProgramGovernanceThread as GovernanceThread;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\User;
use App\Setup\Queries\AdmissionSupportSetupDashboardQuery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use RuntimeException;

final class ProgramGovernanceController extends Controller
{
    public function show(ProgramGovernanceReadRequest $request, Program $program, AcademicCycle $cycle, GovernanceAuthorizer $auth, ProgramAdvisingNoteQuery $notes, ProgramGovernanceDiscussionQuery $discussions, AdmissionSupportSetupDashboardQuery $query): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && (int) $program->institution_id === (int) $cycle->institution_id, 404);
        $canBenchmark = $auth->allows($actor, CapabilityCatalog::ADMISSION_BENCHMARK_GOVERNANCE_VIEW, $program, $cycle);
        $canNote = $auth->allows($actor, CapabilityCatalog::ADMISSION_ADVISING_NOTE_VIEW, $program, $cycle);
        $canDiscuss = false;
        foreach ([
            CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_READ,
            CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_READ,
            CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_ADVISING_READ,
        ] as $capability) {
            $canDiscuss = $canDiscuss || $auth->allows($actor, $capability, $program, $cycle);
        }
        abort_unless($canBenchmark || $canNote || $canDiscuss, 403);
        $pages = $request->validated();
        abort_unless(! isset($pages['note_id']) || $canNote, 403);
        $board = $canNote
            ? $notes->boardFor($actor, $program, $cycle, (int) ($pages['note_page'] ?? 1), 50, $pages['note_search'] ?? '')
            : null;
        $note = null;
        if (isset($pages['note_id'])) {
            $noteId = (int) $pages['note_id'];
            $draftNote = $notes->draftFor($actor, $program, $cycle, $noteId);
            abort_unless($draftNote !== null, 404);
            $published = $notes->publishedFor($actor, $program, $cycle);
            $note = [
                'draft' => $draftNote,
                'published' => collect($published)->firstWhere('note_id', $noteId),
                'history' => $notes->historyFor($actor, $program, $cycle, $noteId, (int) ($pages['note_page'] ?? 1)),
                'publication_history' => $notes->publicationHistoryFor($actor, $program, $cycle, $noteId, (int) ($pages['publication_page'] ?? 1)),
            ];
        }
        $topics = [];
        foreach (['benchmark', 'quota', 'advising'] as $topic) {
            $capability = match ($topic) {
                'benchmark' => CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_READ,
                'quota' => CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_READ,
                default => CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_ADVISING_READ,
            };
            if ($auth->allows($actor, $capability, $program, $cycle)) {
                $topics[$topic] = $discussions->list($actor, $program, $cycle, $topic, (int) (($pages['topic'] ?? $topic) === $topic ? ($pages['discussion_page'] ?? 1) : 1), 20, $pages['discussion_search'] ?? '');
            }
        }
        $review = null;
        if (isset($pages['benchmark_set_id'])) {
            $auth->authorize($actor, CapabilityCatalog::ADMISSION_BENCHMARK_ACTIVATE, $program, $cycle);
            $set = AdmissionBenchmarkSet::query()->where('institution_id', $program->institution_id)
                ->where('status', 'published')->with('features')->findOrFail($pages['benchmark_set_id']);
            abort_unless(hash_equals($set->content_sha256, $set->computedContentDigest())
                && hash_equals($set->schema_sha256, $set->computedSchemaDigest()), 409);
            $tiers = [];
            foreach ($set->exemplars()->where('program_id', $program->id)->with('values.feature')->orderBy('tier_level')->get() as $exemplar) {
                $values = [];
                foreach ($exemplar->values as $cell) {
                    $values[$cell->feature->feature_key] = $cell->is_missing ? null : ($cell->numeric_value ?? $cell->categorical_value);
                }
                $tiers[(string) $exemplar->tier_level] = ['values' => $values];
            }
            abort_unless(count($tiers) === 5, 422);
            $review = ['set' => $set->only(['id', 'version', 'name', 'content_sha256', 'schema_sha256']), 'content' => ['tiers' => $tiers]];
        }
        $workspace = $query->governance($actor, $program, $cycle, $auth, $notes, $discussions);

        $returnTo = ($pages['return_to'] ?? null) === route('consultation.index') ? route('consultation.index') : null;

        return response()->json([
            'program' => ['id' => $program->id, 'code' => $program->code, 'name' => $program->name],
            'cycle' => ['id' => $cycle->id, 'code' => $cycle->code, 'name' => $cycle->name],
            'notes' => $board,
            'note' => $note,
            'discussions' => $topics,
            'tab_badges' => $workspace['tab_badges'] ?? [],
            'benchmark_review' => $review,
            'recovery' => $canBenchmark ? $workspace['recovery'] ?? null : null,
            'schema_features' => $workspace['schema_features'] ?? [],
            'input_schema' => $workspace['input_schema'] ?? ['subjects' => [], 'activities' => []],
            'active_input_schema' => $workspace['active_input_schema'] ?? ['subjects' => [], 'activities' => []],
            'requires_input_cutover' => $workspace['requires_input_cutover'] ?? false,
            'input_cutover_source_digest' => $workspace['input_cutover_source_digest'] ?? null,
            'schema_cutover' => $workspace['schema_cutover'] ?? null,
            'return_to' => $returnTo,
        ]);
    }

    public function saveBenchmark(GovernanceCommandRequest $request, Program $program, AcademicCycle $cycle, SaveProgramBenchmarkDraft $action): JsonResponse
    {
        return $this->respond($request, fn (User $actor, array $v) => $action->handle($actor, $program, $cycle, $v['content'], $v['expected_version'], $v['reason'], $v['idempotency_key']), ['content', 'expected_version', 'reason', 'idempotency_key'], 'revision');
    }

    public function startBenchmark(GovernanceCommandRequest $request, Program $program, AcademicCycle $cycle, StartProgramBenchmarkDraft $action): JsonResponse
    {
        return $this->respond($request, fn (User $actor, array $v) => $action->handle($actor, $program, $cycle, $v['expected_version'], $v['reason'], $v['idempotency_key']), ['expected_version', 'reason', 'idempotency_key'], 'revision', 201);
    }

    public function recoverBenchmark(
        RecoverBenchmarkDraftRequest $request,
        Program $program,
        AcademicCycle $cycle,
        RecoverHistoricalProgramBenchmarkDraft $action,
    ): JsonResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $validated = $request->validated();
        try {
            $revision = $action->handle(
                $actor,
                $program,
                $cycle,
                (int) $validated['expected_version'],
                $validated['expected_source_digest'],
                $validated['reason'],
                $validated['idempotency_key'],
            );
        } catch (AuthorizationException $exception) {
            return response()->json(['status' => 'denied', 'message' => $exception->getMessage()], 403);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['status' => 'invalid', 'message' => $exception->getMessage()], 422);
        } catch (RuntimeException $exception) {
            $message = strtolower($exception->getMessage());
            $conflict = str_contains($message, 'conflict') || str_contains($message, 'changed') || str_contains($message, 'already has') || str_contains($message, 'different request payload');

            return response()->json(['status' => $conflict ? 'conflict' : 'invalid', 'message' => $exception->getMessage()], $conflict ? 409 : 422);
        }

        $draft = AdmissionBenchmarkDraft::query()->with('recovery')->findOrFail($revision->draft_id);
        $recovery = $draft->recovery;

        return response()->json([
            'status' => 'success',
            'revision' => $revision->only(['id', 'draft_id', 'version', 'actor_id', 'content', 'reason', 'base_benchmark_set_id', 'feature_schema_digest', 'created_at']),
            'recovery' => [
                'source_set_id' => $recovery->source_benchmark_set_id,
                'source_version' => $recovery->source_version,
                'source_content_sha256' => $recovery->source_content_sha256,
                'source_snapshot_digest' => $recovery->source_snapshot_sha256,
                'source_activation_id' => $recovery->source_activation_id,
                'status' => 'draft',
                'historical_content_verified' => false,
            ],
        ]);
    }

    public function rebaseBenchmark(GovernanceCommandRequest $request, Program $program, AcademicCycle $cycle, RebaseProgramBenchmarkDraft $action): JsonResponse
    {
        return $this->respond($request, fn (User $actor, array $v) => $action->handle($actor, $program, $cycle, $v['expected_version'], $v['benchmark_set_id'], $v['expected_content_sha256'], $v['reason'], $v['idempotency_key']), ['expected_version', 'benchmark_set_id', 'expected_content_sha256', 'reason', 'idempotency_key'], 'revision');
    }

    public function restoreBenchmark(GovernanceCommandRequest $request, Program $program, AcademicCycle $cycle, RestoreProgramBenchmarkDraftRevision $action): JsonResponse
    {
        return $this->respond($request, function (User $actor, array $v) use ($program, $cycle, $action) {
            $source = AdmissionBenchmarkRevision::query()->whereHas('draft', fn ($query) => $query->where('program_id', $program->id)->where('academic_cycle_id', $cycle->id))->findOrFail($v['source_revision_id']);

            return $action->handle($actor, $program, $cycle, $source, $v['expected_version'], $v['reason'], $v['idempotency_key']);
        }, ['source_revision_id', 'expected_version', 'reason', 'idempotency_key'], 'revision');
    }

    public function publishBenchmark(GovernanceCommandRequest $request, AcademicCycle $cycle, PublishProgramBenchmarkSet $action): JsonResponse
    {
        return $this->respond($request, function (User $actor, array $v) use ($cycle, $action) {
            $recoverySource = isset($v['recovery_source_set_id']) ? [
                'source_set_id' => (int) $v['recovery_source_set_id'],
                'source_snapshot_digest' => $v['expected_source_snapshot_digest'],
                'source_activation_id' => (int) $v['expected_source_activation_id'],
            ] : null;

            return $action->handle(
                $actor,
                $cycle,
                $v['revision_ids_by_program'],
                $v['expected_content_sha256'],
                $v['reason'],
                $v['idempotency_key'],
                $recoverySource,
                ! empty($v['schema_cutover']) ? ['expected_predecessor_schema_digest' => $v['expected_predecessor_schema_digest']] : null,
            );
        }, ['revision_ids_by_program', 'expected_content_sha256', 'reason', 'idempotency_key', 'recovery_source_set_id', 'expected_source_snapshot_digest', 'expected_source_activation_id', 'schema_cutover', 'expected_predecessor_schema_digest'], 'set', 200, ['schema_cutover', 'expected_predecessor_schema_digest']);
    }

    public function activateBenchmark(ActivateBenchmarkSetRequest $request, AcademicCycle $cycle, ActivateAdmissionBenchmarkSet $action): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && (int) $cycle->institution_id === (int) $actor->actorScope()->institutionId, 403);

        return $this->respond($request, function (User $user, array $v) use ($cycle, $action) {
            $set = AdmissionBenchmarkSet::query()->where('institution_id', $cycle->institution_id)->findOrFail($v['benchmark_set_id']);

            return $action->handle($cycle, $set, BenchmarkExecutionContext::human($user), $v['expected_current_activation_id'], $v['expected_content_sha256'], $v['idempotency_key']);
        }, ['benchmark_set_id', 'expected_current_activation_id', 'expected_content_sha256', 'idempotency_key'], 'activation');
    }

    public function preview(SandboxPreviewRequest $request, Program $program, AcademicCycle $cycle, ProgramBenchmarkPreviewQuery $query): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $validated = $request->validated();
        $inputs = $validated['input'];
        try {
            $aptitudes = array_map('floatval', $inputs['aptitudes'] ?? []);
            $input = new AdmissionInputPayload(
                0,
                (int) $program->institution_id,
                (int) $cycle->id,
                isset($inputs['gwa']) ? (float) $inputs['gwa'] : null,
                $aptitudes,
                array_fill_keys(array_keys($aptitudes), 'percentile'),
            );
            $result = $query->handle($actor, $program, $cycle, $input, $validated['source'], $validated['revision_id'] ?? null, $validated['request_id'] ?? null);
        } catch (AuthorizationException $exception) {
            return response()->json(['status' => 'denied', 'message' => $exception->getMessage()], 403);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['status' => 'invalid', 'message' => $exception->getMessage()], 422);
        } catch (RuntimeException $exception) {
            $message = strtolower($exception->getMessage());
            $conflict = str_contains($message, 'stale') || str_contains($message, 'conflict') || str_contains($message, 'changed');

            return response()->json(['status' => $conflict ? 'conflict' : 'invalid', 'message' => $exception->getMessage()], $conflict ? 409 : 422);
        }

        return response()->json(['status' => 'success', 'evaluation' => $result->toArray()]);
    }

    public function saveNote(ProgramAdvisingNoteCommandRequest $request, Program $program, AcademicCycle $cycle, SaveProgramAdvisingNote $action): JsonResponse
    {
        return $this->respond($request, fn (User $actor, array $v) => $action->handle($actor, $program, $cycle, $v['content'], $v['expected_version'], (string) ($v['reason'] ?? ''), $v['idempotency_key'], $v['note_id'] ?? null, $v['audience'] ?? null), ['content', 'expected_version', 'reason', 'idempotency_key', 'note_id', 'audience'], 'revision', 200, ['note_id', 'reason', 'audience']);
    }

    public function restoreNote(ProgramAdvisingNoteCommandRequest $request, Program $program, AcademicCycle $cycle, RestoreProgramAdvisingNoteRevision $action): JsonResponse
    {
        return $this->respond($request, function (User $actor, array $v) use ($program, $cycle, $action) {
            $note = ProgramAdvisingNote::query()->where('program_id', $program->id)->where('academic_cycle_id', $cycle->id)->findOrFail($v['note_id']);
            $source = ProgramAdvisingNoteRevision::query()->where('program_advising_note_id', $note->id)->findOrFail($v['source_revision_id']);

            return $action->handle($actor, $note, $source, $v['expected_version'], (string) ($v['reason'] ?? ''), $v['idempotency_key']);
        }, ['note_id', 'source_revision_id', 'expected_version', 'reason', 'idempotency_key'], 'revision', 200, ['reason']);
    }

    public function publishNote(ProgramAdvisingNoteCommandRequest $request, Program $program, AcademicCycle $cycle, PublishProgramAdvisingNoteRevision $action): JsonResponse
    {
        return $this->notePublication($request, $program, $cycle, $action, 'publish');
    }

    public function retractNote(ProgramAdvisingNoteCommandRequest $request, Program $program, AcademicCycle $cycle, RetractProgramAdvisingNote $action): JsonResponse
    {
        return $this->notePublication($request, $program, $cycle, $action, 'retract');
    }

    private function notePublication(ProgramAdvisingNoteCommandRequest $request, Program $program, AcademicCycle $cycle, object $action, string $operation): JsonResponse
    {
        return $this->respond($request, function (User $actor, array $v) use ($program, $cycle, $action, $operation) {
            $note = ProgramAdvisingNote::query()->where('program_id', $program->id)->where('academic_cycle_id', $cycle->id)->findOrFail($v['note_id']);
            if ($operation === 'publish') {
                $revision = ProgramAdvisingNoteRevision::query()->where('program_advising_note_id', $note->id)->findOrFail($v['revision_id']);

                return $action->handle($actor, $note, $revision, $v['expected_version'], (string) ($v['reason'] ?? ''), $v['idempotency_key']);
            }
            $publication = ProgramAdvisingNotePublication::query()->where('program_advising_note_id', $note->id)->findOrFail($v['publication_id']);

            return $action->handle($actor, $note, $publication, $v['expected_version'], (string) ($v['reason'] ?? ''), $v['idempotency_key']);
        }, $operation === 'publish' ? ['note_id', 'revision_id', 'expected_version', 'reason', 'idempotency_key'] : ['note_id', 'publication_id', 'expected_version', 'reason', 'idempotency_key'], 'publication', 200, ['reason']);
    }

    public function copyNote(ProgramAdvisingNoteCommandRequest $request, Program $program, AcademicCycle $cycle, CopyProgramAdvisingNoteToCycle $action): JsonResponse
    {
        return $this->respond($request, function (User $actor, array $v) use ($program, $cycle, $action) {
            $source = ProgramAdvisingNoteRevision::query()->with('note')->findOrFail($v['source_revision_id']);
            abort_unless(
                (int) $source->note?->program_id === (int) $program->id
                && (int) $source->note?->academic_cycle_id === (int) $cycle->id,
                404,
            );
            $targetCycle = AcademicCycle::query()->findOrFail($v['target_cycle_id']);

            return $action->handle($actor, $source, $program, $targetCycle, $v['expected_version'], (string) ($v['reason'] ?? ''), $v['idempotency_key'], $v['target_audience'] ?? null);
        }, ['source_revision_id', 'target_cycle_id', 'expected_version', 'reason', 'idempotency_key', 'target_audience'], 'revision', 200, ['reason', 'target_audience']);
    }

    public function useNote(GovernanceCommandRequest $request, AdmissionAttempt $attempt, RecordProgramAdvisingNoteUse $action): JsonResponse
    {
        return $this->respond($request, function (User $actor, array $v) use ($attempt, $action) {
            return $action->handle($actor, $attempt, ProgramAdvisingNoteRevision::query()->findOrFail($v['source_revision_id']), $v['idempotency_key']);
        }, ['source_revision_id', 'idempotency_key'], 'usage');
    }

    public function openDiscussion(GovernanceCommandRequest $request, Program $program, AcademicCycle $cycle, OpenProgramGovernanceDiscussion $action): JsonResponse
    {
        return $this->respond($request, fn (User $actor, array $v) => $action->handle($actor, $program, $cycle, $v['topic'], $v['subject'], $v['idempotency_key'], $v['benchmark_revision_id'] ?? null, $v['quota_change_id'] ?? null), ['topic', 'subject', 'idempotency_key', 'benchmark_revision_id', 'quota_change_id'], 'thread', 201);
    }

    public function benchmarkComparison(
        ProgramGovernanceReadRequest $request,
        Program $program,
        AcademicCycle $cycle,
        GovernanceAuthorizer $authorizer,
    ): JsonResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User && (int) $program->institution_id === (int) $cycle->institution_id, 404);
        $authorizer->authorize($actor, CapabilityCatalog::ADMISSION_BENCHMARK_GOVERNANCE_VIEW, $program, $cycle);
        $validated = $request->validated();
        $draft = AdmissionBenchmarkDraft::query()
            ->where('institution_id', $program->institution_id)
            ->where('program_id', $program->id)
            ->where('academic_cycle_id', $cycle->id)
            ->firstOrFail();
        $revision = $draft->revisions()->whereKey($validated['revision_id'] ?? $draft->current_revision_id)->firstOrFail();
        $baselineId = (int) ($validated['set_id'] ?? AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id)?->benchmark_set_id ?? 0);
        $baseline = AdmissionBenchmarkSet::query()
            ->where('institution_id', $program->institution_id)
            ->where('status', 'published')
            ->with('features')
            ->findOrFail($baselineId);
        $hasCycleHistory = AdmissionBenchmarkActivation::query()
            ->where('academic_cycle_id', $cycle->id)
            ->where('institution_id', $program->institution_id)
            ->where('benchmark_set_id', $baseline->id)
            ->exists();
        abort_unless($hasCycleHistory, 404);
        abort_unless(AdmissionBenchmarkExemplar::query()
            ->where('benchmark_set_id', $baseline->id)
            ->where('program_id', $program->id)
            ->exists(), 404);
        abort_unless(hash_equals((string) $baseline->schema_sha256, $baseline->computedSchemaDigest())
            && hash_equals((string) $baseline->content_sha256, $baseline->computedContentDigest()), 409);

        $features = $baseline->features->map(static fn ($feature): array => [
            'key' => (string) $feature->feature_key,
            'label' => (string) $feature->label,
            'logical_type' => (string) $feature->logical_type,
            'source_definition' => $feature->source_definition_json,
        ])->all();
        $tiers = [];
        foreach (AdmissionBenchmarkExemplar::query()
            ->where('benchmark_set_id', $baseline->id)
            ->where('program_id', $program->id)
            ->with('values.feature')
            ->orderBy('tier_level')
            ->get() as $exemplar) {
            $values = array_fill_keys(array_column($features, 'key'), null);
            foreach ($exemplar->values as $value) {
                $values[$value->feature->feature_key] = $value->is_missing
                    ? null
                    : ($value->feature->logical_type === 'categorical' ? $value->categorical_value : $value->numeric_value);
            }
            $tiers[(string) $exemplar->tier_level] = ['values' => $values];
        }
        $baselineRevision = $this->publishedRevisionForProgram($baseline, $program, $cycle);
        $schema = BenchmarkInputSchema::schemaForDraft($draft, $revision);
        $revisionFeatures = array_map(static fn (array $feature): array => [
            'key' => (string) $feature['feature_key'],
            'label' => (string) $feature['label'],
            'logical_type' => (string) $feature['logical_type'],
            'source_definition' => $feature['source_definition'] ?? null,
        ], $schema);
        $revisionSchemaDigest = BenchmarkInputSchema::digest($schema);
        abort_unless(hash_equals((string) $revision->feature_schema_digest, $revisionSchemaDigest), 409);
        $activations = AdmissionBenchmarkActivation::query()
            ->where('academic_cycle_id', $cycle->id)
            ->where('institution_id', $program->institution_id)
            ->with('benchmarkSet.features')
            ->orderByDesc('sequence')
            ->limit(100)
            ->get()
            ->unique('benchmark_set_id')
            ->filter(fn (AdmissionBenchmarkActivation $activation): bool => $activation->benchmarkSet !== null
                && $activation->benchmarkSet->status === 'published'
                && AdmissionBenchmarkExemplar::query()->where('benchmark_set_id', $activation->benchmark_set_id)->where('program_id', $program->id)->exists()
                && hash_equals((string) $activation->benchmarkSet->schema_sha256, $activation->benchmarkSet->computedSchemaDigest())
                && hash_equals((string) $activation->benchmarkSet->content_sha256, $activation->benchmarkSet->computedContentDigest()))
            ->values();
        $activeActivation = AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id);
        $baselineOptions = $activations->map(fn (AdmissionBenchmarkActivation $activation): array => [
            'id' => (int) $activation->benchmark_set_id,
            'version' => (int) $activation->benchmarkSet->version,
            'name' => (string) $activation->benchmarkSet->name,
            'in_use' => $activeActivation !== null && (int) $activeActivation->benchmark_set_id === (int) $activation->benchmark_set_id,
        ])->all();

        return response()->json(['comparison' => [
            'baseline' => [
                'id' => (int) $baseline->id,
                'version' => (int) $baseline->version,
                'name' => (string) $baseline->name,
                'in_use' => $activeActivation !== null && (int) $activeActivation->benchmark_set_id === (int) $baseline->id,
                'schema_digest' => (string) $baseline->schema_sha256,
                'features' => $features,
                'input_schema' => BenchmarkInputSchema::projection($baseline),
                'content' => ['tiers' => $tiers, 'authoring' => $baselineRevision?->content['authoring'] ?? null],
            ],
            'revision' => [
                'id' => (int) $revision->id,
                'version' => (int) $revision->version,
                'features' => $revisionFeatures,
                'schema_digest' => $revisionSchemaDigest,
                'input_schema' => BenchmarkInputSchema::projectionForDraft($draft, $revision),
                'content' => $revision->content,
            ],
            'baselines' => $baselineOptions,
        ]]);
    }

    private function publishedRevisionForProgram(
        AdmissionBenchmarkSet $set,
        Program $program,
        AcademicCycle $cycle,
    ): ?AdmissionBenchmarkRevision {
        $visited = [];
        $candidate = $set;
        for ($depth = 0; $candidate !== null && $depth < 100; $depth++) {
            if (isset($visited[$candidate->id])) {
                return null;
            }
            $visited[$candidate->id] = true;
            $manifest = is_array($candidate->publication_manifest_json) ? $candidate->publication_manifest_json : [];
            $entry = $manifest[(string) $program->id] ?? $manifest[(int) $program->id] ?? null;
            if (is_array($entry) && isset($entry['revision_id'])) {
                $revision = AdmissionBenchmarkRevision::query()->with('draft')->find($entry['revision_id']);
                if ($revision instanceof AdmissionBenchmarkRevision
                    && (int) $revision->draft?->program_id === (int) $program->id
                    && (int) $revision->draft?->academic_cycle_id === (int) $cycle->id
                    && hash_equals((string) $revision->feature_schema_digest, (string) $candidate->schema_sha256)
                    && hash_equals((string) $candidate->schema_sha256, $candidate->computedSchemaDigest())) {
                    return $revision;
                }

                return null;
            }
            $predecessorId = $candidate->predecessor_id;
            $candidate = $predecessorId === null
                ? null
                : AdmissionBenchmarkSet::query()
                    ->where('institution_id', $program->institution_id)
                    ->where('status', 'published')
                    ->find($predecessorId);
        }

        return null;
    }

    public function cutoverBenchmark(
        GovernanceCommandRequest $request,
        Program $program,
        AcademicCycle $cycle,
        CutoverProgramBenchmarkDraftSchema $action,
    ): JsonResponse {
        return $this->respond($request, fn (User $actor, array $v) => $action->handle(
            $actor, $program, $cycle, (int) $v['expected_version'], $v['reason'], $v['idempotency_key'], $v['expected_source_digest'],
        ), ['expected_version', 'reason', 'idempotency_key', 'expected_source_digest'], 'revision');
    }

    public function benchmarkHistory(
        GovernanceCommandRequest $request,
        Program $program,
        AcademicCycle $cycle,
        GovernanceAuthorizer $authorizer,
    ): JsonResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        abort_unless((int) $program->institution_id === (int) $cycle->institution_id, 404);
        $authorizer->authorize($actor, CapabilityCatalog::ADMISSION_BENCHMARK_GOVERNANCE_VIEW, $program, $cycle);
        $page = $request->integer('page', 1);
        $perPage = $request->integer('per_page', 20);
        $draft = AdmissionBenchmarkDraft::query()
            ->where('program_id', $program->id)
            ->where('academic_cycle_id', $cycle->id)
            ->first();
        if ($draft === null) {
            return response()->json(['items' => [], 'pagination' => ['current_page' => $page, 'per_page' => $perPage, 'total' => 0, 'last_page' => 1]]);
        }
        $revisions = $draft->revisions()->with('actor:id,name')->orderByDesc('id')->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'items' => $revisions->getCollection()->map(static fn (AdmissionBenchmarkRevision $revision): array => [
                'id' => $revision->id,
                'version' => $revision->version,
                'content' => $revision->content,
                'actor' => $revision->actor?->name,
                'reason' => $revision->reason,
                'created_at' => $revision->created_at?->toIso8601String(),
            ])->all(),
            'pagination' => ['current_page' => $revisions->currentPage(), 'per_page' => $revisions->perPage(), 'total' => $revisions->total(), 'last_page' => $revisions->lastPage()],
        ]);
    }

    public function discussionHistory(GovernanceCommandRequest $request, Program $program, AcademicCycle $cycle, ProgramGovernanceDiscussionQuery $query): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $thread = GovernanceThread::query()->where('program_id', $program->id)->where('academic_cycle_id', $cycle->id)->findOrFail($request->integer('thread_id'));
        try {
            return response()->json($query->history($thread, $actor, $program, $cycle, $request->integer('page', 1), $request->integer('per_page', 50), $request->input('before_message_id')));
        } catch (AuthorizationException $exception) {
            return response()->json(['status' => 'denied', 'message' => $exception->getMessage()], 403);
        } catch (RuntimeException $exception) {
            return response()->json(['status' => 'invalid', 'message' => $exception->getMessage()], 422);
        }
    }

    public function sendDiscussion(GovernanceCommandRequest $request, Program $program, AcademicCycle $cycle, SendProgramGovernanceMessage $action): JsonResponse
    {
        return $this->discussionAction($request, $program, $cycle, $action, 'send');
    }

    public function stateDiscussion(GovernanceCommandRequest $request, Program $program, AcademicCycle $cycle, SetProgramGovernanceDiscussionState $action): JsonResponse
    {
        return $this->discussionAction($request, $program, $cycle, $action, 'state');
    }

    public function readDiscussion(GovernanceCommandRequest $request, Program $program, AcademicCycle $cycle, MarkProgramGovernanceDiscussionRead $action): JsonResponse
    {
        return $this->discussionAction($request, $program, $cycle, $action, 'read');
    }

    private function discussionAction(GovernanceCommandRequest $request, Program $program, AcademicCycle $cycle, object $action, string $operation): JsonResponse
    {
        return $this->respond($request, function (User $actor, array $v) use ($program, $cycle, $action, $operation) {
            $thread = GovernanceThread::query()->where('program_id', $program->id)->where('academic_cycle_id', $cycle->id)->findOrFail($v['thread_id']);

            return match ($operation) {
                'send' => $action->handle($thread, $actor, $program, $cycle, $v['body'], $v['idempotency_key']),
                'state' => $action->handle($thread, $actor, $program, $cycle, $v['status'], $v['idempotency_key']),
                default => $action->handle($thread, $actor, $program, $cycle, $v['idempotency_key'], $v['through_message_id'] ?? null),
            };
        }, match ($operation) {
            'send' => ['thread_id', 'body', 'idempotency_key'],
            'state' => ['thread_id', 'status', 'idempotency_key'],
            default => ['thread_id', 'idempotency_key', 'through_message_id'],
        }, match ($operation) {
            'send' => 'message', 'state' => 'thread', default => 'read'
        });
    }

    /** @param callable(User, array<string, mixed>): mixed $command @param list<string> $keys */
    private function respond(FormRequest $request, callable $command, array $keys, string $receipt, int $successStatus = 200, array $optionalKeys = []): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $validated = $request->validated();
        foreach ($validated as $key => $_value) {
            if (! in_array($key, $keys, true)) {
                return response()->json(['status' => 'invalid', 'message' => 'Unexpected request field.'], 422);
            }
        }
        foreach ($keys as $required) {
            if (! array_key_exists($required, $validated) && ! in_array($required, [
                'benchmark_revision_id',
                'quota_change_id',
                'through_message_id',
                'recovery_source_set_id',
                'expected_source_snapshot_digest',
                'expected_source_activation_id',
            ], true) && ! in_array($required, $optionalKeys, true)) {
                return response()->json(['status' => 'invalid', 'message' => "Missing required field: {$required}"], 422);
            }
        }
        try {
            $result = $command($actor, $validated);
        } catch (AuthorizationException $exception) {
            return response()->json(['status' => 'denied', 'message' => $exception->getMessage()], 403);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['status' => 'invalid', 'message' => $exception->getMessage()], 422);
        } catch (RuntimeException $exception) {
            $message = strtolower($exception->getMessage());
            $conflict = str_contains($message, 'stale')
                || str_contains($message, 'conflict')
                || str_contains($message, 'already exists')
                || str_contains($message, 'different request payload')
                || str_contains($message, 'version')
                || str_contains($message, 'changed')
                || str_contains($message, 'rebase');

            return response()->json(['status' => $conflict ? 'conflict' : 'invalid', 'message' => $exception->getMessage()], $conflict ? 409 : 422);
        }
        if ($result instanceof Model) {
            $fields = match ($receipt) {
                'revision' => ['id', 'draft_id', 'program_advising_note_id', 'version', 'actor_id', 'actor_user_id', 'authored_by_user_id', 'content', 'reason', 'command', 'base_benchmark_set_id', 'feature_schema_digest', 'created_at'],
                'set' => ['id', 'institution_id', 'version', 'status', 'content_sha256', 'schema_sha256', 'published_at', 'published_by_user_id'],
                'activation' => ['id', 'institution_id', 'academic_cycle_id', 'benchmark_set_id', 'sequence', 'activated_at'],
                'publication' => ['id', 'program_advising_note_id', 'revision_id', 'operation', 'actor_user_id', 'reason', 'expected_version', 'recorded_at'],
                'thread' => ['id', 'institution_id', 'program_id', 'academic_cycle_id', 'topic', 'subject', 'status', 'created_by_user_id', 'created_at'],
                'message' => ['id', 'program_governance_thread_id', 'sender_user_id', 'body', 'created_at'],
                'read' => ['id', 'program_governance_thread_id', 'user_id', 'last_read_message_id', 'last_read_at'],
                'usage' => ['id', 'admission_attempt_id', 'program_advising_note_id', 'revision_id', 'actor_user_id', 'used_at'],
                default => ['id'],
            };
            $value = $result->only($fields);
            if ($receipt === 'revision' && $result instanceof ProgramAdvisingNoteRevision) {
                $value['note_id'] = $result->program_advising_note_id;
                $value['audience'] = $result->note->audience;
            }
        } else {
            $value = is_object($result) && method_exists($result, 'toArray') ? $result->toArray() : $result;
        }

        return response()->json(['status' => 'success', $receipt => $value], $successStatus);
    }
}
