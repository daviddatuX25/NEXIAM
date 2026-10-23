<?php

declare(strict_types=1);

namespace App\Notifications\Templates\Actions;

use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateDraft;
use App\Models\User;
use App\Notifications\Templates\CommunicationTemplateMutation;
use App\Notifications\Templates\CommunicationTemplateState;
use App\Notifications\Templates\Email\NoticeEmailAssetVerifier;
use App\Notifications\Templates\Email\NoticeEmailLinkPolicy;
use App\Notifications\Templates\Email\NoticeEmailSchemaValidationException;
use App\Notifications\Templates\Email\NoticeMessageEmailRenderer;
use App\Notifications\Templates\NoticeMessageOutputReceipt;
use App\Notifications\Templates\NoticeMessageState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class RenderNoticeMessagePreview
{
    public function __construct(private readonly CommunicationTemplateMutation $mutation, private readonly NoticeMessageState $state,
        private readonly NoticeMessageEmailRenderer $renderer, private readonly NoticeEmailAssetVerifier $assets,
        private readonly NoticeMessageOutputReceipt $receipts) {}

    public function execute(CommunicationTemplate $template, array $input, User $actor): array
    {
        Validator::make(['input' => $input], ['input' => ['required', 'array:view,base_version,family_revision,lock_version,draft_id,output_sha256'],
            'input.view' => ['required', Rule::in(['email', 'portal'])], 'input.output_sha256' => ['required', 'regex:/^[a-f0-9]{64}$/D']])->validate();
        [$family, $draft] = DB::transaction(function () use ($template, $input, $actor): array {
            $family = CommunicationTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();
            $this->mutation->authorize($family, $actor);
            $draft = CommunicationTemplateDraft::query()->where('communication_template_id', $family->id)->lockForUpdate()->first();
            $this->mutation->guards($family, $draft, $input);
            abort_unless($draft?->content_schema_version === 'notice-content-3', 422, 'Save a message draft before checking it.');
            $current = $this->state->persisted($draft);
            $rendererVersion = $this->state->rendererVersion($draft);
            $currentDigest = $input['view'] === 'email'
                ? $this->state->emailDigest($family, $current, $rendererVersion)
                : $this->state->portalDigest($current, $rendererVersion);
            abort_unless(hash_equals($currentDigest, $input['output_sha256']), 422, 'This output has changed. Check the current content.');

            return [$family, $draft];
        });
        $rendererVersion = $this->state->rendererVersion($draft);
        $message = $this->state->persisted($draft);
        $context = RenderCommunicationTemplatePreview::syntheticContext($family);
        $view = $input['view'];
        try {
            if ($view === 'email') {
                abort_unless(is_array($message['email_blueprint']) && ($message['email_blueprint']['blocks'] ?? []) !== [], 422, 'Author semantic email blocks before Check.');
                $owned = $this->assets->verify((int) $family->institution_id, (string) $family->id, $message['email_blueprint']);
                $action = $rendererVersion === NoticeMessageState::NEW_EMAIL_RENDERER ? $this->emailAction($message) : null;
                $rendered = $this->renderer->render($message['email_blueprint'], $context, CommunicationTemplateState::tokens($family),
                    'notice-email-1', $rendererVersion, $owned, (string) $message['subject'], $action);
                $projection = $this->renderer->project($rendered['production_html'], $owned);
                $branch = ['email' => ['production_html' => $rendered['production_html'], 'preview_html' => $projection['preview_html'],
                    'projection_sha256' => $projection['projection_sha256'], 'text' => $rendered['text'],
                    'assets' => array_map(static fn (array $asset): array => ['id' => $asset['id'], 'sha256' => $asset['sha256'],
                        'cid' => $asset['cid'], 'mime' => $asset['mime']], $owned)]];
            } else {
                $action = $this->emailAction($message);
                $portal = ['title' => $this->renderer->substitute((string) $message['subject'], $context, CommunicationTemplateState::tokens($family)),
                    'body' => $this->renderer->substitute((string) $message['body'], $context, CommunicationTemplateState::tokens($family)),
                    'action' => $action['enabled'] ? ['label' => $action['label'], 'url' => $action['url']] : null];
                $branch = ['portal' => $portal];
            }
        } catch (HttpException $exception) {
            throw $exception;
        } catch (NoticeEmailSchemaValidationException $exception) {
            abort(422, $exception->getMessage());
        } catch (RuntimeException $exception) {
            $msg = $exception->getMessage();
            abort($msg === 'email_renderer_unavailable' ? 503 : 422,
                $msg === 'email_renderer_unavailable' ? 'Email preview is unavailable.' : $msg);
        }
        $digest = $view === 'email'
            ? $this->state->emailDigest($family, $message, $rendererVersion)
            : $this->state->portalDigest($message, $rendererVersion);

        return DB::transaction(function () use ($family, $input, $actor, $draft, $view, $branch, $digest): array {
            $currentFamily = CommunicationTemplate::query()->whereKey($family->id)->lockForUpdate()->firstOrFail();
            $this->mutation->authorize($currentFamily, $actor);
            $currentDraft = CommunicationTemplateDraft::query()->where('communication_template_id', $family->id)->lockForUpdate()->first();
            $this->mutation->guards($currentFamily, $currentDraft, $input);
            abort_unless($currentDraft !== null && (string) $draft->id === (string) $currentDraft->id, 422);
            $current = $this->state->persisted($currentDraft);
            $rendererVersion = $this->state->rendererVersion($currentDraft);
            $now = $view === 'email'
                ? $this->state->emailDigest($currentFamily, $current, $rendererVersion)
                : $this->state->portalDigest($current, $rendererVersion);
            abort_unless(hash_equals($digest, $now), 422, 'This output changed while it was being checked.');

            return ['view' => $view, 'output_sha256' => $digest, 'preview_receipt' => $this->receipts->issue($currentFamily, $currentDraft, $actor, $view),
                'diagnostics' => [], ...$branch];
        });
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array{enabled:bool,label:?string,route:?string,url:?string}
     */
    private function emailAction(array $message): array
    {
        $routeName = $message['action_route'] ?? null;
        if ($routeName === null) {
            return ['enabled' => false, 'label' => null, 'route' => null, 'url' => null];
        }

        $path = route($routeName, [], false);
        $url = rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
        abort_unless(NoticeEmailLinkPolicy::allows($url), 422, 'The saved notice action destination is unavailable.');

        return ['enabled' => true, 'label' => (string) $message['action_label'], 'route' => $routeName, 'url' => $url];
    }
}
