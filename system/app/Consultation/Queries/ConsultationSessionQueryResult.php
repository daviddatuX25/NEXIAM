<?php

namespace App\Consultation\Queries;

use App\Models\Consultation\ConsultationSession;
use Illuminate\Support\Collection;

/**
 * Typed Read Model for ConsultationSession queries with dual projection boundaries.
 */
final class ConsultationSessionQueryResult
{
    /**
     * @param array{
     *     total_count: int,
     *     assigned_count: int,
     *     capacity_count: int,
     *     by_status: array<string, int>,
     * } $summary
     * @param  Collection<int, ConsultationSession>  $sessions
     * @param  array<string, mixed>  $filters
     * @param  list<array<string, mixed>>  $presenterRows
     */
    public function __construct(
        public readonly array $summary,
        public readonly Collection $sessions,
        public readonly array $filters,
        public readonly array $presenterRows,
    ) {}

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
     * }
     */
    public function forInertia(): array
    {
        return [
            'sessions' => $this->presenterRows,
            'filters' => $this->filters,
            'summary' => $this->summary,
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
     *         scheduled_at: ?string,
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
                'scheduled_at' => isset($row['scheduled_at']) ? (string) $row['scheduled_at'] : null,
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
