<?php

namespace App\Http\Requests\Assessment;

use App\Assessment\Support\ScoringSessionContextResolver;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class RecordScoresRequest extends FormRequest
{
    protected ?GradingSession $resolvedGradingSession = null;

    public function authorize(): bool
    {
        return true;
    }

    public function resolvedGradingSession(): ?GradingSession
    {
        return $this->resolvedGradingSession;
    }

    protected function prepareForValidation(): void
    {
        $examSession = $this->route('examSession');

        if ($examSession instanceof ExamSession) {
            $this->resolvedGradingSession = app(ScoringSessionContextResolver::class)
                ->resolveForRead($examSession, $this->user());
        } elseif ($this->route('gradingSession') instanceof GradingSession) {
            $this->resolvedGradingSession = $this->route('gradingSession');
        } elseif (is_numeric($this->route('gradingSession'))) {
            $this->resolvedGradingSession = GradingSession::query()
                ->find((int) $this->route('gradingSession'));
        }

        $rawScoringMode = $this->input('scoring_mode');
        $scoringMode = is_string($rawScoringMode) && trim($rawScoringMode) !== ''
            ? strtolower(trim($rawScoringMode))
            : 'manual_raw';
        $merged = ['scoring_mode' => $scoringMode];
        $scores = $this->input('scores');
        if (is_array($scores)) {
            foreach ($scores as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $scores[$index]['scoring_mode'] = $scoringMode;
                if ($scoringMode !== 'manual_converted' && ! isset($item['score'])) {
                    if (isset($item['raw_value'])) {
                        $scores[$index]['score'] = $item['raw_value'];
                    } elseif (isset($item['value'])) {
                        $scores[$index]['score'] = $item['value'];
                    }
                }
            }

            $merged['scores'] = $scores;
        }

        $this->merge($merged);
    }

    /**
     * The attempt is bound from the route (`{attempt}`); the action guards
     * roster membership, so no body field is needed here.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $scoringMode = $this->input('scoring_mode', 'manual_raw');
        $isManualConverted = $scoringMode === 'manual_converted';
        $isProfileBound = $this->isProfileBound() || $this->usesMetricCodes();

        $scoreRules = ['required', 'numeric', 'min:0'];
        if (! $isProfileBound && ! $isManualConverted) {
            $scoreRules[] = 'max:100';
        }

        $rules = [
            'expected_baseline' => [$this->expectsJson() ? 'required' : 'sometimes', 'string', 'size:64'],
            'test_kit_version_id' => ['nullable', 'integer', 'exists:test_kit_versions,id'],
            'norm_package_version_id' => ['nullable', 'integer', 'exists:assessment_norm_package_versions,id'],
            'scoring_mode' => ['nullable', 'string', 'in:manual_raw,manual_converted'],
            'scores' => ['required', 'array', 'min:1'],
            'scores.*.scoring_mode' => ['nullable', 'string', 'in:manual_raw,manual_converted'],
            'scores.*.override_omr' => ['nullable', 'boolean'],
            'scores.*.override_reason' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['required', 'string', 'max:128'],
        ];
        if ($isManualConverted) {
            $rules['scores.*.metric_id'] = ['nullable', 'integer'];
            $rules['scores.*.metric_code'] = ['nullable', 'string'];
            $rules['scores.*.typed_outputs'] = ['required', 'array', 'min:1'];
            $rules['scores.*.typed_outputs.*'] = ['required', 'numeric', 'min:0'];
            $rules['scores.*.external_provenance'] = ['nullable', 'string', 'max:1000'];
            $rules['scores.*'] = [
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_array($value) || (! isset($value['metric_id']) && ! isset($value['metric_code']))) {
                        $fail('Each external final must identify an exact profile metric.');
                    }
                },
            ];
        } else {
            $rules['scores.*.score'] = $scoreRules;
        }
        if (! $isManualConverted && ($isProfileBound || $this->usesMetricCodes())) {
            $rules['scores.*.aptitude_area_id'] = ['nullable', 'integer'];
            $rules['scores.*.metric_id'] = ['nullable', 'integer'];
            $rules['scores.*.metric_code'] = ['nullable', 'string'];
            $rules['scores.*'] = [
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (
                        ! is_array($value)
                        || (! isset($value['aptitude_area_id']) && ! isset($value['metric_id']) && ! isset($value['metric_code']))
                    ) {
                        $fail('Each raw score entry must specify an exact metric or aptitude area identifier.');
                    }
                },
            ];
        } elseif (! $isManualConverted) {
            $rules['scores.*.aptitude_area_id'] = ['required', 'integer', 'distinct', 'exists:aptitude_areas,id'];
        }

        return $rules;
    }

    protected function isProfileBound(): bool
    {
        if ($this->filled('test_kit_version_id')) {
            return true;
        }

        $attempt = $this->route('attempt');
        $session = $this->resolvedGradingSession ?? $this->route('gradingSession');

        if ($session instanceof GradingSession) {
            if ($session->examSession?->test_kit_version_id !== null
                || $session->examSession?->test_instrument_version_id !== null) {
                return true;
            }

            if (! $attempt instanceof AdmissionAttempt) {
                return false;
            }

            $assignment = ExamAssignment::query()
                ->where('exam_session_id', $session->exam_session_id)
                ->where('admission_attempt_id', $attempt->id)
                ->active()
                ->first();

            return $assignment !== null && AssessmentAdministration::query()
                ->where('exam_assignment_id', $assignment->id)
                ->active()
                ->exists();
        }

        $examSession = $this->route('examSession');

        return $examSession instanceof ExamSession
            && $examSession->test_instrument_version_id !== null;

    }

    protected function usesMetricCodes(): bool
    {
        $scores = $this->input('scores');
        if (! is_array($scores)) {
            return false;
        }

        foreach ($scores as $item) {
            if (is_array($item) && (isset($item['metric_code']) || isset($item['metric_id']) || isset($item['code']))) {
                return true;
            }
        }

        return false;
    }
}
