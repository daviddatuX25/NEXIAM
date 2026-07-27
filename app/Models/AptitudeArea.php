<?php

namespace App\Models;

use App\Services\FormulaEvaluator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AptitudeArea extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'max_items',
        'formula',
        'scoring_method',
        'display_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'max_items' => 'integer',
            'display_order' => 'integer',
        ];
    }

    public function applicantScores(): HasMany
    {
        return $this->hasMany(ApplicantScore::class, 'aptitude_area_id');
    }

    public function percentileConversions(): HasMany
    {
        return $this->hasMany(PercentileConversion::class);
    }

    public function lookupPercentile(int $rawScore): ?string
    {
        return $this->percentileConversions()
            ->where('raw_score', $rawScore)
            ->value('percentile_output');
    }

    public function resolveScore(float $rawScore): array
    {
        if ($this->scoring_method === 'conversion_table') {
            $percentileString = $this->lookupPercentile((int) $rawScore);

            return [
                'normalized_score' => null,
                'percentile_string' => $percentileString ?? 'N/A',
            ];
        }

        return [
            'normalized_score' => $this->computeNormalizedScore($rawScore),
            'percentile_string' => null,
        ];
    }

    public function computeNormalizedScore(float $rawScore): ?float
    {
        if (! $this->formula) {
            return null;
        }

        return app(FormulaEvaluator::class)->evaluate($this->formula, [
            'x' => $rawScore,
            'max_items' => $this->max_items,
        ]);
    }
}
