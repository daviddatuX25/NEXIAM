<?php

declare(strict_types=1);

namespace App\Notifications\Templates\Actions;

use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateDraft;
use App\Models\User;
use App\Notifications\Templates\CommunicationTemplateMutation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

final class StoreNoticeMessageImage
{
    public function __construct(private readonly CommunicationTemplateMutation $mutation, private readonly StoreCommunicationTemplateDocument $documents) {}

    public function store(CommunicationTemplate $family, UploadedFile $image, array $input, User $actor): array
    {
        abort_unless($image->isValid() && $image->getSize() <= 2097152, 422, 'Invalid or oversized image.');
        $bytes = file_get_contents($image->getRealPath());
        abort_unless(is_string($bytes), 422, 'Image bytes are unavailable.');
        unset($input['image']);
        $input['source_sha256'] = hash('sha256', $bytes);
        Validator::make(['input' => $input], ['input' => ['required', 'array:base_version,family_revision,lock_version,draft_id,idempotency_key,source_sha256'],
            'input.source_sha256' => ['required', 'regex:/^[a-f0-9]{64}$/D']])->validate();

        return $this->mutation->run($family, $input, $actor, 'message_image_upload', 201,
            function (CommunicationTemplate $locked, ?CommunicationTemplateDraft $draft) use ($image, $actor, $input, $bytes): array {
                abort_unless($draft?->content_schema_version === 'notice-content-3', 422, 'Save the message draft before uploading an image.');
                abort_unless(hash_equals($input['source_sha256'], hash_file('sha256', $image->getRealPath()) ?: ''), 422, 'The uploaded image changed.');
                $asset = $this->documents->storeImage($locked, $image, $actor);
                $dimensions = getimagesizefromstring($bytes);
                abort_unless(is_array($dimensions), 422, 'The uploaded image is invalid.');

                return ['asset' => ['id' => $asset->id, 'sha256' => $asset->checksum, 'mime' => $asset->mime_type,
                    'width' => $dimensions[0], 'height' => $dimensions[1], 'alt' => '']];
            });
    }
}
