<?php

declare(strict_types=1);

namespace App\Notifications\Templates;

use App\DocumentTemplate\Import\DocxEmailImporter;
use App\DocumentTemplate\Import\DocxPackageInspectionException;
use App\DocumentTemplate\Validation\DocumentBlueprintValidator;
use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateDraft;
use App\Models\Notifications\CommunicationTemplateVersion;
use App\Models\User;
use App\Notifications\Templates\Actions\ImportCommunicationTemplateDocument;
use App\Notifications\Templates\Actions\ReviewCommunicationTemplateImport;
use App\Notifications\Templates\Actions\StoreCommunicationTemplateDocument;
use App\Notifications\Templates\Email\NoticeEmailSchema;
use App\Notifications\Templates\Email\NoticeEmailSchemaValidationException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CommunicationTemplateState
{
    public const FIELDS = ['subject', 'body', 'action_label', 'action_route', 'email_blueprint', 'document_mode', 'document_asset_id', 'builder_json', 'paper_size', 'orientation', 'document_source_drafts', 'import_provenance'];

    public function __construct(private readonly StoreCommunicationTemplateDocument $store, private readonly DocumentBlueprintValidator $printValidator) {}

    public static function state(CommunicationTemplateDraft|CommunicationTemplateVersion $draft): array
    {
        $state = [];
        foreach (self::FIELDS as $field) {
            $state[$field] = $draft->{$field};
        }

        return $state;
    }

    public static function draftStateFromVersion(CommunicationTemplateVersion $version): array
    {
        $state = self::state($version);
        $state['document_source_drafts'] = null;
        if ($version->content_schema_version !== 'notice-content-2' || $version->email_blueprint === null) {
            $state['subject'] = $version->email_subject;
            $state['body'] = $version->in_app_body;
            $state['email_blueprint'] = null;
            $state['import_provenance'] = null;
        }
        $template = $version->template;
        $slot = CommunicationTemplateRegistry::findBySlug($template->domain, $template->slug);
        if ($state['action_route'] !== null && $slot !== null && $state['action_route'] !== $slot['default_action_route']) {
            $state['action_route'] = $slot['default_action_route'];
        }
        if ($state['document_mode'] === 'none') {
            $inactive = [];
            if ($state['document_asset_id'] !== null) {
                $inactive['docx'] = ['document_asset_id' => $state['document_asset_id'], 'builder_json' => null, 'paper_size' => null, 'orientation' => null];
            }
            if (is_array($state['builder_json'])) {
                $inactive['builder'] = ['document_asset_id' => null, 'builder_json' => $state['builder_json'],
                    'paper_size' => $state['paper_size'] ?? 'a4', 'orientation' => $state['orientation'] ?? 'portrait'];
            }
            $state['document_source_drafts'] = $inactive === [] ? null : $inactive;
            foreach (['document_asset_id', 'builder_json', 'paper_size', 'orientation'] as $field) {
                $state[$field] = null;
            }
        }

        return $state;
    }

    public static function draft(CommunicationTemplateDraft $draft): array
    {
        $template = $draft->template;
        $parameters = ['domain' => $template->domain, 'slug' => $template->slug, 'draft' => $draft->id];

        return ['id' => $draft->id, 'lock_version' => $draft->lock_version, 'stage' => $draft->stage,
            'state' => self::state($draft), 'updated_at' => $draft->updated_at?->toIso8601String(),
            'email_blueprint_sha256' => $draft->email_blueprint === null ? null : self::emailDigest($draft->email_blueprint),
            'diagnostics' => $draft->email_blueprint === null ? [['code' => 'needs_semantic_authoring',
                'message' => 'Historical email remains read-only comparison content. Author and Check an actual semantic email before publication.']] : [],
            'historical_content' => $draft->email_body !== '' ? ['email_subject' => $draft->email_subject, 'email_body' => $draft->email_body,
                'in_app_title' => $draft->in_app_title, 'in_app_body' => $draft->in_app_body] : null,
            'endpoints' => ['load' => route('setup.notifications.templates.editor-state', ['domain' => $template->domain, 'slug' => $template->slug]),
                'update' => route('setup.notifications.templates.drafts.update', $parameters),
                'discard' => route('setup.notifications.templates.drafts.destroy', $parameters)]];
    }

    public static function emailDigest(array $blueprint): string
    {
        return CommunicationTemplatePayload::hash($blueprint);
    }

    public static function publishedProvenance(?array $provenance): ?array
    {
        if ($provenance === null) {
            return null;
        }
        unset($provenance['candidate_email_blueprint'], $provenance['candidate_email_blueprint_sha256']);

        return $provenance;
    }

    public function validate(CommunicationTemplate $template, array $state): array
    {
        $rules = [
            'subject' => ['present', 'nullable', 'string', 'max:255'], 'body' => ['present', 'nullable', 'string', 'max:500'],
            'action_label' => ['present', 'nullable', 'string', 'max:64'], 'action_route' => ['present', 'nullable', 'string', 'max:128'],
            'email_blueprint' => ['present', 'nullable', 'array'], 'document_mode' => ['required', Rule::in(['none', 'docx', 'builder'])],
            'document_asset_id' => ['present', 'nullable', 'uuid'], 'builder_json' => ['present', 'nullable', 'array'],
            'paper_size' => ['present', 'nullable', Rule::in(['a4', 'letter', 'legal'])],
            'orientation' => ['present', 'nullable', Rule::in(['portrait', 'landscape'])],
            'document_source_drafts' => ['present', 'nullable', 'array:docx,builder'],
            'import_provenance' => ['present', 'nullable', 'array'],
        ];
        Validator::make(['state' => $state], ['state' => ['required', 'array:'.implode(',', self::FIELDS)]])->validate();
        Validator::make($state, $rules)->validate();
        $tokens = self::tokens($template);
        foreach (['subject', 'body'] as $field) {
            $this->email(['schema_version' => 'notice-email-1', 'layout' => 'flow', 'blocks' => [['type' => 'paragraph', 'text' => $state[$field] ?? '']]], $tokens, $field);
        }
        if ($state['action_route'] !== null) {
            $binding = CommunicationTemplateRegistry::findBySlug($template->domain, $template->slug)['default_action_route'] ?? null;
            $route = Route::getRoutes()->getByName($state['action_route']);
            abort_unless($state['action_route'] === $binding && $route !== null
                && in_array('GET', $route->methods(), true) && $route->parameterNames() === [],
                422, 'Use the existing canonical notice action binding.');
        }
        if ($state['email_blueprint'] !== null) {
            $this->email($state['email_blueprint'], $tokens, 'email_blueprint');
            foreach ($this->imageIds($state['email_blueprint']) as $id) {
                $asset = $this->store->verified($template, $id);
                abort_unless(in_array($asset->mime_type, ['image/png', 'image/jpeg'], true), 422, 'Email requires an owned raster image.');
            }
        }
        $this->print($template, $state);
        foreach ($state['document_source_drafts'] ?? [] as $mode => $source) {
            abort_unless(is_array($source), 422, 'Invalid inactive print source.');
            $inactive = ['document_mode' => $mode, 'document_asset_id' => $source['document_asset_id'] ?? null,
                'builder_json' => $source['builder_json'] ?? null, 'paper_size' => $source['paper_size'] ?? null,
                'orientation' => $source['orientation'] ?? null];
            abort_if(array_diff(array_keys($source), ['document_asset_id', 'builder_json', 'paper_size', 'orientation']) !== [], 422, 'Invalid inactive print fields.');
            $this->print($template, $inactive);
        }
        $provenance = $state['import_provenance'];
        if ($provenance !== null) {
            $source = $this->store->verified($template, (string) ($provenance['source_asset_id'] ?? ''), 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            $report = $this->store->verified($template, (string) ($provenance['report_id'] ?? ''), 'application/json');
            abort_unless(hash_equals($source->checksum, (string) ($provenance['source_sha256'] ?? ''))
                && hash_equals($report->checksum, (string) ($provenance['report_sha256'] ?? '')), 422, 'Import source or report changed.');
            $persisted = json_decode(Storage::disk('local')->get($report->storage_path), true, 64, JSON_THROW_ON_ERROR);
            abort_unless(is_array($persisted) && ($persisted['source_sha256'] ?? null) === $source->checksum
                && ($persisted['converter_version'] ?? null) === ($provenance['converter_version'] ?? null)
                && CommunicationTemplatePayload::hash($persisted['findings'] ?? null) === CommunicationTemplatePayload::hash($provenance['findings'] ?? null),
                422, 'Import provenance differs from its immutable report.');
        }

        return $state;
    }

    public static function tokens(CommunicationTemplate $template): array
    {
        return array_map(static fn (array $token): string => trim($token['token'], '{} '), CommunicationTemplateRegistry::findBySlug($template->domain, $template->slug)['tokens'] ?? []);
    }

    public function digest(CommunicationTemplate $template, CommunicationTemplateDraft|array $source): string
    {
        $state = is_array($source) ? $source : self::state($source);
        unset($state['document_source_drafts']);
        $state['import_provenance'] = self::publishedProvenance($state['import_provenance'] ?? null);
        $ids = $this->imageIds($state['email_blueprint'] ?? []);
        foreach (['document_asset_id' => $state['document_asset_id'] ?? null,
            'source_asset_id' => $state['import_provenance']['source_asset_id'] ?? null,
            'report_id' => $state['import_provenance']['report_id'] ?? null] as $id) {
            if ($id !== null) {
                $ids[] = $id;
            }
        }
        $assets = [];
        foreach (array_unique($ids) as $id) {
            $asset = $this->store->metadata($template, (string) $id);
            $assets[$id] = ['sha256' => $asset->checksum, 'mime' => $asset->mime_type, 'bytes' => $asset->file_size_bytes];
        }

        return CommunicationTemplatePayload::hash(['content_schema_version' => 'notice-content-2',
            'email_schema_version' => 'notice-email-1', 'email_renderer_version' => 'notice-email-1', 'state' => $state, 'assets' => $assets]);
    }

    public function persist(CommunicationTemplate $template, ?CommunicationTemplateDraft $draft, array $state, int $stage, User $actor, ?array $serverProvenance = null): CommunicationTemplateDraft
    {
        abort_unless($stage >= 1 && $stage <= 3, 422, 'Stage must be 1, 2 or 3.');
        Validator::make($state, array_fill_keys(self::FIELDS, ['present']))->validate();
        if ($serverProvenance === null) {
            abort_unless(CommunicationTemplatePayload::hash($state['import_provenance'] ?? null) === CommunicationTemplatePayload::hash($draft?->import_provenance), 422, 'Import provenance is server-owned.');
            $state['import_provenance'] = $draft?->import_provenance;
            if ($draft !== null && $state['import_provenance'] !== null
                && self::emailDigest($state['email_blueprint'] ?? []) !== self::emailDigest($draft->email_blueprint ?? [])) {
                $state['import_provenance']['review_acknowledgment'] = null;
            }
        } else {
            $state['import_provenance'] = $serverProvenance;
        }
        $state = $this->validate($template, $state);
        $fields = [...$state, 'base_version' => $template->active_version, 'lock_version' => ($draft?->lock_version ?? 0) + 1,
            'stage' => $stage, 'content_schema_version' => 'notice-content-2', 'email_renderer_version' => 'notice-email-1',
            'content_sha256' => $this->digest($template, $state), 'updated_by_user_id' => $actor->id];
        if ($draft === null) {
            $draft = $template->draft()->create([...$fields, 'email_subject' => '', 'email_body' => '', 'in_app_title' => '', 'in_app_body' => '']);
        } else {
            $draft->update($fields);
        }
        $draft->setRelation('template', $template);

        return $draft;
    }

    public function assertPublishable(CommunicationTemplate $template, CommunicationTemplateDraft $draft, User $actor): void
    {
        $this->validate($template, self::state($draft));
        abort_unless($draft->content_schema_version === 'notice-content-2' && $draft->email_renderer_version === 'notice-email-1'
            && $draft->stage === 3 && trim((string) $draft->subject) !== '' && trim((string) $draft->body) !== ''
            && is_array($draft->email_blueprint) && $draft->email_blueprint['blocks'] !== [], 422, 'Complete Setup, Design and Check before publication.');
        if ($draft->document_mode === 'docx') {
            $document = $this->store->verified($template, $draft->document_asset_id, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            try {
                $analysis = app(NoticePrintContext::class)->analyzeDocx($template, Storage::disk('local')->path($document->storage_path));
            } catch (DocxPackageInspectionException $exception) {
                throw ValidationException::withMessages(['document_asset_id' => $exception->getMessage()]);
            }
            if (! $analysis['has_printable_content']) {
                throw ValidationException::withMessages(['document_asset_id' => 'The selected print source has no printable content.']);
            }
            if ($analysis['missing_required'] !== []) {
                throw ValidationException::withMessages(['document_asset_id' => 'Include the required reference field in the print document.']);
            }
            if ($analysis['unknown'] !== [] || $analysis['unresolved'] !== [] || ! $analysis['valid']) {
                throw ValidationException::withMessages(['document_asset_id' => 'The selected print source has missing, unknown or unresolved canonical print placeholders.']);
            }
        } elseif ($draft->document_mode === 'builder') {
            $analysis = NoticePrintContext::analyzeBuilder($draft->builder_json ?? []);
            if (! $analysis['has_printable_content']) {
                throw ValidationException::withMessages(['builder_json' => 'The print document has no printable content.']);
            }
            if ($analysis['unresolved'] !== [] || array_diff($analysis['bindings'], NoticePrintContext::catalog($template)['all']) !== []) {
                throw ValidationException::withMessages(['builder_json' => 'The print document contains unsupported placeholders.']);
            }
            if (NoticePrintContext::missingRequiredBindings($template, $analysis['bindings']) !== []) {
                throw ValidationException::withMessages(['builder_json' => 'Include the required reference field in the print document.']);
            }
        }
        try {
            DocxEmailImporter::assertTokens(
                DocxEmailImporter::authoredText($draft->email_blueprint),
                self::tokens($template), ImportCommunicationTemplateDocument::requiredTokens($template)
            );
        } catch (DocxPackageInspectionException $exception) {
            throw ValidationException::withMessages(['email_blueprint' => $exception->getMessage()]);
        }
        abort_unless(hash_equals((string) $draft->content_sha256, $this->digest($template, $draft)), 422, 'Saved notice fingerprint is stale.');
        if ($draft->import_provenance !== null) {
            $provenance = $draft->import_provenance;
            $ack = $provenance['review_acknowledgment'] ?? null;
            abort_unless(is_array($ack) && ! empty($ack['actor_id']) && ! empty($ack['acknowledged_at'])
                && hash_equals((string) $provenance['report_sha256'], (string) ($ack['report_sha256'] ?? ''))
                && hash_equals(self::emailDigest($draft->email_blueprint), (string) ($ack['email_blueprint_sha256'] ?? '')), 422, 'Current import review is required.');
            $reviewReceipt = $template->receipts()->where('operation', 'import_reviewed')->get()->contains(
                static fn ($receipt): bool => CommunicationTemplatePayload::hash(self::publishedProvenance($receipt->response_payload['draft']['state']['import_provenance'] ?? null))
                    === CommunicationTemplatePayload::hash(self::publishedProvenance($provenance))
            );
            abort_unless($reviewReceipt, 422, 'Attributable source-bound server import review is required.');
            ReviewCommunicationTemplateImport::assertResolutions($provenance, $draft->email_blueprint, $ack['resolutions'] ?? []);
        }
    }

    private function email(array $blueprint, array $tokens, string $field): void
    {
        try {
            NoticeEmailSchema::assertValid($blueprint, $tokens);
        } catch (NoticeEmailSchemaValidationException $exception) {
            throw ValidationException::withMessages([$field => array_column($exception->diagnostics, 'message')]);
        }
    }

    private function print(CommunicationTemplate $template, array $state): void
    {
        $mode = $state['document_mode'];
        if ($mode !== 'none') {
            abort_unless(in_array([$template->domain, $template->slug, $template->event_key], [
                ['admissions', 'accepted', 'admission.accepted.v1'], ['examination', 'session-published', 'exam.session_published.v1'],
                ['results', 'released-online', 'result.released.online.v1'],
            ], true), 422, 'This canonical notice does not permit a PDF.');
        }
        if ($mode === 'none') {
            abort_if($state['document_asset_id'] !== null || $state['builder_json'] !== null || $state['paper_size'] !== null || $state['orientation'] !== null, 422, 'Inactive print fields must be null.');
        } elseif ($mode === 'docx') {
            abort_if($state['document_asset_id'] === null || $state['builder_json'] !== null || $state['paper_size'] !== null || $state['orientation'] !== null, 422, 'DOCX and builder fields cannot be mixed.');
            $this->store->verified($template, $state['document_asset_id'], 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        } else {
            abort_unless($mode === 'builder' && $state['document_asset_id'] === null && is_array($state['builder_json'])
                && in_array($state['paper_size'], ['a4', 'letter', 'legal'], true) && in_array($state['orientation'], ['portrait', 'landscape'], true)
                && $this->printValidator->validate($state['builder_json'], NoticePrintContext::catalog($template)['all']) === [], 422, 'Invalid print builder configuration.');
        }
    }

    private function imageIds(array $value): array
    {
        $ids = [];
        if (($value['type'] ?? '') === 'image') {
            $ids[] = $value['asset_id'];
        }
        foreach ($value as $child) {
            if (is_array($child)) {
                array_push($ids, ...$this->imageIds($child));
            }
        }

        return $ids;
    }
}
