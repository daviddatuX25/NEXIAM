<?php

declare(strict_types=1);

namespace App\Http\Controllers\Consultation;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Consultation\Queries\ConsultationAdmissionSupportQuery;
use App\Consultation\Services\JitsiMediaAdapter;
use App\Http\Controllers\Controller;
use App\Models\Consultation\ConsultationEncounter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ConsultationEncounterController extends Controller
{
    public function show(
        Request $request,
        ConsultationEncounter $encounter,
        JitsiMediaAdapter $media,
        CapabilityAuthorizer $authorizer,
        ConsultationAdmissionSupportQuery $supportQuery,
    ): Response {
        $user = $request->user();
        abort_unless($user !== null && $encounter->medium === ConsultationEncounter::MEDIUM_ONLINE, 404);
        abort_unless(in_array($encounter->status, [
            ConsultationEncounter::STATUS_INVITED,
            ConsultationEncounter::STATUS_WAITING,
            ConsultationEncounter::STATUS_IN_PROGRESS,
        ], true), 404);
        abort_unless($media->accessWindowEnd($encounter) !== null, 403);
        abort_unless($media->isParticipantAuthorized($encounter, $user), 403);

        $isCounselor = (int) $encounter->counselor_user_id === (int) $user->getKey();
        if ($isCounselor) {
            abort_unless($authorizer->allows($user, CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE), 403);
        }

        $encounter->loadMissing('admissionAttempt.identity');
        $attempt = $encounter->admissionAttempt;
        abort_unless($attempt !== null, 404);

        $name = trim((string) ($attempt->identity?->first_name ?? '').' '.(string) ($attempt->identity?->last_name ?? ''));
        $decisionUrl = route('admission-support.show', ['attempt' => $attempt->getKey(), 'encounter_id' => $encounter->getKey()]);
        $backUrl = $isCounselor ? route('consultation.index') : route('applicant.portal');

        return Inertia::render('Consultation/OnlineEncounter', [
            'encounter' => [
                'id' => $encounter->getKey(),
                'status' => $encounter->status,
                'status_label' => str($encounter->status)->replace('_', ' ')->title()->toString(),
            ],
            'applicant' => [
                'attempt_id' => (int) $attempt->id,
                'name' => $name !== '' ? $name : $attempt->reference_code,
                'reference_code' => $attempt->reference_code,
            ],
            'participant_name' => $isCounselor ? (string) $user->name : $name,
            'score_summary' => $isCounselor ? $supportQuery->for($attempt)['score_profile'] : null,
            'can_control' => $isCounselor,
            'decision_url' => $decisionUrl,
            'back_url' => $backUrl,
        ]);
    }
}
