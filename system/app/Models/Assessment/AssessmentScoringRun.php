<?php

namespace App\Models\Assessment;

use App\Models\Examination\AssessmentAdministration;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $administration_id
 * @property int|null $test_kit_version_id
 * @property int|null $test_kit_version_revision
 * @property int|null $acceptance_receipt_id
 * @property string|null $manifest_sha256
 * @property string $run_uuid
 * @property string $source
 * @property int|null $actor_user_id
 * @property string $idempotency_key
 * @property Carbon $created_at
 */
class AssessmentScoringRun extends Model
{
    public const UPDATED_AT = null;

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_OMR = 'omr';

    public const SOURCE_IMPORT = 'import';

    protected $table = 'assessment_scoring_runs';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'administration_id',
        'run_uuid',
        'source',
        'actor_user_id',
        'idempotency_key',
        'test_kit_version_id',
        'test_kit_version_revision',
        'acceptance_receipt_id',
        'manifest_sha256',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AssessmentAdministration, $this>
     */
    public function administration(): BelongsTo
    {
        return $this->belongsTo(AssessmentAdministration::class, 'administration_id');
    }

    /**
     * @return BelongsTo<TestKitVersion, $this>
     */
    public function testKitVersion(): BelongsTo
    {
        return $this->belongsTo(TestKitVersion::class, 'test_kit_version_id');
    }

    /**
     * @return BelongsTo<TestKitAcceptanceReceipt, $this>
     */
    public function acceptanceReceipt(): BelongsTo
    {
        return $this->belongsTo(TestKitAcceptanceReceipt::class, 'acceptance_receipt_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * @return HasMany<AssessmentRawResultRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(AssessmentRawResultRevision::class, 'scoring_run_id');
    }
}
