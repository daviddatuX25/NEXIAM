<?php

namespace App\Examination\Queries;

use App\Models\Examination\ExamSession;
use Illuminate\Support\Collection;

/**
 * Typed Read Model for ExamSession queries with dual projection boundaries.
 */
final class ExamSessionQueryResult
{
    /**
     * @var list<array<string, mixed>>
     */
    public readonly array $examineeMatches;

    /**
     * @var list<array<string, mixed>>
     */
    public readonly array $examinee_matches;

    /**
     * @param array{
     *     total_count: int,
     *     assigned_count: int,
     *     capacity_count: int,
     *     by_status: array<string, int>,
     * } $summary
     * @param  Collection<int, ExamSession>  $sessions
     * @param  array<string, mixed>  $filters
     * @param  list<array<string, mixed>>  $presenterRows
     * @param  list<array<string, mixed>>  $examineeMatches
     */
    public function __construct(
        public readonly array $summary,
        public readonly Collection $sessions,
        public readonly array $filters,
        public readonly array $presenterRows,
        array $examineeMatches = [],
    ) {
        $this->examineeMatches = $examineeMatches;
        $this->examinee_matches = $examineeMatches;
    }

    /**
     * Full presentation projection for Inertia web views.
     *
     * @return array{
     *     sessions: list<array<string, mixed>>,
     *     filters: array<string, mixed>,
     *     summary: array{
     *         total_count: int,
     *         assigned_count: int,
     *         capacity_count: int,
     *         by_status: array<string, int>,
     *     },
     *     examinee_matches: list<array<string, mixed>>,
     * }
     */
    public function forInertia(): array
    {
        return [
            'sessions' => $this->presenterRows,
            'filters' => $this->filters,
            'summary' => $this->summary,
            'examinee_matches' => $this->examineeMatches,
        ];
    }

    /**
     * Minimized, privacy-scrubbed DTO for LLM / Xiam tool results.
     * Guaranteed zero applicant PII and pre-computed authoritative SQL totals.
     *
     * @return array{
     *     summary: array{
     *         total_count: int,
     *         assigned_count: int,
     *         capacity_count: int,
     *         by_status: array<string, int>,
     *     },
     *     sessions: list<array{
     *         id: int,
     *         code: string,
     *         status: string,
     *         starts_at: ?string,
     *         assigned_count: int,
     *         room_capacity: int,
     *         room_code: ?string,
     *     }>,
     * }
     */
    public function forXiamSafe(int $sampleLimit = 5): array
    {
        $sample = array_slice($this->presenterRows, 0, max(1, $sampleLimit));

        $safeRows = array_map(static function (array $row): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'code' => (string) ($row['code'] ?? ''),
                'status' => (string) ($row['status'] ?? ''),
                'starts_at' => isset($row['starts_at']) ? (string) $row['starts_at'] : null,
                'assigned_count' => (int) ($row['assigned_count'] ?? 0),
                'room_capacity' => (int) ($row['room']['capacity'] ?? 0),
                'room_code' => isset($row['room']['code']) ? (string) $row['room']['code'] : null,
            ];
        }, $sample);

        return [
            'summary' => $this->summary,
            'sessions' => $safeRows,
        ];
    }
}
