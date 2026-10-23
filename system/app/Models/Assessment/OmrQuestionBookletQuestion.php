<?php

namespace App\Models\Assessment;

use App\Models\Foundation\AptitudeArea;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class OmrQuestionBookletQuestion extends Model
{
    protected $table = 'omr_question_booklet_questions';

    protected $fillable = ['booklet_version_id', 'template_question_id', 'aptitude_area_id', 'question_position', 'correct_option_code'];

    protected static function booted(): void
    {
        static::saving(static function (self $question): void {
            if (self::hasSealedBookletVersion($question->getRawOriginal('booklet_version_id'))
                || self::hasSealedBookletVersion($question->booklet_version_id)) {
                throw new RuntimeException('Finalized booklet questions are immutable.');
            }
        });

        static::deleting(static function (self $question): void {
            if (self::hasSealedBookletVersion($question->getRawOriginal('booklet_version_id'))
                || self::hasSealedBookletVersion($question->booklet_version_id)) {
                throw new RuntimeException('Finalized booklet questions are immutable.');
            }
        });
    }

    private static function hasSealedBookletVersion(?int $versionId): bool
    {
        if ($versionId === null) {
            return false;
        }

        return OmrQuestionBookletVersion::query()
            ->whereKey($versionId)
            ->where(static function ($query): void {
                $query->where('status', OmrQuestionBookletVersion::STATUS_FINALIZED)
                    ->orWhereNotNull('finalized_at');
            })
            ->exists();
    }

    protected function casts(): array
    {
        return ['question_position' => 'integer'];
    }

    public function bookletVersion(): BelongsTo
    {
        return $this->belongsTo(OmrQuestionBookletVersion::class, 'booklet_version_id');
    }

    public function templateQuestion(): BelongsTo
    {
        return $this->belongsTo(OmrTemplateQuestion::class, 'template_question_id');
    }

    public function aptitudeArea(): BelongsTo
    {
        return $this->belongsTo(AptitudeArea::class);
    }
}
