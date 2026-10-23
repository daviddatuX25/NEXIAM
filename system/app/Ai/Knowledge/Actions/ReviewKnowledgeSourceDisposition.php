<?php

declare(strict_types=1);

namespace App\Ai\Knowledge\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Ai\KnowledgeSource;
use App\Models\Ai\KnowledgeSourceReconciliation;
use App\Models\Ai\KnowledgeSourceVersion;
use App\Models\Communications\PublicPolicy;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ReviewKnowledgeSourceDisposition
{
    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, array $payload): KnowledgeSourceReconciliation
    {
        CapabilityAuthorizer::authorizeUser($actor, CapabilityCatalog::KNOWLEDGE_GOVERNANCE);

        $validated = Validator::make($payload, [
            'knowledge_source_id' => ['required', 'uuid', 'exists:knowledge_sources,id'],
            'knowledge_source_version_id' => ['required', 'uuid', 'exists:knowledge_source_versions,id'],
            'expected_checksum' => ['required', 'string', 'size:64'],
            'disposition' => ['required', 'string', Rule::in([
                KnowledgeSourceReconciliation::DISPOSITION_RETAIN_INTERNAL,
                KnowledgeSourceReconciliation::DISPOSITION_REFERENCE_PUBLIC,
                KnowledgeSourceReconciliation::DISPOSITION_HOLD,
            ])],
            'target_type' => ['nullable', 'string'],
            'target_id' => ['nullable'],
            'target_version' => ['nullable', 'integer'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ])->validate();

        return DB::transaction(function () use ($actor, $validated): KnowledgeSourceReconciliation {
            /** @var KnowledgeSource $source */
            $source = KnowledgeSource::query()
                ->where('id', $validated['knowledge_source_id'])
                ->lockForUpdate()
                ->firstOrFail();

            /** @var KnowledgeSourceVersion $version */
            $version = KnowledgeSourceVersion::query()
                ->where('id', $validated['knowledge_source_version_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($version->knowledge_source_id !== $source->id) {
                throw ValidationException::withMessages([
                    'knowledge_source_version_id' => 'Version does not belong to the specified knowledge source.',
                ]);
            }

            $actorInstitutionId = $actor->institution_id ?? $actor->actorScope()->institutionId;
            if ($source->institution_id !== null && (int) $source->institution_id !== (int) $actorInstitutionId) {
                throw new AuthorizationException('Cross-institution knowledge access denied.');
            }

            if ($source->classification === KnowledgeSource::CLASSIFICATION_OFFICE_INTERNAL) {
                $scope = $actor->actorScope();
                $actorOffice = $scope->office;

                if ($source->owning_office !== null && $actorOffice !== $source->owning_office) {
                    throw new AuthorizationException(
                        "Actor from office '{$actorOffice}' cannot review sources owned by '{$source->owning_office}'."
                    );
                }
            }

            if ($version->content_checksum !== $validated['expected_checksum']) {
                throw ValidationException::withMessages([
                    'expected_checksum' => 'Version checksum has changed; review aborted due to concurrent modification.',
                ]);
            }

            if ($version->status === KnowledgeSourceVersion::STATUS_REVOKED) {
                throw ValidationException::withMessages([
                    'version' => 'Cannot review a revoked version.',
                ]);
            }

            $targetType = $validated['target_type'] ?? null;
            $targetId = isset($validated['target_id']) ? (string) $validated['target_id'] : null;
            $targetVersion = isset($validated['target_version']) ? (int) $validated['target_version'] : null;

            if ($targetType !== null && $targetType !== PublicPolicy::class) {
                throw ValidationException::withMessages([
                    'target_type' => 'Target type must be an approved public policy model.',
                ]);
            }

            if ($validated['disposition'] === KnowledgeSourceReconciliation::DISPOSITION_REFERENCE_PUBLIC) {
                if ($targetType !== PublicPolicy::class || empty($targetId)) {
                    throw ValidationException::withMessages([
                        'target_id' => 'Target public policy must exist and be active.',
                    ]);
                }

                $target = PublicPolicy::query()->find($targetId);
                if ($target === null) {
                    throw ValidationException::withMessages([
                        'target_id' => 'Target public policy must exist and be active.',
                    ]);
                }

                if (isset($target->status) && $target->status !== 'published') {
                    throw ValidationException::withMessages([
                        'target_id' => 'Target public policy must exist and be active.',
                    ]);
                }

                if (isset($target->is_active) && ! $target->is_active) {
                    throw ValidationException::withMessages([
                        'target_id' => 'Target public policy must exist and be active.',
                    ]);
                }

                if ($targetVersion === null && isset($target->revision)) {
                    $targetVersion = (int) $target->revision;
                }
            }

            /** @var KnowledgeSourceReconciliation|null $existing */
            $existing = KnowledgeSourceReconciliation::query()
                ->where('knowledge_source_version_id', $version->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $matchesDisposition = $existing->disposition === $validated['disposition'];
                $matchesTargetType = $existing->target_type === $targetType;
                $matchesTargetId = ((string) ($existing->target_id ?? '')) === ((string) ($targetId ?? ''));
                $matchesReason = $existing->reason === $validated['reason'];

                if ($matchesDisposition && $matchesTargetType && $matchesTargetId && $matchesReason) {
                    return $existing;
                }

                throw ValidationException::withMessages([
                    'knowledge_source_version_id' => 'This version has already been reconciled. Existing reconciliation is immutable and cannot be overwritten.',
                ]);
            }

            return KnowledgeSourceReconciliation::create([
                'knowledge_source_id' => $source->id,
                'knowledge_source_version_id' => $version->id,
                'disposition' => $validated['disposition'],
                'target_type' => $targetType,
                'target_id' => $targetId,
                'target_version' => $targetVersion,
                'checksum_at_review' => $version->content_checksum,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'reason' => $validated['reason'],
            ]);
        });
    }
}
