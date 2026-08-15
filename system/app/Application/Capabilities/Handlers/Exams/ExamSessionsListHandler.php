<?php

namespace App\Application\Capabilities\Handlers\Exams;

final class ExamSessionsListHandler
{
    public function __construct(private readonly ExamSessionProjection $projection) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input = []): array
    {
        $sessions = $this->projection->list(
            status: isset($input['status']) && is_string($input['status']) ? $input['status'] : null,
            academicCycleId: isset($input['academic_cycle_id']) ? (int) $input['academic_cycle_id'] : null,
            search: isset($input['search']) && is_string($input['search']) ? $input['search'] : null,
        );

        return [
            'sessions' => $sessions,
            'page_state' => $sessions === [] ? 'empty' : 'ready',
        ];
    }
}
