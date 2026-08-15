<?php

namespace App\Models\Assessment;

use App\Models\Foundation\AptitudeArea;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OmrQuestionBookletQuestion extends Model
{
    protected $table = 'omr_question_booklet_questions';

    protected $fillable = ['booklet_version_id', 'template_question_id', 'aptitude_area_id', 'question_position', 'correct_option_code'];

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
