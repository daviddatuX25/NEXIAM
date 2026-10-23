<?php

namespace App\Http\Requests\Examination;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Examination\ExamSession;
use Illuminate\Foundation\Http\FormRequest;

class ResolveExamSnapshotInterpretationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $session = $this->route('session');

        return $session instanceof ExamSession && app(CapabilityAuthorizer::class)->decideFresh(
            $this->user(),
            CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
            [
                'institution_id' => (int) $session->academicCycle->institution_id,
                'academic_cycle_id' => (int) $session->academic_cycle_id,
                'office' => 'guidance',
            ],
        )->allowed;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'expected_version' => ['required', 'integer', 'min:1'],
            'seating_mode' => ['required', 'in:simple_capacity,assigned_seating'],
            'reason' => ['required', 'string', 'min:5'],
            'provenance_notes' => ['required', 'string'],
        ];
    }
}
