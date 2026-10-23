<?php

declare(strict_types=1);

namespace App\Notifications\Templates;

use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\NoticeDocumentVersion;
use App\Notifications\Templates\Actions\StoreCommunicationTemplateDocument;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

final class NoticeDocumentPin
{
    public function __construct(private readonly StoreCommunicationTemplateDocument $assets) {}

    public function selected(CommunicationTemplate $family, ?int $id, bool $verifyBytes = true): ?NoticeDocumentVersion
    {
        if ($id === null) {
            return null;
        }
        abort_unless(CommunicationTemplateRegistry::authoringMode($family->domain, $family->slug) === 'email_pdf', 422, 'This message cannot select a document.');
        $version = NoticeDocumentVersion::query()->whereKey($id)->where('communication_template_id', $family->id)->first();
        abort_unless($version !== null, 422, 'Select a ready document from this family.');
        try {
            $proof = json_decode(Crypt::decryptString($version->proof_receipt), true, 16, JSON_THROW_ON_ERROR);
            $state = $version->state;
            if (($proof['family_id'] ?? null) !== $family->id
                || (int) ($proof['institution_id'] ?? 0) !== (int) $family->institution_id
                || ($proof['render_sha256'] ?? null) !== $version->render_sha256
                || ! preg_match('/^[a-f0-9]{64}$/D', (string) ($proof['pdf_sha256'] ?? ''))
                || CommunicationTemplatePayload::hash($state) !== $version->definition_sha256
                || self::renderDigest($state) !== $version->render_sha256) {
                throw new RuntimeException('notice_document_integrity');
            }
            foreach ($this->references($state) as $assetId => $expected) {
                $asset = $verifyBytes ? $this->assets->verified($family, $assetId) : $this->assets->metadata($family, $assetId);
                if (! hash_equals($expected, $asset->checksum)) {
                    throw new RuntimeException('notice_document_source_changed');
                }
                $source = ($state['source_kind'] ?? null) === 'docx' && $assetId === ($state['document_asset_id'] ?? null);
                if ($source ? $asset->mime_type !== 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                    : ! in_array($asset->mime_type, ['image/png', 'image/jpeg'], true)) {
                    throw new RuntimeException('notice_document_source_invalid');
                }
            }
        } catch (\Throwable $exception) {
            abort(422, 'The selected ready document failed integrity verification.');
        }

        return $version;
    }

    public function binding(CommunicationTemplate $family, ?int $id): array
    {
        $version = $this->selected($family, $id, false);

        return ['institution_id' => (int) $family->institution_id, 'family_id' => $family->id,
            'document_revision_id' => $version?->id, 'definition_sha256' => $version?->definition_sha256,
            'render_sha256' => $version?->render_sha256, 'source_kind' => $version?->state['source_kind'],
            'pins' => $version?->state['pins'], 'schema' => 'notice-attachment-binding-1'];
    }

    public function freeze(CommunicationTemplate $family, ?int $id): ?array
    {
        $version = $this->selected($family, $id, false);
        if ($version === null) {
            return null;
        }
        $assets = [];
        foreach ($this->references($version->state) as $assetId => $sha) {
            $asset = $this->assets->metadata($family, $assetId);
            $assets[$assetId] = ['id' => $assetId, 'sha256' => $sha, 'mime' => $asset->mime_type,
                'storage_path' => $asset->storage_path, 'size' => (int) $asset->file_size_bytes];
        }

        return ['mode' => 'notice_document', 'document_revision_id' => (int) $version->id,
            'version' => (int) $version->version, 'definition_sha256' => $version->definition_sha256,
            'render_sha256' => $version->render_sha256, 'state' => $version->state, 'assets' => $assets];
    }

    public static function renderDigest(array $state): string
    {
        $pins = $state['pins'] ?? [];
        if (! in_array($state['source_kind'] ?? null, ['builder', 'docx'], true)
            || ! in_array($state['paper_size'] ?? null, ['a4', 'letter', 'legal'], true)
            || ! in_array($state['orientation'] ?? null, ['portrait', 'landscape'], true)
            || ($pins['paper_size'] ?? null) !== $state['paper_size'] || ($pins['orientation'] ?? null) !== $state['orientation']
            || ($pins['compiler'] ?? null) !== 'notice-document-compiler-v1'
            || ($pins['renderer'] ?? null) !== 'notice-document-renderer-v1'
            || ($pins['synthetic_context'] ?? null) !== 'notice-document-synthetic-v1'
            || ! is_array($pins['catalog'] ?? null) || ! is_array($pins['image_sha256s'] ?? null)) {
            throw new RuntimeException('notice_document_pins_invalid');
        }
        $canonical = array_intersect_key($state, array_flip(['source_kind', 'document_asset_id', 'builder_json', 'paper_size', 'orientation']));

        return CommunicationTemplatePayload::hash(['state' => $canonical, 'source_sha256' => $pins['source_checksum'] ?? null,
            'image_sha256s' => $pins['image_sha256s'], 'paper_size' => $pins['paper_size'], 'orientation' => $pins['orientation'],
            'catalog' => $pins['catalog'], 'compiler' => $pins['compiler'], 'renderer' => $pins['renderer'], 'synthetic_context' => $pins['synthetic_context']]);
    }

    public function references(array $state): array
    {
        $pins = $state['pins'];
        $references = $pins['image_sha256s'];
        if ($state['source_kind'] === 'docx') {
            if (! is_string($state['document_asset_id'] ?? null) || ! is_string($pins['source_checksum'] ?? null)) {
                throw new RuntimeException('notice_document_source_invalid');
            }
            $references[$state['document_asset_id']] = $pins['source_checksum'];
        } else {
            $ids = [];
            foreach ($state['builder_json']['components'] ?? [] as $component) {
                if (($component['type'] ?? null) === 'image') {
                    $id = $component['props']['asset_id'] ?? null;
                    if (! is_string($id) || ! isset($references[$id])) {
                        throw new RuntimeException('notice_document_image_invalid');
                    }
                    $ids[] = $id;
                }
            }
            if (array_diff(array_keys($references), $ids) !== []) {
                throw new RuntimeException('notice_document_image_invalid');
            }
        }
        foreach ($references as $sha) {
            if (! is_string($sha) || ! preg_match('/^[a-f0-9]{64}$/D', $sha)) {
                throw new RuntimeException('notice_document_source_invalid');
            }
        }

        return $references;
    }
}
