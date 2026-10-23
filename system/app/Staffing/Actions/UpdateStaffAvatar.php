<?php

declare(strict_types=1);

namespace App\Staffing\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\StaffProfile;
use App\Models\User;
use App\Support\Media\ImagePolicy;
use App\Support\Media\ImageProcessor;
use App\Support\Media\MediaStorageLifecycle;
use App\Support\Media\RasterInput;
use App\Support\Media\StagedMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UpdateStaffAvatar
{
    private const PRESETS = [
        'counselor',
        'guidance_counselor',
        'guidance_clerk',
        'guidance_support',
        'guidance_support_staff',
        'registrar_admin',
        'records',
        'registrar_clerk',
        'registrar_support',
        'registrar_support_staff',
        'program_head',
        'dean',
        'academic_exec',
        'hr_officer',
        'exam_proctor',
    ];

    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly ImageProcessor $imageProcessor,
        private readonly MediaStorageLifecycle $lifecycle,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{type: string, preset: ?string, url: ?string}
     */
    public function handle(User $actor, array $data, ?UploadedFile $photo = null): array
    {
        $type = $data['type'] ?? null;

        if ($type === 'upload') {
            if ($photo === null) {
                throw ValidationException::withMessages(['photo' => 'A photo is required.']);
            }

            $sanitized = $this->imageProcessor->process(RasterInput::fromUploadedFile($photo), ImagePolicy::staffAvatar(), 'photo');
            $targetPath = 'avatars/'.$actor->id.'/'.Str::uuid().'.png';
            $staged = $this->lifecycle->stage('public', $targetPath, $sanitized->bytes, 'image/png');

            $result = $this->lifecycle->commit($staged, function (StagedMedia $staged) use ($actor, $type): array {
                $profile = StaffProfile::query()->where('user_id', $actor->id)->lockForUpdate()->firstOrFail();
                $oldPath = $profile->avatar_path;
                $profile->update([
                    'avatar_type' => 'upload',
                    'avatar_path' => $staged->path,
                    'avatar_preset' => null,
                ]);
                $this->recordAuditEvent->handle('auth.staff_avatar.updated', [
                    'user_id' => $actor->id,
                    'avatar_type' => $type,
                ], (string) $actor->id);

                return [
                    'old_path' => $oldPath,
                    'projection' => $profile->fresh()->avatarProjection(),
                ];
            });

            return $result['projection'];
        }

        if ($type === 'preset') {
            $preset = $data['preset'] ?? null;
            if (! is_string($preset) || ! in_array($preset, self::PRESETS, true)) {
                throw ValidationException::withMessages(['preset' => 'The selected preset is invalid.']);
            }

            $result = DB::transaction(function () use ($actor, $preset, $type): array {
                $profile = StaffProfile::query()->where('user_id', $actor->id)->lockForUpdate()->firstOrFail();
                $oldPath = $profile->avatar_path;
                $profile->update([
                    'avatar_type' => 'preset',
                    'avatar_preset' => $preset,
                    'avatar_path' => null,
                ]);
                $this->recordAuditEvent->handle('auth.staff_avatar.updated', [
                    'user_id' => $actor->id,
                    'avatar_type' => $type,
                ], (string) $actor->id);

                return [
                    'old_path' => $oldPath,
                    'projection' => $profile->fresh()->avatarProjection(),
                ];
            });

            $this->lifecycle->deleteQuietly('public', $result['old_path']);

            return $result['projection'];
        }

        if ($type === 'initials') {
            $result = DB::transaction(function () use ($actor, $type): array {
                $profile = StaffProfile::query()->where('user_id', $actor->id)->lockForUpdate()->firstOrFail();
                $oldPath = $profile->avatar_path;
                $profile->update([
                    'avatar_type' => 'initials',
                    'avatar_preset' => null,
                    'avatar_path' => null,
                ]);
                $this->recordAuditEvent->handle('auth.staff_avatar.updated', [
                    'user_id' => $actor->id,
                    'avatar_type' => $type,
                ], (string) $actor->id);

                return [
                    'old_path' => $oldPath,
                    'projection' => $profile->fresh()->avatarProjection(),
                ];
            });

            $this->lifecycle->deleteQuietly('public', $result['old_path']);

            return $result['projection'];
        }

        throw ValidationException::withMessages(['type' => 'The selected avatar type is invalid.']);
    }
}
