<?php

namespace App\Models\Assessment;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OmrPendingCapture extends Model
{
    public const STATUS_RECEIVED = 'received';

    public const STATUS_ANALYZING = 'analyzing';

    public const STATUS_NEEDS_RECOVERY = 'needs_recovery';

    public const STATUS_RESOLUTION_REQUIRED = 'resolution_required';

    public const STATUS_READY = 'ready';

    public const STATUS_ADOPTED = 'adopted';

    public const STATUS_QUARANTINED = 'quarantined';

    public const STATUS_FAILED = 'failed';

    public const ASSET_LIVE = 'live';

    public const ASSET_CLEANUP_PENDING = 'cleanup_pending';

    protected $table = 'omr_pending_captures';

    protected $fillable = [
        'client_capture_id',
        'status',
        'capture_mode',
        'page_no',
        'page_count',
        'qr_state',
        'qr_payload',
        'qr_payload_hash',
        'institution_id',
        'academic_cycle_id',
        'template_version_id',
        'blank_instance_id',
        'blank_instance_page_id',
        'asset_path',
        'asset_hmac',
        'asset_hmac_key_id',
        'asset_key_id',
        'asset_format_version',
        'asset_state',
        'original_mime',
        'original_bytes',
        'input_hash',
        'aligned_mime',
        'aligned_bytes',
        'aligned_width',
        'aligned_height',
        'aligned_hash',
        'aligned_asset_path',
        'aligned_asset_hmac',
        'aligned_asset_hmac_key_id',
        'aligned_asset_key_id',
        'evidence_json',
        'resolution_json',
        'analysis_code',
        'analysis_detail',
        'detected_applicant_number',
        'detected_applicant_confidence',
        'detected_birthdate',
        'grading_session_id',
        'admission_attempt_id',
        'adopted_sheet_id',
        'uploaded_by_user_id',
        'resolved_by_user_id',
        'adopted_by_user_id',
        'version',
        'captured_at',
        'analyzed_at',
        'adopted_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'page_no' => 'integer',
            'page_count' => 'integer',
            'institution_id' => 'integer',
            'academic_cycle_id' => 'integer',
            'template_version_id' => 'integer',
            'blank_instance_id' => 'integer',
            'blank_instance_page_id' => 'integer',
            'original_bytes' => 'integer',
            'aligned_bytes' => 'integer',
            'aligned_width' => 'integer',
            'aligned_height' => 'integer',
            'evidence_json' => 'array',
            'resolution_json' => 'array',
            'detected_applicant_confidence' => 'float',
            'grading_session_id' => 'integer',
            'admission_attempt_id' => 'integer',
            'adopted_sheet_id' => 'integer',
            'uploaded_by_user_id' => 'integer',
            'resolved_by_user_id' => 'integer',
            'adopted_by_user_id' => 'integer',
            'version' => 'integer',
            'captured_at' => 'datetime',
            'analyzed_at' => 'datetime',
            'adopted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'institution_id');
    }

    public function academicCycle(): BelongsTo
    {
        return $this->belongsTo(AcademicCycle::class, 'academic_cycle_id');
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(OmrAnswerSheetTemplateVersion::class, 'template_version_id');
    }

    public function blankInstance(): BelongsTo
    {
        return $this->belongsTo(OmrBlankInstance::class, 'blank_instance_id');
    }

    public function blankInstancePage(): BelongsTo
    {
        return $this->belongsTo(OmrBlankInstancePage::class, 'blank_instance_page_id');
    }

    public function gradingSession(): BelongsTo
    {
        return $this->belongsTo(GradingSession::class, 'grading_session_id');
    }

    public function admissionAttempt(): BelongsTo
    {
        return $this->belongsTo(AdmissionAttempt::class, 'admission_attempt_id');
    }

    public function adoptedSheet(): BelongsTo
    {
        return $this->belongsTo(OmrCaptureSheet::class, 'adopted_sheet_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function adoptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adopted_by_user_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_ADOPTED, self::STATUS_QUARANTINED, self::STATUS_FAILED], true);
    }

    public function isRecoverable(): bool
    {
        return in_array($this->status, [self::STATUS_NEEDS_RECOVERY, self::STATUS_RESOLUTION_REQUIRED, self::STATUS_READY], true);
    }
}
