<?php

namespace App\Models\Printing;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintJob extends Model
{
    public const ARTIFACT_BLANK_SHEET = 'blank_sheet';
    public const ARTIFACT_RESULT_SHEET = 'result_sheet';

    public const STATUS_QUEUED = 'queued';
    public const STATUS_RENDERING = 'rendering';
    public const STATUS_READY = 'ready';
    public const STATUS_FAILED = 'failed';

    protected $table = 'print_jobs';

    protected $attributes = ['status' => self::STATUS_QUEUED];

    protected $fillable = [
        'artifact_type', 'scope_type', 'scope_id', 'status',
        'requested_by_user_id', 'file_path', 'file_checksum',
        'safe_error', 'progress_total', 'progress_done',
        'per_item_manifest', 'pinned_input_json', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'scope_id' => 'integer',
            'progress_total' => 'integer',
            'progress_done' => 'integer',
            'per_item_manifest' => 'array',
            'pinned_input_json' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }
}
