<?php

namespace App\Http\Controllers\Applicant;

use App\Admission\Support\ApplicantScope;
use App\Admission\Support\AttemptProgramFacts;
use App\Applicant\Support\ApplicantResultAccess;
use App\Consultation\Support\AttemptConsultationStatus;
use App\Http\Controllers\Controller;
use App\Models\Admission\AdmissionAttempt;
use App\Release\Actions\ReleaseResult;
use App\Release\Support\ResultDocumentPdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ApplicantResultController extends Controller
{
    public function show(
        Request $request,
        ApplicantResultAccess $access,
    ): Response {
        $identityId = ApplicantScope::identityIdForUser($request->user());
        $attempt = $identityId === null
            ? null
            : AdmissionAttempt::query()
                ->where('applicant_identity_id', $identityId)
                ->latest('id')
                ->first();

        if ($attempt === null) {
            return Inertia::render('Applicant/Results', [
                'page_state' => 'denied',
                'release' => null,
                'score_profile' => null,
                'can_download_pdf' => false,
                'is_advised' => false,
                'attempt' => null,
            ]);
        }

        $facts = $access->forAttempt($attempt);
        $release = $facts['release'];
        $isAdvised = AttemptConsultationStatus::isAdvised($attempt);
        $pageState = match (true) {
            $facts['available'] => 'unlocked',
            $facts['pending'] => 'preparing_pdf',
            $facts['retracted'] || $facts['artifact_failed'] => 'unavailable',
            $facts['completed_f2f'] && ! $isAdvised => 'unavailable',
            $release?->mode === ReleaseResult::MODE_F2F && ! $isAdvised => 'held_f2f',
            $release !== null || AttemptProgramFacts::isResultReleased($attempt) => 'unavailable',
            default => 'not_ready',
        };
        $scoreProfile = $pageState === 'unlocked'
            ? array_map(
                static fn (array $score): array => [
                    'area_code' => is_scalar($score['area_code'] ?? null) ? (string) $score['area_code'] : '',
                    'area_name' => is_scalar($score['domain'] ?? null) ? (string) $score['domain'] : '',
                    'score' => is_scalar($score['converted'] ?? null) ? $score['converted'] : null,
                    'scale' => ($score['scale'] ?? null) === 'percentile_rank' ? 'percentile_rank' : null,
                    'available' => is_numeric($score['converted'] ?? null),
                ],
                is_array($facts['document']->snapshot_payload_json['scores'] ?? null)
                    ? $facts['document']->snapshot_payload_json['scores']
                    : [],
            )
            : null;

        return Inertia::render('Applicant/Results', [
            'page_state' => $pageState,
            'release' => $release === null ? null : [
                'mode' => $release->mode,
                'release_day_copy' => $release->consultation_decision_id !== null,
                'released_at' => $release->released_at?->toIso8601String(),
                'version' => $release->release_version,
            ],
            'score_profile' => $scoreProfile,
            'can_download_pdf' => $facts['available'],
            'is_advised' => $isAdvised,
            'attempt' => $attempt->reference_code,
        ]);
    }

    public function downloadPdf(
        Request $request,
        ApplicantResultAccess $access,
        ResultDocumentPdf $pdf,
    ): HttpResponse {
        $presentation = $request->validate([
            'presentation' => ['sometimes', 'in:inline,attachment'],
        ])['presentation'] ?? 'attachment';

        $identityId = ApplicantScope::identityIdForUser($request->user());
        $attempt = $identityId === null
            ? null
            : AdmissionAttempt::query()
                ->where('applicant_identity_id', $identityId)
                ->latest('id')
                ->first();
        abort_if($attempt === null, 403);

        $facts = $access->forAttempt($attempt, verifyArtifact: false);
        abort_unless(
            $facts['available'] && $facts['document'] !== null,
            403,
            'The official result PDF is not currently available.',
        );

        try {
            $pdfBytes = $pdf->read($facts['document']);
        } catch (RuntimeException) {
            abort(404, 'The official result PDF is unavailable.');
        }

        $disposition = $presentation === 'inline' ? 'inline' : 'attachment';

        return response($pdfBytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="result-sheet-'.$attempt->reference_code.'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
