<?php

declare(strict_types=1);

namespace App\Communications\Actions;

use App\Models\Communications\ProgramPublicProfileRevision;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

final class SaveProgramPublicProfile
{
    public function __construct(private readonly ProgramPublicProfileCommandSupport $commands) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, Program $program, array $data): ProgramPublicProfileRevision
    {
        $validated = Validator::make($data, [
            'request_id' => ['required', 'uuid'],
            'expected_revision' => ['required', 'integer', 'min:0'],
            'short_description' => ['nullable', 'string', 'max:240'],
            'description' => ['required', 'string', 'min:1', 'max:10000'],
            'photos' => ['present', 'array', 'max:6'],
            'photos.*' => ['required', 'array'],
            'photos.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'photos.*.alt_text' => ['required', 'string', 'min:1', 'max:160'],
            'photos.*.caption' => ['nullable', 'string', 'max:240'],
        ])->validate();

        $photos = array_values(array_map(static fn (array $photo): array => [
            'id' => (int) $photo['id'],
            'alt_text' => trim((string) $photo['alt_text']),
            'caption' => trim((string) ($photo['caption'] ?? '')),
        ], $validated['photos']));

        return $this->commands->save($actor, $program, [
            'request_id' => strtolower((string) $validated['request_id']),
            'expected_revision' => (int) $validated['expected_revision'],
            'short_description' => trim((string) ($validated['short_description'] ?? '')),
            'description' => trim((string) $validated['description']),
            'photos' => $photos,
        ]);
    }
}
