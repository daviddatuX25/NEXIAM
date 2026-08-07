<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class Applicant extends Model implements AuthenticatableContract
{
    use Authenticatable, HasFactory, Notifiable;

    protected $fillable = [
        'application_id',
        'email',
        'password',
        'setup_token',
        'setup_token_expires_at',
    ];

    protected $hidden = [
        'password',
        'setup_token',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'setup_token_expires_at' => 'datetime',
            'email_verified_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function applicantScores(): HasMany
    {
        return $this->hasMany(ApplicantScore::class);
    }

    public function consultationSummary(): HasOne
    {
        return $this->hasOne(ConsultationSummary::class);
    }

    public function gradingSessions(): BelongsToMany
    {
        return $this->belongsToMany(GradingSession::class, 'grading_session_applicant')
            ->withPivot('result_printed_at')
            ->withTimestamps();
    }

    public function aiCompanionMessages(): HasMany
    {
        return $this->hasMany(AiCompanionMessage::class);
    }

    /** Exam sessions this applicant is assigned to (at most one per spec). */
    public function examSessions(): BelongsToMany
    {
        return $this->belongsToMany(ExamSession::class, 'exam_session_applicant')
            ->withPivot([
                'attendance_status',
                'attendance_marked_at',
                'attendance_marked_by',
                'submission_status',
                'submitted_at',
                'submitted_to',
            ])
            ->withTimestamps();
    }

    public static function generateSetupToken(): string
    {
        return Str::random(64);
    }

    public function hasCompletedSetup(): bool
    {
        return $this->password !== null && $this->setup_token === null;
    }

    public function isSetupTokenValid(): bool
    {
        return $this->setup_token
            && $this->setup_token_expires_at
            && $this->setup_token_expires_at->isFuture();
    }
}
