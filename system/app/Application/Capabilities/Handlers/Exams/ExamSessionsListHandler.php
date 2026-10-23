<?php

namespace App\Application\Capabilities\Handlers\Exams;

use App\Examination\Queries\ExamSessionListQuery;
use Illuminate\Contracts\Auth\Authenticatable;

final class ExamSessionsListHandler
{
    public function __construct(private readonly ExamSessionListQuery $listQuery) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input = [], ?Authenticatable $actor = null): array
    {
        $institutionId = ($actor !== null && method_exists($actor, 'actorScope'))
            ? (int) $actor->actorScope()->institutionId
            : 0;

        if ($institutionId < 1) {
            abort(403, 'Institutional context required.');
        }

        $result = $this->listQuery->query($input, $institutionId);

        return $result->forXiamSafe();
    }
}
