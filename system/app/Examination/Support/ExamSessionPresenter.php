<?php

namespace App\Examination\Support;

use App\Foundation\SeatingArrangementPreview;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\TestKitVersion;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Foundation\SeatingArrangement;
use App\Notifications\Policies\ExamNotificationPolicy;

/**
 * Shared Inertia payload shapes and option catalogs for the exam-session
 * scheduling and monitoring surfaces. Keeps controllers and queries free of
 * presentation assembly; publishBlockReason is a UX pre-check only — the
 * PublishExamSession action is the authority on publishability.
 */
class ExamSessionPresenter
{
    public function __construct(
        private readonly SeatingArrangementPreview $seatingPreview,
        private readonly ExamSessionPublishPreflight $publishPreflight,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function listPayload(ExamSession $session): array
    {
        $preflight = $this->publishPreflight->evaluate($session);
        $session->loadMissing('currentLogisticsPlan');
        $snapshot = $session->hasLogisticsGeneration() && $session->currentLogisticsPlan === null
            ? null
            : $session->currentMaterialSnapshot;

        return [
            'id' => $session->id,
            'code' => $session->code,
            'status' => $session->status,
            'booking_mode' => $session->booking_mode ?? ExamSession::BOOKING_STAFF_ONLY,
            'auto_assign_enabled' => (bool) $session->auto_assign_enabled,
            'seating_mode' => $session->status === ExamSession::STATUS_DRAFT
                ? $session->seating_mode
                : ($session->currentLogisticsPlan?->seating_mode ?? $snapshot?->seating_mode),
            'reservation_opened_at' => $session->reservation_opened_at?->toIso8601String(),
            'reservation_closed_at' => $session->reservation_closed_at?->toIso8601String(),
            'starts_at' => $session->starts_at?->toIso8601String(),
            'effective_end_at' => $session->effective_end_at?->toIso8601String(),
            'started_at' => $session->started_at?->toIso8601String(),
            'closed_at' => $session->closed_at?->toIso8601String(),
            'date' => $session->starts_at?->toDateString(),
            'start_time' => $session->starts_at?->format('H:i'),
            'end_time' => $session->effective_end_at?->format('H:i'),
            'scheduled_duration_minutes' => $session->scheduledDurationMinutes(),
            'assigned_count' => (int) ($session->assigned_count ?? 0),
            'room' => $session->room ? [
                'id' => $session->room->id,
                'code' => $session->room->code,
                'name' => $session->room->name,
                'capacity' => $session->room->capacity,
            ] : null,
            'academic_cycle_id' => (int) $session->academic_cycle_id,
            'academic_cycle' => $session->academicCycle ? [
                'id' => $session->academicCycle->id,
                'label' => $session->academicCycle->staffLabel(),
            ] : null,
            'booklet_version_id' => $session->status === ExamSession::STATUS_DRAFT
                ? ($session->booklet_version_id === null ? null : (int) $session->booklet_version_id)
                : ($snapshot?->booklet_version_id === null ? null : (int) $snapshot->booklet_version_id),
            'test_kit_version_id' => $session->status === ExamSession::STATUS_DRAFT
                ? ($session->getAttribute('test_kit_version_id') === null ? null : (int) $session->getAttribute('test_kit_version_id'))
                : ($snapshot?->test_kit_version_id === null ? null : (int) $snapshot->test_kit_version_id),
            'seating_arrangement_id' => $session->status === ExamSession::STATUS_DRAFT
                ? ($session->seating_arrangement_id === null ? null : (int) $session->seating_arrangement_id)
                : ($session->currentLogisticsPlan !== null
                    ? $session->currentLogisticsPlan->seating_arrangement_id
                    : $snapshot?->seating_arrangement_id),
            'capacity_limit' => $session->currentLogisticsPlan?->capacity_limit ?? $snapshot?->capacity_limit,
            'material' => $this->materialPayload($session),
            'is_publishable' => $preflight['ok'],
            'publish_block_reason' => $session->status === ExamSession::STATUS_DRAFT
                ? $preflight['reason']
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detailPayload(ExamSession $session): array
    {
        $session->loadMissing('academicCycle');
        $effectivePolicy = ExamNotificationPolicy::fromSnapshot($session->notification_policy_snapshot)
            ?? ExamNotificationPolicy::fromStorage($session->academicCycle?->exam_notification_policy)
                ->withSessionOverride($session->notification_policy);

        return [
            ...$this->listPayload($session),
            'auto_assign_enabled' => (bool) $session->auto_assign_enabled,
            'version' => $session->version,
            'academic_cycle_id' => $session->academic_cycle_id,
            'room_id' => $session->room_id,
            'booklet_version_id' => $session->booklet_version_id,
            'seating_arrangement_id' => $session->seating_arrangement_id,
            'notification_policy' => $session->notification_policy,
            'effective_notification_policy' => $effectivePolicy->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function materialPayload(ExamSession $session): array
    {
        $logisticsPlan = $session->currentLogisticsPlan;
        if ($logisticsPlan !== null) {
            $arrangement = $logisticsPlan->arrangement;

            return [
                'booklet' => null,
                'test_kit' => null,
                'seating_arrangement' => $arrangement === null ? null : [
                    'id' => (int) $arrangement->id,
                    'name' => (string) $arrangement->name,
                    'desk_count' => (int) $logisticsPlan->capacity_limit,
                ],
                'snapshot_id' => null,
                'logistics_plan_id' => (int) $logisticsPlan->id,
                'logistics_plan_revision' => (int) $logisticsPlan->revision,
                'capacity_limit' => (int) $logisticsPlan->capacity_limit,
            ];
        }
        $booklet = $session->bookletVersion;
        $snapshot = $session->hasLogisticsGeneration() ? null : $session->currentMaterialSnapshot;
        $testKitVersionId = $session->status === ExamSession::STATUS_DRAFT
            ? $session->getAttribute('test_kit_version_id')
            : $snapshot?->test_kit_version_id;
        $testKitVersion = $testKitVersionId === null
            ? null
            : TestKitVersion::query()->with('testKit')->find($testKitVersionId);
        $sheetVersion = $session->status === ExamSession::STATUS_DRAFT
            ? ($testKitVersion?->templateVersion ?? $booklet?->templateVersion)
            : ($snapshot?->templateVersion ?? $booklet?->templateVersion);

        return [
            'booklet' => $booklet === null ? null : [
                'id' => (int) $booklet->id,
                'label' => sprintf(
                    '%s v%d',
                    $booklet->booklet?->name ?? 'Booklet',
                    (int) $booklet->version,
                ),
                'answer_sheet' => sprintf(
                    '%s v%d',
                    $sheetVersion?->template?->name ?? 'Answer Sheet',
                    (int) ($sheetVersion?->version ?? 0),
                ),
            ],
            'test_kit' => $testKitVersion === null ? null : [
                'version_id' => (int) $testKitVersion->id,
                'version_number' => (int) $testKitVersion->version_number,
                'code' => $testKitVersion->testKit?->code,
                'name' => $testKitVersion->testKit?->name,
                'status' => $testKitVersion->status,
                'manifest_sha256' => $testKitVersion->manifest_sha256,
                'acceptance_receipt_id' => $session->status === ExamSession::STATUS_DRAFT
                    ? ($testKitVersion->selected_acceptance_receipt_id === null
                        ? null
                        : (int) $testKitVersion->selected_acceptance_receipt_id)
                    : ($snapshot?->acceptance_receipt_id === null
                        ? null
                        : (int) $snapshot->acceptance_receipt_id),
            ],
            'seating_arrangement' => $session->seatingArrangement === null ? null : [
                'id' => (int) $session->seatingArrangement->id,
                'name' => (string) $session->seatingArrangement->name,
                'desk_count' => (int) $session->seatingArrangement->desk_count,
            ],
            'snapshot_id' => $snapshot?->id === null ? null : (int) $snapshot->id,
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function statusOptions(): array
    {
        return [
            ['value' => ExamSession::STATUS_DRAFT, 'label' => 'Draft'],
            ['value' => ExamSession::STATUS_PUBLISHED, 'label' => 'Published'],
            ['value' => ExamSession::STATUS_IN_PROGRESS, 'label' => 'In progress'],
            ['value' => ExamSession::STATUS_COMPLETED, 'label' => 'Completed'],
            ['value' => ExamSession::STATUS_CANCELLED, 'label' => 'Cancelled'],
        ];
    }

    /**
     * @return list<array{id: int, label: string, is_active: bool}>
     */
    public function cycleOptions(): array
    {
        return AcademicCycle::query()
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->get()
            ->map(fn (AcademicCycle $cycle) => [
                'id' => $cycle->id,
                'label' => $cycle->staffLabel(),
                'is_active' => (bool) $cycle->is_active,
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, label: string, capacity: int}>
     */
    public function roomOptions(): array
    {
        return Room::query()
            ->where('is_active', true)
            ->examRooms()
            ->orderBy('code')
            ->get()
            ->map(fn (Room $room) => [
                'id' => $room->id,
                'label' => trim(($room->name ?: $room->code).' (cap. '.$room->capacity.')'),
                'capacity' => $room->capacity,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function bookletOptions(): array
    {
        return OmrQuestionBookletVersion::query()
            ->with(['booklet', 'templateVersion.template', 'sets'])
            ->where('status', OmrQuestionBookletVersion::STATUS_FINALIZED)
            ->whereNull('archived_at')
            ->orderByDesc('finalized_at')
            ->get()
            ->map(fn (OmrQuestionBookletVersion $version): array => [
                'id' => (int) $version->id,
                'label' => sprintf('%s v%d', $version->booklet?->name ?? 'Booklet', (int) $version->version),
                'booklet_name' => $version->booklet?->name,
                'answer_sheet' => sprintf(
                    '%s v%d',
                    $version->templateVersion?->template?->name ?? 'Answer Sheet',
                    (int) ($version->templateVersion?->version ?? 0),
                ),
                'sets' => $version->sets->map(fn ($set): array => [
                    'id' => (int) $set->id,
                    'code' => (string) $set->set_code,
                    'order_hash' => $set->order_hash,
                    'answer_key_hash' => $set->answer_key_hash,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function testKitOptions(?int $cycleId = null): array
    {
        $institutionId = (int) (Institution::current()?->id ?? 1);

        return TestKitVersion::query()
            ->with([
                'testKit',
                'bookletVersion.booklet',
                'templateVersion.template',
                'testInstrumentVersion.instrument',
            ])
            ->whereHas('testKit', fn ($q) => $q->where('institution_id', $institutionId))
            ->whereNull('revoked_at')
            ->whereIn('status', [TestKitVersion::STATUS_ACTIVE, TestKitVersion::STATUS_CANDIDATE])
            ->orderByDesc('id')
            ->get()
            ->map(fn (TestKitVersion $version): array => [
                'id' => (int) $version->id,
                'test_kit_id' => (int) $version->test_kit_id,
                'code' => (string) ($version->testKit?->code ?? ''),
                'name' => (string) ($version->testKit?->name ?? ''),
                'version_number' => (int) $version->version_number,
                'status' => (string) $version->status,
                'label' => sprintf(
                    '%s v%d%s',
                    $version->testKit?->name ?? $version->testKit?->code ?? 'Test Kit',
                    (int) $version->version_number,
                    $version->testKit?->code ? " ({$version->testKit->code})" : '',
                ),
                'booklet_label' => $version->bookletVersion === null
                    ? 'Not applicable (direct/manual scoring)'
                    : sprintf(
                        '%s v%d',
                        $version->bookletVersion->booklet?->name ?? 'Booklet',
                        (int) $version->bookletVersion->version,
                    ),
                'answer_sheet_label' => $version->templateVersion === null
                    ? 'Not applicable (direct/manual scoring)'
                    : sprintf(
                        '%s v%d',
                        $version->templateVersion->template?->name ?? 'Answer Sheet',
                        (int) $version->templateVersion->version,
                    ),
                'profile_label' => (string) ($version->testInstrumentVersion?->instrument?->name ?? $version->testInstrumentVersion?->display_name ?? 'Profile'),
                'booklet_version_id' => (int) $version->booklet_version_id,
                'template_version_id' => (int) $version->template_version_id,
                'test_instrument_version_id' => $version->test_instrument_version_id === null ? null : (int) $version->test_instrument_version_id,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function seatingArrangementOptions(?int $roomId = null): array
    {
        $room = $roomId === null ? null : Room::query()->find($roomId);
        $query = SeatingArrangement::query()
            ->active()
            ->with('rooms:id,code,name,capacity')
            ->when($room !== null, function ($query) use ($room, $roomId): void {
                $query
                    ->where(function ($available) use ($roomId): void {
                        $available
                            ->where('availability_mode', SeatingArrangement::AVAILABILITY_ALL_ROOMS)
                            ->orWhereHas('rooms', fn ($rooms) => $rooms->whereKey($roomId));
                    })
                    ->whereRaw('(rows * columns) <= ?', [(int) $room->capacity]);
            })
            ->orderBy('normalized_name');

        return $query->get()
            ->map(fn (SeatingArrangement $arrangement): array => [
                'id' => (int) $arrangement->id,
                'name' => (string) $arrangement->name,
                'label' => sprintf('%s · %s', $arrangement->name, $arrangement->booklet_pattern),
                'pattern' => (string) $arrangement->booklet_pattern,
                'booklet_pattern' => (string) $arrangement->booklet_pattern,
                'availability_mode' => (string) $arrangement->availability_mode,
                'desk_count' => (int) $arrangement->desk_count,
                'rows' => (int) $arrangement->rows,
                'columns' => (int) $arrangement->columns,
                'room_ids' => $arrangement->rooms->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
                'preview' => $this->seatingPreview->forArrangement($arrangement),
            ])
            ->values()
            ->all();
    }

    public function publishBlockReason(ExamSession $session): ?string
    {
        return $this->publishPreflight->blockReason($session);
    }

    public function attemptName(?AdmissionAttempt $attempt): string
    {
        if ($attempt === null) {
            return '—';
        }

        $identity = $attempt->identity;
        if ($identity === null) {
            return $attempt->reference_code;
        }

        return trim(implode(' ', array_filter([
            $identity->first_name,
            $identity->middle_name,
            $identity->last_name,
            $identity->suffix,
        ]))) ?: $attempt->reference_code;
    }
}
