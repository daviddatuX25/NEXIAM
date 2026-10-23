<?php

namespace App\Admission\Actions;

use App\Admission\Queries\CurrentIntakeQuery;
use App\Models\Admission\ApplicationOpeningAvailabilityState;
use App\Models\Communications\PublicPolicy;
use App\Models\Communications\PublicPolicyRevision;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ObserveApplicationOpeningAvailability
{
    public function __construct(private readonly CurrentIntakeQuery $currentIntake) {}

    public function observeSourceChange(Model $source): ?ApplicationOpeningAvailabilityState
    {
        $institutionId = match (true) {
            $source instanceof AcademicCycle => (int) $source->institution_id,
            $source instanceof ApplicationWindow => (int) AcademicCycle::query()->whereKey($source->academic_cycle_id)->value('institution_id'),
            $source instanceof PublicPolicy => (int) $source->institution_id,
            $source instanceof PublicPolicyRevision => (int) PublicPolicy::query()->whereKey($source->public_policy_id)->value('institution_id'),
            default => throw new InvalidArgumentException('Unsupported application-opening availability source.'),
        };
        if ($institutionId < 1) {
            return null;
        }

        return $this->observeInstitution($institutionId, CarbonImmutable::now());
    }

    public function observeInstitution(int $institutionId, ?CarbonImmutable $sourceTransitionAt = null): ?ApplicationOpeningAvailabilityState
    {
        if (! config('application-opening-alerts.enabled')) {
            return null;
        }

        return DB::transaction(function () use ($institutionId, $sourceTransitionAt): ApplicationOpeningAvailabilityState {
            $institution = Institution::query()->findOrFail($institutionId);
            DB::table('application_opening_availability_states')->insertOrIgnore([
                'institution_id' => $institutionId,
                'is_available' => false,
                'generation' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $state = ApplicationOpeningAvailabilityState::query()->whereKey($institutionId)->lockForUpdate()->firstOrFail();
            $period = $this->currentIntake->forInstitution($institution);
            if ($period === null) {
                if ($state->is_available) {
                    $state->forceFill(['is_available' => false, 'available_since' => null, 'observed_at' => now()])->save();
                }

                return $state->fresh();
            }

            $cycleId = (int) $period['cycle']->id;
            $windowId = (int) $period['window']->id;
            $opensAt = $period['window']->opens_at;
            $sameOpening = $state->is_available
                && (int) $state->academic_cycle_id === $cycleId
                && (int) $state->application_window_id === $windowId
                && $state->opens_at?->equalTo($opensAt);
            if (! $sameOpening) {
                $readyAt = $sourceTransitionAt !== null && $sourceTransitionAt->greaterThan($opensAt)
                    ? $sourceTransitionAt
                    : $opensAt;
                $state->forceFill([
                    'is_available' => true,
                    'generation' => (int) $state->generation + 1,
                    'academic_cycle_id' => $cycleId,
                    'application_window_id' => $windowId,
                    'opens_at' => $opensAt,
                    'available_since' => $readyAt,
                    'observed_at' => now(),
                ])->save();
            }

            return $state->fresh();
        });
    }
}
