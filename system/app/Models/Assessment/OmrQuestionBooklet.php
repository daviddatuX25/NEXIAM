<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OmrQuestionBooklet extends Model
{
    protected $table = 'omr_question_booklets';

    protected $fillable = ['code', 'name'];

    public function versions(): HasMany
    {
        return $this->hasMany(OmrQuestionBookletVersion::class, 'booklet_id');
    }
}
