<?php

namespace App\Testing;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class PracticePurpose
{
    public static function root(Builder|QueryBuilder $query, string $table, ?string $setId): Builder|QueryBuilder
    {
        [$schemaTable, $alias] = array_pad(explode(' as ', $table, 2), 2, $table);
        if (! app(PracticeWorkspace::class)->hasProvenanceColumn($schemaTable)) {
            if ($setId !== null) {
                $query->whereRaw('1 = 0');
            }

            return $query;
        }

        return $setId === null
            ? $query->whereNull($alias.'.practice_fixture_set_id')
            : $query->where($alias.'.practice_fixture_set_id', $setId);
    }

    public static function attempts(Builder|QueryBuilder $query, string $foreignKey, ?string $setId): Builder|QueryBuilder
    {
        return $query->whereIn($foreignKey, self::root(AdmissionAttempt::query(), 'admission_attempts', $setId)->select('admission_attempts.id'));
    }

    public static function sessions(Builder|QueryBuilder $query, string $foreignKey, ?string $setId): Builder|QueryBuilder
    {
        return $query->whereIn($foreignKey, self::root(ExamSession::query(), 'exam_sessions', $setId)->select('exam_sessions.id'));
    }
}
