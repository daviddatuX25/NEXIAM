<?php

namespace App\AdmissionEngine\Actions;

use App\Admission\Support\CommandIdempotency;
use App\AdmissionEngine\Support\BenchmarkInputSchema;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Authorization\GovernanceAuthorizer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionBenchmarkSubjectBinding;
use App\Models\Foundation\Institution;
use App\Models\Foundation\IntakeGradeField;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final readonly class ApproveAdmissionBenchmarkSubjectBinding
{
    public function __construct(
        private GovernanceAuthorizer $authorization,
        private CapabilityAuthorizer $catalogueCapabilities,
        private CommandIdempotency $idempotency,
        private RecordAuditEvent $audit,
    ) {}

    public function handle(
        User $actor,
        Institution $institution,
        IntakeGradeField $source,
        string $featureKey,
        string $unit,
        bool $modelRequired,
        string $reason,
        string $idempotencyKey,
    ): AdmissionBenchmarkSubjectBinding {
        $reason = trim($reason);
        $featureKey = trim($featureKey);
        $unit = trim($unit);
        if (! preg_match('/^[a-z][a-z0-9_]{1,63}$/', $featureKey) || mb_strlen($unit) > 64 || $unit === ''
            || $reason === '' || mb_strlen($reason) > 500 || $idempotencyKey === '' || mb_strlen($idempotencyKey) > 128) {
            throw new InvalidArgumentException('Subject binding requires a stable feature key, explicit unit, reason, and idempotency key.');
        }

        if (! $this->authorization->canPublishInInstitution($actor, (int) $institution->id)
            || ! $this->catalogueCapabilities->decideFresh($actor, CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG)->allowed) {
            throw new AuthorizationException('Current Guidance/Test Administration publication and Guidance catalogue authoring authority are required.');
        }

        return DB::transaction(function () use ($actor, $institution, $source, $featureKey, $unit, $modelRequired, $reason, $idempotencyKey): AdmissionBenchmarkSubjectBinding {
            $freshInstitution = Institution::query()->whereKey($institution->id)->lockForUpdate()->firstOrFail();
            if (! $this->authorization->canPublishInInstitution($actor, (int) $institution->id)
                || ! $this->catalogueCapabilities->decideFresh($actor, CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG)->allowed) {
                throw new AuthorizationException('Publication or schema-authoring authority is no longer active.');
            }
            $field = IntakeGradeField::query()->whereKey($source->id)->lockForUpdate()->firstOrFail();
            if ((int) $field->institution_id !== (int) $freshInstitution->id || $field->field_kind !== IntakeGradeField::KIND_SUBJECT || ! $field->is_active) {
                throw new InvalidArgumentException('Only an active subject field in the same institution can be bound to a model feature.');
            }
            if (! hash_equals(BenchmarkInputSchema::sourceVersion($source), BenchmarkInputSchema::sourceVersion($field))
                || (int) $source->sort_order !== (int) $field->sort_order
                || $source->updated_at?->toISOString() !== $field->updated_at?->toISOString()) {
                throw new RuntimeException('The source field changed after the approval screen was loaded; refresh and review the current definition.');
            }
            if ($field->min_value === null || $field->max_value === null || ! is_numeric($field->min_value) || ! is_numeric($field->max_value)
                || (float) $field->min_value > (float) $field->max_value) {
                throw new InvalidArgumentException('The source field has no valid numeric range to snapshot.');
            }
            $sourceSnapshot = [
                'source_id' => (int) $field->id,
                'source_code' => (string) $field->code,
                'source_label' => (string) $field->label,
                'source_updated_at' => $field->updated_at?->toISOString(),
                'field_kind' => (string) $field->field_kind,
                'source_sort_order' => (int) $field->sort_order,
                'intake_required' => (bool) $field->is_required,
                'min_value' => (float) $field->min_value,
                'max_value' => (float) $field->max_value,
                'precision' => 2,
                'unit' => $unit,
                'missing_value' => null,
            ];
            $payload = [
                'institution_id' => (int) $freshInstitution->id,
                'source_id' => (int) $field->id,
                'feature_key' => $featureKey,
                'unit' => $unit,
                'model_required' => $modelRequired,
                'source_snapshot' => $sourceSnapshot,
                'reason' => $reason,
            ];
            $operation = $this->idempotency->claim($actor, 'benchmark.subject-binding.approve', (string) $freshInstitution->id, $payload, $idempotencyKey);
            if (isset($operation->payload['binding_id'])) {
                return AdmissionBenchmarkSubjectBinding::query()->whereKey($operation->payload['binding_id'])->firstOrFail();
            }
            $existing = AdmissionBenchmarkSubjectBinding::query()
                ->where('institution_id', $freshInstitution->id)
                ->where('feature_key', $featureKey)
                ->orderByDesc('version')
                ->lockForUpdate()
                ->first();
            if ($existing !== null && (int) $existing->intake_grade_field_id !== (int) $field->id) {
                throw new RuntimeException('This feature key is already bound to a different immutable source identity.');
            }
            if ($existing !== null) {
                $previousSource = $existing->source_snapshot_json;
                foreach (['source_id', 'source_code', 'source_label', 'field_kind', 'unit'] as $identityKey) {
                    if ((string) ($previousSource[$identityKey] ?? '') !== (string) ($sourceSnapshot[$identityKey] ?? '')) {
                        throw new RuntimeException('A source identity, label, or unit change requires a new model feature key to preserve prior meanings.');
                    }
                }
                if ((float) $previousSource['min_value'] !== (float) $sourceSnapshot['min_value']
                    || (float) $previousSource['max_value'] !== (float) $sourceSnapshot['max_value']) {
                    throw new RuntimeException('A source range change requires a new model feature key to preserve prior meanings.');
                }
            }
            $binding = AdmissionBenchmarkSubjectBinding::query()->create([
                'institution_id' => $freshInstitution->id,
                'intake_grade_field_id' => $field->id,
                'version' => ($existing?->version ?? 0) + 1,
                'feature_key' => $featureKey,
                'label' => (string) $field->label,
                'unit' => $unit,
                'min_value' => $field->min_value,
                'max_value' => $field->max_value,
                'is_model_required' => $modelRequired,
                'source_snapshot_json' => $sourceSnapshot,
                'approved_by_user_id' => $actor->id,
                'approved_at' => now(),
                'reason' => $reason,
                'idempotency_key' => hash('sha256', $actor->id.':'.$idempotencyKey),
            ]);
            $operation->forceFill(['payload' => [...$operation->payload, 'binding_id' => $binding->id]])->save();
            $this->audit->handle('admission_benchmark.subject_binding_approved', [
                'binding_id' => $binding->id,
                'institution_id' => $freshInstitution->id,
                'source_id' => $field->id,
                'feature_key' => $featureKey,
                'version' => $binding->version,
                'unit' => $unit,
                'model_required' => $modelRequired,
                'intake_required' => (bool) $field->is_required,
                'reason' => $reason,
            ], (string) $actor->id);

            return $binding;
        });
    }
}
