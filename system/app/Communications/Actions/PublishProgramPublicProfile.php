<?php

declare(strict_types=1);

namespace App\Communications\Actions;

use App\Models\Communications\ProgramPublicProfileRevision;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

final class PublishProgramPublicProfile
{
    public function __construct(private readonly ProgramPublicProfileCommandSupport $commands) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, Program $program, array $data): ProgramPublicProfileRevision
    {
        $validated = Validator::make($data, [
            'request_id' => ['required', 'uuid'],
            'expected_revision' => ['required', 'integer', 'min:1'],
        ])->validate();

        return $this->commands->publish($actor, $program, [
            'request_id' => strtolower((string) $validated['request_id']),
            'expected_revision' => (int) $validated['expected_revision'],
        ]);
    }
}
