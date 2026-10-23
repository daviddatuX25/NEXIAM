<?php

namespace App\Http\Requests\Omr\Canonical;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;

final class DiscardOmrPhysicalAttemptRequest extends FormRequest
{
    public function authorize(CapabilityAuthorizer $authorizer): bool
    {
        $actor = $this->user();
        if (! $authorizer->decideFresh($actor, CapabilityCatalog::GUIDANCE_SCORING_OMR)->allowed) {
        }
        if (! $actor instanceof User) {
            return false;
        }

        $run = $this->route('run');
        if (! $run instanceof OmrTemplateVerificationRun) {
            return false;
        }
        $capture = $this->route('capture');
        if (! $capture instanceof OmrTemplateVerificationCapture || (int) $capture->run_id !== (int) $run->id) {
            throw (new ModelNotFoundException)->setModel(OmrTemplateVerificationCapture::class);
        }

        $run->loadMissing(['draft', 'startedBy.staffProfile']);
        if ($run->draft_id !== null) {
            if ((int) ($run->draft?->owner_user_id ?? 0) !== (int) $actor->id) {
                throw (new ModelNotFoundException)->setModel(OmrTemplateVerificationRun::class, [$run->id]);
            }

            return true;
        }

        $actorInstitution = $actor->actorScope()->institutionId;
        $starterInstitution = $run->startedBy?->actorScope()->institutionId;
        if ($actorInstitution !== null && $starterInstitution !== null && (int) $actorInstitution !== (int) $starterInstitution) {
            throw (new ModelNotFoundException)->setModel(OmrTemplateVerificationRun::class, [$run->id]);
        }

        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'method' => ['required', 'string', 'in:pre_shaded,hand_shaded'],
            'page_no' => ['required', 'integer', 'min:1', 'max:4'],
            'page_version' => ['required', 'integer', 'min:0'],
            'capture_lock_version' => ['required', 'integer', 'min:0'],
            'target_hash' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'plan_hash' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'expected_vector_hash' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'idempotency_key' => ['required', 'string', 'max:190'],
        ];
    }
}
