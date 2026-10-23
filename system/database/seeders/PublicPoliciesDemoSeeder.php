<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Authorization\CapabilityCatalog;
use App\Communications\Actions\PublishPublicPolicy;
use App\Communications\Actions\SavePublicPolicy;
use App\Communications\PublicPolicySourceAuthorizer;
use App\Models\Communications\PublicPolicy;
use App\Models\Communications\PublicPolicyCommandReceipt;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class PublicPoliciesDemoSeeder extends Seeder
{
    public const TITLE_REGISTRAR_CREDENTIALS = 'Academic Credential Verification & Enrollment Documentation Policy';

    public const LEGACY_TITLE_REGISTRAR_CREDENTIALS = '[DEMO] Registrar records guidance';

    public const CONTENT_REGISTRAR_CREDENTIALS = <<<'POLICY'
Pursuant to the General Academic Policies and Procedures of the Ilocos Sur Polytechnic State College (ISPSC), the standards of the Commission on Higher Education (CHED), and Republic Act No. 10931:

1. MANDATORY CREDENTIAL SUBMISSION
All qualified applicants seeking enrollment confirmation must submit authentic physical copies of standard academic credentials to the Office of the Campus Registrar:
• Original Senior High School Report Card (DepEd Form 138 / SF-9) with graduation eligibility;
• Certificate of Good Moral Character from the originating school principal or guidance counselor;
• Philippine Statistics Authority (PSA) Authenticated Birth Certificate (original and clear photocopies);
• Recent 2x2 color ID photographs on white background with printed name tag.

2. IN-BOUND TRANSFEREES & NON-TRADITIONAL APPLICANTS
Transferees must present an official Certificate of Transfer Credentials (Honorable Dismissal) and an authenticated Transcript of Records or Certificate of Final Grades. ALS qualifiers must submit an official DepEd Certificate of Rating indicating eligibility for tertiary education.

3. AUTHENTICITY, FALSIFICATION & DISQUALIFICATION
All physical credentials submitted are subject to direct verification with originating institutions and testing authorities. Any willful misrepresentation, alteration, or fraudulent omission shall result in immediate forfeiture of admission eligibility and disciplinary referral.

4. CONDITIONAL ENROLLMENT UNDERTAKING
Applicants whose credentials are held by their secondary school due to administrative clearance processing may be granted conditional enrollment upon executing an Undertaking with the Registrar specifying an absolute compliance deadline.
POLICY;

    public const TITLE_GUIDANCE_ETHICS = 'Guidance Counseling Confidentiality & Aptitude Testing Ethics Notice';

    public const LEGACY_TITLE_GUIDANCE_ETHICS = '[DEMO] Guidance public guidance';

    public const CONTENT_GUIDANCE_ETHICS = <<<'POLICY'
In accordance with Republic Act No. 9258 (Guidance and Counseling Act of 2004), Republic Act No. 10173 (Data Privacy Act of 2012), and the Code of Ethics for Registered Guidance Counselors:

1. PRIVILEGED NATURE OF COUNSELING RECORDS
All personal disclosures, interview notes, individualized counseling records, and consultation encounters conducted through the Office of Guidance and Counseling Services constitute confidential, privileged communications. Counseling records are maintained strictly separate from academic transcripts and registrar scholastic files.

2. APTITUDE TESTING & PSYCHOMETRIC INTEGRITY
Aptitude area percentile ranks, standardized examination scores, and career fit assessments are utilized solely for diagnostic evaluation, course placement, and developmental guidance. Results are accessible only to the applicant, designated guidance personnel, and authorized college academic screening committees.

3. EXCEPTIONS TO CONFIDENTIALITY
Information gathered during guidance sessions will not be disclosed to parents, faculty, or external parties without explicit written consent, except in emergency cases involving imminent threat to the life, safety, or physical well-being of the examinee or others, or pursuant to statutory court mandate.

4. APPLICANT ADVISING RIGHTS
Every applicant who completes the ICAT is entitled to schedule an academic counseling consultation to review their aptitude profile, explore program options, and discuss academic development opportunities.
POLICY;

    public const TITLE_GUIDANCE_EXAM = 'ISPSC College Admission Test (ICAT) Examination Protocols & Code of Conduct';

    public const LEGACY_TITLE_GUIDANCE_EXAM = '[DEMO] Guidance policy list revision workflow';

    public const CONTENT_GUIDANCE_EXAM = <<<'POLICY'
The Office of Guidance and Counseling Services administers the ISPSC College Admission Test (ICAT) to evaluate cognitive aptitude and degree-program readiness across all academic cycles.

1. TEST DAY REPORTING & ADMISSION
• Examinees must arrive at their assigned testing venue at least thirty (30) minutes before the scheduled examination start time.
• Every examinee must present an official Notice of Admission (NOA) / Examination Permit and a valid government, school, or student photo identification card. Examinees without verifiable identification cannot be admitted.

2. AUTHORIZED & PROHIBITED MATERIALS
• Examinees must bring lead pencils (No. 2), black ink pens, and a pencil eraser.
• Mobile phones, smartwatches, programmable calculators, tablets, and electronic recording devices are strictly prohibited inside testing rooms. All electronic devices must be powered off and deposited with proctors prior to test administration.

3. TESTING ROOM INTEGRITY & DISQUALIFICATION
• Strict silence must be observed during the entire examination period.
• Opening test booklets before instructed, communicating with other examinees, copying answers, or leaving the examination venue without authorization constitutes examination dishonesty and will result in immediate paper cancellation.

4. SPECIAL TESTING ACCOMMODATIONS
Examinees with documented physical disabilities, chronic health conditions, or specialized needs should contact the Guidance Office at least three (3) working days prior to their scheduled testing session for room and proctor accommodations.
POLICY;

    public const CONTENT_GUIDANCE_EXAM_SUCCESSOR = <<<'POLICY'
The Office of Guidance and Counseling Services administers the ISPSC College Admission Test (ICAT) to evaluate cognitive aptitude and degree-program readiness across all academic cycles.

1. TEST DAY REPORTING & ADMISSION
• Examinees must arrive at their assigned testing venue at least thirty (30) minutes before the scheduled examination start time.
• Every examinee must present an official Notice of Admission (NOA) / Examination Permit and a valid government, school, or student photo identification card. Examinees without verifiable identification cannot be admitted.

2. AUTHORIZED & PROHIBITED MATERIALS
• Examinees must bring lead pencils (No. 2), black ink pens, and a pencil eraser.
• Mobile phones, smartwatches, programmable calculators, tablets, and electronic recording devices are strictly prohibited inside testing rooms. All electronic devices must be powered off and deposited with proctors prior to test administration.

3. TESTING ROOM INTEGRITY & DISQUALIFICATION
• Strict silence must be observed during the entire examination period.
• Opening test booklets before instructed, communicating with other examinees, copying answers, or leaving the examination venue without authorization constitutes examination dishonesty and will result in immediate paper cancellation.

4. SPECIAL TESTING ACCOMMODATIONS
Examinees with documented physical disabilities, chronic health conditions, or specialized needs should contact the Guidance Office at least three (3) working days prior to their scheduled testing session for room and proctor accommodations.

5. POST-EXAMINATION ADVISING & SCORE RELEASE
Examination scores and aptitude profiles are processed under psychometric standards and released through the official NEXIAM portal following the Institutional Academic Calendar.
POLICY;

    public const TITLE_BSIT_LABORATORY = 'BSIT Program Academic & Laboratory Policy';

    public const LEGACY_TITLE_BSIT_LABORATORY = '[DEMO] BSN student support information';

    public const CONTENT_BSIT_LABORATORY = <<<'POLICY'
The College of Computing and Information Technology (CCIT) establishes the following academic guidelines and laboratory regulations for the Bachelor of Science in Information Technology (BSIT) program:

1. COMPUTING LABORATORY CODE OF CONDUCT
Students enrolled in IT courses must observe responsible use of university workstation hardware, network infrastructure, and licensed software. Unauthorized installation of software, modification of system settings, or tampering with computer equipment is strictly prohibited.

2. CAPSTONE PROJECT & PRACTICUM REQUIREMENTS
BSIT candidates must complete an approved capstone project solving authentic institutional or community technical needs, followed by mandatory industry on-the-job training (OJT) under formal memorandums of agreement.

3. ACADEMIC INTEGRITY IN PROGRAMMING ASSIGNMENTS
All source code, design artifacts, and technical documentation submitted for coursework must be the student's authentic original work. Plagiarism of code repositories or unacknowledged use of external code assets constitutes academic dishonesty.
POLICY;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing', 'production'])) {
            throw new RuntimeException('Public policy content is restricted to local, testing, and production environments.');
        }
        $policyCapabilities = [
            CapabilityCatalog::COMMUNICATIONS_POLICIES_MANAGE,
            CapabilityCatalog::COMMUNICATIONS_POLICIES_PUBLISH,
        ];
        foreach ($policyCapabilities as $capability) {
            Permission::findOrCreate($capability, 'web');
        }
        foreach (CapabilityCatalog::roleHelperGrants() as $roleName => $capabilities) {
            $grants = array_values(array_intersect($capabilities, $policyCapabilities));
            if ($grants !== []) {
                Role::findOrCreate($roleName, 'web')->givePermissionTo($grants);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $institution = Institution::query()->where('code', 'SYN-INST-001')->first();
        if ($institution === null) {
            return;
        }

        $guidanceActor = $this->officeActor('guidance', 'guidance.clerk@nexiam.test');
        if ($guidanceActor !== null) {
            $this->publishIfMissing(
                $guidanceActor,
                'office:guidance',
                null,
                self::TITLE_GUIDANCE_ETHICS,
                self::CONTENT_GUIDANCE_ETHICS,
                'policy',
                '32f373fa-9845-4d1c-8a56-39222d110001',
                '32f373fa-9845-4d1c-8a56-39222d110002',
                self::LEGACY_TITLE_GUIDANCE_ETHICS,
            );
            $this->seedSavedSuccessor($guidanceActor);
        }

        $registrarActor = $this->officeActor('registrar', 'registrar.clerk@nexiam.test');
        if ($registrarActor !== null) {
            $this->publishIfMissing(
                $registrarActor,
                'office:registrar',
                null,
                self::TITLE_REGISTRAR_CREDENTIALS,
                self::CONTENT_REGISTRAR_CREDENTIALS,
                'policy',
                '32f373fa-9845-4d1c-8a56-39222d110003',
                '32f373fa-9845-4d1c-8a56-39222d110004',
                self::LEGACY_TITLE_REGISTRAR_CREDENTIALS,
            );
            $this->publishIfMissing(
                $registrarActor,
                'office:registrar',
                null,
                'Campus Navigation & Geolocation Privacy Notice',
                "Zero Server-Side Tracking Guarantee\n\nCampus navigation processes your device location and orientation only in your browser. These readings are not transmitted to NEXIAM servers, logged, or stored. The server provides only the published room locations and related static information.\n\nStatic Target Geometry\n\nThe map targets are fixed room entrance coordinates published by the institution. They describe the venue entrance and do not track a person or change based on an applicant's movement.\n\nVoluntary Participation & Manual Fallback\n\nUsing geolocation is optional. You may decline or stop location access at any time and browse manually using the building name, floor, entrance photo, and written directions. Choosing manual browsing does not affect your application status, examination schedule, or admission results.\n\nNo Cross-Campus or External Location Linkage\n\nLocation readings are never combined with your applicant identity, examination attempt, or external analytics services. When you leave the campus navigation screen, all local sensor readings stop immediately.",
                'privacy',
                '32f373fa-9845-4d1c-8a56-39222d110020',
                '32f373fa-9845-4d1c-8a56-39222d110021',
            );
        }

        $program = Program::query()->where('institution_id', $institution->id)->where('code', 'BSIT')->where('is_active', true)->first();
        if ($program !== null) {
            $academicActor = User::query()->whereHas('staffProfile', fn ($query) => $query->where('institution_id', $program->institution_id)->where('status', 'active')->whereNull('deactivated_at'))
                ->get()->first(function (User $user) use ($program): bool {
                    $scope = $user->actorScope();

                    return $scope->office === 'program' && in_array($program->code, $scope->programCodes, true);
                });
            if ($academicActor !== null) {
                $this->publishIfMissing(
                    $academicActor,
                    'program:'.$program->code,
                    (int) $program->id,
                    self::TITLE_BSIT_LABORATORY,
                    self::CONTENT_BSIT_LABORATORY,
                    'policy',
                    '32f373fa-9845-4d1c-8a56-39222d110005',
                    '32f373fa-9845-4d1c-8a56-39222d110006',
                    self::LEGACY_TITLE_BSIT_LABORATORY,
                );
            }
        }
    }

    private function officeActor(string $office, string $email): ?User
    {
        $actor = User::query()->where('email', $email)->first();
        $scope = $actor?->actorScope();
        if ($scope === null || $scope->office !== $office || $scope->institutionId !== (int) Institution::current()->getKey()) {
            return null;
        }

        return $actor;
    }

    private function publishIfMissing(
        User $actor,
        string $publisherKey,
        ?int $programId,
        string $title,
        string $content,
        string $kind = 'policy',
        ?string $saveId = null,
        ?string $publishId = null,
        ?string $legacyTitle = null,
    ): void {
        $scope = $actor->actorScope();
        $sources = app(PublicPolicySourceAuthorizer::class);
        if ($scope->institutionId === null
            || ! $sources->canManage($actor, $publisherKey)
            || ! $sources->canPublish($actor, $publisherKey)) {
            return;
        }
        [$defaultSaveId, $defaultPublishId] = match ($publisherKey) {
            'office:guidance' => ['32f373fa-9845-4d1c-8a56-39222d110001', '32f373fa-9845-4d1c-8a56-39222d110002'],
            'office:registrar' => ['32f373fa-9845-4d1c-8a56-39222d110003', '32f373fa-9845-4d1c-8a56-39222d110004'],
            'program:BSIT' => ['32f373fa-9845-4d1c-8a56-39222d110005', '32f373fa-9845-4d1c-8a56-39222d110006'],
            default => [null, null],
        };
        $saveId ??= $defaultSaveId;
        $publishId ??= $defaultPublishId;

        if ($legacyTitle !== null) {
            $legacyPolicy = PublicPolicy::query()->where('institution_id', $scope->institutionId)
                ->where('publisher_key', $publisherKey)
                ->where('program_id', $programId)
                ->where('title', $legacyTitle)
                ->first();
            if ($legacyPolicy !== null) {
                $updateSaveId = '32f373fa-9845-4d1c-8a56-'.substr(md5($legacyTitle.'-save'), 0, 12);
                $updatePublishId = '32f373fa-9845-4d1c-8a56-'.substr(md5($legacyTitle.'-publish'), 0, 12);
                if (! PublicPolicyCommandReceipt::query()->where('actor_id', $actor->id)->where('request_id', $updateSaveId)->exists()) {
                    app(SavePublicPolicy::class)->handle($actor, [
                        'policy_id' => (int) $legacyPolicy->getKey(),
                        'expected_revision' => (int) $legacyPolicy->revision,
                        'request_id' => $updateSaveId,
                        'title' => $title,
                        'content' => $content,
                        'kind' => $kind,
                    ]);
                }
                $legacyPolicy->refresh();
                if (! PublicPolicyCommandReceipt::query()->where('actor_id', $actor->id)->where('request_id', $updatePublishId)->exists()) {
                    app(PublishPublicPolicy::class)->handle($actor, $legacyPolicy, [
                        'request_id' => $updatePublishId,
                        'expected_revision' => (int) $legacyPolicy->revision,
                    ]);
                }

                return;
            }
        }

        if ($saveId !== null && PublicPolicyCommandReceipt::query()->where('actor_id', $actor->id)->where('request_id', $saveId)->exists()) {
            return;
        }
        $existing = PublicPolicy::query()->where('institution_id', $scope->institutionId)->where('publisher_key', $publisherKey)
            ->where('program_id', $programId)->where('title', $title)->first();
        if ($existing !== null) {
            return;
        }

        $policy = app(SavePublicPolicy::class)->handle($actor, [
            'publisher_key' => $publisherKey, 'program_id' => $programId, 'kind' => $kind,
            'title' => $title, 'content' => $content, 'request_id' => $saveId,
        ]);
        app(PublishPublicPolicy::class)->handle($actor, $policy, [
            'request_id' => $publishId, 'expected_revision' => (int) $policy->revision,
        ]);
    }

    private function seedSavedSuccessor(User $actor): void
    {
        $publisherKey = 'office:guidance';
        $scope = $actor->actorScope();
        $sources = app(PublicPolicySourceAuthorizer::class);
        if ($scope->institutionId === null
            || ! $sources->canManage($actor, $publisherKey)
            || ! $sources->canPublish($actor, $publisherKey)) {
            return;
        }

        $title = self::TITLE_GUIDANCE_EXAM;
        $content = self::CONTENT_GUIDANCE_EXAM;
        $successorContent = self::CONTENT_GUIDANCE_EXAM_SUCCESSOR;
        $legacyTitle = self::LEGACY_TITLE_GUIDANCE_EXAM;

        $saveId = '32f373fa-9845-4d1c-8a56-39222d110007';
        $publishId = '32f373fa-9845-4d1c-8a56-39222d110008';
        $successorId = '32f373fa-9845-4d1c-8a56-39222d110009';

        $legacyPolicy = PublicPolicy::query()
            ->where('institution_id', $scope->institutionId)
            ->where('publisher_key', $publisherKey)
            ->whereNull('program_id')
            ->where('title', $legacyTitle)
            ->first();
        if ($legacyPolicy !== null) {
            $updateSaveId = '32f373fa-9845-4d1c-8a56-'.substr(md5($legacyTitle.'-save'), 0, 12);
            $updatePublishId = '32f373fa-9845-4d1c-8a56-'.substr(md5($legacyTitle.'-publish'), 0, 12);
            $updateSuccessorId = '32f373fa-9845-4d1c-8a56-'.substr(md5($legacyTitle.'-successor'), 0, 12);

            if (! PublicPolicyCommandReceipt::query()->where('actor_id', $actor->getKey())->where('request_id', $updateSaveId)->exists()) {
                app(SavePublicPolicy::class)->handle($actor, [
                    'policy_id' => (int) $legacyPolicy->getKey(),
                    'expected_revision' => (int) $legacyPolicy->revision,
                    'request_id' => $updateSaveId,
                    'title' => $title,
                    'content' => $content,
                ]);
            }
            $legacyPolicy->refresh();
            if (! PublicPolicyCommandReceipt::query()->where('actor_id', $actor->getKey())->where('request_id', $updatePublishId)->exists()) {
                app(PublishPublicPolicy::class)->handle($actor, $legacyPolicy, [
                    'request_id' => $updatePublishId,
                    'expected_revision' => (int) $legacyPolicy->revision,
                ]);
            }
            $legacyPolicy->refresh();
            if (! PublicPolicyCommandReceipt::query()->where('actor_id', $actor->getKey())->where('request_id', $updateSuccessorId)->exists()) {
                app(SavePublicPolicy::class)->handle($actor, [
                    'policy_id' => (int) $legacyPolicy->getKey(),
                    'expected_revision' => (int) $legacyPolicy->revision,
                    'request_id' => $updateSuccessorId,
                    'title' => $title,
                    'content' => $successorContent,
                ]);
            }

            return;
        }

        $policy = PublicPolicy::query()
            ->where('institution_id', $scope->institutionId)
            ->where('publisher_key', $publisherKey)
            ->whereNull('program_id')
            ->where('title', $title)
            ->first();
        $seedSaveExists = PublicPolicyCommandReceipt::query()
            ->where('actor_id', $actor->getKey())
            ->where('request_id', $saveId)
            ->exists();

        if (! $seedSaveExists) {
            if ($policy !== null) {
                return;
            }

            $policy = app(SavePublicPolicy::class)->handle($actor, [
                'publisher_key' => $publisherKey,
                'program_id' => null,
                'title' => $title,
                'content' => $content,
                'request_id' => $saveId,
            ]);
            $policy = app(PublishPublicPolicy::class)->handle($actor, $policy, [
                'request_id' => $publishId,
                'expected_revision' => (int) $policy->revision,
            ]);
        }

        if ($policy === null
            || PublicPolicyCommandReceipt::query()->where('actor_id', $actor->getKey())->where('request_id', $successorId)->exists()) {
            return;
        }
        $policy->loadMissing('publishedRevision');
        if ($policy->status !== 'published'
            || $policy->title !== $title
            || $policy->content !== $content
            || $policy->publishedRevision === null
            || (int) $policy->revision !== (int) $policy->publishedRevision->revision) {
            return;
        }

        app(SavePublicPolicy::class)->handle($actor, [
            'policy_id' => (int) $policy->getKey(),
            'expected_revision' => (int) $policy->revision,
            'request_id' => $successorId,
            'title' => $title,
            'content' => $successorContent,
        ]);
    }
}
