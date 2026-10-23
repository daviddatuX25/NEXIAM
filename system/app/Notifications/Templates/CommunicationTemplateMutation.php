<?php

declare(strict_types=1);

namespace App\Notifications\Templates;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateDocument;
use App\Models\Notifications\CommunicationTemplateDraft;
use App\Models\Notifications\CommunicationTemplateReceipt;
use App\Models\User;
use App\Notifications\Support\CommunicationTemplateScope;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class CommunicationTemplateMutation
{
    public function __construct(private readonly CommunicationTemplateScope $scope, private readonly RecordAuditEvent $audit) {}

    public function authorize(CommunicationTemplate $template, User $actor): void
    {
        $actor = User::query()->findOrFail($actor->id);
        $current = CommunicationTemplate::query()->findOrFail($template->id);
        abort_unless($current->domain === $template->domain && $current->slug === $template->slug
            && (int) $current->institution_id === (int) $template->institution_id, 404);
        abort_if(CommunicationTemplateRegistry::authoringMode($template->domain, $template->slug) === 'builtin', 403);
        $institution = $this->scope->authorizeFullSource($actor, $template->domain);
        abort_unless((int) $institution->id === (int) $template->institution_id, 404);
    }

    public function guards(CommunicationTemplate $template, ?CommunicationTemplateDraft $draft, array $data): void
    {
        Validator::make($data, [
            'base_version' => ['required', 'integer', 'min:1'],
            'family_revision' => ['required', 'integer', 'min:1'],
            'lock_version' => ['present', 'nullable', 'integer', 'min:1'],
        ])->validate();
        if ((int) $data['base_version'] !== $template->active_version || (int) $data['family_revision'] !== $template->family_revision) {
            $this->conflict('template_conflict', $template, $draft);
        }
        if (($draft === null) !== ($data['lock_version'] === null)
            || ($draft !== null && (int) $data['lock_version'] !== $draft->lock_version)
            || ($draft !== null && $draft->base_version !== (int) $data['base_version'])
            || (isset($data['draft_id']) && (string) $data['draft_id'] !== (string) $draft?->id)) {
            $this->conflict('draft_conflict', $template, $draft);
        }
    }

    public function run(CommunicationTemplate $template, array $data, User $actor, string $operation, int $status, Closure $mutation): array
    {
        return DB::transaction(function () use ($template, $data, $actor, $operation, $status, $mutation): array {
            $locked = CommunicationTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();
            $this->authorize($locked, $actor);
            Validator::make($data, ['idempotency_key' => ['required', 'string', 'max:128']])->validate();
            $draft = CommunicationTemplateDraft::query()->where('communication_template_id', $locked->id)->lockForUpdate()->first();
            $payload = $data;
            unset($payload['idempotency_key']);
            foreach (['base_version', 'family_revision', 'lock_version', 'stage', 'source_version'] as $field) {
                if (isset($payload[$field]) && is_numeric($payload[$field])) {
                    $payload[$field] = (int) $payload[$field];
                }
            }
            if (isset($payload['replace_email'])) {
                $payload['replace_email'] = filter_var($payload['replace_email'], FILTER_VALIDATE_BOOLEAN);
            }
            $sources = [];
            $this->sourceHashes($locked, $payload['state'] ?? [], $sources);
            $payload['asset_sha256'] = $sources;
            $hash = CommunicationTemplatePayload::hash(['operation' => $operation, 'actor_id' => $actor->id, 'family_id' => $locked->id, 'payload' => $payload]);
            $receipt = CommunicationTemplateReceipt::query()
                ->where('institution_id', $locked->institution_id)->where('domain', $locked->domain)
                ->where('canonical_slug', $locked->slug)->where('operation', $operation)
                ->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first();
            if ($receipt !== null) {
                if (! hash_equals($receipt->payload_sha256, $hash)) {
                    throw new HttpResponseException(response()->json(['error' => 'idempotency_payload_conflict', 'message' => 'This operation key is bound to a different payload.'], 409));
                }

                return [...$receipt->response_payload, 'replayed' => true];
            }
            $this->guards($locked, $draft, $data);
            $result = $mutation($locked, $draft);
            $locked->increment('family_revision');
            $operationId = (string) Str::uuid();
            $result = [...$result, 'family_revision' => $locked->family_revision, 'active_version' => $locked->active_version,
                'operation_id' => $operationId, 'replayed' => false, '_status' => $status];
            $this->audit->handle(match ($operation) {
                'save_draft' => 'communication_template.draft_saved',
                'publish' => 'communication_template.version_published',
                'discard' => 'communication_template.draft_discarded',
                default => 'communication_template.'.$operation,
            }, ['template_id' => $locked->id, 'institution_id' => $locked->institution_id, 'slug' => $locked->slug,
                'operation_id' => $operationId, 'family_revision' => $locked->family_revision,
                'operation' => $operation, 'event_key' => $locked->event_key, 'base_version' => (int) $data['base_version'],
                'previous_family_revision' => (int) $data['family_revision'], 'previous_lock_version' => $data['lock_version'],
                'lock_version' => $result['draft']['lock_version'] ?? null, 'version' => $result['version']['version'] ?? null,
                'source_sha256' => $data['source_sha256'] ?? $result['draft']['state']['import_provenance']['source_sha256'] ?? null,
                'source_asset_id' => $result['source']['asset_id'] ?? $result['import']['source_asset_id'] ?? null,
                'purpose' => $result['source']['purpose'] ?? ($operation === 'document_imported' ? 'email' : null),
                'active_version' => $locked->active_version, 'content_sha256' => $result['content_sha256'] ?? $result['version']['content_sha256'] ?? null], (string) $actor->id);
            CommunicationTemplateReceipt::query()->create([
                'id' => $operationId, 'institution_id' => $locked->institution_id, 'domain' => $locked->domain,
                'canonical_slug' => $locked->slug, 'operation' => $operation, 'idempotency_key' => $data['idempotency_key'],
                'payload_sha256' => $hash, 'response_payload' => $result, 'created_by_user_id' => $actor->id,
            ]);

            return $result;
        });
    }

    private function sourceHashes(CommunicationTemplate $template, mixed $value, array &$hashes): void
    {
        if (! is_array($value)) {
            return;
        }
        foreach ($value as $key => $child) {
            if (in_array($key, ['asset_id', 'source_asset_id', 'document_asset_id', 'report_id'], true) && is_string($child)) {
                $asset = CommunicationTemplateDocument::query()->whereKey($child)
                    ->where('communication_template_id', $template->id)->where('institution_id', $template->institution_id)->first();
                abort_unless($asset !== null, 422, 'A source is not owned by this notice family.');
                $hashes[$child] = $asset->checksum;
            } elseif (is_array($child)) {
                $this->sourceHashes($template, $child, $hashes);
            }
        }
    }

    private function conflict(string $error, CommunicationTemplate $template, ?CommunicationTemplateDraft $draft): never
    {
        throw new HttpResponseException(response()->json(['error' => $error, 'message' => 'The notice changed. Reload and reconcile your work.',
            'current' => ['active_version' => $template->active_version, 'family_revision' => $template->family_revision,
                'draft_id' => $draft?->id, 'lock_version' => $draft?->lock_version]], 409));
    }
}
