<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Application extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_year_id',
        'reference_number',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'birthdate',
        'age',
        'sex',
        'applicant_type',
        'last_school_enrolled',
        'strand',
        'email',
        'phone',
        'address_line',
        'city',
        'province',
        'zip_code',
        'course_preference_1',
        'course_preference_2',
        'course_preference_3',
        'status',
        'processed_by',
        'processed_at',
        'rejection_reason',
        'appointment_id',
        'submitted_at',
        'gwa',
        'admission_slip_printed_at',
        'pipeline_status',
        'pipeline_milestones',
    ];

    protected $casts = [
        'birthdate' => 'date',
        'processed_at' => 'datetime',
        'submitted_at' => 'datetime',
        'admission_slip_printed_at' => 'datetime',
        'pipeline_milestones' => 'array',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function scopeForAcademicYear($query, $academicYear): void
    {
        if ($academicYear instanceof AcademicYear) {
            $query->where('academic_year_id', $academicYear->id);
        } elseif ($academicYear !== null) {
            $query->where('academic_year_id', $academicYear);
        }
    }

    public function coursePreference1(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_preference_1');
    }

    public function coursePreference2(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_preference_2');
    }

    public function coursePreference3(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_preference_3');
    }

    public function applicant(): HasOne
    {
        return $this->hasOne(Applicant::class);
    }

    public static function nextReferenceNumber(): string
    {
        $year = date('Y');
        $prefix = "APP-{$year}-";

        return DB::transaction(function () use ($prefix) {
            $count = static::where('reference_number', 'like', $prefix.'%')->count();

            return $prefix.str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Get the exam session status if this application has an assigned session.
     * Returns null if no session assigned.
     */
    public function assignedSessionStatus(): ?string
    {
        $applicant = $this->applicant;
        if (! $applicant) {
            return null;
        }

        $examSession = $applicant->examSessions()->first();

        return $examSession?->status;
    }

    /**
     * Persist a new pipeline status and record its milestone timestamp.
     *
     * Only called by ApplicationPipelineService — do not invoke directly from
     * controllers or other services.
     *
     * @param  array<string, mixed>  $extraMeta  Additional context stored in the milestone record.
     */
    public function updatePipelineStatus(string $newStatus, array $extraMeta = []): void
    {
        $milestones = $this->pipeline_milestones ?? [];

        // Only record a timestamp on the first time a milestone is reached.
        if (! isset($milestones[$newStatus])) {
            $milestones[$newStatus] = array_merge(
                ['at' => now()->toIso8601String()],
                $extraMeta
            );
        }

        $this->update([
            'pipeline_status' => $newStatus,
            'pipeline_milestones' => $milestones,
        ]);
    }

    /**
     * Check if the applicant can edit this application.
     * Rules:
     * - Application status must be 'accepted'
     * - No exam session assigned OR assigned session is 'draft'
     */
    public function isEditableByApplicant(): bool
    {
        // Must be accepted first
        if ($this->status !== 'accepted') {
            return false;
        }

        // Must have an applicant record
        if (! $this->applicant) {
            return false;
        }

        $sessionStatus = $this->assignedSessionStatus();

        // No session assigned → editable
        if ($sessionStatus === null) {
            return true;
        }

        // Session is draft → editable
        if ($sessionStatus === ExamSession::STATUS_DRAFT) {
            return true;
        }

        // Session is published/in_progress/completed/cancelled → locked
        return false;
    }
}
