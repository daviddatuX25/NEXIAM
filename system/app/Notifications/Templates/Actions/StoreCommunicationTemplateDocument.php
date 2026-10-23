<?php

declare(strict_types=1);

namespace App\Notifications\Templates\Actions;

use App\DocumentTemplate\Import\DocxPackageInspectionException;
use App\DocumentTemplate\Import\DocxPackageInspector;
use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateDocument;
use App\Models\User;
use App\Notifications\Templates\CommunicationTemplateMutation;
use App\Notifications\Templates\CommunicationTemplatePayload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class StoreCommunicationTemplateDocument
{
    public function __construct(private readonly CommunicationTemplateMutation $mutation, private readonly DocxPackageInspector $inspector) {}

    public function store(CommunicationTemplate $template, UploadedFile $file, User $actor): CommunicationTemplateDocument
    {
        $this->mutation->authorize($template, $actor);
        abort_unless($file->isValid() && strtolower($file->getClientOriginalExtension()) === 'docx'
            && $file->getSize() > 0 && $file->getSize() <= 10485760, 422, 'Only a bounded valid DOCX upload is accepted.');
        try {
            $this->inspector->inspect($file);
        } catch (DocxPackageInspectionException) {
            abort(422, 'notice_document_package_invalid');
        }
        $bytes = file_get_contents($file->getRealPath());
        abort_unless(is_string($bytes), 422, 'The uploaded DOCX is unavailable.');

        return $this->write($template, $bytes, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', basename($file->getClientOriginalName()), 'docx', $actor);
    }

    public function storeImage(CommunicationTemplate $template, UploadedFile $file, User $actor): CommunicationTemplateDocument
    {
        abort_unless($file->isValid() && $file->getSize() <= 2097152, 422, 'Invalid or oversized image.');
        $bytes = file_get_contents($file->getRealPath());
        abort_unless(is_string($bytes), 422, 'Image bytes are unavailable.');

        return $this->storeImageBytes($template, $bytes, (string) $file->getMimeType(), $actor);
    }

    public function storeImageBytes(CommunicationTemplate $template, string $bytes, string $mime, User $actor): CommunicationTemplateDocument
    {
        $this->mutation->authorize($template, $actor);
        $this->assertRaster($bytes, $mime);

        return $this->write($template, $bytes, $mime, 'inline-image.'.($mime === 'image/png' ? 'png' : 'jpg'), $mime === 'image/png' ? 'png' : 'jpg', $actor);
    }

    public function storeReport(CommunicationTemplate $template, string $reportJson, User $actor): CommunicationTemplateDocument
    {
        $this->mutation->authorize($template, $actor);
        abort_if(strlen($reportJson) > 1048576, 422, 'Import report exceeds its bound.');
        $report = json_decode($reportJson, true, 64, JSON_THROW_ON_ERROR);
        abort_unless(is_array($report) && CommunicationTemplatePayload::json($report) === $reportJson, 422, 'Import reports require canonical JSON.');

        return $this->write($template, $reportJson, 'application/json', 'import-report.json', 'json', $actor);
    }

    public function metadata(CommunicationTemplate $template, string $id): CommunicationTemplateDocument
    {
        $document = CommunicationTemplateDocument::query()->whereKey($id)->where('communication_template_id', $template->id)
            ->where('institution_id', $template->institution_id)->first();
        abort_unless($document !== null, 422, 'The asset is not available in this notice family.');

        return $document;
    }

    public function verified(CommunicationTemplate $template, string $id, ?string $mime = null): CommunicationTemplateDocument
    {
        $document = $this->metadata($template, $id);
        abort_if($mime !== null && $document->mime_type !== $mime, 422, 'Unexpected asset type.');
        $disk = Storage::disk('local');
        abort_unless(str_starts_with($document->storage_path, 'communication-templates/'.$template->id.'/')
            && ! str_contains($document->storage_path, '..') && $disk->exists($document->storage_path), 422, 'The private asset is unavailable.');
        $limit = match ($document->mime_type) {
            'image/png', 'image/jpeg' => 2097152, 'application/json' => 1048576,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 10485760,
            default => 0,
        };
        abort_unless($limit > 0 && $disk->size($document->storage_path) <= $limit, 422, 'Asset size exceeds its bound.');
        $bytes = $disk->get($document->storage_path);
        abort_unless(is_string($bytes) && strlen($bytes) === (int) $document->file_size_bytes
            && hash_equals($document->checksum, hash('sha256', $bytes)), 422, 'The private asset checksum changed.');
        if (in_array($document->mime_type, ['image/png', 'image/jpeg'], true)) {
            $this->assertRaster($bytes, $document->mime_type);
        }

        return $document;
    }

    public function deletePending(array $ids): void
    {
        foreach (array_unique($ids) as $id) {
            if (! is_string($id) || ! Str::isUuid($id)) {
                continue;
            }

            $document = CommunicationTemplateDocument::query()->find($id);
            if ($document !== null) {
                $this->pruneUnreferenced($document->template, null, [$id]);
            }
        }
    }

    public function pruneUnreferenced(CommunicationTemplate $template, ?string $keepId = null, ?array $onlyIds = null): int
    {
        return DB::transaction(function () use ($template, $keepId, $onlyIds): int {
            $locked = CommunicationTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();
            abort_if($keepId !== null && ! Str::isUuid($keepId), 422, 'An asset retention identifier must be a UUID.');
            foreach ($onlyIds ?? [] as $id) {
                abort_unless(is_string($id) && Str::isUuid($id), 422, 'A pending asset identifier must be a UUID.');
            }
            $ids = $keepId === null ? [] : [$keepId];
            foreach ($locked->versions()->get() as $version) {
                $this->references($version->toArray(), $ids);
            }
            foreach ($locked->draft()->get() as $draft) {
                $this->references($draft->toArray(), $ids);
            }
            foreach ($locked->documentVersions()->get() as $version) {
                $this->references($version->toArray(), $ids);
            }
            foreach ($locked->documentDraft()->get() as $draft) {
                $this->references($draft->toArray(), $ids);
            }
            foreach ($locked->receipts()->get(['response_payload']) as $receipt) {
                $this->references($receipt->response_payload, $ids);
            }
            DB::table('notice_document_receipts')
                ->where('institution_id', $locked->institution_id)
                ->where('domain', $locked->domain)
                ->where('canonical_slug', $locked->slug)
                ->orderBy('id')
                ->chunk(100, function ($rows) use (&$ids): void {
                    foreach ($rows as $row) {
                        $this->references(json_decode((string) $row->response_payload, true, 512, JSON_THROW_ON_ERROR), $ids);
                    }
                });
            DB::table('notification_deliveries')->select('payload')->orderBy('id')->chunk(100, function ($rows) use (&$ids): void {
                foreach ($rows as $row) {
                    $this->references(json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR), $ids);
                }
            });
            $query = CommunicationTemplateDocument::query()->where('communication_template_id', $locked->id)
                ->where('institution_id', $locked->institution_id)->whereNotIn('id', array_unique($ids))->lockForUpdate();
            if ($onlyIds !== null) {
                $query->whereIn('id', $onlyIds);
            }
            $documents = $query->get();
            foreach ($documents as $document) {
                $path = $document->storage_path;
                $document->delete();
                DB::afterCommit(fn () => Storage::disk('local')->delete($path));
            }

            return $documents->count();
        });
    }

    private function references(mixed $value, array &$ids, bool $assetCollection = false): void
    {
        if (! is_array($value)) {
            return;
        }
        foreach ($value as $key => $child) {
            $explicitAssetReference = in_array($key, ['asset_id', 'document_asset_id', 'source_asset_id', 'report_id'], true);
            $assetMetadataId = $key === 'id' && ($assetCollection || array_key_exists('sha256', $value) || array_key_exists('checksum', $value));
            if (is_string($child) && ($explicitAssetReference || $assetMetadataId || ($assetCollection && is_int($key))) && Str::isUuid($child)) {
                $ids[] = $child;
            } elseif (is_array($child)) {
                $this->references($child, $ids, $key === 'assets' || ($assetCollection && is_int($key)));
            }
        }
    }

    private function assertRaster(string $bytes, string $mime): void
    {
        $image = strlen($bytes) <= 2097152 ? @getimagesizefromstring($bytes) : false;
        abort_unless(in_array($mime, ['image/png', 'image/jpeg'], true) && $image !== false
            && ($image['mime'] ?? null) === $mime && $image[0] > 0 && $image[1] > 0 && $image[0] * $image[1] <= 20000000
            && (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) === $mime, 422, 'Image signature, dimensions or bounds are invalid.');
    }

    private function write(CommunicationTemplate $template, string $bytes, string $mime, string $name, string $extension, User $actor): CommunicationTemplateDocument
    {
        $id = (string) Str::uuid();
        $path = 'communication-templates/'.$template->id.'/'.$id.'.'.$extension;
        $disk = Storage::disk('local');
        if (! $disk->put($path, $bytes, ['visibility' => 'private'])) {
            throw new RuntimeException('The private asset could not be stored.');
        }
        try {
            return CommunicationTemplateDocument::query()->create(['id' => $id,
                'communication_template_id' => $template->id, 'institution_id' => $template->institution_id,
                'original_name' => $name, 'storage_path' => $path, 'checksum' => hash('sha256', $bytes),
                'mime_type' => $mime, 'file_size_bytes' => strlen($bytes), 'created_by_user_id' => $actor->id]);
        } catch (\Throwable $exception) {
            $disk->delete($path);

            throw $exception;
        }
    }
}
