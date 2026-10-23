<?php

namespace App\Admission\Actions;

use App\Admission\Support\ApplicantReference;
use App\Admission\Support\ApplicantReferenceResolver;
use App\Admission\Support\NormalizedIdentityKey;
use App\Admission\Support\ReferenceCodeGenerator;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\User;
use App\Testing\PracticeWorkspace;
use Carbon\Carbon;
use RuntimeException;

final class CreateAdmissionAttempt
{
    public function __construct(
        private readonly ReferenceCodeGenerator $referenceCodeGenerator,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(
        Institution $institution,
        AcademicCycle $cycle,
        ?ApplicationWindow $window,
        array $payload,
        User $actor,
    ): AdmissionAttempt {
        $firstName = (string) ($payload['first_name'] ?? '');
        $lastName = (string) ($payload['last_name'] ?? '');
        $middleName = filled($payload['middle_name'] ?? null) ? (string) $payload['middle_name'] : null;
        $birthdate = Carbon::parse($payload['birthdate'] ?? null)->format('Y-m-d');
        $sex = strtolower((string) ($payload['sex'] ?? ''));

        if (! in_array($sex, ['male', 'female'], true)) {
            throw new RuntimeException('Sex must be male or female.');
        }

        $programCode = (string) ($payload['course_preference_1'] ?? '');
        $programCodes = array_values(array_filter([
            $programCode,
            $payload['course_preference_2'] ?? null,
            $payload['course_preference_3'] ?? null,
        ], static fn (mixed $code): bool => filled($code)));
        $offeredProgramCount = Program::query()
            ->where('institution_id', $institution->id)
            ->whereIn('code', $programCodes)
            ->where('is_active', true)
            ->where('is_admission_offered', true)
            ->count();
        if ($programCode === '' || $offeredProgramCount !== count(array_unique($programCodes))) {
            throw new RuntimeException('Every program preference must be offered for admission at this institution.');
        }

        $workspace = app(PracticeWorkspace::class);
        $workspace->assertWritable();

        $identity = ApplicantIdentity::query()->firstOrCreate(
            [
                'institution_id' => $institution->id,
                'normalized_key' => $workspace->normalizedKey(NormalizedIdentityKey::make($firstName, $lastName, $birthdate, $sex, $middleName)),
            ],
            [
                'first_name' => $firstName,
                'middle_name' => $middleName,
                'last_name' => $lastName,
                'suffix' => filled($payload['suffix'] ?? null) ? (string) $payload['suffix'] : null,
                'birthdate' => $birthdate,
                'sex' => $sex,
                'email' => filled($payload['contact_email'] ?? null) ? (string) $payload['contact_email'] : null,
                'phone' => filled($payload['contact_phone'] ?? null) ? (string) $payload['contact_phone'] : null,
            ],
        );

        $referenceCode = filled($payload['applicant_number'] ?? null) ? trim((string) $payload['applicant_number']) : null;
        if ($referenceCode !== null) {
            $sequence = ApplicantReference::extractSequence($referenceCode, $cycle);
            if (ctype_digit($referenceCode) && $sequence !== null) {
                $referenceCode = ApplicantReference::format($cycle, $sequence);
            } else {
                $referenceCode = ApplicantReferenceResolver::normalizeForCycle($cycle, $referenceCode, (int) $institution->id);
            }
        }
        if ($referenceCode === null || AdmissionAttempt::query()->where('reference_code', $referenceCode)->exists()) {
            $referenceCode = $this->referenceCodeGenerator->next($cycle);
        }

        $attempt = AdmissionAttempt::query()->create([
            'applicant_identity_id' => $identity->id,
            'institution_id' => $institution->id,
            'academic_cycle_id' => $cycle->id,
            'application_window_id' => $window?->id,
            'reference_code' => $referenceCode,
            'status' => AdmissionAttempt::STATUS_ACCEPTED,
            'preferred_program_code' => $programCode,
            'program_preference_1_code' => $programCode,
            'program_preference_2_code' => filled($payload['course_preference_2'] ?? null) ? (string) $payload['course_preference_2'] : null,
            'program_preference_3_code' => filled($payload['course_preference_3'] ?? null) ? (string) $payload['course_preference_3'] : null,
            'applicant_type' => filled($payload['applicant_type'] ?? null) ? (string) $payload['applicant_type'] : 'new',
            'last_school_enrolled' => $payload['last_school_enrolled'] ?? null,
            'strand' => $payload['strand'] ?? null,
            'gwa' => null,
            'contact_email' => filled($payload['contact_email'] ?? null) ? (string) $payload['contact_email'] : null,
            'contact_phone' => filled($payload['contact_phone'] ?? null) ? (string) $payload['contact_phone'] : null,
            'address_line' => $payload['address_line'] ?? null,
            'city' => $payload['city'] ?? null,
            'province' => $payload['province'] ?? null,
            'zip_code' => $payload['zip_code'] ?? null,
            'payload' => $payload,
            'version' => 1,
            'accepted_at' => now(),
        ]);
        app(EnsureAttemptRequirementChecklist::class)->handle($attempt, $actor);

        $this->recordAuditEvent->handle('admission.attempt_imported', [
            'attempt_id' => $attempt->id,
            'reference_code' => $referenceCode,
            'identity_id' => $identity->id,
        ], (string) $actor->id);

        return $attempt;
    }
}
