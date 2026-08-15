<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OmrAnswerSheetTemplate extends Model
{
    protected $table = 'omr_answer_sheet_templates';

    protected $attributes = ['is_synthetic' => false];

    protected $fillable = ['code', 'name', 'is_synthetic'];

    protected function casts(): array
    {
        return ['is_synthetic' => 'boolean'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(OmrAnswerSheetTemplateVersion::class, 'template_id');
    }
}
