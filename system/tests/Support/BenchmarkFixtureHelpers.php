<?php

use App\AdmissionEngine\Actions\ActivateAdmissionBenchmarkSet;
use App\AdmissionEngine\Actions\BenchmarkExecutionContext;
use App\AdmissionEngine\Actions\PublishProgramBenchmarkSet;
use App\AdmissionEngine\Actions\SaveProgramBenchmarkDraft;
use App\Foundation\Support\TagudinUndergraduateCatalog;
use App\Models\Admission\AdmissionBenchmarkActivation;
use App\Models\Admission\AdmissionBenchmarkSet;
use App\Models\Foundation\AcademicCycle;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Completes missing required cells only while constructing isolated synthetic test data.
 * The source set and its digest are re-pinned before the test exercises domain Actions.
 */
function completeSyntheticBenchmarkTestBase(): AdmissionBenchmarkSet
{
    if (! app()->environment('testing')) {
        throw new RuntimeException('Synthetic benchmark completion is restricted to the test environment.');
    }

    $activation = AdmissionBenchmarkActivation::currentForCycle((int) AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->value('id'));
    if ($activation === null) {
        throw new RuntimeException('A current published benchmark is required for this test fixture.');
    }

    $set = AdmissionBenchmarkSet::query()
        ->with(['features', 'exemplars.values.feature'])
        ->findOrFail($activation->benchmark_set_id);
    foreach ($set->exemplars as $exemplar) {
        $tier = (int) $exemplar->tier_level;
        foreach ($set->features as $feature) {
            if (! $feature->is_required) {
                continue;
            }

            $cell = $exemplar->values->firstWhere('benchmark_feature_id', $feature->id);
            if ($cell === null || ! $cell->is_missing) {
                continue;
            }

            $value = match ($feature->logical_type) {
                'categorical' => 'STEM',
                'binary' => $tier === 5 ? 0 : 1,
                default => [1 => 92.0, 2 => 87.0, 3 => 82.0, 4 => 77.0, 5 => 60.0][$tier],
            };
            DB::table('admission_benchmark_exemplar_values')
                ->where('id', $cell->id)
                ->update([
                    'is_missing' => false,
                    'numeric_value' => $feature->logical_type === 'categorical' ? null : $value,
                    'categorical_value' => $feature->logical_type === 'categorical' ? $value : null,
                ]);
        }

        if ($tier === 5) {
            $hasNegativeAnchor = $exemplar->values->contains(static fn ($cell): bool => str_starts_with((string) $cell->feature->feature_key, 'riasec_')
                && ! $cell->is_missing && (float) $cell->numeric_value > 0);
            if (! $hasNegativeAnchor) {
                $feature = $set->features->first(static fn ($item): bool => str_starts_with((string) $item->feature_key, 'riasec_'));
                $cell = $feature === null ? null : $exemplar->values->firstWhere('benchmark_feature_id', $feature->id);
                if ($cell !== null) {
                    DB::table('admission_benchmark_exemplar_values')
                        ->where('id', $cell->id)
                        ->update(['is_missing' => false, 'numeric_value' => 1, 'categorical_value' => null]);
                }
            }
        }
    }

    $freshSet = AdmissionBenchmarkSet::query()
        ->with(['features', 'exemplars.values.feature'])
        ->findOrFail($set->id);
    DB::table('admission_benchmark_sets')
        ->where('id', $freshSet->id)
        ->update(['content_sha256' => $freshSet->computedContentDigest()]);

    return AdmissionBenchmarkSet::query()
        ->with(['features', 'exemplars.values.feature'])
        ->findOrFail($freshSet->id);
}

/**
 * Builds a complete, explicitly synthetic benchmark successor for publication tests.
 * Missing required cells are filled only in the isolated test database; these values
 * are not institutional evidence and must never be copied into developer/live seeders.
 */
function publishSyntheticCompleteBenchmarkSuccessor(
    User $actor,
    AcademicCycle $cycle,
    bool $activate = true,
    string $suffix = 'fixture',
): AdmissionBenchmarkSet {
    if (! app()->environment('testing')) {
        throw new RuntimeException('Synthetic benchmark completion is restricted to the test environment.');
    }

    $activation = AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id);
    if ($activation === null) {
        throw new RuntimeException('A current published benchmark is required for this test fixture.');
    }

    $base = AdmissionBenchmarkSet::query()
        ->with(['features', 'exemplars.program', 'exemplars.values.feature'])
        ->findOrFail($activation->benchmark_set_id);
    $retiredCodes = app(TagudinUndergraduateCatalog::class)->retiredCodes();
    $revisionIdsByProgram = [];

    foreach ($base->exemplars->groupBy('program_id') as $programId => $exemplars) {
        $program = $exemplars->first()->program;
        if (in_array((string) $program->code, $retiredCodes, true)) {
            continue;
        }

        $content = ['tiers' => []];
        foreach (range(1, 5) as $tier) {
            $exemplar = $exemplars->firstWhere('tier_level', $tier);
            $values = [];
            foreach ($exemplar?->values ?? [] as $cell) {
                $feature = $cell->feature;
                $values[$feature->feature_key] = $cell->is_missing
                    ? null
                    : ($feature->logical_type === 'categorical' ? $cell->categorical_value : (float) $cell->numeric_value);
            }

            foreach ($base->features as $feature) {
                if (! array_key_exists($feature->feature_key, $values)) {
                    $values[$feature->feature_key] = null;
                }
                if ($feature->is_required && $values[$feature->feature_key] === null) {
                    $values[$feature->feature_key] = match ($feature->logical_type) {
                        'categorical' => 'STEM',
                        'binary' => $tier === 5 ? 0 : 1,
                        default => [1 => 92.0, 2 => 87.0, 3 => 82.0, 4 => 77.0, 5 => 60.0][$tier],
                    };
                }
            }

            if ($tier === 5) {
                $hasNegativeAnchor = false;
                foreach ($values as $key => $value) {
                    if (str_starts_with((string) $key, 'riasec_') && (float) $value > 0) {
                        $hasNegativeAnchor = true;
                        break;
                    }
                }
                if (! $hasNegativeAnchor) {
                    $riasecFeature = $base->features->first(static fn ($feature): bool => str_starts_with((string) $feature->feature_key, 'riasec_'));
                    if ($riasecFeature !== null) {
                        $values[$riasecFeature->feature_key] = 1;
                    }
                }
            }
            if ($tier === 1 && isset($values['gwa']) && is_numeric($values['gwa'])) {
                $values['gwa'] = min(100.0, round((float) $values['gwa'] + 0.5, 2));
            }

            ksort($values, SORT_STRING);
            $content['tiers'][(string) $tier] = ['values' => $values];
        }

        $revision = app(SaveProgramBenchmarkDraft::class)->handle(
            $actor,
            $program,
            $cycle,
            $content,
            0,
            'Test-only completion of missing required exemplar cells; synthetic, not institutional evidence.',
            'synthetic-complete-save-'.$suffix.'-'.$programId,
        );
        $revisionIdsByProgram[(int) $programId] = (int) $revision->id;
    }

    if ($revisionIdsByProgram === []) {
        throw new RuntimeException('The benchmark fixture has no retained programme revisions to publish.');
    }

    $published = app(PublishProgramBenchmarkSet::class)->handle(
        $actor,
        $cycle,
        $revisionIdsByProgram,
        (string) $base->content_sha256,
        'Test-only complete synthetic publication fixture; not institutional evidence.',
        'synthetic-complete-publish-'.$suffix,
    );

    if ($activate) {
        $current = AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id);
        app(ActivateAdmissionBenchmarkSet::class)->handle(
            $cycle,
            $published,
            BenchmarkExecutionContext::human($actor),
            $current?->id,
            (string) $published->content_sha256,
            'Test-only activation of a complete synthetic publication fixture.',
            'synthetic-complete-activate-'.$suffix,
        );
    }

    return $published;
}
