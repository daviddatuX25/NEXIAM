<?php

namespace App\Application\Capabilities\Handlers\Exams;

final class ExamSessionShowHandler
{
    public function __construct(private readonly ExamSessionProjection $projection) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input = []): array
    {
        $sessionId = (int) ($input['session_id'] ?? 0);

        return [
            'session' => $this->projection->show($sessionId),
        ];
    }
}
