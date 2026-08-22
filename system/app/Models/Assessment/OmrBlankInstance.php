<?php

namespace App\Models\Assessment;

use App\Models\Printing\PrintJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OmrBlankInstance extends Model
{
    public const STATUS_PRINTED = 'printed';
    public const STATUS_CAPTURED = 'captured';
    public const STATUS_SUPERSEDED = 'superseded';
    public const STATUS_VOID = 'void';

    public const TERMINAL_STATUSES = [
        self::STATUS_CAPTURED,
        self::STATUS_SUPERSEDED,
        self::STATUS_VOID,
    ];

    protected $table = 'omr_blank_instances';

    protected $attributes = [
        'status' => self::STATUS_PRINTED,
        'signature_version' => 1,
    ];

    protected $fillable = [
        'template_version_id', 'instance_code', 'display_code', 'page_set_no',
        'page_count', 'status', 'print_job_id', 'superseded_by_instance_id',
        'qr_manifest_hash', 'signing_key_id', 'signature_version',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'template_version_id' => 'integer',
            'page_set_no' => 'integer',
            'page_count' => 'integer',
            'print_job_id' => 'integer',
            'superseded_by_instance_id' => 'integer',
            'signature_version' => 'integer',
            'created_by_user_id' => 'integer',
        ];
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(OmrAnswerSheetTemplateVersion::class, 'template_version_id');
    }

    public function printJob(): BelongsTo
    {
        return $this->belongsTo(PrintJob::class, 'print_job_id');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_instance_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(OmrBlankInstancePage::class, 'instance_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    public function isFullyCaptured(): bool
    {
        return $this->pages()->where('status', '!=', OmrBlankInstancePage::STATUS_CAPTURED)->doesntExist()
            && $this->pages()->count() === $this->page_count;
    }
}
