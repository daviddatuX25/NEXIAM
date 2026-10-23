<?php

declare(strict_types=1);

namespace App\Communications\Actions;

use App\Communications\HomepageAuthorizer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Communications\PublicHomepageMedia;
use App\Models\User;
use App\Support\Media\ImagePolicy;
use App\Support\Media\ImageProcessor;
use App\Support\Media\MediaStorageLifecycle;
use App\Support\Media\RasterInput;
use App\Support\Media\StagedMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class StageHomepageImage
{
    public function __construct(
        private readonly HomepageAuthorizer $authorizer,
        private readonly RecordAuditEvent $audit,
        private readonly ImageProcessor $imageProcessor,
        private readonly MediaStorageLifecycle $lifecycle,
    ) {}

    public function handle(User $actor, UploadedFile $image): PublicHomepageMedia
    {
        $institutionId = $this->authorizer->assertCanManage($actor);

        Validator::make(['image' => $image], [
            'image' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
        ])->validate();

        $sanitized = $this->imageProcessor->process(
            RasterInput::fromUploadedFile($image),
            ImagePolicy::homepageImage(),
            'image'
        );

        $path = 'homepage/'.$institutionId.'/'.Str::uuid().'.'.$sanitized->extension;
        $staged = $this->lifecycle->stage('local', $path, $sanitized->bytes, $sanitized->mime);

        return $this->lifecycle->commit($staged, function (StagedMedia $staged) use ($actor, $institutionId, $sanitized): PublicHomepageMedia {
            $media = PublicHomepageMedia::query()->create([
                'institution_id' => $institutionId,
                'storage_disk' => 'local',
                'storage_path' => $staged->path,
                'sha256' => $sanitized->sha256,
                'mime_type' => $sanitized->mime,
                'byte_size' => $sanitized->byteSize,
                'width' => $sanitized->width,
                'height' => $sanitized->height,
                'status' => 'ready',
                'created_by' => $actor->id,
            ]);
            $this->audit->handle('communications.homepage_image_staged', [
                'media_id' => (int) $media->id,
                'institution_id' => (int) $institutionId,
                'sha256' => $sanitized->sha256,
                'mime_type' => $sanitized->mime,
            ], (string) $actor->id);

            return $media;
        });
    }
}
