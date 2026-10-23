<?php

namespace App\Models\Foundation;

use App\Setup\Actions\ActivateAcademicCycle;
use App\Setup\Actions\DeactivateAcademicCycle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicCycle extends Model
{
    protected $attributes = [
        'allow_guidance_all_in_one_intake' => false,
    ];

    protected $fillable = [
        'institution_id',
        'code',
        'name',
        'school_year',
        'semester',
        'opens_on',
        'closes_on',
        'is_active',
        'allow_guidance_all_in_one_intake',
        'admission_notification_policy',
        'exam_notification_policy',
        'communication_master_controls',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opens_on' => 'date',
            'closes_on' => 'date',
            'is_active' => 'boolean',
            'allow_guidance_all_in_one_intake' => 'boolean',
            'admission_notification_policy' => 'array',
            'exam_notification_policy' => 'array',
            'communication_master_controls' => 'array',
        ];
    }

    public static function active(): ?self
    {
        return static::query()->where('is_active', true)->first();
    }

    /**
     * Staff-facing label (legacy AcademicYear feel: school year + semester).
     */
    public function staffLabel(): string
    {
        $year = $this->school_year ?: $this->name;
        $semester = $this->semesterLabel();

        if ($semester === '') {
            return (string) $year;
        }

        return trim("{$year} {$semester}");
    }

    public function semesterLabel(): string
    {
        return match ((string) $this->semester) {
            '1' => '1st Semester',
            '2' => '2nd Semester',
            default => (string) ($this->semester ?? ''),
        };
    }

    public function primaryWindow(): ?ApplicationWindow
    {
        return $this->applicationWindows()->orderByDesc('id')->first();
    }

    public function applicationWindowLabel(): string
    {
        $window = $this->primaryWindow();
        if ($window === null) {
            return 'No window set';
        }

        $start = $window->opens_at?->format('M j, Y');
        $end = $window->closes_at?->format('M j, Y');
        if ($start && $end) {
            return "{$start} to {$end}";
        }

        return $start ? "From {$start}" : ($end ? "Until {$end}" : 'No window set');
    }

    /**
     * Singleton activation (INV-004 / legacy AcademicYear::activate).
     */
    public function activate(): void
    {
        app(ActivateAcademicCycle::class)->handle($this, 'system');
    }

    public function deactivate(): void
    {
        if (! $this->is_active) {
            return;
        }

        app(DeactivateAcademicCycle::class)->handle($this, 'system');
    }

    /**
     * @return BelongsTo<Institution, $this>
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * @return HasMany<ApplicationWindow, $this>
     */
    public function applicationWindows(): HasMany
    {
        return $this->hasMany(ApplicationWindow::class);
    }
}
