<?php

namespace App\Models\Assessment;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Immutable pre-run plan and attributable source/review evidence.
 *
 * @property array<string, mixed>|null $plan_payload
 */
class TestKitAcceptancePlanRecord extends Model
{
    public $timestamps = false;

    protected $table = 'test_kit_acceptance_plans';

    protected $fillable = [
        'public_id',
        'test_kit_version_id',
        'manifest_sha256',
        'source_key',
        'authored_source_sha256',
        'protocol_schema_version',
        'protocol_sha256',
        'plan_schema_version',
        'plan_sha256',
        'plan_payload',
        'provenance_kind',
        'author_reference',
        'truth_source_references_json',
        'review_evidence_reference',
        'review_evidence_sha256',
        'created_by_user_id',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new RuntimeException('test_kit_acceptance_plans_are_append_only');
        });

        static::deleting(static function (): never {
            throw new RuntimeException('test_kit_acceptance_plans_are_append_only');
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'test_kit_version_id' => 'integer',
            'plan_payload' => 'encrypted:array',
            'truth_source_references_json' => 'array',
            'created_by_user_id' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<TestKitVersion, $this> */
    public function testKitVersion(): BelongsTo
    {
        return $this->belongsTo(TestKitVersion::class, 'test_kit_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
