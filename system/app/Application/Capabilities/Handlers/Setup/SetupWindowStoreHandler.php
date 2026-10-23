<?php

namespace App\Application\Capabilities\Handlers\Setup;

use App\Ai\Proposals\ProposalReview;
use App\Ai\Proposals\ProposalViewContract;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\User;
use App\Setup\Actions\StoreApplicationWindow;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;

final class SetupWindowStoreHandler
{
    private const CAPABILITY_ID = 'setup.windows.store@1';

    public function __construct(
        private readonly StoreApplicationWindow $action,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input = [], ?Authenticatable $actor = null): array
    {
        $cycleId = $input['academic_cycle_id'] ?? null;
        $code = $input['code'] ?? null;
        $name = $input['name'] ?? null;
        $opensAt = $input['opens_at'] ?? null;
        $closesAt = $input['closes_at'] ?? null;
        $status = $input['status'] ?? 'draft';
        if (! (is_int($cycleId) || (is_string($cycleId) && ctype_digit($cycleId))) || (int) $cycleId < 1) {
            throw new RuntimeException('academic_cycle_id_invalid');
        }
        if (! is_string($code) || trim($code) === '') {
            throw new RuntimeException('window_code_invalid');
        }
        if (! is_string($name) || trim($name) === '') {
            throw new RuntimeException('window_name_invalid');
        }
        if (! is_string($opensAt) || trim($opensAt) === '') {
            throw new RuntimeException('opens_at_invalid');
        }
        if (! is_string($closesAt) || trim($closesAt) === '') {
            throw new RuntimeException('closes_at_invalid');
        }
        if (! is_string($status) || ! in_array($status, ['draft', 'published', 'closed'], true)) {
            throw new RuntimeException('window_status_invalid');
        }

        $normalizedCode = strtoupper(trim($code));
        $attributes = [
            'academic_cycle_id' => $cycleId,
            'code' => $normalizedCode,
            'name' => trim($name),
            'opens_at' => $opensAt,
            'closes_at' => $closesAt,
            'status' => $status,
        ];
        $existing = ApplicationWindow::query()
            ->where('academic_cycle_id', $cycleId)
            ->where('code', $normalizedCode)
            ->first();

        if (! (bool) ($input['execute'] ?? false)) {
            $formatInstant = static function (string $raw): string {
                if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D', $raw)) {
                    return $raw;
                }

                return Carbon::parse($raw)->setTimezone('Asia/Manila')->format('Y-m-d\TH:i:sP');
            };
            $opensAtIso = $formatInstant($opensAt);
            $closesAtIso = $formatInstant($closesAt);

            $cycle = AcademicCycle::query()->find($cycleId);
            $cycleRecord = $cycle !== null
                ? ['type' => 'record', 'label' => (string) $cycle->code, 'context' => array_values(array_filter([(string) $cycle->staffLabel()]))]
                : ['type' => 'null'];

            $statusLabels = ['draft' => 'Draft', 'published' => 'Published', 'closed' => 'Closed'];

            $beforeOpensAt = $existing !== null && $existing->opens_at !== null
                ? ['type' => 'instant', 'value' => Carbon::parse($existing->opens_at)->setTimezone('Asia/Manila')->format('Y-m-d\TH:i:sP'), 'precision' => 'second', 'display_zone' => 'Asia/Manila']
                : ['type' => 'missing'];
            $afterOpensAt = ['type' => 'instant', 'value' => $opensAtIso, 'precision' => 'second', 'display_zone' => 'Asia/Manila'];

            $beforeClosesAt = $existing !== null && $existing->closes_at !== null
                ? ['type' => 'instant', 'value' => Carbon::parse($existing->closes_at)->setTimezone('Asia/Manila')->format('Y-m-d\TH:i:sP'), 'precision' => 'second', 'display_zone' => 'Asia/Manila']
                : ['type' => 'missing'];
            $afterClosesAt = ['type' => 'instant', 'value' => $closesAtIso, 'precision' => 'second', 'display_zone' => 'Asia/Manila'];

            $fields = [
                ProposalViewContract::field('cycle', 'Academic cycle', $cycleRecord, $cycleRecord, 'context'),
                ProposalViewContract::field(
                    'code',
                    'Window code',
                    $existing !== null ? ['type' => 'code', 'value' => (string) $existing->code] : ['type' => 'missing'],
                    ['type' => 'code', 'value' => $normalizedCode],
                    'choice',
                ),
                ProposalViewContract::field(
                    'name',
                    'Window name',
                    $existing !== null ? ['type' => 'text', 'value' => (string) $existing->name, 'multiline' => false] : ['type' => 'missing'],
                    ['type' => 'text', 'value' => trim($name), 'multiline' => false],
                    'choice',
                ),
                ProposalViewContract::field('opens_at', 'Opening date/time', $beforeOpensAt, $afterOpensAt, 'choice'),
                ProposalViewContract::field('closes_at', 'Closing date/time', $beforeClosesAt, $afterClosesAt, 'choice'),
                ProposalViewContract::field(
                    'status',
                    'Window status',
                    $existing !== null ? ['type' => 'enum', 'code' => (string) $existing->status, 'label' => $statusLabels[$existing->status] ?? ucfirst($existing->status)] : ['type' => 'missing'],
                    ['type' => 'enum', 'code' => $status, 'label' => $statusLabels[$status] ?? ucfirst($status)],
                    'choice',
                ),
            ];

            $command = [
                'academic_cycle_id' => is_int($cycleId) ? $cycleId : (int) $cycleId,
                'code' => $normalizedCode,
                'name' => trim($name),
                'opens_at' => $opensAt,
                'closes_at' => $closesAt,
                'status' => $status,
            ];

            $coverage = [
                'academic_cycle_id' => ['classification' => 'display', 'field' => 'cycle'],
                'code' => ['classification' => 'display', 'field' => 'code'],
                'name' => ['classification' => 'display', 'field' => 'name'],
                'opens_at' => ['classification' => 'display', 'field' => 'opens_at'],
                'closes_at' => ['classification' => 'display', 'field' => 'closes_at'],
                'status' => ['classification' => 'display', 'field' => 'status'],
            ];

            $parsedOpensAt = Carbon::parse($opensAtIso);
            $parsedClosesAt = Carbon::parse($closesAtIso);
            $timeValid = $parsedClosesAt->gt($parsedOpensAt);
            $checks = [
                [
                    'key' => 'window_timing',
                    'label' => 'Closing time is after opening time',
                    'status' => $timeValid ? 'pass' : 'fail',
                    'detail' => $timeValid ? 'Application window schedule is chronological' : 'Closing time must be after opening time',
                ],
            ];
            $outcome = $timeValid ? 'pass' : 'fail';

            $result = [
                'mode' => 'propose',
                'ok' => $outcome === 'pass',
                'capability_id' => self::CAPABILITY_ID,
                'command_input' => $command,
                'impact' => ['academic_cycle_id' => (int) $cycleId],
                'proposal_review' => [
                    'version' => 1,
                    'operation' => $existing !== null ? 'update' : 'create',
                    'title' => 'Update application window',
                    'sections' => [
                        ProposalViewContract::fieldsSection('application_window', 'Application window configuration', $fields),
                    ],
                    'preflight' => ProposalViewContract::preflight($outcome, $checks),
                    'coverage' => $coverage,
                ],
                'diff' => [[
                    'key' => 'application_window',
                    'label' => 'Application window',
                    'before' => $existing === null ? 'None' : $existing->name.' ('.$existing->code.')',
                    'after' => trim($name).' ('.$normalizedCode.')',
                ]],
                'evidence' => ['outcome' => $outcome, 'checks' => $checks],
            ];
            ProposalReview::fromResult(self::CAPABILITY_ID, $result);

            return $result;
        }

        if (! $actor instanceof User) {
            throw new RuntimeException('staff_actor_required');
        }

        $window = $this->action->handle($attributes, $actor);

        return [
            'mode' => 'execute',
            'ok' => true,
            'application_window_id' => (int) $window->id,
            'academic_cycle_id' => (int) $window->academic_cycle_id,
            'code' => $window->code,
            'status' => $window->status,
        ];
    }
}
