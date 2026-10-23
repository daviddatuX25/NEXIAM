<?php

namespace App\Models\Admission;

use App\Admission\Support\AttemptRequirementChecklistBoundary;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdmissionDocumentCorrectionRequest extends Model
{
    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new \LogicException('Admission document correction request receipts are immutable.');
        });
        static::deleting(static function (): never {
            throw new \LogicException('Admission document correction request receipts are immutable.');
        });
    }

    protected $fillable = [
        'admission_attempt_id',
        'requirement_set_id',
        'institution_id',
        'academic_cycle_id',
        'practice_fixture_set_id',
        'requested_by_user_id',
        'expected_version',
        'recorded_version',
        'idempotency_key',
        'overall_note',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'expected_version' => 'integer',
            'recorded_version' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    public function admissionAttempt(): BelongsTo
    {
        return $this->belongsTo(AdmissionAttempt::class);
    }

    public function requirementSet(): BelongsTo
    {
        return $this->belongsTo(AdmissionAttemptRequirementSet::class, 'requirement_set_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AdmissionDocumentCorrectionRequestItem::class, 'document_correction_request_id');
    }

    /**
     * @return array{requests:list<array{id:int,recorded_at:string,status:string,overall_note:?string,items:list<array{requirement_item_id:int,label:string,observed_status:string,applicant_instruction:string,expected_latest_verification_id:?int,status:string}>}>}
     */
    public static function applicantProjectionFor(AdmissionAttempt $attempt): array
    {
        $requests = self::query()
            ->with('items')
            ->where('admission_attempt_id', $attempt->getKey())
            ->orderBy('id')
            ->get();
        if ($requests->isEmpty()) {
            return ['requests' => []];
        }

        $currentSet = AdmissionAttemptRequirementSet::query()
            ->where('admission_attempt_id', $attempt->getKey())
            ->where('is_current', true)
            ->first();
        $currentTrack = app(AttemptRequirementChecklistBoundary::class)->currentTrack($attempt);
        $items = $requests->flatMap(static fn (self $request) => $request->items);
        $itemIds = $items->pluck('requirement_item_id')->unique()->all();
        $latestVerifications = AdmissionAttemptDocumentVerification::query()
            ->where('admission_attempt_id', $attempt->getKey())
            ->whereIn('requirement_item_id', $itemIds)
            ->orderByDesc('id')
            ->get()
            ->unique('requirement_item_id')
            ->keyBy('requirement_item_id');
        $latestRequestItemIds = $items->groupBy('requirement_item_id')
            ->map(static fn ($requestItems) => (int) $requestItems->max('id'));

        $projection = [];
        foreach ($requests as $request) {
            $projectedItems = [];
            $hasCurrentItem = false;
            foreach ($request->items as $item) {
                $itemStatus = 'stale';
                if ($currentSet !== null && (int) $currentSet->getKey() === (int) $request->requirement_set_id
                    && $currentSet->applicant_track === $currentTrack) {
                    $latest = $latestVerifications->get($item->requirement_item_id);
                    if ($latest !== null && $latest->status === AdmissionAttemptDocumentVerification::STATUS_VERIFIED) {
                        $itemStatus = 'resolved';
                    } elseif ((int) $latestRequestItemIds->get($item->requirement_item_id) !== (int) $item->getKey()) {
                        $itemStatus = 'superseded';
                    } elseif (($latest === null ? null : (int) $latest->getKey()) === $item->expected_latest_verification_id) {
                        $itemStatus = 'current';
                        $hasCurrentItem = true;
                    }
                }

                $projectedItems[] = [
                    'requirement_item_id' => (int) $item->requirement_item_id,
                    'label' => $item->label,
                    'observed_status' => $item->observed_status,
                    'applicant_instruction' => $item->applicant_instruction,
                    'expected_latest_verification_id' => $item->expected_latest_verification_id === null
                        ? null : (int) $item->expected_latest_verification_id,
                    'status' => $itemStatus,
                ];
            }

            $projection[] = [
                'id' => (int) $request->getKey(),
                'recorded_at' => $request->recorded_at?->toIso8601String() ?? '',
                'status' => $hasCurrentItem ? 'current' : 'historical',
                'overall_note' => $request->overall_note,
                'items' => $projectedItems,
            ];
        }

        return ['requests' => $projection];
    }

    /** @param Builder<self> $query */
    public function scopeForAttempt(Builder $query, AdmissionAttempt $attempt): Builder
    {
        return $query->where('admission_attempt_id', $attempt->getKey());
    }
}
