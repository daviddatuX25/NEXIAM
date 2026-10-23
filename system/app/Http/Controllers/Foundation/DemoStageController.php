<?php

namespace App\Http\Controllers\Foundation;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\DemoGuideAssets;
use App\Foundation\DemoGuideCatalog;
use App\Foundation\DemoPersonaCatalog;
use App\Http\Controllers\Controller;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Testing\Actions\GenerateDemoPipelineApplicants;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DemoStageController extends Controller
{
    public function index(
        Request $request,
        DemoGuideCatalog $catalog,
        DemoGuideAssets $assets,
        CapabilityAuthorizer $authorizer,
        DemoPersonaCatalog $personas,
    ): Response {
        $canControl = $authorizer->decideFresh($request->user(), CapabilityCatalog::DEMO_STAGE_CONTROL)->allowed;

        $institution = Institution::query()->where('code', 'SYN-INST-001')->first()
            ?? Institution::query()->first();

        $cycle = $institution ? (AcademicCycle::query()->where('institution_id', $institution->id)->where('is_active', true)->first()
            ?? AcademicCycle::query()->where('institution_id', $institution->id)->latest('id')->first()) : null;

        $window = $cycle ? (ApplicationWindow::query()->where('academic_cycle_id', $cycle->id)->where('status', 'published')->first()
            ?? ApplicationWindow::query()->where('academic_cycle_id', $cycle->id)->first()) : null;

        $programs = $institution ? Program::query()
            ->where('institution_id', $institution->id)
            ->where('is_active', true)
            ->where('is_admission_offered', true)
            ->orderBy('code')
            ->pluck('code')
            ->all() : [];

        if ($programs === []) {
            $programs = ['BSIT', 'BSEd', 'BEEd', 'BSBA', 'BA-Psych'];
        }

        $recentAttempts = AdmissionAttempt::query()
            ->when($cycle, fn ($q) => $q->where('academic_cycle_id', $cycle->id))
            ->with(['identity', 'accessChallenge'])
            ->latest('id')
            ->limit(25)
            ->get()
            ->map(fn (AdmissionAttempt $attempt) => [
                'id' => (int) $attempt->id,
                'reference_code' => (string) $attempt->reference_code,
                'full_name' => (string) ($attempt->identity?->fullName() ?? 'Applicant'),
                'email' => (string) ($attempt->identity?->email ?? $attempt->contact_email),
                'birthdate' => $attempt->identity?->birthdate?->format('Y-m-d') ?? '2008-01-01',
                'sex' => (string) ($attempt->identity?->sex ?? 'unspecified'),
                'programs' => array_values(array_filter([
                    $attempt->program_preference_1_code,
                    $attempt->program_preference_2_code,
                    $attempt->program_preference_3_code,
                ])),
                'strand' => (string) ($attempt->strand ?? 'STEM'),
                'school' => (string) ($attempt->last_school_enrolled ?? 'Tagudin National High School'),
                'status' => (string) $attempt->status,
                'created_at' => $attempt->created_at?->diffForHumans() ?? 'Just now',
                'view_url' => "/admissions/{$attempt->id}",
            ]);

        $totalAttempts = AdmissionAttempt::query()
            ->when($cycle, fn ($q) => $q->where('academic_cycle_id', $cycle->id))
            ->count();

        return Inertia::render('Foundation/DemoGuide', [
            'pipeline_generator' => [
                'active_cycle' => $cycle ? ['id' => $cycle->id, 'code' => $cycle->code] : null,
                'application_window' => $window ? ['id' => $window->id, 'label' => $window->label ?? $window->code, 'status' => $window->status] : null,
                'programs' => $programs,
                'recent_attempts' => $recentAttempts,
                'total_attempts' => $totalAttempts,
                'default_count' => 10,
                'generate_url' => route('demo.generate', absolute: false),
            ],
            // Backwards-compatible props for existing tests and safe meta guide readers:
            'workflows' => $catalog->workflows($assets, $personas),
            'materials' => $assets->materials(),
            'can_control' => $canControl,
            'preparation_url' => $canControl ? route('demo.preparation.index', absolute: false) : null,
            'persona_directory' => $personas->directory(),
        ]);
    }

    public function generate(
        Request $request,
        GenerateDemoPipelineApplicants $generator,
    ): JsonResponse|RedirectResponse {
        $validated = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', 'string', 'in:pending,accepted'],
        ]);

        $count = (int) $validated['count'];
        $status = $validated['status'] ?? 'pending';

        $result = $generator->generate($count, $status);

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Successfully generated {$result['count']} applicant(s) with readable names.",
                'data' => $result,
            ]);
        }

        return back()->with('flash.success', "Successfully generated {$result['count']} applicant(s).");
    }
}
