<?php

declare(strict_types=1);

namespace App\Notifications\Templates;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Notifications\NotificationDelivery;
use App\ResultSheet\Data\TemplateFieldAliasCatalog;
use App\ResultSheet\Support\ResultSheetPlaceholderService;
use Illuminate\Support\Str;
use RuntimeException;

final class CanonicalReleasedResultBinding
{
    public function __construct(private readonly ResultSheetPlaceholderService $placeholders) {}

    /** @return array<string,mixed> */
    public function freeze(ResultRelease $release, ResultDocument $document, AdmissionAttempt $attempt, ApplicantIdentity $recipient): array
    {
        if ((int) $release->admission_attempt_id !== (int) $attempt->id
            || (int) $release->result_document_id !== (int) $document->id
            || (int) $document->admission_attempt_id !== (int) $attempt->id
            || (int) $attempt->applicant_identity_id !== (int) $recipient->id
            || (int) $attempt->institution_id !== (int) $recipient->institution_id
            || $document->status !== ResultDocument::STATUS_READY || $release->mode !== 'online'
            || ! $release->is_current || $release->unreleased_at !== null) {
            throw new RuntimeException('released_result_resource_mismatch');
        }
        $data = $document->snapshot_payload_json;
        $available = $this->hasData($data, (int) $attempt->id);
        $replacements = null;
        $docx = null;
        if ($available) {
            $replacements = $this->placeholders->buildReplacements([array_replace($data, ['scores' => []])]);
            $replacements['institution_name'] = (string) $attempt->academicCycle?->institution?->name;
            $scoreByCode = [];
            foreach ($data['scores'] as $score) {
                $code = $score['area_code'] ?? null;
                if (is_string($code)) {
                    $scoreByCode[$code] = $score;
                }
                $this->scoreValues($replacements, str_replace('-', '_', Str::slug((string) ($score['domain'] ?? ''), '_')), $score);
            }
            foreach (TemplateFieldAliasCatalog::domainAliases() as $alias => $code) {
                if ($code !== null && isset($scoreByCode[$code])) {
                    $this->scoreValues($replacements, $alias, $scoreByCode[$code]);
                }
            }
            foreach (TemplateFieldAliasCatalog::aliases() as $alias => $canonical) {
                foreach (['', '_rating', '_raw', '_pct'] as $suffix) {
                    if (array_key_exists($canonical.$suffix, $replacements)) {
                        $replacements[$alias.$suffix] = $replacements[$canonical.$suffix];
                    }
                }
            }
            $domains = TemplateFieldAliasCatalog::domainAliases();
            foreach ($replacements as $key => $value) {
                $canonical = TemplateFieldAliasCatalog::canonical($key);
                $grade = array_key_exists($canonical, $domains)
                    || str_ends_with($key, '_rating') || str_ends_with($key, '_raw') || str_ends_with($key, '_pct');
                if ($grade && trim((string) $value) === '') {
                    unset($replacements[$key]);
                }
            }
            $docx = [];
            $scoreText = implode("\n", array_map(static fn (array $score): string => (string) $score['domain'].': '.(string) $score['raw'].' — '.(string) $score['converted'], $data['scores']));
            foreach ($replacements as $key => $value) {
                $value = TemplateFieldAliasCatalog::canonical($key) === 'scores_rows' ? $scoreText : (string) $value;
                $docx[$key] = str_replace(['{{', '}}'], ['{ {', '} }'], $value);
            }
        }

        return [
            'binding_schema_version' => 'released-result-1', 'institution_id' => (int) $attempt->institution_id,
            'recipient' => ['type' => ApplicantIdentity::class, 'id' => (int) $recipient->id],
            'attempt_id' => (int) $attempt->id, 'release_id' => (int) $release->id,
            'release_version' => (int) $release->release_version, 'mode' => 'online',
            'document_id' => (int) $document->id, 'document_version' => (int) $document->version,
            'document_checksum' => $document->checksum, 'document_path' => $document->storage_path,
            'template_version' => $document->template_version,
            'data_provider' => 'result-document.snapshot_payload_json',
            'data_sha256' => is_array($data) ? CommunicationTemplatePayload::hash($data) : null,
            'replacements' => $replacements, 'docx_replacements' => $docx,
            'score_rows' => $available ? $data['scores'] : null,
            'presentation_sha256' => $replacements === null ? null : CommunicationTemplatePayload::hash([$replacements, $docx, $data['scores']]),
            'preparation_failure' => $available ? null : ['code' => 'released_result_data_unavailable'],
        ];
    }

    public function verify(array $binding, NotificationDelivery $delivery, bool $requiresData = true): void
    {
        $resource = in_array($delivery->payload['snapshot_schema_version'] ?? null, ['notice-delivery-2', 'notice-delivery-3'], true)
            ? ($delivery->payload['event_resource'] ?? []) : $delivery->payload;
        if (($binding['binding_schema_version'] ?? null) !== 'released-result-1'
            || ($binding['data_provider'] ?? null) !== 'result-document.snapshot_payload_json'
            || $delivery->event_type !== 'result.released.v1' || $delivery->template_key !== 'result.released.online'
            || $delivery->recipient_type !== ApplicantIdentity::class
            || ($binding['recipient']['type'] ?? null) !== $delivery->recipient_type
            || (int) ($binding['recipient']['id'] ?? 0) !== (int) $delivery->recipient_id
            || (int) ($binding['institution_id'] ?? 0) !== (int) ($delivery->payload['institution_id'] ?? 0)
            || (int) ($binding['release_id'] ?? 0) !== (int) ($resource['release_id'] ?? 0)
            || (int) ($binding['release_version'] ?? 0) !== (int) ($resource['release_version'] ?? 0)
            || (int) ($binding['attempt_id'] ?? 0) !== (int) ($resource['attempt_id'] ?? 0)
            || ($binding['mode'] ?? null) !== 'online') {
            throw new RuntimeException('released_result_binding_invalid');
        }
        $release = ResultRelease::query()->whereKey($binding['release_id'])
            ->where('admission_attempt_id', $binding['attempt_id'])->where('result_document_id', $binding['document_id'])
            ->where('release_version', $binding['release_version'])->where('mode', 'online')
            ->where('is_current', true)->whereNull('unreleased_at')
            ->whereHas('admissionAttempt', fn ($query) => $query
                ->where('applicant_identity_id', $delivery->recipient_id)->where('institution_id', $binding['institution_id']))
            ->first();
        $document = ResultDocument::query()->whereKey($binding['document_id'])
            ->where('admission_attempt_id', $binding['attempt_id'])->where('status', ResultDocument::STATUS_READY)->first();
        if ($release === null || $document === null
            || (int) $document->version !== (int) ($binding['document_version'] ?? 0)
            || $document->checksum !== ($binding['document_checksum'] ?? null)
            || $document->storage_path !== ($binding['document_path'] ?? null)
            || $document->template_version !== ($binding['template_version'] ?? null)
            || (is_array($document->snapshot_payload_json) ? CommunicationTemplatePayload::hash($document->snapshot_payload_json) : null)
                !== ($binding['data_sha256'] ?? null)) {
            throw new RuntimeException('released_result_binding_changed');
        }
        if ($requiresData && (($binding['preparation_failure'] ?? null) !== null
            || ! is_array($binding['replacements'] ?? null) || ! is_array($binding['docx_replacements'] ?? null)
            || ! is_array($binding['score_rows'] ?? null))) {
            throw new RuntimeException('released_result_data_unavailable');
        }
        if (is_array($binding['score_rows'] ?? null) && $binding['score_rows'] !== ($document->snapshot_payload_json['scores'] ?? null)) {
            throw new RuntimeException('released_result_presentation_changed');
        }
        if (is_array($binding['replacements'] ?? null)
            && (! is_string($binding['presentation_sha256'] ?? null)
                || ! hash_equals($binding['presentation_sha256'], CommunicationTemplatePayload::hash([$binding['replacements'], $binding['docx_replacements'], $binding['score_rows']])))) {
            throw new RuntimeException('released_result_presentation_changed');
        }
    }

    /** @return array<string,mixed> */
    public static function metadata(array $binding): array
    {
        return array_intersect_key($binding, array_fill_keys(['binding_schema_version', 'institution_id', 'recipient',
            'attempt_id', 'release_id', 'release_version', 'document_id', 'document_version', 'document_checksum',
            'data_provider', 'data_sha256', 'presentation_sha256'], true));
    }

    private function scoreValues(array &$values, string $key, array $score): void
    {
        if ($key === '') {
            return;
        }
        $values[$key] = (string) ($score['converted'] ?? '');
        $values[$key.'_rating'] = (string) ($score['rating'] ?? $score['converted'] ?? '');
        $values[$key.'_raw'] = (string) ($score['raw'] ?? '');
        $values[$key.'_pct'] = (string) ($score['pct'] ?? '');
    }

    private function hasData(mixed $data, int $attemptId): bool
    {
        if (! is_array($data) || (int) ($data['id'] ?? 0) !== $attemptId
            || ! is_string($data['name'] ?? null) || ! is_string($data['reference'] ?? null)
            || ! is_scalar($data['overall_pct'] ?? null) || ! is_array($data['scores'] ?? null)) {
            return false;
        }
        foreach ($data['scores'] as $score) {
            if (! is_array($score)) {
                return false;
            }
            foreach (['domain', 'area_code', 'raw', 'converted', 'rating', 'pct'] as $key) {
                if (! is_scalar($score[$key] ?? null)) {
                    return false;
                }
            }
        }

        return true;
    }
}
