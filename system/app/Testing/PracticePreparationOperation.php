<?php

namespace App\Testing;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PracticePreparationOperation extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'preview'];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'prerequisite_snapshot' => 'array',
            'expected_revision' => 'integer',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'result' => 'array',
        ];
    }

    public function fixtureSet(): BelongsTo
    {
        return $this->belongsTo(PracticeFixtureSet::class, 'practice_fixture_set_id');
    }
}
