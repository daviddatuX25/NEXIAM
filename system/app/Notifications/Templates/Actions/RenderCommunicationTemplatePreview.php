<?php

declare(strict_types=1);

namespace App\Notifications\Templates\Actions;

use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateDraft;
use App\Models\User;
use App\Notifications\Templates\CommunicationTemplateMutation;
use App\Notifications\Templates\CommunicationTemplatePayload;
use App\Notifications\Templates\CommunicationTemplatePreviewReceipt;
use App\Notifications\Templates\CommunicationTemplateRegistry;
use App\Notifications\Templates\CommunicationTemplateState;
use App\Notifications\Templates\Email\NoticeEmailAssetVerifier;
use App\Notifications\Templates\Email\NoticeEmailRenderer;
use App\Notifications\Templates\NoticeTemplateSyntheticPrint;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;

class RenderCommunicationTemplatePreview
{
    public function __construct(private readonly CommunicationTemplateMutation $mutation,
        private readonly CommunicationTemplateState $state, private readonly NoticeEmailRenderer $renderer,
        private readonly NoticeEmailAssetVerifier $assets, private readonly CommunicationTemplatePreviewReceipt $receipts,
        private readonly NoticeTemplateSyntheticPrint $print) {}

    public function execute(CommunicationTemplate $template, array $input, User $actor): array
    {
        Validator::make(['input' => $input], ['input' => ['required', 'array:view,base_version,family_revision,lock_version,content_sha256'],
            'input.view' => ['required', Rule::in(['email', 'portal', 'print'])],
            'input.content_sha256' => ['required', 'regex:/^[a-f0-9]{64}$/D']])->validate();
        [$family, $draft] = DB::transaction(function () use ($template, $input, $actor): array {
            $family = CommunicationTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();
            $this->mutation->authorize($family, $actor);
            $draft = CommunicationTemplateDraft::query()->where('communication_template_id', $family->id)->lockForUpdate()->first();
            $this->mutation->guards($family, $draft, $input);
            abort_unless($draft !== null, 422, 'Save a notice draft before Check.');
            $this->state->validate($family, CommunicationTemplateState::state($draft));
            $this->assertContent($family, $draft, $input['content_sha256']);

            return [$family, $draft];
        });
        $view = $input['view'];
        if ($view === 'print' && $draft->document_mode === 'none') {
            $this->failure('print_not_enabled', 422);
        }
        try {
            $context = self::syntheticContext($family);
            if ($view === 'email') {
                $rendered = $this->email($family, $draft);
                $projection = $this->renderer->project($rendered['production_html'], $rendered['assets']);
                $branch = ['email' => ['production_html' => $rendered['production_html'], ...$projection,
                    'text' => $rendered['text'], 'assets' => array_map(static fn (array $asset): array => [
                        'id' => $asset['id'], 'sha256' => $asset['sha256'], 'cid' => $asset['cid'], 'mime' => $asset['mime'],
                    ], $rendered['assets'])]];
                $projectionHash = $projection['projection_sha256'];
            } elseif ($view === 'portal') {
                $portal = ['title' => $this->renderer->substitute((string) $draft->subject, $context, CommunicationTemplateState::tokens($family)),
                    'body' => $this->renderer->substitute((string) $draft->body, $context, CommunicationTemplateState::tokens($family)),
                    'action' => $draft->action_route === null ? null : ['label' => $draft->action_label, 'url' => route($draft->action_route)]];
                $branch = ['portal' => $portal];
                $projectionHash = CommunicationTemplatePayload::hash($portal);
            } else {
                $pdf = $this->print->render($family, $draft, $context);
                $branch = ['print' => ['pdf_base64' => base64_encode($pdf['bytes']), 'sha256' => $pdf['sha256']]];
                $projectionHash = $pdf['sha256'];
            }
        } catch (HttpResponseException $exception) {
            throw $exception;
        } catch (RuntimeException $exception) {
            if ($view === 'print') {
                $code = $exception->getMessage();
                $messages = [
                    'print_source_empty' => 'Add printable text, fields or an image to the document.',
                    'print_required_token_missing' => 'Add the required reference field to the document.',
                    'print_token_unknown' => 'Replace unsupported document fields with an available field.',
                    'print_context_missing' => 'A document field has no value in the sample check.',
                    'print_source_tokens_invalid' => 'Correct missing or unresolved fields in the document.',
                ];
                if (isset($messages[$code])) {
                    $this->failure($code, 422, [['code' => $code, 'path' => 'document', 'message' => $messages[$code]]]);
                }

                $this->failure('print_preview_unavailable', 503);
            }

            $this->failure('email_preview_invalid', $exception->getMessage() === 'email_renderer_unavailable' ? 503 : 422);
        }

        return DB::transaction(function () use ($family, $input, $actor, $view, $branch, $projectionHash): array {
            $current = CommunicationTemplate::query()->whereKey($family->id)->lockForUpdate()->firstOrFail();
            $this->mutation->authorize($current, $actor);
            $currentDraft = CommunicationTemplateDraft::query()->where('communication_template_id', $current->id)->lockForUpdate()->first();
            $this->mutation->guards($current, $currentDraft, $input);
            abort_unless($currentDraft !== null, 422);
            $this->assertContent($current, $currentDraft, $input['content_sha256']);

            return ['view' => $view, 'content_sha256' => $input['content_sha256'],
                'preview_receipt' => $this->receipts->issue($current, $currentDraft, $actor, $view, $projectionHash),
                'diagnostics' => [], ...$branch];
        });
    }

    /** Internal server-authored context; no live facts or caller overrides. */
    public static function syntheticContext(CommunicationTemplate $template): array
    {
        $context = CommunicationTemplateRegistry::sampleContextFor($template->domain, $template->slug);
        foreach ($context as $key => $value) {
            if (str_ends_with($key, '_url')) {
                $context[$key] = 'https://example.invalid/synthetic/'.rawurlencode($key);
            }
        }

        return $context;
    }

    public function email(CommunicationTemplate $template, CommunicationTemplateDraft $draft): array
    {
        if (! is_array($draft->email_blueprint) || ($draft->email_blueprint['blocks'] ?? []) === []) {
            $this->failure('email_blueprint_incomplete', 422, [['code' => 'needs_semantic_authoring',
                'path' => 'email_blueprint', 'message' => 'Author semantic email blocks before Check.']]);
        }
        $assets = $this->assets->verify((int) $template->institution_id, (string) $template->id, $draft->email_blueprint ?? []);

        return $this->renderer->render($draft->email_blueprint ?? [], self::syntheticContext($template),
            CommunicationTemplateState::tokens($template), (string) ($draft->email_blueprint['schema_version'] ?? ''),
            (string) $draft->email_renderer_version, $assets, (string) $draft->subject);
    }

    private function assertContent(CommunicationTemplate $family, CommunicationTemplateDraft $draft, string $hash): void
    {
        if (! hash_equals($hash, (string) $draft->content_sha256) || ! hash_equals($hash, $this->state->digest($family, $draft))) {
            $this->failure('preview_stale', 422);
        }
    }

    private function failure(string $code, int $status, array $diagnostics = []): never
    {
        throw new HttpResponseException(response()->json(['error' => $code,
            'message' => 'This notice could not be checked. Verify current content and sources.', 'diagnostics' => $diagnostics], $status));
    }
}
