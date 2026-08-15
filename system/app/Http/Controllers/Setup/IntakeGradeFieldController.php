<?php

namespace App\Http\Controllers\Setup;

use App\Authorization\CapabilityAuthorizer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Setup\Concerns\HasSetupCapabilities;
use App\Http\Requests\Setup\StoreIntakeGradeFieldRequest;
use App\Http\Requests\Setup\UpdateIntakeGradeFieldRequest;
use App\Models\Foundation\Institution;
use App\Models\Foundation\IntakeGradeField;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Intake grade field catalog (institutional setup catalog, shared `setup.manage`).
 */
class IntakeGradeFieldController extends Controller
{
    use HasSetupCapabilities;

    public function grades(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $this->requireSetup($authorizer, $request);

        return Inertia::render('Setup/Grades', [
            'page_state' => 'ready',
            'fields' => IntakeGradeField::query()
                ->where('institution_id', Institution::current()->id)
                ->orderBy('sort_order')
                ->get(),
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function storeGrade(StoreIntakeGradeFieldRequest $request, CapabilityAuthorizer $authorizer, RecordAuditEvent $audit): RedirectResponse
    {
        $this->requireSetup($authorizer, $request);

        $field = IntakeGradeField::query()->create([
            'institution_id' => Institution::current()->id,
            'code' => strtoupper($request->validated('code')),
            'label' => $request->validated('label'),
            'field_kind' => $request->validated('field_kind'),
            'sort_order' => (int) ($request->validated('sort_order') ?? 100),
            'is_required' => (bool) ($request->validated('is_required') ?? false),
            'is_active' => (bool) ($request->validated('is_active') ?? true),
            'min_value' => 0,
            'max_value' => 100,
        ]);

        $audit->handle('setup.grade_field_upserted', [
            'field_id' => $field->id,
            'code' => $field->code,
            'is_active' => $field->is_active,
        ], (string) $request->user()->id);

        return back()->with('status', 'Grade field saved.');
    }

    public function updateGrade(
        UpdateIntakeGradeFieldRequest $request,
        IntakeGradeField $field,
        CapabilityAuthorizer $authorizer,
        RecordAuditEvent $audit,
    ): RedirectResponse {
        $this->requireSetup($authorizer, $request);

        $field->update($request->validated());

        $audit->handle('setup.grade_field_upserted', [
            'field_id' => $field->id,
            'code' => $field->code,
            'is_active' => $field->is_active,
        ], (string) $request->user()->id);

        return back()->with('status', 'Grade field updated.');
    }
}
