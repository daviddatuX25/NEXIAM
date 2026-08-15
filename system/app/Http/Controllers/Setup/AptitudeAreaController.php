<?php

namespace App\Http\Controllers\Setup;

use App\Authorization\CapabilityAuthorizer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Setup\Concerns\HasSetupCapabilities;
use App\Http\Requests\Setup\StoreAptitudeAreaRequest;
use App\Http\Requests\Setup\StoreAptitudeScoringRequest;
use App\Http\Requests\Setup\UpdateAptitudeAreaRequest;
use App\Models\Foundation\AptitudeArea;
use App\Setup\Actions\SaveAptitudeScoring;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Aptitude area catalog + conversion-table scoring. Test-Administrator gated
 * (`guidance.assessment.catalog`) — distinct from the registrar setup catalog.
 */
class AptitudeAreaController extends Controller
{
    use HasSetupCapabilities;

    public function aptitude(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $this->requireAssessment($authorizer, $request);

        $areas = AptitudeArea::query()
            ->orderBy('code')
            ->withCount('conversionRows')
            ->get();

        return Inertia::render('Setup/Aptitude', [
            'page_state' => 'ready',
            'areas' => $areas->map(fn (AptitudeArea $area): array => [
                'id' => $area->id,
                'code' => $area->code,
                'name' => $area->name,
                'version' => $area->version,
                'scoring_method' => $area->scoring_method,
                'max_items' => $area->max_items,
                'conversion_rows_count' => (int) $area->conversion_rows_count,
                'is_active' => $area->is_active,
            ])->values()->all(),
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function scoring(Request $request, AptitudeArea $area, CapabilityAuthorizer $authorizer): Response
    {
        $this->requireAssessment($authorizer, $request);

        $area->load('conversionRows');

        return Inertia::render('Setup/AptitudeScoring', [
            'page_state' => 'ready',
            'area' => [
                'id' => $area->id,
                'code' => $area->code,
                'name' => $area->name,
                'version' => $area->version,
                'scoring_method' => $area->scoring_method,
                'max_items' => $area->max_items,
            ],
            'conversion_rows' => $area->conversionRows->map(fn ($row): array => [
                'min_score' => (int) $row->min_score,
                'max_score' => (int) $row->max_score,
                'output_value' => $row->output_value,
            ])->values()->all(),
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function storeScoring(
        StoreAptitudeScoringRequest $request,
        AptitudeArea $area,
        CapabilityAuthorizer $authorizer,
        SaveAptitudeScoring $action,
    ): RedirectResponse {
        $this->requireAssessment($authorizer, $request);

        try {
            $action->handle(
                $area,
                $request->user(),
                $request->validated('scoring_method'),
                $request->integer('max_items'),
                $request->validated('conversion_rows'),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['scoring' => $exception->getMessage()])->with('page_state', 'validation');
        }

        return back()->with('status', 'Scoring configuration saved.');
    }

    public function storeAptitude(StoreAptitudeAreaRequest $request, CapabilityAuthorizer $authorizer, RecordAuditEvent $audit): RedirectResponse
    {
        $this->requireAssessment($authorizer, $request);

        $area = AptitudeArea::query()->updateOrCreate(
            [
                'code' => strtoupper($request->validated('code')),
                'version' => $request->validated('version'),
            ],
            [
                'name' => $request->validated('name'),
                'is_active' => (bool) ($request->validated('is_active') ?? true),
            ],
        );

        $audit->handle('setup.aptitude_upserted', [
            'area_id' => $area->id,
            'code' => $area->code,
            'is_active' => $area->is_active,
        ], (string) $request->user()->id);

        return back()->with('status', 'Aptitude area saved.');
    }

    public function updateAptitude(
        UpdateAptitudeAreaRequest $request,
        AptitudeArea $area,
        CapabilityAuthorizer $authorizer,
        RecordAuditEvent $audit,
    ): RedirectResponse {
        $this->requireAssessment($authorizer, $request);

        $area->update($request->validated());

        $audit->handle('setup.aptitude_upserted', [
            'area_id' => $area->id,
            'code' => $area->code,
            'is_active' => $area->is_active,
        ], (string) $request->user()->id);

        return back()->with('status', 'Aptitude area updated.');
    }
}
