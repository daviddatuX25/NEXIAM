<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

/**
 * Immutable answer-key-blind machine evidence for one diagnostic item.
 *
 * Diagnostic evidence is intentionally separate from production capture and
 * template-verification evidence. Expected answers never cross this boundary.
 *
 * @property int $id
 * @property int $diagnostic_capture_id
 * @property string $item_key
 * @property int $page_no
 * @property string|null $option_code
 * @property array<string, mixed>|null $option_evidence_json
 * @property array<string, mixed>|null $machine_proposal_json
 * @property float|null $machine_confidence
 * @property string|null $evidence_hash
 * @property string $evidence_version
 */
class OmrDiagnosticItemEvidence extends Model
{
    protected $table = 'omr_diagnostic_item_evidence';

    protected $attributes = [
        'evidence_version' => 'v1',
    ];

    protected $fillable = [
        'diagnostic_capture_id',
        'item_key',
        'page_no',
        'area_code',
        'question_no',
        'option_code',
        'option_evidence_json',
        'machine_proposal_json',
        'machine_confidence',
        'evidence_hash',
        'evidence_version',
    ];

    protected function casts(): array
    {
        return [
            'diagnostic_capture_id' => 'integer',
            'page_no' => 'integer',
            'question_no' => 'integer',
            'option_evidence_json' => 'array',
            'machine_proposal_json' => 'array',
            'machine_confidence' => 'float',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(static function (self $evidence): void {
            if ((int) $evidence->diagnostic_capture_id < 1
                || trim((string) $evidence->item_key) === ''
                || (int) $evidence->page_no < 1
                || ($evidence->area_code !== null && trim((string) $evidence->area_code) === '')
                || ($evidence->question_no !== null && (int) $evidence->question_no < 1)
                || ($evidence->option_code !== null && trim((string) $evidence->option_code) === '')) {
                throw new RuntimeException('omr_diagnostic_item_evidence_identity_invalid');
            }

            if ($evidence->machine_confidence !== null
                && ((float) $evidence->machine_confidence < 0.0 || (float) $evidence->machine_confidence > 1.0)) {
                throw new RuntimeException('omr_diagnostic_item_evidence_confidence_invalid');
            }

            if (preg_match('/^[a-fA-F0-9]{64}$/', (string) $evidence->evidence_hash) !== 1
                || trim((string) $evidence->evidence_version) === '') {
                throw new RuntimeException('omr_diagnostic_item_evidence_provenance_invalid');
            }
        });

        static::updating(static function (): void {
            throw new RuntimeException('omr_diagnostic_item_evidence_immutable');
        });

        static::deleting(static function (): void {
            throw new RuntimeException('omr_diagnostic_item_evidence_immutable');
        });
    }

    public function capture(): BelongsTo
    {
        return $this->belongsTo(OmrDiagnosticCapture::class, 'diagnostic_capture_id');
    }

    public function reviewRevisions(): HasMany
    {
        return $this->hasMany(OmrDiagnosticReviewRevision::class, 'diagnostic_item_evidence_id');
    }

    public function scopeForCapture(Builder $query, int $captureId): Builder
    {
        return $query->where('diagnostic_capture_id', $captureId);
    }

    public function scopeStableOrder(Builder $query): Builder
    {
        return $query
            ->orderBy('page_no')
            ->orderByRaw('CASE WHEN area_code IS NULL THEN 1 ELSE 0 END')
            ->orderBy('area_code')
            ->orderByRaw('CASE WHEN question_no IS NULL THEN 1 ELSE 0 END')
            ->orderBy('question_no')
            ->orderByRaw('COALESCE(item_key, \'\')')
            ->orderBy('id');
    }
}
