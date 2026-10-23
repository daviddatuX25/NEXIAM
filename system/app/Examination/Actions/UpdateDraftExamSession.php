<?php

namespace App\Examination\Actions;

use App\Assessment\Support\KitCompositionContext;
use App\Assessment\Support\TestKitManifestBuilder;
use App\Foundation\Actions\RecordAuditEvent;
use App\Foundation\SeatingArrangementEligibility;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBookletSet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitVersion;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Foundation\SeatingArrangement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UpdateDraftExamSession
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly SeatingArrangementEligibility $arrangementEligibility,
        private readonly KitCompositionContext $kitCompositionContext,
        private readonly TestKitManifestBuilder $testKitManifestBuilder,
    ) {}

    public function handle(
        ExamSession $session,
        AcademicCycle $cycle,
        Room $room,
        string $code,
        string $startsAt,
        string $effectiveEndAt,
        User $actor,
        int $expectedVersion,
        string $seatingMode,
        ?OmrQuestionBookletVersion $bookletVersion = null,
        ?SeatingArrangement $seatingArrangement = null,
        ?array $notificationPolicy = null,
        bool $confirmClearSeatingArrangement = false,
        ?TestInstrumentVersion $profileVersion = null,
        ?int $testKitVersionId = null,
        ?bool $autoAssignEnabled = null,
    ): ExamSession {
        $institutionId = (int) Institution::current()->id;

        return DB::transaction(function () use ($session, $cycle, $room, $code, $startsAt, $effectiveEndAt, $actor, $expectedVersion, $seatingMode, $bookletVersion, $seatingArrangement, $notificationPolicy, $confirmClearSeatingArrangement, $profileVersion, $testKitVersionId, $autoAssignEnabled, $institutionId): ExamSession {
            $institution = Institution::query()->lockForUpdate()->findOrFail($institutionId);
            $session = ExamSession::query()->lockForUpdate()->findOrFail($session->id);

            if ((int) $session->version !== $expectedVersion) {
                throw new RuntimeException('Stale exam session version.');
            }

            if ($session->status !== ExamSession::STATUS_DRAFT) {
                throw new RuntimeException('Only draft sessions may be edited.');
            }

            if ($session->isOpenForReservation()) {
                throw new RuntimeException('Close reservation before editing schedule fields.');
            }

            if (! in_array($seatingMode, ExamSession::SEATING_MODES, true)) {
                throw new RuntimeException('Select a valid exam seating mode.');
            }

            $room = Room::query()->lockForUpdate()->findOrFail($room->id);
            $currentArrangementId = $session->seating_arrangement_id;
            $requestedArrangementId = $seatingArrangement?->id;
            $roomChanged = (int) $session->room_id !== (int) $room->id;
            if ($seatingMode === ExamSession::SEATING_MODE_ASSIGNED_SEATING
                && $currentArrangementId !== null
                && $requestedArrangementId === null) {
                throw new RuntimeException('Retain or explicitly replace the assigned seating arrangement.');
            }

            if ($seatingMode === ExamSession::SEATING_MODE_SIMPLE_CAPACITY) {
                if ($seatingArrangement !== null) {
                    throw new RuntimeException('Simple-capacity sessions cannot select a seating arrangement.');
                }
                if ($currentArrangementId !== null && ! $confirmClearSeatingArrangement) {
                    throw new RuntimeException('Confirm clearing the saved seating arrangement before switching to room capacity.');
                }
            } elseif (! $institution->usesSeatingArrangements()) {
                $preservingDisabledAssignment = $session->seating_mode === ExamSession::SEATING_MODE_ASSIGNED_SEATING
                    && $requestedArrangementId === $currentArrangementId;
                if (! $preservingDisabledAssignment) {
                    throw new RuntimeException('Assigned seating is not available for new or changed session selections.');
                }
                if ($roomChanged && $seatingArrangement !== null) {
                    $this->arrangementEligibility->assertEligible($room, $seatingArrangement);
                }
            } elseif ($seatingArrangement !== null) {
                $this->arrangementEligibility->assertEligible($room, $seatingArrangement);
            }
            [$bookletVersion, $profileVersion] = $this->resolveKitSelection(
                $session->getAttribute('test_kit_version_id') === null ? null : (int) $session->getAttribute('test_kit_version_id'),
                $testKitVersionId,
                $cycle,
                $bookletVersion,
                $profileVersion,
                $institution,
            );
            $this->assertMaterialSelection($room, $bookletVersion, $seatingArrangement, $seatingMode);
            if ($profileVersion !== null && $profileVersion->status !== TestInstrumentVersion::STATUS_APPROVED) {
                throw new RuntimeException('Selected instrument profile is not approved.');
            }

            $overlap = ExamSession::query()
                ->where('room_id', $room->id)
                ->where('id', '!=', $session->id)
                ->where('status', '!=', ExamSession::STATUS_CANCELLED)
                ->where('starts_at', '<', $effectiveEndAt)
                ->where('effective_end_at', '>', $startsAt)
                ->exists();

            if ($overlap) {
                throw new RuntimeException('Room scheduling conflict.');
            }
            if (! $room->isExamRoom()) {
                throw new RuntimeException("Room {$room->name} is not configured for examination sessions.");
            }

            $session->update([
                'academic_cycle_id' => $cycle->id,
                'room_id' => $room->id,
                'code' => $code,
                'starts_at' => $startsAt,
                'effective_end_at' => $effectiveEndAt,
                'booklet_version_id' => $bookletVersion?->id,
                'test_kit_version_id' => $testKitVersionId,
                'seating_arrangement_id' => $seatingArrangement?->id,
                'seating_mode' => $seatingMode,
                'notification_policy' => $notificationPolicy,
                'test_instrument_version_id' => $profileVersion?->id,
                'auto_assign_enabled' => $autoAssignEnabled ?? $session->auto_assign_enabled,
                'version' => $session->version + 1,
            ]);

            $this->recordAuditEvent->handle('exam.session_updated', [
                'exam_session_id' => $session->id,
                'code' => $code,
            ], (string) $actor->id);

            return $session->fresh(['room', 'academicCycle']);
        });
    }

    private function assertMaterialSelection(
        Room $room,
        ?OmrQuestionBookletVersion $bookletVersion,
        ?SeatingArrangement $seatingArrangement,
        string $seatingMode,
    ): void {
        if ($bookletVersion !== null && ! $bookletVersion->isFinalized()) {
            throw new RuntimeException('Select a finalized Booklet before configuring session materials.');
        }
        if ($bookletVersion?->archived_at !== null) {
            throw new RuntimeException('Archived Booklets cannot be selected for new exam sessions.');
        }
        if ($seatingMode === ExamSession::SEATING_MODE_SIMPLE_CAPACITY && $seatingArrangement !== null) {
            throw new RuntimeException('Simple-capacity sessions cannot select a seating arrangement.');
        }
        if ($seatingArrangement !== null) {
            if ($seatingMode !== ExamSession::SEATING_MODE_ASSIGNED_SEATING) {
                throw new RuntimeException('A seating arrangement requires assigned seating.');
            }
            $this->arrangementEligibility->assertEligible($room, $seatingArrangement);
        }
    }

    /**
     * @return array{?OmrQuestionBookletVersion, ?TestInstrumentVersion}
     */
    private function resolveKitSelection(
        ?int $previousTestKitVersionId,
        ?int $testKitVersionId,
        AcademicCycle $cycle,
        ?OmrQuestionBookletVersion $bookletVersion,
        ?TestInstrumentVersion $profileVersion,
        Institution $institution,
    ): array {
        if ($previousTestKitVersionId === null && $testKitVersionId === null) {
            return [$bookletVersion, $profileVersion];
        }

        if ($testKitVersionId === null) {
            return [null, null];
        }

        $versionIds = array_values(array_unique(array_filter(
            [$previousTestKitVersionId, $testKitVersionId],
            static fn (?int $versionId): bool => $versionId !== null,
        )));
        sort($versionIds);
        $unlockedVersions = TestKitVersion::query()->whereIn('id', $versionIds)->get(['id', 'test_kit_id'])->keyBy('id');
        if ($unlockedVersions->count() !== count($versionIds)) {
            throw new RuntimeException('The selected Test Kit version is unavailable.');
        }

        $familyIds = $unlockedVersions->pluck('test_kit_id')->unique()->sort()->values()->all();
        TestKit::query()->whereIn('id', $familyIds)->orderBy('id')->lockForUpdate()->get(['id']);
        $lockedVersions = TestKitVersion::query()
            ->whereIn('id', $versionIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $version = $lockedVersions->get($testKitVersionId);
        $kitInstitutionId = $version === null
            ? null
            : TestKit::query()->whereKey($version->test_kit_id)->value('institution_id');
        $preservingExistingPin = $previousTestKitVersionId !== null
            && $testKitVersionId === $previousTestKitVersionId;
        if ($version === null
            || (! $preservingExistingPin
                && (! in_array($version->status, [TestKitVersion::STATUS_CANDIDATE, TestKitVersion::STATUS_ACTIVE], true)
                    || $version->revoked_at !== null))
            || (int) $version->institution_id !== (int) $institution->id
            || (int) $cycle->institution_id !== (int) $institution->id
            || (int) $kitInstitutionId !== (int) $institution->id
            || ($bookletVersion !== null && (int) $bookletVersion->id !== (int) $version->booklet_version_id)
            || ($profileVersion !== null && (int) $profileVersion->id !== (int) $version->test_instrument_version_id)
            || ($version->manifest_schema_version < TestKitManifestBuilder::MANIFEST_SCHEMA_VERSION
                && $version->booklet_set_code !== 'A')) {
            throw new RuntimeException('The selected Test Kit does not match this session’s exact materials and institution.');
        }

        if ((int) $version->manifest_schema_version === TestKitManifestBuilder::MANIFEST_SCHEMA_VERSION) {
            $this->testKitManifestBuilder->verify($version);
            $resolved = $this->kitCompositionContext->resolveVersion($version);

            return [$resolved['booklet'], $resolved['profile']];
        }

        $bookletVersion = OmrQuestionBookletVersion::query()->find($version->booklet_version_id);
        $profileVersion = TestInstrumentVersion::query()->find($version->test_instrument_version_id);
        $bookletSet = OmrBookletSet::query()
            ->where('booklet_version_id', $version->booklet_version_id)
            ->whereKey($version->booklet_set_id)
            ->where('set_code', 'A')
            ->where('status', OmrBookletSet::STATUS_FINALIZED)
            ->first();
        $templateVersion = OmrAnswerSheetTemplateVersion::query()->find($version->template_version_id);
        if ($bookletVersion === null || ! $bookletVersion->isFinalized()
            || (int) $version->booklet_id !== (int) $bookletVersion->booklet_id
            || $profileVersion === null
            || (int) $version->test_instrument_id !== (int) $profileVersion->test_instrument_id
            || $bookletSet === null
            || $bookletSet->order_hash !== $version->booklet_set_order_hash
            || $bookletSet->answer_key_hash !== $version->booklet_set_answer_key_hash
            || $templateVersion === null || ! $templateVersion->isCompiled()
            || (int) $version->template_id !== (int) $templateVersion->template_id) {
            throw new RuntimeException('The selected Test Kit has incomplete or mismatched pinned materials.');
        }

        return [$bookletVersion, $profileVersion];
    }
}
