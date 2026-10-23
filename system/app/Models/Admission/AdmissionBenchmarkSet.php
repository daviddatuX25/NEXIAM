<?php

namespace App\Models\Admission;

use App\Models\Foundation\Institution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class AdmissionBenchmarkSet extends Model
{
    protected static function booted(): void
    {
        static::updating(function (self $set): void {
            if ($set->getOriginal('status') === 'published') {
                throw new RuntimeException('Published benchmark sets are immutable.');
            }
        });

        static::deleting(function (self $set): void {
            if ($set->status === 'published') {
                throw new RuntimeException('Published benchmark sets cannot be deleted.');
            }
        });
    }

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['raw_ranges_json' => 'array', 'publication_manifest_json' => 'array', 'published_at' => 'datetime', 'version' => 'integer', 'lock_version' => 'integer'];
    }

    /** @return HasMany<AdmissionBenchmarkFeature, $this> */
    public function features(): HasMany
    {
        return $this->hasMany(AdmissionBenchmarkFeature::class, 'benchmark_set_id')->orderBy('position');
    }

    /** @return HasMany<AdmissionBenchmarkExemplar, $this> */
    public function exemplars(): HasMany
    {
        return $this->hasMany(AdmissionBenchmarkExemplar::class, 'benchmark_set_id');
    }

    /** @return HasMany<AdmissionBenchmarkActivation, $this> */
    public function activations(): HasMany
    {
        return $this->hasMany(AdmissionBenchmarkActivation::class, 'benchmark_set_id');
    }

    public function computedContentDigest(): string
    {
        $this->loadMissing(['features', 'exemplars.program', 'exemplars.values.feature']);
        $features = $this->features->map(static function (AdmissionBenchmarkFeature $feature): array {
            $definition = [
                $feature->feature_key,
                $feature->label,
                $feature->logical_type,
                $feature->aptitude_area_id,
                (bool) $feature->is_required,
            ];
            if (is_array($feature->source_definition_json)) {
                $definition[] = $feature->source_definition_json;
            }

            return $definition;
        })->all();
        $exemplars = $this->exemplars->sortBy([['program_id', 'asc'], ['tier_level', 'asc']])->map(static function (AdmissionBenchmarkExemplar $exemplar): array {
            $values = [];
            foreach ($exemplar->values->sortBy('feature.feature_key') as $value) {
                $values[$value->feature->feature_key] = $value->is_missing
                    ? null
                    : ($value->feature->logical_type === 'categorical' ? $value->categorical_value : (float) $value->numeric_value);
            }

            return [
                'program_code' => $exemplar->program->code,
                'tier_level' => (int) $exemplar->tier_level,
                'tier_label' => $exemplar->tier_label,
                'target_class' => (int) $exemplar->target_class,
                'values' => $values,
            ];
        })->values()->all();

        return hash('sha256', json_encode(['features' => $features, 'exemplars' => $exemplars], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }

    public function computedSchemaDigest(): string
    {
        $this->loadMissing('features');
        $schema = $this->features->map(static function (AdmissionBenchmarkFeature $feature): array {
            $definition = [
                $feature->feature_key,
                $feature->label,
                $feature->logical_type,
                $feature->aptitude_area_id,
                (bool) $feature->is_required,
            ];
            if (is_array($feature->source_definition_json)) {
                $definition[] = $feature->source_definition_json;
            }

            return $definition;
        })->all();

        return hash('sha256', json_encode($schema, JSON_THROW_ON_ERROR));
    }

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}
