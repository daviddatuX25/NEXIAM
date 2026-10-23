<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $institution_id
 * @property bool|null $is_synthetic
 * @property string $code
 * @property string $name
 */
class OmrQuestionBooklet extends Model
{
    protected $table = 'omr_question_booklets';

    protected $fillable = ['institution_id', 'is_synthetic', 'code', 'name'];

    protected function casts(): array
    {
        return [
            'is_synthetic' => 'boolean',
        ];
    }

    /**
     * @return HasMany<OmrQuestionBookletVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(OmrQuestionBookletVersion::class, 'booklet_id');
    }
}
