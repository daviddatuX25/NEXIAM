<?php

namespace App\Examination\Actions;

use App\Assessment\Support\KitCompositionContext;
use App\Assessment\Support\TestKitManifestBuilder;
use App\Examination\Support\CreateExamSessionEvaluator;
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
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CreateExamSession
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly SeatingArrangementEligibility $arrangementEligibility,
        private readonly CreateExamSessionEvaluator $evaluator,
        private readonly KitCompositionContext $kitCompositionContext,
        private readonly TestKitManifestBuilder $testKitManifestBuilder,
    ) {}

    public function handle(
        AcademicCycle $cycle,
        Room $room,
        string $code,
        string $startsAt,
        string $effectiveEndAt,
        User $actor,
        string $seatingMode,
        ?OmrQuestionBookletVersion $bookletVersion = null,
        ?SeatingArrangement $seatingArrangement = null,
        ?array $notificationPolicy = null,
        ?TestInstrumentVersion $profileVersion = null,
        ?int $testKitVersionId = null,
        bool $autoAssignEnabled = false,
    ): ExamSession {
        $institutionId = (int) Institution::current()->id;

        return DB::transaction(function () use ($cycle, $room, $code, $startsAt, $effectiveEndAt, $actor, $seatingMode, $bookletVersion, $seatingArrangement, $notificationPolicy, $profileVersion, $testKitVersionId, $autoAssignEnabled, $institutionId): ExamSession {
            $institution = Institution::query()->lockForUpdate()->findOrFail($institutionId);
            if (ExamSession::query()
                ->where('academic_cycle_id', $cycle->id)
                ->where('code', $code)
                ->exists()) {
                throw ValidationException::withMessages([
                    'code' => 'This session code is already used in the selected academic year. Enter a different code.',
                ]);
            }
            if (! in_array($seatingMode, ExamSession::SEATING_MODES, true)) {
                throw new RuntimeException('Select a valid exam seating mode.');
            }
            if ($seatingMode === ExamSession::SEATING_MODE_ASSIGNED_SEATING && ! $institution->usesSeatingArrangements()) {
                throw new RuntimeException('Assigned seating is not available for new sessions.');
            }

            if ($seatingMode === ExamSession::SEATING_MODE_SIMPLE_CAPACITY && $seatingArrangement !== null) {
                throw new RuntimeException('Simple-capacity sessions cannot select a seating arrangement.');
            }

            $room = Room::query()->lockForUpdate()->findOrFail($room->id);
            $preflight = $this->evaluator->evaluate(
                $cycle,
                $room,
                CarbonImmutable::parse($startsAt),
                CarbonImmutable::parse($effectiveEndAt),
                $code,
            );
            if ($preflight->outcome !== 'pass') {
                foreach ($preflight->checks as $check) {
                    if ($check['status'] !== 'pass') {
                        throw new RuntimeException(match ($check['key']) {
                            'room_suitable' => 'room_not_suitable_for_exam',
                            'no_room_collision' => 'room_schedule_collision',
                            default => 'exam_session_schedule_invalid',
                        });
                    }
                }
            }

            [$bookletVersion, $profileVersion] = $this->resolveKitSelection(
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

            $session = ExamSession::query()->create([
                'academic_cycle_id' => $cycle->id,
                'room_id' => $room->id,
                'code' => $code,
                'status' => ExamSession::STATUS_DRAFT,
                'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
                'starts_at' => $startsAt,
                'effective_end_at' => $effectiveEndAt,
                'booklet_version_id' => $bookletVersion?->id,
                'test_kit_version_id' => $testKitVersionId,
                'seating_arrangement_id' => $seatingArrangement?->id,
                'seating_mode' => $seatingMode,
                'notification_policy' => $notificationPolicy,
                'test_instrument_version_id' => $profileVersion?->id,
                'auto_assign_enabled' => $autoAssignEnabled,
                'version' => 1,
            ]);
            $this->recordAuditEvent->handle('exam.session_created', [
                'exam_session_id' => $session->id,
                'code' => $code,
            ], (string) $actor->id);

            return $session->fresh();
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
        ?int $testKitVersionId,
        AcademicCycle $cycle,
        ?OmrQuestionBookletVersion $bookletVersion,
        ?TestInstrumentVersion $profileVersion,
        Institution $institution,
    ): array {
        if ($testKitVersionId === null) {
            return [$bookletVersion, $profileVersion];
        }

        $unlockedVersion = TestKitVersion::query()->find($testKitVersionId);
        if ($unlockedVersion === null) {
            throw new RuntimeException('The selected Test Kit version is unavailable.');
        }
        TestKit::query()->whereKey($unlockedVersion->test_kit_id)->lockForUpdate()->firstOrFail();
        $version = TestKitVersion::query()
            ->where('test_kit_id', $unlockedVersion->test_kit_id)
            ->lockForUpdate()
            ->find($testKitVersionId);
        if ($version === null
            || ! in_array($version->status, [TestKitVersion::STATUS_CANDIDATE, TestKitVersion::STATUS_ACTIVE], true)
            || $version->revoked_at !== null
            || (int) $version->institution_id !== (int) $institution->id
            || (int) $cycle->institution_id !== (int) $institution->id
            || (int) TestKit::query()->whereKey($version->test_kit_id)->value('institution_id') !== (int) $institution->id
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
