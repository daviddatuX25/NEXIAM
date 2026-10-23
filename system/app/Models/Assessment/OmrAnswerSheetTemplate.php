<?php

namespace App\Models\Assessment;

use Database\Factories\OmrAnswerSheetTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OmrAnswerSheetTemplate extends Model
{
    use HasFactory;

    protected static function newFactory(): OmrAnswerSheetTemplateFactory
    {
        return OmrAnswerSheetTemplateFactory::new();
    }

    protected $table = 'omr_answer_sheet_templates';

    protected $attributes = ['is_synthetic' => false];

    protected $fillable = ['institution_id', 'code', 'name', 'is_synthetic'];

    protected function casts(): array
    {
        return ['is_synthetic' => 'boolean'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(OmrAnswerSheetTemplateVersion::class, 'template_id');
    }
}
