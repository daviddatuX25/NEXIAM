<?php

namespace App\Models\Foundation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class AcademicCycle extends Model
{
    protected $fillable = [
        'institution_id',
        'code',
        'name',
        'school_year',
        'semester',
        'opens_on',
        'closes_on',
        'is_active',
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
        DB::transaction(function (): void {
            static::query()
                ->where('institution_id', $this->institution_id)
                ->where('id', '!=', $this->id)
                ->update(['is_active' => false]);

            $this->update(['is_active' => true]);

            $window = $this->primaryWindow();
            if ($window !== null) {
                ApplicationWindow::query()
                    ->where('academic_cycle_id', $this->id)
                    ->where('id', '!=', $window->id)
                    ->where('status', 'published')
                    ->update(['status' => 'closed']);

                $window->update(['status' => 'published']);
            }
        });
    }

    public function deactivate(): void
    {
        DB::transaction(function (): void {
            $this->update(['is_active' => false]);
            ApplicationWindow::query()
                ->where('academic_cycle_id', $this->id)
                ->where('status', 'published')
                ->update(['status' => 'closed']);
        });
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
