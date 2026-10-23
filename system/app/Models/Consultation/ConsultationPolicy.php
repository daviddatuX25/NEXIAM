<?php

declare(strict_types=1);

namespace App\Models\Consultation;

use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsultationPolicy extends Model
{
    public const METHOD_F2F = 'f2f';

    public const METHOD_ONLINE = 'online';

    protected $attributes = [
        'normal_method' => self::METHOD_ONLINE,
        'allow_online_exceptions' => false,
        'version' => 1,
    ];

    protected $fillable = [
        'institution_id',
        'academic_cycle_id',
        'normal_method',
        'allow_online_exceptions',
        'configured_by_user_id',
        'version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allow_online_exceptions' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function allowsOnlineConsultation(): bool
    {
        return $this->normal_method === self::METHOD_ONLINE
            || ($this->normal_method === self::METHOD_F2F && $this->allow_online_exceptions);
    }

    /**
     * @return BelongsTo<Institution, $this>
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * @return BelongsTo<AcademicCycle, $this>
     */
    public function academicCycle(): BelongsTo
    {
        return $this->belongsTo(AcademicCycle::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function configuredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'configured_by_user_id');
    }
}
