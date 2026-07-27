<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class AcademicYear extends Model
{
    use HasFactory;
    protected $table = 'academic_years';

    protected $fillable = ['academic_year', 'semester', 'is_active', 'application_start_date', 'application_end_date'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'application_start_date' => 'date',
            'application_end_date' => 'date',
        ];
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'academic_year_id');
    }

    public function examSessions(): HasMany
    {
        return $this->hasMany(ExamSession::class, 'academic_year_id');
    }

    public static function active(): ?self
    {
        return self::query()->where('is_active', true)->first();
    }

    public function isApplicationWindowOpen(): bool
    {
        $today = now()->toDateString();
        if ($this->application_start_date && $today < $this->application_start_date->toDateString()) {
            return false;
        }
        if ($this->application_end_date && $today > $this->application_end_date->toDateString()) {
            return false;
        }

        return true;
    }

    public function applicationWindowLabel(): string
    {
        if (! $this->application_start_date && ! $this->application_end_date) {
            return 'No window set';
        }
        $format = 'M j, Y';
        $start = $this->application_start_date?->format($format);
        $end = $this->application_end_date?->format($format);
        if ($start && $end) {
            return sprintf('%s – %s', $start, $end);
        }
        if ($start && ! $end) {
            return sprintf('From %s (no end date)', $start);
        }

        return sprintf('Until %s', $end);
    }

    public function semesterLabel(): string
    {
        return match ($this->semester) {
            '1' => '1st Semester',
            '2' => '2nd Semester',
            default => $this->semester,
        };
    }

    public function activate(): void
    {
        DB::transaction(function () {
            self::query()->where('id', '!=', $this->id)->update(['is_active' => false]);
            $this->update(['is_active' => true]);
        });
    }
}
