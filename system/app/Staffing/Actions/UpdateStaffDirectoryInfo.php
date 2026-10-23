<?php

declare(strict_types=1);

namespace App\Staffing\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class UpdateStaffDirectoryInfo
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{phone_extension: ?string, room_number: ?string, consultation_hours: ?string}
     */
    public function handle(User $actor, array $data): array
    {
        $validated = Validator::validate($data, [
            'phone_extension' => ['nullable', 'string', 'max:30'],
            'room_number' => ['nullable', 'string', 'max:100'],
            'consultation_hours' => ['nullable', 'string', 'max:255'],
        ]);

        $profile = $actor->staffProfile()->firstOrFail();
        $profile->update($validated);

        $this->recordAuditEvent->handle('auth.staff_directory.updated', [
            'user_id' => $actor->id,
        ], (string) $actor->id);

        return $profile->fresh()->directoryProjection();
    }
}
