<?php

namespace App\Assessment\Actions;

use App\Assessment\Support\TestKitPreparationReceipt;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitPreparation;
use App\Models\Foundation\Institution;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreateTestKitPreparation
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly TestKitPreparationReceipt $receipts,
        private readonly RecordAuditEvent $audit,
    ) {}

    /**
     * @param  array{code:string,name:string,expected_revision:int|string,idempotency_key:string}  $input
     */
    public function handle(User $actor, array $input): TestKitPreparation
    {
        $unexpectedFields = array_diff(array_keys($input), ['code', 'name', 'expected_revision', 'idempotency_key']);
        if ($unexpectedFields !== []) {
            throw ValidationException::withMessages([
                (string) array_values($unexpectedFields)[0] => 'Only the Test Kit code and name may be supplied as creation data.',
            ]);
        }

        $expectedRevision = $input['expected_revision'] ?? null;
        if (! is_int($expectedRevision)
            && (! is_string($expectedRevision) || preg_match('/^(0|[1-9][0-9]*)$/D', $expectedRevision) !== 1)) {
            throw ValidationException::withMessages(['expected_revision' => 'A non-negative expected revision is required.']);
        }
        $expectedRevision = (int) $expectedRevision;

        $code = Str::upper(trim((string) ($input['code'] ?? '')));
        $name = Str::squish((string) ($input['name'] ?? ''));
        if ($code === '' || mb_strlen($code) > 64) {
            throw ValidationException::withMessages(['code' => 'Enter a Test Kit code of no more than 64 characters.']);
        }
        if ($name === '' || mb_strlen($name) > 255) {
            throw ValidationException::withMessages(['name' => 'Enter a Test Kit name of no more than 255 characters.']);
        }

        $idempotencyKey = $input['idempotency_key'] ?? null;
        if (! is_string($idempotencyKey) || ! Str::isUuid($idempotencyKey)) {
            throw ValidationException::withMessages(['idempotency_key' => 'A valid idempotency UUID is required.']);
        }

        return DB::transaction(function () use ($actor, $code, $name, $expectedRevision, $idempotencyKey): TestKitPreparation {
            $principal = User::query()->find($actor->getKey());
            if ($principal === null) {
                throw new AuthorizationException('Test Kit creation is not authorized.');
            }

            $scope = $principal->actorScope();
            $institutionId = (int) ($scope->institutionId ?? 0);
            $activeProfile = $principal->staffProfile()
                ->where('status', StaffProfile::STATUS_ACTIVE)
                ->first();
            if ($scope->office !== 'guidance'
                || $institutionId < 1
                || $activeProfile === null
                || (int) $activeProfile->institution_id !== $institutionId) {
                throw new AuthorizationException('Test Kit creation is not authorized.');
            }

            Institution::query()->lockForUpdate()->findOrFail($institutionId);

            $principal = User::query()->find($actor->getKey());
            if ($principal === null) {
                throw new AuthorizationException('Test Kit creation is not authorized.');
            }
            $scope = $principal->actorScope();
            $activeProfile = $principal->staffProfile()
                ->where('status', StaffProfile::STATUS_ACTIVE)
                ->first();
            if ((int) ($scope->institutionId ?? 0) !== $institutionId
                || $scope->office !== 'guidance'
                || $activeProfile === null
                || (int) $activeProfile->institution_id !== $institutionId
                || ! $this->authorizer->decideFresh(
                    $principal,
                    CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
                    ['office' => 'guidance', 'institution_id' => $institutionId],
                )->allowed) {
                throw new AuthorizationException('Guidance assessment-catalog authority for this institution is required.');
            }

            $payload = [
                'institution_id' => $institutionId,
                'code' => $code,
                'name' => $name,
                'expected_revision' => $expectedRevision,
            ];
            $receipt = $this->receipts->claim(
                $principal,
                'test_kit.create_preparation',
                (string) $institutionId,
                $payload,
                $idempotencyKey,
            );
            if (($result = $this->receipts->result($receipt)) !== null) {
                return TestKitPreparation::query()
                    ->where('test_kit_id', $result['test_kit_id'])
                    ->whereHas('testKit', fn ($query) => $query->where('institution_id', $institutionId))
                    ->findOrFail($result['preparation_id']);
            }
            if ($expectedRevision !== 0) {
                throw ValidationException::withMessages([
                    'expected_revision' => 'A new Test Kit must start at expected revision 0.',
                ]);
            }

            if (TestKit::query()->where('institution_id', $institutionId)->where('code', $code)->exists()) {
                throw ValidationException::withMessages([
                    'code' => 'A Test Kit family with this code already exists in the institution.',
                ]);
            }

            $kit = TestKit::query()->create([
                'public_id' => (string) Str::uuid(),
                'institution_id' => $institutionId,
                'code' => $code,
                'name' => $name,
                'is_synthetic' => false,
                'created_by_user_id' => (int) $principal->getKey(),
            ]);
            $preparation = TestKitPreparation::query()->create([
                'test_kit_id' => (int) $kit->getKey(),
                'state' => TestKitPreparation::STATE_PREPARING,
                'revision' => 1,
                'created_by_user_id' => (int) $principal->getKey(),
                'updated_by_user_id' => (int) $principal->getKey(),
            ]);

            $this->receipts->remember($receipt, $preparation);
            $this->audit->handle('assessment.test_kit.preparation_created', [
                'test_kit_id' => (int) $kit->getKey(),
                'preparation_id' => (int) $preparation->getKey(),
                'preparation_revision' => (int) $preparation->revision,
            ], (string) $principal->getKey());

            return $preparation;
        });
    }
}
