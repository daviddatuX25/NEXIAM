<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Applicant;
use App\Models\SystemSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDirectAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! (bool) SystemSetting::get('allow_direct_assessment', true)) {
            return false;
        }

        return $this->user()?->hasAnyRole(['super_admin', 'registrar_administrator', 'test_administrator']) ?? false;
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'applicant_ids' => ['required', 'array', 'min:1', 'distinct'],
            'applicant_ids.*' => ['integer'],
            'label' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $applicantIds = $this->input('applicant_ids', []);
            if (empty($applicantIds) || ! is_array($applicantIds)) {
                return;
            }

            $applicants = Applicant::with(['application', 'examSessions.gradingSession'])
                ->whereIn('id', $applicantIds)
                ->get()
                ->keyBy('id');

            foreach ($applicantIds as $index => $id) {
                $attribute = "applicant_ids.{$index}";
                $applicant = $applicants->get($id);

                if (! $applicant) {
                    $validator->errors()->add($attribute, "Applicant ID {$id} does not exist.");

                    continue;
                }

                if (! $applicant->application || $applicant->application->status !== 'accepted') {
                    $validator->errors()->add($attribute, "Applicant ID {$id} does not have an accepted application.");
                }

                $inActiveSession = $applicant->examSessions
                    ->some(fn ($es) => $es->gradingSession && ! in_array($es->gradingSession->status, ['finalized'], true));

                if ($inActiveSession) {
                    $validator->errors()->add($attribute, "Applicant ID {$id} is already in an active grading session.");
                }
            }
        });
    }
}
