<?php

namespace App\Models\ResultSheet;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Server-owned, resumable authoring state for a result-sheet template.
 * Drafts are deleted when their final template save succeeds.
 */
class ResultSheetTemplateDraft extends Model
{
    public const STAGE_SETUP = 1;

    public const STAGE_DESIGN = 2;

    public const STAGE_VERIFY = 3;

    protected $fillable = [
        'source_template_id',
        'owner_user_id',
        'stage',
        'state',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'stage' => 'integer',
            'state' => 'array',
            'lock_version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ResultSheetTemplate, $this>
     */
    public function sourceTemplate(): BelongsTo
    {
        return $this->belongsTo(ResultSheetTemplate::class, 'source_template_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
