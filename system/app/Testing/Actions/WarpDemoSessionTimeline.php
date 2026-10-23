<?php

namespace App\Testing\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Examination\Support\CreateExamSessionEvaluator;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\User;
use App\Testing\PracticeFixtureSet;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class WarpDemoSessionTimeline
{
    public const STAGES = ['72h-reminder', '24h-reminder', 'tomorrow', '2h-reminder', '1h-reminder', 'door-open', 'soon', 'live', 'in-progress', 'ended'];

    /** @return list<array{key: string, label: string, description: string}> */
    public function options(): array
    {
        return array_map(fn (string $key): array => [
            'key' => $key, 'label' => $key,
            'description' => 'Changes the selected active practice session timeline only; no reminder is sent.',
        ], array_values(array_diff(self::STAGES, ['tomorrow', 'in-progress'])));
    }

    /** @return array{session: ExamSession, description: string, stage: string, status: null} */
    public function handle(ExamSession $session, string $stage, User $actor, PracticeFixtureSet $set, ?CarbonImmutable $referenceTime = null): array
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Practice timing is restricted to local/testing.');
        }
        if (! in_array($stage, self::STAGES, true)) {
            throw new InvalidArgumentException('The requested timeline preset is invalid.');
        }

        return DB::transaction(function () use ($session, $stage, $actor, $set, $referenceTime): array {
            $set = PracticeFixtureSet::query()->lockForUpdate()->findOrFail($set->id);
            $session = ExamSession::query()->lockForUpdate()->findOrFail($session->id);
            $institution = Institution::current();
            $cycle = AcademicCycle::query()->whereKey($set->academic_cycle_id)
                ->where('institution_id', $institution->id)->where('is_active', true)->firstOrFail();
            if ($set->status !== 'active' || (int) $set->created_by_user_id !== (int) $actor->id
                || (int) $set->institution_id !== (int) $institution->id
                || $session->practice_fixture_set_id !== $set->id
                || (int) $session->academic_cycle_id !== (int) $cycle->id
                || (int) $session->room?->institution_id !== (int) $institution->id
                || in_array($session->status, [ExamSession::STATUS_COMPLETED, ExamSession::STATUS_CANCELLED], true)) {
                throw new RuntimeException('Timing requires your active practice set and an editable owned session in the current scope.');
            }
            if (! app(CapabilityAuthorizer::class)->decideFresh($actor, CapabilityCatalog::DEMO_STAGE_CONTROL,
                ['institution_id' => $institution->id, 'cycle' => $cycle->code])->allowed) {
                throw new RuntimeException('Current practice-control authority is required.');
            }
            $minutes = match ($stage) {
                '72h-reminder' => 4320,
                '24h-reminder', 'tomorrow' => 1440,
                '2h-reminder' => 120,
                '1h-reminder' => 60,
                'door-open' => 20,
                'soon' => 45,
                'live', 'in-progress' => -30,
                'ended' => -155,
            };
            $starts = ($referenceTime ?? CarbonImmutable::now())->addMinutes($minutes);
            $ends = $starts->addHours(2);
            $preflight = app(CreateExamSessionEvaluator::class)->evaluate($cycle, $session->room,
                $starts, $ends, $session->code, (int) $session->id);
            if ($preflight->outcome !== 'pass') {
                $details = array_column(array_filter($preflight->checks, static fn (array $check): bool => $check['status'] !== 'pass'), 'detail');
                throw new RuntimeException('The selected timing is unavailable: '.implode('; ', $details).'.');
            }
            $session->forceFill(['starts_at' => $starts, 'effective_end_at' => $ends])->save();

            return ['session' => $session->fresh(), 'stage' => $stage, 'status' => null,
                'description' => 'Selected practice timeline changed; session status, policy and all reminder delivery remain unchanged.'];
        });
    }
}
