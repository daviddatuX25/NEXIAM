<?php

namespace App\Models\Printing;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrintJob extends Model
{
    public const ARTIFACT_BLANK_SHEET = 'blank_sheet';

    public const ARTIFACT_RESULT_SHEET = 'result_sheet';

    public const ARTIFACT_OFFICE_DOCUMENT = 'office_document';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RENDERING = 'rendering';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    protected $table = 'print_jobs';

    protected $attributes = ['status' => self::STATUS_QUEUED];

    protected $fillable = [
        'artifact_type', 'scope', 'requester_user_id', 'institution_id',
        'input_manifest', 'artifact_manifest', 'status', 'stage',
        'progress_total', 'progress_done', 'is_preview', 'scope_type', 'scope_id',
        'claim_token', 'claimed_at', 'claim_expires_at', 'attempt_count',
        'requested_by_user_id', 'file_path', 'file_checksum',
        'safe_error', 'per_item_manifest', 'output_manifest', 'pinned_input_json', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_preview' => 'boolean',
            'scope_id' => 'integer',
            'progress_total' => 'integer',
            'progress_done' => 'integer',
            'per_item_manifest' => 'array',
            'output_manifest' => 'array',
            'pinned_input_json' => 'array',
            'claimed_at' => 'datetime',
            'claim_expires_at' => 'datetime',
            'attempt_count' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /**
     * @return HasMany<PrintJobItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PrintJobItem::class);
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }
}
