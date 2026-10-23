<?php

namespace App\Http\Controllers\Setup;

use App\Admission\Queries\ProgramGovernanceDiscussionQuery;
use App\AdmissionEngine\Actions\BenchmarkExecutionContext;
use App\AdmissionEngine\Actions\ImportAdmissionBenchmarkSet;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Authorization\GovernanceAuthorizer;
use App\Consultation\Queries\ProgramAdvisingNoteQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\DecisionSupportWorkspaceRequest;
use App\Http\Requests\Setup\ImportBenchmarkSetRequest;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Setup\Queries\AdmissionSupportSetupDashboardQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdmissionSupportSetupController extends Controller
{
    public function decisionSupport(
        DecisionSupportWorkspaceRequest $request,
        AdmissionSupportSetupDashboardQuery $query,
        GovernanceAuthorizer $authorizer,
        ProgramAdvisingNoteQuery $notes,
        ProgramGovernanceDiscussionQuery $discussions,
    ): Response {
        $institution = Institution::current();
        $programValue = $request->query('program_id', $request->query('program'));
        $cycleValue = $request->query('cycle_id', $request->query('cycle'));
        $program = $programValue === null ? null : Program::query()
            ->where('institution_id', $institution->id)
            ->where(static fn ($builder) => $builder->where('code', $programValue)
                ->when(ctype_digit((string) $programValue), static fn ($query) => $query->orWhere('id', $programValue)))
            ->firstOrFail();
        $cycle = $cycleValue === null ? null : AcademicCycle::query()
            ->where('institution_id', $institution->id)
            ->where(static fn ($builder) => $builder->where('code', $cycleValue)
                ->when(ctype_digit((string) $cycleValue), static fn ($query) => $query->orWhere('id', $cycleValue)))
            ->firstOrFail();

        $governance = $query->governance($request->user(), $program, $cycle, $authorizer, $notes, $discussions);
        abort_unless($governance['programs'] !== [], 403);
        $governance['return_to'] = $request->query('return_to') === route('consultation.index')
            ? route('consultation.index')
            : null;
        if ($programValue !== null && $cycleValue !== null) {
            abort_unless($governance['selected_program'] !== null && $governance['selected_cycle'] !== null, 403);
        }
        $navigation = $request->validated();
        $canBenchmark = ($governance['capabilities'][CapabilityCatalog::ADMISSION_BENCHMARK_GOVERNANCE_VIEW] ?? false)
            || ($governance['capabilities'][CapabilityCatalog::ADMISSION_BENCHMARK_DRAFT_EDIT] ?? false)
            || ($governance['capabilities'][CapabilityCatalog::ADMISSION_BENCHMARK_PREVIEW] ?? false);
        $canNotes = $governance['capabilities'][CapabilityCatalog::ADMISSION_ADVISING_NOTE_VIEW] ?? false;
        $availableTabs = [
            'tools' => $canBenchmark,
            'notes' => $canNotes,
            'discussion' => collect($governance['capabilities'] ?? [])->contains(
                fn (bool $allowed, string $capability): bool => $allowed && str_starts_with($capability, 'admission.governance-discussion.') && str_ends_with($capability, '.read'),
            ),
        ];
        $requestedTab = $navigation['tab'] ?? 'tools';
        $governance['initial_tab'] = ($availableTabs[$requestedTab] ?? false)
            ? $requestedTab : (array_search(true, $availableTabs, true) ?: 'tools');
        $governance['initial_tool'] = $canBenchmark ? ($navigation['tool'] ?? null) : null;
        $governance['note_detail'] = null;
        if (isset($navigation['note_id'])) {
            abort_unless($program !== null && $cycle !== null && $canNotes, 403);
            $noteId = (int) $navigation['note_id'];
            $note = $notes->draftFor($request->user(), $program, $cycle, $noteId);
            abort_unless($note !== null, 404);
            $governance['initial_tab'] = 'notes';
            $governance['note_detail'] = [
                'draft' => $note,
                'published' => collect($notes->publishedFor($request->user(), $program, $cycle))->firstWhere('note_id', $noteId),
                'history' => $notes->historyFor($request->user(), $program, $cycle, $noteId),
                'publication_history' => $notes->publicationHistoryFor($request->user(), $program, $cycle, $noteId),
            ];
        }

        return Inertia::render('Setup/AdmissionSupport/TabFmStudio', ['governance' => $governance]);
    }

    public function yukeeStudio(
        Request $request,
        CapabilityAuthorizer $authorizer,
        AdmissionSupportSetupDashboardQuery $query,
    ): Response {
        abort_unless($authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG), 403);

        $institution = Institution::current();

        return Inertia::render('Setup/AdmissionSupport/YukeeStudio', $query->handle($institution));
    }

    public function importBenchmarks(
        ImportBenchmarkSetRequest $request,
        AcademicCycle $cycle,
        CapabilityAuthorizer $authorizer,
        ImportAdmissionBenchmarkSet $action,
    ): RedirectResponse|JsonResponse {
        abort_unless($authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG), 403);
        $validated = $request->validated();
        $institution = Institution::current();
        abort_unless((int) $cycle->institution_id === (int) $institution->id, 404);
        $set = $action->handle(
            $institution,
            $cycle,
            BenchmarkExecutionContext::human($request->user()),
            $request->benchmarkPayload(),
            $validated['idempotency_key'],
        );
        if ($request->expectsJson()) {
            return response()->json(['status' => 'success', 'set' => $set->only(['id', 'version', 'name', 'status', 'content_sha256', 'schema_sha256', 'published_at'])], 201);
        }

        return back()->with('success', "Benchmark set v{$set->version} imported successfully.");
    }
}
