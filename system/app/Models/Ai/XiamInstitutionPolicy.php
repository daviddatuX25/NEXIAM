<?php

namespace App\Models\Ai;

use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Durable per-institution AI policy, emergency stop, and tool switchboard.
 *
 * @property int $id
 * @property int $institution_id
 * @property bool $emergency_stop
 * @property string|null $emergency_stop_reason
 * @property Carbon|null $emergency_stopped_at
 * @property array<string>|null $disabled_tools
 * @property int $policy_version
 * @property int|null $updated_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class XiamInstitutionPolicy extends Model
{
    protected $table = 'xiam_institution_policies';

    protected $fillable = [
        'institution_id',
        'emergency_stop',
        'emergency_stop_reason',
        'emergency_stopped_at',
        'disabled_tools',
        'policy_version',
        'updated_by_user_id',
        'admin_locked_tools',
        'applicant_inquiry_enabled',
        'applicant_self_booking_enabled',
        'daily_institution_ask_ceiling',
        'daily_applicant_pool_asks',
        'office_ask_allocations',
        'daily_institution_budget_micro',
        'office_budget_allocations_micro',
        'per_staff_daily_ask_limit',
        'per_applicant_daily_ask_limit',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'emergency_stop' => 'boolean',
            'emergency_stopped_at' => 'datetime',
            'disabled_tools' => 'array',
            'policy_version' => 'integer',
            'admin_locked_tools' => 'array',
            'applicant_inquiry_enabled' => 'boolean',
            'applicant_self_booking_enabled' => 'boolean',
            'daily_institution_ask_ceiling' => 'integer',
            'daily_applicant_pool_asks' => 'integer',
            'office_ask_allocations' => 'array',
            'daily_institution_budget_micro' => 'integer',
            'office_budget_allocations_micro' => 'array',
            'per_staff_daily_ask_limit' => 'integer',
            'per_applicant_daily_ask_limit' => 'integer',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function isEmergencyStopActive(): bool
    {
        return (bool) $this->emergency_stop;
    }

    public function isToolEnabled(string $capabilityId): bool
    {
        $disabled = $this->disabled_tools ?? [];

        return ! in_array($capabilityId, $disabled, true);
    }

    public static function forInstitution(int $institutionId): self
    {
        /** @var self|null $policy */
        $policy = self::query()->where('institution_id', $institutionId)->first();

        if ($policy !== null) {
            return $policy;
        }

        return new self([
            'institution_id' => $institutionId,
            'emergency_stop' => false,
            'disabled_tools' => [],
            'policy_version' => 1,
        ]);
    }
}
