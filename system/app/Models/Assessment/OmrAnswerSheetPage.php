<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OmrAnswerSheetPage extends Model
{
    protected $table = 'omr_answer_sheet_pages';

    protected $fillable = [
        'template_version_id', 'page_no', 'width', 'height',
        'page_manifest_json', 'page_manifest_hash',
    ];

    protected function casts(): array
    {
        return [
            'page_no' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'page_manifest_json' => 'array',
        ];
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(OmrAnswerSheetTemplateVersion::class, 'template_version_id');
    }
}
