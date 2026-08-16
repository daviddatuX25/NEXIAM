<?php

namespace App\Http\Controllers\Admission;

use App\Admission\Support\RecordThreadService;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Consultation\Queries\ConsultationAdmissionSupportQuery;
use App\Http\Controllers\Controller;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Foundation\Program;
use App\ProgramHead\Queries\ProgramHeadApplicantQueries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Shared per-applicant admission-support workspace (founder 2026-08-15).
 *
 * ONE inner page per applicant, consumed by Guidance (full counseling
 * workspace) and Program Head (assigned-program record + scoped threads),
 * with the two reserved model-visualization slots. Registrar is NOT here —
 * it keeps the dedicated eligibility page for status checks and stamps.
 */
class AdmissionSupportController extends Controller
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly ConsultationAdmissionSupportQuery $support,
        private readonly ProgramHeadApplicantQueries $phQueries,
        private readonly RecordThreadService $threads,
    ) {
    }

    public function show(Request $request, AdmissionAttempt $attempt): Response
    {
        $user = $request->user();

        if ($this->authorizer->allows($user, CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE)
            || $this->authorizer->allows($user, CapabilityCatalog::GUIDANCE_SELECTION_MANAGE)) {
            return $this->render($attempt, 'guidance');
        }

        $program = $this->phProgramFor($attempt, $user);

        if ($program !== null) {
            return $this->render($attempt, 'program_head', $program);
        }

        return Inertia::render('Admission/ApplicantAdmissionSupport', [
            'page_state' => 'denied',
            'role' => null,
            'attempt' => null,
        ]);
    }

    public function supportJson(Request $request, AdmissionAttempt $attempt): JsonResponse
    {
        if (! $this->authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE)
            && ! $this->authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_SELECTION_MANAGE)) {
            return response()->json(['page_state' => 'denied']);
        }

        return response()->json(['support' => $this->support->for($attempt)]);
    }

    private function render(AdmissionAttempt $attempt, string $role, ?Program $program = null): Response
    {
        $support = $this->support->for($attempt);

        $props = [
            'page_state' => 'ready',
            'role' => $role,
            'attempt' => [
                'id' => $attempt->id,
                'reference_code' => $attempt->reference_code,
                'name' => $attempt->identity
                    ? trim($attempt->identity->first_name.' '.$attempt->identity->last_name)
                    : $attempt->reference_code,
            ],
            'support' => $support,
            'ph_record' => null,
            'ph_program' => null,
            'threads' => [],
        ];

        if ($role === 'program_head' && $program !== null) {
            try {
                $props['ph_record'] = $this->phQueries->protectedRecord($attempt, $program, request()->user());
            } catch (RuntimeException) {
                $props['ph_record'] = $this->phQueries->summary($attempt, $program);
            }

            $props['ph_program'] = ['code' => $program->code, 'name' => $program->name];
            $props['threads'] = array_map(fn ($thread) => [
                'id' => $thread->id,
                'subject' => $thread->subject,
                'status' => $thread->status,
                'messages' => array_map(fn ($message) => [
                    'id' => $message->id,
                    'sender' => $message->sender?->name,
                    'body' => $message->body,
                    'created_at' => self::isoOrNull($message->created_at),
                ], $this->threads->messagesFor($thread)),
            ], $this->threads->forAttempt($attempt, 'internal_ph_guidance'));
        }

        return Inertia::render('Admission/ApplicantAdmissionSupport', $props);
    }

    private function phProgramFor(AdmissionAttempt $attempt, $user): ?Program
    {
        if (! $this->authorizer->allows($user, CapabilityCatalog::PROGRAM_HEAD_VIEW)) {
            return null;
        }

        $programCodes = $user->actorScope()->programCodes;

        if ($programCodes === []) {
            return null;
        }

        // The attempt must actually relate to one of the PH's programs.
        $poolCodes = $attempt->programPools()->with('options.program')->get()
            ->flatMap(fn ($pool) => $pool->options->pluck('program.code'))
            ->unique()
            ->all();

        $relatable = array_values(array_intersect($poolCodes, $programCodes));

        $code = $relatable[0] ?? null;

        return $code === null ? null : Program::query()->where('code', $code)->where('is_active', true)->first();
    }

    private static function isoOrNull(mixed $value): ?string
    {
        return $value === null ? null : \Illuminate\Support\Carbon::parse($value)->toIso8601String();
    }
}
