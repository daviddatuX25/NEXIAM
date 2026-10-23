<?php

namespace App\Setup\Actions;

use App\Admission\Queries\CurrentIntakeQuery;
use App\Admission\Support\CommandIdempotency;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use App\Models\User;
use App\Setup\Queries\AcademicCycleCalendarQuery;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class ExtendApplicationWindow
{
    private const COMMAND = 'setup.application_window.extend';

    private const MAX_SUPPORTED_DATE = '9999-12-31';

    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly CommandIdempotency $idempotency,
        private readonly CurrentIntakeQuery $currentIntake,
        private readonly RecordAuditEvent $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $baseline
     * @return array{
     *     cycle_id: int,
     *     window_id: int,
     *     days: int,
     *     old_closes_at: string,
     *     new_closes_at: string,
     *     old_cycle_closes_on: string,
     *     new_cycle_closes_on: string,
     *     reason: string
     * }
     */
    public function handle(
        User $actor,
        AcademicCycle $cycle,
        int $windowId,
        int $days,
        array $baseline,
        string $idempotencyKey,
        string $reason,
    ): array {
        $this->validateCommand($windowId, $days, $baseline, $idempotencyKey, $reason);

        return DB::transaction(function () use ($actor, $cycle, $windowId, $days, $baseline, $idempotencyKey, $reason): array {
            $currentInstitution = Institution::current();
            $institution = Institution::query()->lockForUpdate()->findOrFail($currentInstitution->getKey());
            $scope = ['institution_id' => (int) $institution->getKey()];
            $authorized = $this->authorizer->decideFresh($actor, CapabilityCatalog::SETUP_CYCLES_MANAGE, $scope)->allowed
                || $this->authorizer->decideFresh($actor, CapabilityCatalog::SETUP_INSTITUTION_MANAGE, $scope)->allowed;

            if (! $authorized) {
                throw new AuthorizationException('Application-window extension requires current academic-cycle or institutional setup authority.');
            }

            $lockedCycle = AcademicCycle::query()
                ->where('institution_id', $institution->getKey())
                ->lockForUpdate()
                ->findOrFail($cycle->getKey());
            $window = ApplicationWindow::query()
                ->where('academic_cycle_id', $lockedCycle->getKey())
                ->lockForUpdate()
                ->findOrFail($windowId);

            $commandPayload = [
                'institution_id' => (int) $institution->getKey(),
                'cycle_id' => (int) $lockedCycle->getKey(),
                'window_id' => (int) $window->getKey(),
                'days' => $days,
                'baseline' => $this->normalizeBaseline($baseline),
                'reason' => trim($reason),
            ];

            try {
                $receipt = $this->idempotency->claim(
                    $actor,
                    self::COMMAND,
                    'cycle:'.$lockedCycle->getKey().':window:'.$window->getKey(),
                    $commandPayload,
                    $idempotencyKey,
                );
            } catch (RuntimeException $exception) {
                if ($exception->getMessage() !== 'Idempotency key was already used with a different request payload.') {
                    throw $exception;
                }

                throw ValidationException::withMessages([
                    'idempotency_key' => 'This request key was already used for a different extension. Refresh and try again.',
                ]);
            }

            /** @var array<string, mixed> $receiptPayload */
            $receiptPayload = (array) $receipt->payload;
            if (is_array($receiptPayload['result'] ?? null)) {
                return $receiptPayload['result'];
            }

            $currentBaseline = AcademicCycleCalendarQuery::baselineFor($lockedCycle, $window);
            if ($this->normalizeBaseline($baseline) !== $currentBaseline) {
                throw ValidationException::withMessages([
                    'baseline' => 'The application dates changed since this calendar was loaded. Refresh the calendar before confirming an extension.',
                ]);
            }

            $this->assertCurrentPrimaryAndOpen($institution, $lockedCycle, $window);

            if ($lockedCycle->closes_on->toDateString() !== $window->closes_at->copy()->utc()->toDateString()) {
                throw ValidationException::withMessages([
                    'window_id' => 'The academic-year and application-window deadlines are independently bounded. Use Edit to review and align them before extending.',
                ]);
            }

            $oldClose = CarbonImmutable::instance($window->closes_at->copy()->utc());
            $newClose = $this->extendedDeadline($oldClose, $days);
            $oldCycleClose = $lockedCycle->closes_on->toDateString();
            $newCycleClose = $newClose->toDateString();

            $window->update(['closes_at' => $newClose]);
            $lockedCycle->update(['closes_on' => $newCycleClose]);

            $result = [
                'cycle_id' => (int) $lockedCycle->getKey(),
                'window_id' => (int) $window->getKey(),
                'days' => $days,
                'old_closes_at' => $oldClose->format('Y-m-d H:i:s'),
                'new_closes_at' => $newClose->format('Y-m-d H:i:s'),
                'old_cycle_closes_on' => $oldCycleClose,
                'new_cycle_closes_on' => $newCycleClose,
                'reason' => trim($reason),
            ];

            $this->audit->handle('setup.application_window_extended', [
                ...$result,
                'institution_id' => (int) $institution->getKey(),
                'reason' => trim($reason),
                'idempotency_key' => $idempotencyKey,
            ], (string) $actor->getKey());

            $receipt->forceFill([
                'payload' => [...$receiptPayload, 'result' => $result],
            ])->saveOrFail();

            return $result;
        }, 3);
    }

    /** @param array<string, mixed> $baseline */
    private function validateCommand(int $windowId, int $days, array $baseline, string $idempotencyKey, string $reason): void
    {
        if ($windowId < 1 || $days < 1 || ! Str::isUuid($idempotencyKey)) {
            throw ValidationException::withMessages([
                'days' => 'Choose a positive number of calendar days and a valid request identity.',
            ]);
        }

        $this->normalizeBaseline($baseline);

        if (mb_strlen(trim($reason)) < 3 || mb_strlen(trim($reason)) > 500) {
            throw ValidationException::withMessages([
                'reason' => 'Enter a short reason of 3 to 500 characters.',
            ]);
        }

        if ((int) ($baseline['window_id'] ?? 0) !== $windowId) {
            throw ValidationException::withMessages([
                'baseline' => 'The extension target does not match the loaded calendar.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $baseline
     * @return array{
     *     cycle_id: int,
     *     cycle_is_active: bool,
     *     cycle_opens_on: string,
     *     cycle_closes_on: string,
     *     school_year: string,
     *     semester: string,
     *     window_id: int,
     *     opens_at: string,
     *     closes_at: string,
     *     status: string
     * }
     */
    private function normalizeBaseline(array $baseline): array
    {
        $keys = [
            'cycle_id', 'cycle_is_active', 'cycle_opens_on', 'cycle_closes_on', 'school_year',
            'semester', 'window_id', 'opens_at', 'closes_at', 'status',
        ];
        if (array_diff(array_keys($baseline), $keys) !== [] || array_diff($keys, array_keys($baseline)) !== []) {
            throw ValidationException::withMessages([
                'baseline' => 'The calendar baseline is incomplete. Refresh the calendar before trying again.',
            ]);
        }

        $isActive = filter_var($baseline['cycle_is_active'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if ($isActive === null) {
            throw ValidationException::withMessages([
                'baseline' => 'The calendar baseline is invalid. Refresh the calendar before trying again.',
            ]);
        }

        foreach (['cycle_opens_on', 'cycle_closes_on', 'school_year', 'semester', 'opens_at', 'closes_at', 'status'] as $key) {
            if (! is_string($baseline[$key])) {
                throw ValidationException::withMessages([
                    'baseline' => 'The calendar baseline is invalid. Refresh the calendar before trying again.',
                ]);
            }
        }

        if (! is_numeric($baseline['cycle_id']) || ! is_numeric($baseline['window_id'])) {
            throw ValidationException::withMessages([
                'baseline' => 'The calendar baseline is invalid. Refresh the calendar before trying again.',
            ]);
        }

        return [
            'cycle_id' => (int) $baseline['cycle_id'],
            'cycle_is_active' => $isActive,
            'cycle_opens_on' => $baseline['cycle_opens_on'],
            'cycle_closes_on' => $baseline['cycle_closes_on'],
            'school_year' => $baseline['school_year'],
            'semester' => $baseline['semester'],
            'window_id' => (int) $baseline['window_id'],
            'opens_at' => $baseline['opens_at'],
            'closes_at' => $baseline['closes_at'],
            'status' => $baseline['status'],
        ];
    }

    private function assertCurrentPrimaryAndOpen(Institution $institution, AcademicCycle $cycle, ApplicationWindow $window): void
    {
        if (! $cycle->is_active) {
            throw ValidationException::withMessages([
                'window_id' => 'Only the active academic year can be extended.',
            ]);
        }

        $primaryWindowId = $cycle->applicationWindows()->orderByDesc('id')->value('id');
        if ((int) $primaryWindowId !== (int) $window->getKey()) {
            throw ValidationException::withMessages([
                'window_id' => 'The application window is no longer the active academic year’s primary window. Refresh the calendar.',
            ]);
        }

        if ($window->status !== 'published') {
            throw ValidationException::withMessages([
                'window_id' => 'Only a published application window can be extended.',
            ]);
        }

        $intake = $this->currentIntake->forInstitution($institution);
        if ($intake === null
            || (int) $intake['cycle']->getKey() !== (int) $cycle->getKey()
            || (int) $intake['window']->getKey() !== (int) $window->getKey()) {
            throw ValidationException::withMessages([
                'window_id' => 'Applications are not currently open for this exact primary window. Refresh the calendar or use Edit to reopen it explicitly.',
            ]);
        }
    }

    private function extendedDeadline(CarbonImmutable $deadline, int $days): CarbonImmutable
    {
        if ($deadline->toDateString() > self::MAX_SUPPORTED_DATE) {
            throw ValidationException::withMessages([
                'days' => 'The deadline is outside the supported calendar date range.',
            ]);
        }
        $maximum = CarbonImmutable::parse(self::MAX_SUPPORTED_DATE.' 23:59:59', 'UTC');
        $remainingDays = (int) $deadline->startOfDay()->diff($maximum->startOfDay())->format('%a');

        if ($days > $remainingDays) {
            throw ValidationException::withMessages([
                'days' => 'The requested extension exceeds the supported calendar date range.',
            ]);
        }

        $newDeadline = CarbonImmutable::instance($deadline)->addDays($days);
        if ($newDeadline->gt($maximum)) {
            throw ValidationException::withMessages([
                'days' => 'The requested extension exceeds the supported calendar date range.',
            ]);
        }

        return $newDeadline;
    }
}
