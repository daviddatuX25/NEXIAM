<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestKitPreparationCompositionRevision extends Model
{
    public $timestamps = false;

    protected $table = 'test_kit_preparation_composition_revisions';

    protected $fillable = [
        'test_kit_preparation_id',
        'test_kit_id',
        'composition_revision',
        'template_version_id',
        'booklet_version_id',
        'booklet_set_id',
        'test_instrument_version_id',
        'component_vector_json',
        'component_vector_sha256',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'test_kit_preparation_id' => 'integer',
            'test_kit_id' => 'integer',
            'composition_revision' => 'integer',
            'template_version_id' => 'integer',
            'booklet_version_id' => 'integer',
            'booklet_set_id' => 'integer',
            'test_instrument_version_id' => 'integer',
            'component_vector_json' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<TestKitPreparation, $this> */
    public function preparation(): BelongsTo
    {
        return $this->belongsTo(TestKitPreparation::class, 'test_kit_preparation_id');
    }
}
