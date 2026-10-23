<?php

namespace App\Application\Capabilities\Handlers\Exams;

use App\Examination\Queries\ExamSessionDetailQuery;
use Illuminate\Contracts\Auth\Authenticatable;

final class ExamSessionShowHandler
{
    public function __construct(private readonly ExamSessionDetailQuery $detailQuery) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input = [], ?Authenticatable $actor = null): array
    {
        $sessionId = (int) ($input['session_id'] ?? 0);
        $institutionId = ($actor !== null && method_exists($actor, 'actorScope'))
            ? (int) $actor->actorScope()->institutionId
            : 0;

        if ($institutionId < 1) {
            abort(403, 'Institutional context required.');
        }

        $detail = $this->detailQuery->query($sessionId, $institutionId);

        return [
            'session' => ($detail['forXiamSafe'])(),
        ];
    }
}
