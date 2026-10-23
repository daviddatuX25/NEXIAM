<?php

namespace App\Testing;

use Closure;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class PracticeWorkspace
{
    private ?PracticeFixtureSet $set = null;

    /** @var array<string, bool> */
    private array $provenanceColumns = [];

    public function hasProvenanceColumn(string $table): bool
    {
        return $this->provenanceColumns[$table] ??= Schema::hasColumn($table, 'practice_fixture_set_id');
    }

    public function currentId(): ?string
    {
        return $this->set?->getKey();
    }

    public function current(): ?PracticeFixtureSet
    {
        return $this->set;
    }

    public function set(?PracticeFixtureSet $set): void
    {
        if ($set !== null && (! Schema::hasTable('practice_fixture_sets') || ! Schema::hasColumn('admission_attempts', 'practice_fixture_set_id'))) {
            throw new RuntimeException('Apply the practice preparation migration before selecting practice data.');
        }
        $this->set = $set;
    }

    public function run(PracticeFixtureSet $set, Closure $callback): mixed
    {
        $previous = $this->set;
        $this->set($set);
        try {
            return $callback();
        } finally {
            $this->set = $previous;
        }
    }

    public function normalizedKey(string $operationalKey): string
    {
        return $this->currentId() === null ? $operationalKey : hash('sha256', $this->currentId().'|'.$operationalKey);
    }

    public function assertWritable(): void
    {
        if ($this->set !== null) {
            $fresh = PracticeFixtureSet::query()->find($this->set->getKey());
            if ($fresh === null || $fresh->status !== 'active') {
                throw new RuntimeException('This practice set is retired and cannot be changed.');
            }
        }
    }
}
