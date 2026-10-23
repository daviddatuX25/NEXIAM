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
use App\Notifications\Templates\CommunicationTemplatePreviewReceipt;
use App\Notifications\Templates\CommunicationTemplateState;
use App\Notifications\Templates\NoticeTemplateSyntheticPrint;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class SendCommunicationTemplateTestMail
{
    public function __construct(private readonly CommunicationTemplateMutation $mutation,
        private readonly CommunicationTemplateState $state, private readonly CommunicationTemplatePreviewReceipt $previews,
        private readonly RenderCommunicationTemplatePreview $preview, private readonly NoticeTemplateSyntheticPrint $print,
        private readonly RecordAuditEvent $audit) {}

    public function execute(CommunicationTemplate $template, array $input, User $actor): array
    {
        Validator::make(['input' => $input], ['input' => ['required', 'array:base_version,family_revision,lock_version,idempotency_key,content_sha256,preview_receipt'],
            'input.idempotency_key' => ['required', 'string', 'max:128'],
            'input.content_sha256' => ['required', 'regex:/^[a-f0-9]{64}$/D'],
            'input.preview_receipt' => ['required', 'string', 'max:16384']])->validate();
        $claim = DB::transaction(function () use ($template, $input, $actor): array {
            $family = CommunicationTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();
            $this->mutation->authorize($family, $actor);
            $principal = User::query()->findOrFail($actor->id);
            abort_unless($principal->staffProfile?->isActive() && $principal->email_verified_at !== null
                && filter_var($principal->email, FILTER_VALIDATE_EMAIL), 422, 'A verified staff email address is required.');
            $payload = $input;
            unset($payload['idempotency_key']);
            $hash = CommunicationTemplatePayload::hash(['operation' => 'test_mail', 'actor_id' => (string) $principal->id,
                'family_id' => (string) $family->id, 'recipient' => $principal->email, 'payload' => $payload]);
            $draft = CommunicationTemplateDraft::query()->where('communication_template_id', $family->id)->lockForUpdate()->first();
            $existing = CommunicationTemplateReceipt::query()->where('institution_id', $family->institution_id)
                ->where('domain', $family->domain)->where('canonical_slug', $family->slug)->where('operation', 'test_mail')
                ->where('idempotency_key', $input['idempotency_key'])->lockForUpdate()->first();
            if ($existing !== null) {
                if (! hash_equals($hash, $existing->payload_sha256)) {
                    $this->failure(['error' => 'idempotency_payload_conflict', 'message' => 'This test key belongs to different content.'], 409);
                }
                $outcome = $existing->response_payload;
                if (in_array($outcome['status'] ?? '', ['claimed', 'unknown'], true)) {
                    $this->failure(['error' => 'test_send_ambiguous', 'test_send_id' => $existing->id,
                        'status' => $outcome['status'], 'message' => 'The prior send is in flight or its outcome is unknown. No duplicate test was sent.'], 409);
                }

                return ['replay' => [...$outcome, 'replayed' => true]];
            }
            $this->mutation->guards($family, $draft, $input);
            abort_unless($draft !== null, 422, 'Save a draft before test mail.');
            $this->state->assertPublishable($family, $draft, $principal);
            abort_unless(hash_equals($input['content_sha256'], $this->state->digest($family, $draft)), 422, 'The checked content is stale.');
            $this->previews->verify($family, $draft, $principal, 'email', $input['preview_receipt']);
            $id = (string) Str::uuid();
            $receipt = CommunicationTemplateReceipt::query()->create(['id' => $id, 'institution_id' => $family->institution_id,
                'domain' => $family->domain, 'canonical_slug' => $family->slug, 'operation' => 'test_mail',
                'idempotency_key' => $input['idempotency_key'], 'payload_sha256' => $hash,
                'response_payload' => ['status' => 'claimed', 'test_send_id' => $id, 'operation_id' => $id],
                'created_by_user_id' => $principal->id]);
            $this->audit->handle('communication_template.test_mail_claimed', ['template_id' => $family->id,
                'institution_id' => $family->institution_id, 'operation_id' => $id,
                'content_sha256' => $input['content_sha256']], (string) $principal->id);

            return compact('family', 'draft', 'principal', 'receipt');
        });
        if (isset($claim['replay'])) {
            if (($claim['replay']['_status'] ?? 200) !== 200) {
                $this->failure($claim['replay'], $claim['replay']['_status']);
            }

            return $claim['replay'];
        }
        $attempted = false;
        try {
            $rendered = $this->preview->email($claim['family'], $claim['draft']);
            $pdf = $claim['draft']->document_mode === 'none' ? null
                : $this->print->render($claim['family'], $claim['draft'], RenderCommunicationTemplatePreview::syntheticContext($claim['family']));
            $mailer = config('mail.default');
            $transport = config('mail.mailers.'.$mailer.'.transport');
            if ($mailer === null || in_array($transport, [null, 'log', 'array'], true)) {
                throw new \RuntimeException('test_provider_unavailable');
            }
            $attempted = true;
            $sent = Mail::to($claim['principal']->email)->send(new NoticeTemplateTestMailable($rendered, $pdf));
            if ($sent === null) {
                throw new \RuntimeException('test_send_unobserved');
            }
            $result = ['test_send_id' => $claim['receipt']->id, 'status' => 'sent',
                'recipient_masked' => $this->mask($claim['principal']->email), 'content_sha256' => $input['content_sha256'],
                'operation_id' => $claim['receipt']->id, 'replayed' => false, '_status' => 200];
            $this->complete($claim['receipt'], $claim['family'], $claim['principal'], $result, $pdf['sha256'] ?? null);

            return $result;
        } catch (Throwable $exception) {
            $result = ['error' => 'test_send_unavailable', 'message' => 'The synthetic test could not be confirmed. No automatic resend will occur.',
                'test_send_id' => $claim['receipt']->id, 'operation_id' => $claim['receipt']->id,
                'status' => $attempted ? 'unknown' : 'failed', 'content_sha256' => $input['content_sha256'], 'replayed' => false, '_status' => 503];
            try {
                $this->complete($claim['receipt'], $claim['family'], $claim['principal'], $result);
            } catch (Throwable) {
                // The committed claim remains ambiguous; never retry the provider from this operation.
            }
            $this->failure($result, 503);
        }
    }

    private function complete(CommunicationTemplateReceipt $receipt, CommunicationTemplate $family, User $actor, array $result, ?string $pdfSha256 = null): void
    {
        DB::transaction(function () use ($receipt, $family, $actor, $result, $pdfSha256): void {
            $locked = CommunicationTemplateReceipt::query()->whereKey($receipt->id)->lockForUpdate()->firstOrFail();
            $locked->update(['response_payload' => $result]);
            $this->audit->handle('communication_template.test_mail_'.$result['status'], [
                'template_id' => $family->id, 'institution_id' => $family->institution_id, 'operation_id' => $receipt->id,
                'content_sha256' => $result['content_sha256'], 'status' => $result['status'], 'pdf_sha256' => $pdfSha256,
            ], (string) $actor->id);
        });
    }

    private function mask(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 1).'***@'.$domain;
    }

    private function failure(array $result, int $status): never
    {
        unset($result['_status']);
        throw new HttpResponseException(response()->json($result, $status));
    }
}
