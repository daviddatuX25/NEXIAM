<?php

namespace App\Scheduling;

use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class CohortPlanner
{
    public const STRATEGY_FCFS = 'fcfs';

    public const STRATEGY_ALPHABETICAL = 'alphabetical';

    public const STRATEGY_PROGRAM = 'program';

    public const PACKING_SEQUENTIAL = 'sequential';

    public const PACKING_BALANCED = 'balanced';

    /**
     * @param  Collection<int, array{
     *     id: int,
     *     reference_code: string,
     *     applicant_identity_id: int,
     *     first_name: string,
     *     last_name: string,
     *     preferred_program_code: ?string,
     *     strand: ?string,
     *     program_strand_key: string,
     *     accepted_at: mixed,
     *     eligibility_timestamp: Carbon,
     *     result_ready_at?: Carbon,
     *     ready_document_id?: ?int,
     *     ready_document_checksum?: ?string,
     *     ready_document_version?: ?int,
     *     ready_document_created_at?: ?Carbon,
     *     score_evidence_type?: ?string,
     *     result_set_id?: ?int,
     *     grading_session_id?: ?int,
     *     administration_id?: ?int,
     *     score_locked_at?: ?Carbon,
     *     scoring_fingerprint?: ?string,
     *     print_receipt_id?: int,
     *     print_evidence_revision?: int,
     *     print_confirmed_at?: Carbon,
     * @param  Collection<int, ExamSession|ConsultationSession>  $targetSessions
     * @return array{
     *     domain: string,
     *     academic_cycle_id: int,
     *     sort_strategy: string,
     *     packing_strategy: string,
     *     plan_hash: string,
     *     pool_fingerprint: array<string, mixed>,
     *     total_candidates: int,
     *     assignable_count: int,
     *     unassigned_count: int,
     *     total_capacity: int,
     *     session_mappings: list<array{
     *         session_id: int,
     *         session_code: string,
     *         room_name: string,
     *         room_capacity: int,
     *         version: int,
     *         current_active: int,
     *         remaining_capacity: int,
     *         allocated_count: int,
     *         candidate_ids: list<int>,
     *     }>,
     *     assignments: list<array{session_id: int, admission_attempt_id: int}>,
     *     unassigned_candidate_ids: list<int>,
     * }
     */
    public function plan(
        string $domain,
        int $academicCycleId,
        Collection $candidates,
        Collection $targetSessions,
        string $sortStrategy,
        string $packingStrategy,
        AssignmentInvariant $invariant,
    ): array {
        if (! in_array($sortStrategy, [self::STRATEGY_FCFS, self::STRATEGY_ALPHABETICAL, self::STRATEGY_PROGRAM], true)) {
            throw new InvalidArgumentException("Unsupported candidate sorting strategy [{$sortStrategy}].");
        }

        if (! in_array($packingStrategy, [self::PACKING_SEQUENTIAL, self::PACKING_BALANCED], true)) {
            throw new InvalidArgumentException("Unsupported distribution packing strategy [{$packingStrategy}].");
        }

        $orderedSessions = $this->orderSessions($targetSessions, $invariant);
        $orderedCandidates = $this->orderCandidates($candidates, $sortStrategy);

        $capacities = $orderedSessions->map(fn (array $s): int => $s['remaining_capacity'])->all();
        $totalCapacity = (int) array_sum($capacities);
        $totalCandidates = $orderedCandidates->count();
        $assignableCount = min($totalCandidates, $totalCapacity);

        // Program allocation selects the same earliest eligible candidates as FCFS,
        // then groups that bounded set for placement.
        $selectionOrderCandidates = $sortStrategy === self::STRATEGY_PROGRAM
            ? $this->orderCandidates($candidates, self::STRATEGY_FCFS)
            : $orderedCandidates;
        $selectedCandidates = $selectionOrderCandidates->take($assignableCount)->values();
        $unassignedCandidates = $selectionOrderCandidates->slice($assignableCount)->values();

        $quotas = $this->computeQuotas($capacities, $assignableCount, $packingStrategy);
        $allocatedCandidates = array_fill(0, count($quotas), []);

        if ($sortStrategy === self::STRATEGY_PROGRAM) {
            $programGroups = $selectedCandidates
                ->groupBy('program_strand_key')
                ->map(fn (Collection $group): Collection => $group->values())
                ->all();
            uksort($programGroups, function (string|int $a, string|int $b) use ($programGroups): int {
                $sizeCompare = count($programGroups[$b]) <=> count($programGroups[$a]);

                return $sizeCompare !== 0 ? $sizeCompare : strcmp((string) $a, (string) $b);
            });

            $remainingQuotas = $quotas;
            foreach ($programGroups as $group) {
                $groupCandidates = $group->values()->all();
                $groupSize = count($groupCandidates);
                while ($groupSize > 0) {
                    $availableIndices = array_keys(array_filter($remainingQuotas, fn (int $quota): bool => $quota > 0));
                    if ($availableIndices === []) {
                        break;
                    }

                    $fittingIndex = null;
                    foreach ($availableIndices as $sessionIndex) {
                        if ($remainingQuotas[$sessionIndex] >= $groupSize) {
                            $fittingIndex = $sessionIndex;
                            break;
                        }
                    }

                    $sessionIndex = $fittingIndex ?? $availableIndices[0];
                    if ($fittingIndex === null) {
                        foreach ($availableIndices as $availableIndex) {
                            if ($remainingQuotas[$availableIndex] > $remainingQuotas[$sessionIndex]) {
                                $sessionIndex = $availableIndex;
                            }
                        }
                    }

                    $take = min($groupSize, $remainingQuotas[$sessionIndex]);
                    array_push($allocatedCandidates[$sessionIndex], ...array_splice($groupCandidates, 0, $take));
                    $remainingQuotas[$sessionIndex] -= $take;
                    $groupSize -= $take;
                }
            }
        } else {
            $pointer = 0;
            foreach ($quotas as $sessionIndex => $quota) {
                $allocatedCandidates[$sessionIndex] = $selectedCandidates->slice($pointer, $quota)->values()->all();
                $pointer += $quota;
            }
        }

        $sessionMappings = [];
        $assignments = [];
        $assignedCandidates = [];

        foreach ($orderedSessions as $index => $sessionMeta) {
            $sessionCandidates = $allocatedCandidates[$index] ?? [];
            usort($sessionCandidates, fn (array $a, array $b): int => $this->compareSurname($a, $b));
            $candidateIds = array_map(fn (array $candidate): int => (int) $candidate['id'], $sessionCandidates);
            foreach ($sessionCandidates as $candidate) {
                $assignments[] = [
                    'session_id' => $sessionMeta['session_id'],
                    'admission_attempt_id' => (int) $candidate['id'],
                ];
                $assignedCandidates[] = $candidate;
            }

            $sessionMappings[] = [
                'session_id' => $sessionMeta['session_id'],
                'session_code' => $sessionMeta['session_code'],
                'room_name' => $sessionMeta['room_name'],
                'room_capacity' => $sessionMeta['room_capacity'],
                'version' => $sessionMeta['version'],
                'current_active' => $sessionMeta['current_active'],
                'remaining_capacity' => $sessionMeta['remaining_capacity'],
                'allocated_count' => count($candidateIds),
                'candidate_ids' => $candidateIds,
            ];
        }

        $unassignedCandidateIds = $unassignedCandidates->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $unassignedCount = count($unassignedCandidateIds);

        $poolFingerprint = [
            'domain' => $domain,
            'academic_cycle_id' => $academicCycleId,
            'sort_strategy' => $sortStrategy,
            'packing_strategy' => $packingStrategy,
            'target_sessions' => array_map(fn (array $s): array => [
                'id' => $s['session_id'],
                'code' => $s['session_code'],
                'version' => $s['version'],
                'room_capacity' => $s['room_capacity'],
                'remaining_capacity' => $s['remaining_capacity'],
            ], $orderedSessions->all()),
            'candidates' => $orderedCandidates->map(fn (array $c): array => [
                'id' => $c['id'],
                'eligibility_timestamp' => $c['eligibility_timestamp']->toISOString(),
                'last_name' => strtoupper($c['last_name']),
                'first_name' => strtoupper($c['first_name']),
                'program_strand_key' => $c['program_strand_key'],
                'ready_document_id' => $c['ready_document_id'] ?? null,
                'ready_document_checksum' => $c['ready_document_checksum'] ?? null,
                'ready_document_version' => $c['ready_document_version'] ?? null,
                'score_evidence_type' => $c['score_evidence_type'] ?? null,
                'result_set_id' => $c['result_set_id'] ?? null,
                'grading_session_id' => $c['grading_session_id'] ?? null,
                'administration_id' => $c['administration_id'] ?? null,
                'score_locked_at' => isset($c['score_locked_at']) && $c['score_locked_at'] !== null
                    ? $c['score_locked_at']->toISOString()
                    : null,
                'scoring_fingerprint' => $c['scoring_fingerprint'] ?? null,
                'ready_document_created_at' => isset($c['ready_document_created_at']) && $c['ready_document_created_at'] !== null
                    ? $c['ready_document_created_at']->toISOString()
                    : null,
                'print_receipt_id' => $c['print_receipt_id'] ?? null,
                'print_evidence_revision' => $c['print_evidence_revision'] ?? null,
                'print_confirmed_at' => isset($c['print_confirmed_at'])
                    ? $c['print_confirmed_at']->toISOString()
                    : null,
            ])->values()->all(),
        ];
        $assignedSessionMap = [];
        foreach ($sessionMappings as $mapping) {
            foreach ($mapping['candidate_ids'] as $cid) {
                $assignedSessionMap[$cid] = [
                    'session_id' => $mapping['session_id'],
                    'session_code' => $mapping['session_code'],
                    'room_name' => $mapping['room_name'],
                ];
            }
        }

        $candidatePreviewList = collect($assignedCandidates)->concat($unassignedCandidates)->map(function (array $c) use ($assignedSessionMap): array {
            $assigned = $assignedSessionMap[$c['id']] ?? null;

            return [
                'id' => (int) $c['id'],
                'reference_code' => (string) $c['reference_code'],
                'name' => trim($c['last_name'].', '.$c['first_name']),
                'program_strand_key' => (string) $c['program_strand_key'],
                'eligibility_date' => $c['eligibility_timestamp']->format('M j, Y g:i A'),
                'assigned_session_id' => $assigned['session_id'] ?? null,
                'assigned_session_code' => $assigned['session_code'] ?? null,
                'assigned_room_name' => $assigned['room_name'] ?? null,
            ];
        })->values()->all();

        $planHash = hash('sha256', json_encode($poolFingerprint, JSON_THROW_ON_ERROR));

        return [
            'domain' => $domain,
            'academic_cycle_id' => $academicCycleId,
            'sort_strategy' => $sortStrategy,
            'packing_strategy' => $packingStrategy,
            'plan_hash' => $planHash,
            'pool_fingerprint' => $poolFingerprint,
            'total_candidates' => $totalCandidates,
            'assignable_count' => $assignableCount,
            'unassigned_count' => $unassignedCount,
            'total_capacity' => $totalCapacity,
            'session_mappings' => $sessionMappings,
            'assignments' => $assignments,
            'unassigned_candidate_ids' => $unassignedCandidateIds,
            'candidates' => $candidatePreviewList,
        ];
    }

    /**
     * @param  Collection<int, ExamSession|ConsultationSession>  $sessions
     * @return Collection<int, array{
     *     model: ExamSession|ConsultationSession,
     *     session_id: int,
     *     session_code: string,
     *     room_name: string,
     *     room_capacity: int,
     *     version: int,
     *     current_active: int,
     *     remaining_capacity: int,
     *     schedule_timestamp: ?string,
     * }>
     */
    private function orderSessions(Collection $sessions, AssignmentInvariant $invariant): Collection
    {
        return $sessions
            ->map(function (ExamSession|ConsultationSession $session) use ($invariant): array {
                $room = $session->room;
                $roomCapacity = $invariant->capacityLimit($session);
                $remainingCapacity = $invariant->remainingCapacity($session);
                $currentActive = $session instanceof ExamSession
                    ? ExamAssignment::query()->active()->where('exam_session_id', $session->id)->count()
                    : ConsultationAssignment::query()->active()->whereHas('admissionAttempt', fn ($query) => $query->forWorkspace())->where('consultation_session_id', $session->id)->count();
                $scheduleTimestamp = $session instanceof ExamSession
                    ? ($session->starts_at?->toISOString() ?? '')
                    : ($session->scheduled_at?->toISOString() ?? '');

                return [
                    'model' => $session,
                    'session_id' => (int) $session->id,
                    'session_code' => (string) $session->code,
                    'room_name' => $room?->name ?? 'Room',
                    'room_capacity' => $roomCapacity,
                    'version' => (int) $session->version,
                    'current_active' => $currentActive,
                    'remaining_capacity' => $remainingCapacity,
                    'schedule_timestamp' => $scheduleTimestamp,
                ];
            })
            ->sort(function (array $a, array $b): int {
                $timeCompare = strcmp((string) $a['schedule_timestamp'], (string) $b['schedule_timestamp']);
                if ($timeCompare !== 0) {
                    return $timeCompare;
                }

                return $a['session_id'] <=> $b['session_id'];
            })
            ->values();
    }

    private function compareSurname(array $a, array $b): int
    {
        $lastCompare = strcasecmp((string) ($a['last_name'] ?? ''), (string) ($b['last_name'] ?? ''));
        if ($lastCompare !== 0) {
            return $lastCompare;
        }

        $firstCompare = strcasecmp((string) ($a['first_name'] ?? ''), (string) ($b['first_name'] ?? ''));
        if ($firstCompare !== 0) {
            return $firstCompare;
        }

        return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
    }

    /**
     * @param  Collection<int, array{
     *     id: int,
     *     first_name: string,
     *     last_name: string,
     *     program_strand_key: string,
     *     eligibility_timestamp: Carbon,
     * }>  $candidates
     * @return Collection<int, array>
     */
    private function orderCandidates(Collection $candidates, string $strategy): Collection
    {
        return $candidates
            ->sort(function (array $a, array $b) use ($strategy): int {
                if ($strategy === self::STRATEGY_ALPHABETICAL) {
                    $lastCompare = strcasecmp((string) $a['last_name'], (string) $b['last_name']);
                    if ($lastCompare !== 0) {
                        return $lastCompare;
                    }

                    $firstCompare = strcasecmp((string) $a['first_name'], (string) $b['first_name']);
                    if ($firstCompare !== 0) {
                        return $firstCompare;
                    }

                    return $a['id'] <=> $b['id'];
                }

                if ($strategy === self::STRATEGY_PROGRAM) {
                    $programCompare = strcasecmp((string) $a['program_strand_key'], (string) $b['program_strand_key']);
                    if ($programCompare !== 0) {
                        return $programCompare;
                    }

                    $timeCompare = $a['eligibility_timestamp']->timestamp <=> $b['eligibility_timestamp']->timestamp;
                    if ($timeCompare !== 0) {
                        return $timeCompare;
                    }

                    return $a['id'] <=> $b['id'];
                }

                // FCFS default
                $timeCompare = $a['eligibility_timestamp']->timestamp <=> $b['eligibility_timestamp']->timestamp;
                if ($timeCompare !== 0) {
                    return $timeCompare;
                }

                return $a['id'] <=> $b['id'];
            })
            ->values();
    }

    /**
     * @param  list<int>  $capacities
     * @return list<int>
     */
    private function computeQuotas(array $capacities, int $assignableCount, string $packingStrategy): array
    {
        $k = count($capacities);
        if ($k === 0 || $assignableCount <= 0) {
            return array_fill(0, $k, 0);
        }

        if ($packingStrategy === self::PACKING_SEQUENTIAL) {
            $quotas = [];
            $remaining = $assignableCount;
            foreach ($capacities as $capacity) {
                $quota = min($remaining, $capacity);
                $quotas[] = $quota;
                $remaining -= $quota;
            }

            return $quotas;
        }

        $quotas = array_fill(0, $k, 0);
        $unassigned = min($assignableCount, array_sum($capacities));

        while ($unassigned > 0) {
            $eligibleIndices = [];
            foreach ($capacities as $index => $capacity) {
                if ($quotas[$index] < $capacity) {
                    $eligibleIndices[] = $index;
                }
            }

            if ($eligibleIndices === []) {
                break;
            }

            $lowestQuota = min(array_map(fn (int $index): int => $quotas[$index], $eligibleIndices));
            $lowestIndices = array_values(array_filter(
                $eligibleIndices,
                fn (int $index): bool => $quotas[$index] === $lowestQuota,
            ));
            $baseShare = intdiv($unassigned, count($lowestIndices));
            $remainder = $unassigned % count($lowestIndices);
            $allocatedThisRound = 0;

            foreach ($lowestIndices as $position => $index) {
                $roomLeft = $capacities[$index] - $quotas[$index];
                $additional = $baseShare + ($position < $remainder ? 1 : 0);
                $give = min($additional, $roomLeft);
                $quotas[$index] += $give;
                $allocatedThisRound += $give;
            }

            if ($allocatedThisRound === 0) {
                break;
            }

            $unassigned -= $allocatedThisRound;
        }

        return $quotas;
    }
}
