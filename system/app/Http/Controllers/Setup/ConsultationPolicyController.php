<?php

declare(strict_types=1);

namespace App\Http\Controllers\Setup;

use App\Consultation\Actions\ManageConsultationPolicyAction;
use App\Consultation\Support\ConsultationMediaReadiness;
use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\UpdateConsultationPolicyRequest;
use App\Models\Consultation\ConsultationPolicy;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class ConsultationPolicyController extends Controller
{
    public function index(Request $request, ConsultationMediaReadiness $readiness): Response
    {
        $returnTo = $request->query('return_to') === route('consultation.index')
            ? route('consultation.index')
            : null;
        $institution = Institution::current();

        $cycles = AcademicCycle::query()
            ->where('institution_id', $institution->id)
            ->orderByDesc('id')
            ->get();
        $cycleId = $request->integer('cycle_id');
        $cycle = ($cycleId > 0
            ? $cycles->firstWhere('id', $cycleId)
            : $cycles->firstWhere('is_active', true)) ?? $cycles->first();

        if ($cycle === null) {
            return Inertia::render('Setup/ConsultationPolicy', [
                'academic_cycles' => [],
                'selected_cycle_id' => null,
                'policy' => null,
                'return_to' => $returnTo,
            ]);
        }

        $policy = ConsultationPolicy::query()
            ->where('institution_id', $institution->id)
            ->where('academic_cycle_id', $cycle->id)
            ->first();
        $media = $readiness->report();

        return Inertia::render('Setup/ConsultationPolicy', [
            'academic_cycles' => $cycles->map(fn (AcademicCycle $academicCycle): array => [
                'id' => $academicCycle->id,
                'label' => $academicCycle->staffLabel(),
                'is_active' => (bool) $academicCycle->is_active,
            ])->values(),
            'selected_cycle_id' => $cycle->id,
            'policy' => [
                'normal_method' => $policy?->normal_method ?? ConsultationPolicy::METHOD_ONLINE,
                'allow_online_exceptions' => (bool) ($policy?->allow_online_exceptions ?? false),
                'version' => (int) ($policy?->version ?? 0),
                'media_ready' => $media['ready'],
                'media_unavailable_reason' => $media['reason'],
            ],
            'return_to' => $returnTo,
        ]);
    }

    public function updatePolicy(UpdateConsultationPolicyRequest $request, ManageConsultationPolicyAction $action): RedirectResponse
    {
        $validated = $request->validated();
        $institution = Institution::current();
        abort_if($institution === null, 403);
        $cycle = AcademicCycle::query()
            ->where('institution_id', $institution->id)
            ->findOrFail((int) $validated['academic_cycle_id']);

        try {
            $action->handle($cycle, $request->user(), [
                'normal_method' => $validated['normal_method'],
                'allow_online_exceptions' => (bool) $validated['allow_online_exceptions'],
                'expected_version' => (int) $validated['expected_version'],
            ]);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('status', 'Consultation policy updated.');
    }
}
