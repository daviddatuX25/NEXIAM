<?php

declare(strict_types=1);

namespace App\Foundation\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\Institution;
use App\Models\User;
use App\Support\Media\ImagePolicy;
use App\Support\Media\ImageProcessor;
use App\Support\Media\MediaStorageLifecycle;
use App\Support\Media\RasterInput;
use App\Support\Media\StagedMedia;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class UploadInstitutionLogo
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly ImageProcessor $imageProcessor,
        private readonly MediaStorageLifecycle $lifecycle,
        private readonly RecordAuditEvent $audit,
    ) {}

    public function handle(Institution $institution, UploadedFile $file, User|string $actor): Institution
    {
        Validator::validate(['logo' => $file], [
            'logo' => ['required', 'file', 'mimes:jpeg,png,webp', 'max:2048'],
        ]);

        $policy = ImagePolicy::institutionLogo();
        $sanitized = $this->imageProcessor->process(
            RasterInput::fromUploadedFile($file),
            $policy,
            'logo'
        );

        $targetPath = 'institutions/'.$institution->id.'/logo/'.Str::uuid().'.'.$sanitized->extension;
        $staged = $this->lifecycle->stage('public', $targetPath, $sanitized->bytes, $sanitized->mime);

        $result = $this->lifecycle->commit($staged, function (StagedMedia $staged) use ($institution, $actor): array {
            $locked = Institution::query()->lockForUpdate()->findOrFail($institution->id);

            if ($actor instanceof User && ! $this->authorizer->decideFresh($actor, CapabilityCatalog::SETUP_INSTITUTION_MANAGE, [
                'institution_id' => (int) $locked->id,
            ])->allowed) {
                throw new AuthorizationException('Unauthorized to manage institution logo.');
            }

            $oldPath = $locked->logo_path;
            $locked->update([
                'logo_path' => $staged->path,
                'logo_mime' => $staged->mime,
                'logo_byte_size' => $staged->byteSize,
                'logo_sha256' => $staged->sha256,
            ]);

            $this->audit->handle('setup.institution_logo_updated', [
                'institution_id' => (int) $locked->id,
                'old_path' => $oldPath,
                'new_path' => $staged->path,
                'sha256' => $staged->sha256,
                'byte_size' => $staged->byteSize,
                'mime' => $staged->mime,
            ], is_string($actor) ? $actor : (string) $actor->id);

            return [
                'old_path' => $oldPath,
                'institution' => $locked->fresh(),
            ];
        });

        if (! empty($result['old_path']) && $result['old_path'] !== $staged->path) {
            $this->lifecycle->deleteQuietly('public', $result['old_path']);
        }

        return $result['institution'];
    }
}
