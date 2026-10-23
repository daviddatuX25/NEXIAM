<?php

declare(strict_types=1);

namespace App\Notifications\Templates\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateDraft;
use App\Models\Notifications\CommunicationTemplateReceipt;
use App\Models\User;
use App\Notifications\Mail\NoticeTemplateTestMailable;
use App\Notifications\Templates\CommunicationTemplateMutation;
use App\Notifications\Templates\CommunicationTemplatePayload;
use App\Notifications\Templates\CommunicationTemplateState;
use App\Notifications\Templates\Email\NoticeEmailAssetVerifier;
use App\Notifications\Templates\Email\NoticeEmailLinkPolicy;
use App\Notifications\Templates\Email\NoticeMessageEmailRenderer;
use App\Notifications\Templates\NoticeMessageOutputReceipt;
use App\Notifications\Templates\NoticeMessageState;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

final class SendNoticeMessageTestMail
{
    public function __construct(private readonly CommunicationTemplateMutation $mutation, private readonly NoticeMessageState $state,
        private readonly NoticeMessageOutputReceipt $receipts, private readonly NoticeMessageEmailRenderer $renderer,
        private readonly NoticeEmailAssetVerifier $assets, private readonly RecordAuditEvent $audit) {}

    public function execute(CommunicationTemplate $family, array $input, User $actor): array
    {
        Validator::make(['input' => $input], ['input' => ['required', 'array:base_version,family_revision,lock_version,draft_id,idempotency_key,output_sha256,preview_receipt'],
            'input.idempotency_key' => ['required', 'string', 'max:128'], 'input.output_sha256' => ['required', 'regex:/^[a-f0-9]{64}$/D'],
            'input.preview_receipt' => ['required', 'string', 'max:16384']])->validate();
        $claim = DB::transaction(function () use ($family, $input, $actor): array {
            $locked = CommunicationTemplate::query()->whereKey($family->id)->lockForUpdate()->firstOrFail();
            $this->mutation->authorize($locked, $actor);
            $principal = User::query()->findOrFail($actor->id);
            abort_unless($principal->staffProfile?->isActive() && $principal->email_verified_at !== null && filter_var($principal->email, FILTER_VALIDATE_EMAIL), 422,
                'A verified staff email address is required.');
            $payload = $input;
            unset($payload['idempotency_key']);
            $hash = CommunicationTemplatePayload::hash(['operation' => 'message_test_mail', 'actor_id' => (string) $principal->id,
                'family_id' => (string) $locked->id, 'recipient' => $principal->email, 'payload' => $payload]);
            $existing = CommunicationTemplateReceipt::query()->where('institution_id', $locked->institution_id)->where('domain', $locked->domain)
                ->where('canonical_slug', $locked->slug)->where('operation', 'message_test_mail')->where('idempotency_key', $input['idempotency_key'])->lockForUpdate()->first();
            if ($existing !== null) {
                abort_unless(hash_equals($hash, $existing->payload_sha256), 409, 'This test key is bound to different content.');
                $outcome = $existing->response_payload;
                abort_unless(! in_array($outcome['status'] ?? '', ['claimed', 'unknown'], true), 409, 'The prior test outcome is ambiguous; no duplicate was sent.');

                return ['replay' => [...$outcome, 'replayed' => true]];
            }
            $draft = CommunicationTemplateDraft::query()->where('communication_template_id', $locked->id)->lockForUpdate()->first();
            $this->mutation->guards($locked, $draft, $input);
            abort_unless($draft?->content_schema_version === 'notice-content-3', 422, 'Save a message draft before test mail.');
            $this->receipts->verify($locked, $draft, $principal, 'email', $input['preview_receipt'], $input['output_sha256']);
            $id = (string) Str::uuid();
            $receipt = CommunicationTemplateReceipt::query()->create(['id' => $id, 'institution_id' => $locked->institution_id,
                'domain' => $locked->domain, 'canonical_slug' => $locked->slug, 'operation' => 'message_test_mail',
                'idempotency_key' => $input['idempotency_key'], 'payload_sha256' => $hash,
                'response_payload' => ['status' => 'claimed', 'test_send_id' => $id, 'operation_id' => $id], 'created_by_user_id' => $principal->id]);
            $this->audit->handle('communication_template.message_test_mail_claimed', ['template_id' => $locked->id,
                'institution_id' => $locked->institution_id, 'operation_id' => $id, 'content_sha256' => $draft->content_sha256], (string) $principal->id);

            return compact('locked', 'draft', 'principal', 'receipt');
        });
        if (isset($claim['replay'])) {
            return $claim['replay'];
        }
        $attempted = false;
        try {
            $draft = $claim['draft'];
            $state = $this->state->persisted($draft);
            $rendererVersion = $this->state->rendererVersion($draft);
            $owned = $this->assets->verify((int) $claim['locked']->institution_id, (string) $claim['locked']->id, $state['email_blueprint'] ?? []);
            $action = $rendererVersion === NoticeMessageState::NEW_EMAIL_RENDERER ? $this->emailAction($state) : null;
            $rendered = $this->renderer->render($state['email_blueprint'] ?? [], RenderCommunicationTemplatePreview::syntheticContext($claim['locked']),
                CommunicationTemplateState::tokens($claim['locked']), 'notice-email-1', $rendererVersion, $owned,
                (string) $state['subject'], $action);
            $mailer = config('mail.default');
            $transport = config('mail.mailers.'.$mailer.'.transport');
            if ($mailer === null || in_array($transport, [null, 'log', 'array'], true)) {
                throw new \RuntimeException('test_provider_unavailable');
            }
            $attempted = true;
            $sent = Mail::to($claim['principal']->email)->send(new NoticeTemplateTestMailable($rendered, null));
            if ($sent === null) {
                throw new \RuntimeException('test_send_unobserved');
            }
            $local = explode('@', $claim['principal']->email, 2)[0];
            $domain = explode('@', $claim['principal']->email, 2)[1];
            $result = ['test_send_id' => $claim['receipt']->id, 'status' => 'sent', 'recipient_masked' => mb_substr($local, 0, 1).'***@'.$domain,
                'output_sha256' => $input['output_sha256'], 'operation_id' => $claim['receipt']->id, 'replayed' => false];
            $this->complete($claim['receipt'], $claim['locked'], $claim['principal'], $result);

            return $result;
        } catch (Throwable $exception) {
            $result = ['error' => 'test_send_unavailable', 'status' => $attempted ? 'unknown' : 'failed',
                'test_send_id' => $claim['receipt']->id, 'operation_id' => $claim['receipt']->id, 'output_sha256' => $input['output_sha256'], 'replayed' => false];
            try {
                $this->complete($claim['receipt'], $claim['locked'], $claim['principal'], $result);
            } catch (Throwable) {
            }
            throw new HttpResponseException(response()->json($result, 503));
        }
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array{enabled:bool,label:?string,route:?string,url:?string}
     */
    private function emailAction(array $state): array
    {
        $routeName = $state['action_route'] ?? null;
        if ($routeName === null) {
            return ['enabled' => false, 'label' => null, 'route' => null, 'url' => null];
        }

        $path = route($routeName, [], false);
        $url = rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
        if (! NoticeEmailLinkPolicy::allows($url)) {
            throw new \RuntimeException('email_action_invalid');
        }

        return ['enabled' => true, 'label' => (string) $state['action_label'], 'route' => $routeName, 'url' => $url];
    }

    private function complete(CommunicationTemplateReceipt $receipt, CommunicationTemplate $family, User $actor, array $result): void
    {
        DB::transaction(function () use ($receipt, $family, $actor, $result): void {
            CommunicationTemplateReceipt::query()->whereKey($receipt->id)->lockForUpdate()->firstOrFail()->update(['response_payload' => $result]);
            $this->audit->handle('communication_template.message_test_mail_'.$result['status'], ['template_id' => $family->id,
                'institution_id' => $family->institution_id, 'operation_id' => $receipt->id, 'output_sha256' => $result['output_sha256'],
                'status' => $result['status']], (string) $actor->id);
        });
    }
}
