<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAptitudeScoringRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Guidance-locked to `conversion_table`; the normalized/formula basis is
     * rejected (SCV-1). Semantic table rules (gaps/overlaps/coverage) are
     * enforced in `SaveAptitudeScoring` via `ConversionTableValidator`.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scoring_method' => ['required', Rule::in(['conversion_table'])],
            'max_items' => ['required', 'integer', 'min:1', 'max:1000'],
            'conversion_rows' => ['required', 'array', 'min:1'],
            'conversion_rows.*.min_score' => ['required', 'integer', 'min:0', 'max:1000', 'distinct'],
            'conversion_rows.*.max_score' => ['required', 'integer', 'gte:conversion_rows.*.min_score', 'max:1000'],
            'conversion_rows.*.output_value' => ['required', 'string', 'max:50'],
        ];
    }
}
