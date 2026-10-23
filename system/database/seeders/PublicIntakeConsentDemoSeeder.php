<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Authorization\CapabilityCatalog;
use App\Communications\Actions\DesignateIntakePublicPolicy;
use App\Communications\Actions\PublishPublicPolicy;
use App\Communications\Actions\SavePublicPolicy;
use App\Communications\PublicPolicySourceAuthorizer;
use App\Models\Communications\PublicPolicy;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class PublicIntakeConsentDemoSeeder extends Seeder
{
    public const TITLE = 'Institutional Admissions Privacy Notice & Applicant Consent Agreement';

    public const CONTENT = <<<'POLICY'
In accordance with Republic Act No. 10173, also known as the Data Privacy Act of 2012 (DPA of 2012), its Implementing Rules and Regulations (IRR), and relevant issuances of the National Privacy Commission (NPC), this Admissions Privacy Notice sets forth the policies and practices of the Institution regarding the collection, use, processing, storage, disclosure, and protection of personal and sensitive personal information submitted by applicants seeking admission.

1. STATUTORY MANDATE & LEGAL BASIS

The processing of applicant personal data is necessary for the performance of institutional mandates, academic admissions assessments, fulfillment of regulatory compliance with the Commission on Higher Education (CHED), and implementation of statutory student assistance programs pursuant to Republic Act No. 10931 (Universal Access to Quality Tertiary Education Act). Personal data is processed on the lawful bases of:
• Explicit consent freely given by the applicant upon submission of the application.
• Preparation for entering into an academic enrollment contract between the applicant and the Institution.
• Compliance with legal and statutory reporting obligations established by law and regulatory agencies.
• Legitimate educational and administrative interests pursued by the Institution in evaluating candidate eligibility, aptitude, and course placement.

2. SCOPE & CATEGORIES OF PERSONAL DATA COLLECTED

The Institution collects and processes the following personal information and sensitive personal information:
• Individual Identity Data: Legal full name, maiden name (if applicable), sex, date of birth, place of birth, nationality, civil status, and government or student identification numbers.
• Contact & Geographical Information: Current residence, permanent residential address, official email address, mobile telephone number, and emergency contact details.
• Prior Academic Records & Qualifications: Previous educational institutions attended, Senior High School academic track and strand, Learner Reference Number (LRN), Year of Graduation, General Weighted Average (GWA), and official subject grade marks.
• Standardized Assessment & Examination Results: Institutional College Aptitude Test (ICAT) raw and scaled scores, normative percentile ranks, aptitude profile domains, proctor logs, and examination seating allocations.
• Health, Accommodation & Vulnerability Data: Physical health disclosures, specialized medical certificates, or disability documentation voluntarily provided solely for the purpose of granting testing accommodations.
• Identity Verification & Authentication Artifacts: Digital or printed applicant photographic likeness, system access keys, hashed admission attempt tokens, and IP connection access logs.

3. PURPOSES OF DATA PROCESSING

Personal data collected through this application intake portal shall be used exclusively for:
• Validating candidate identity, pre-requisite eligibility, and compliance with statutory admission criteria.
• Scheduling examination dates, testing venues, proctor assignments, and consultation encounters.
• Evaluating cognitive aptitude and academic potential through normalized multi-factor assessment scoring.
• Generating official examination rosters, room assignment masterlists, and admission clearance certificates.
• Official communications concerning application verification, scheduled appointments, examination results, and clearance deadlines.
• Institutional research, demographic reporting, and accreditation reviews using aggregated, non-identifiable statistical datasets.

4. PHYSICAL RECORD VERIFICATION & AUTHENTICITY

Online electronic submission represents an initial intent to apply and does not constitute final verification or guaranteed enrollment:
• Academic grades, graduation credentials, and identity claims entered into this system must be verified against original physical Form 138 (Report Card) or Official Transcript of Records (TOR) at the Office of the Registrar.
• Any willful misrepresentation, falsification, tampering, or fraudulent omission of academic marks, disciplinary status, or identity discovered during verification shall result in immediate disqualification and forfeiture of admission eligibility.
• The Institution reserves the right to authenticate credentials directly with originating secondary or tertiary institutions and government examination repositories.

5. THIRD PARTY DISCLOSURES & RECIPIENTS

Applicant information is treated with strict confidentiality and will not be shared with external third parties without prior written consent, except in the following mandatory instances:
• Commission on Higher Education (CHED) and UniFAST for tertiary education subsidies, statutory enrollment audits, and educational registry maintenance.
• Legal authorities and law enforcement bodies pursuant to valid subpoenas, court orders, or statutory legal processes.
• Accrediting agencies evaluating degree programs and institutional performance benchmarks on a confidential, aggregate basis.
• The Institution does not sell, lease, monetize, or disclose applicant personal data to commercial vendors, marketing entities, or unauthorized external parties.

6. DATA RETENTION & SAFE DISPOSAL SCHEDULE

Personal information is retained only as long as necessary to fulfill admissions evaluation, regulatory reporting, and institutional audit obligations:
• Matriculating Applicants: For applicants who are accepted and successfully complete enrollment clearance, admissions dossiers transition into permanent student scholastic records governed by the National Archives of the Philippines.
• Non-Matriculating Applicants: Records of applicants who withdraw, are not admitted, or do not proceed with enrollment are retained in secure institutional digital storage for a period of two (2) academic calendar years following the close of the admission cycle.
• Upon expiration of the designated retention cycle, digital files are irreversibly sanitized and paper artifacts shredded in accordance with NPC guidelines.

7. DATA SUBJECT RIGHTS UNDER THE DATA PRIVACY ACT

Under Section 16 of Republic Act No. 10173, every applicant possesses the following rights as a data subject:
• Right to be Informed: The right to be informed whether personal data pertaining to them is being or has been processed.
• Right to Access: The right to reasonable access, upon written request, to their personal information and admission examination outcome records.
• Right to Rectification: The right to dispute any inaccuracy or error in their personal data and have the Institution correct it promptly prior to enrollment finalization.
• Right to Erasure or Blocking: The right to suspend, withdraw, or order the removal of personal data from institutional records upon legitimate grounds, subject to statutory retention limits.
• Right to Damages: The right to be indemnified for any damages sustained due to inaccurate, false, unlawfully obtained, or unauthorized processing of personal information.

8. INSTITUTIONAL DATA PROTECTION OFFICER & INQUIRIES

To exercise any statutory data privacy rights, file a concern, or request clarification regarding the handling of your personal data, you may reach out to:
• Official Communications Email: dpo@nexiam.edu.ph or registrar@nexiam.edu.ph.
• Applicants may also register formal complaints or inquiries with the National Privacy Commission (NPC) at https://privacy.gov.ph.
POLICY;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $institution = Institution::query()->where('code', 'SYN-INST-001')->first();
        if ($institution === null) {
            return;
        }

        $institutionId = (int) $institution->getKey();
        $designated = PublicPolicy::designatedIntake($institutionId);
        if ($designated !== null) {
            $needsUpdate = $designated->content !== self::CONTENT || $designated->title !== self::TITLE;

            if (! $needsUpdate) {
                return;
            }

            $actor = User::query()->where('email', 'registrar.clerk@nexiam.test')->first();
            if ($actor !== null && $actor->actorScope()->office === 'registrar' && $actor->actorScope()->institutionId === $institutionId) {
                $this->ensurePermissions();
                DB::transaction(function () use ($actor, $designated): void {
                    $saved = app(SavePublicPolicy::class)->handle($actor, [
                        'policy_id' => (int) $designated->getKey(),
                        'kind' => 'privacy',
                        'title' => self::TITLE,
                        'content' => self::CONTENT,
                        'expected_revision' => (int) $designated->revision,
                        'request_id' => (string) Str::uuid(),
                    ]);
                    app(PublishPublicPolicy::class)->handle($actor, $saved, [
                        'request_id' => (string) Str::uuid(),
                        'expected_revision' => (int) $saved->revision,
                    ]);
                });
            }

            return;
        }

        $hasExistingDesignation = PublicPolicy::query()
            ->where('institution_id', $institutionId)
            ->where('is_designated_intake', true)
            ->exists();
        if ($hasExistingDesignation) {
            throw new RuntimeException('A designated public privacy policy exists but is not eligible; the demo seeder will not replace or repair it.');
        }

        $actor = User::query()->where('email', 'registrar.clerk@nexiam.test')->first();
        if ($actor === null || $actor->actorScope()->office !== 'registrar' || $actor->actorScope()->institutionId !== $institutionId) {
            throw new RuntimeException('The Registrar demo publisher is required to publish the local intake consent fixture.');
        }

        $this->ensurePermissions();

        $existingFixture = PublicPolicy::query()
            ->where('institution_id', $institutionId)
            ->where('publisher_key', 'office:registrar')
            ->whereNull('program_id')
            ->where('title', self::TITLE)
            ->exists();
        if ($existingFixture) {
            throw new RuntimeException('The synthetic intake privacy fixture exists without a valid designated publication; it will not be overwritten.');
        }

        $sources = app(PublicPolicySourceAuthorizer::class);
        if (! $sources->canManage($actor, 'office:registrar') || ! $sources->canPublish($actor, 'office:registrar')) {
            throw new RuntimeException('The Registrar demo publisher cannot manage and publish the local intake consent fixture.');
        }

        DB::transaction(function () use ($actor): void {
            $policy = app(SavePublicPolicy::class)->handle($actor, [
                'publisher_key' => 'office:registrar',
                'program_id' => null,
                'kind' => 'privacy',
                'title' => self::TITLE,
                'content' => self::CONTENT,
                'request_id' => 'b742d123-2a20-4b61-b5bb-000000000001',
            ]);
            $policy = app(PublishPublicPolicy::class)->handle($actor, $policy, [
                'request_id' => 'b742d123-2a20-4b61-b5bb-000000000002',
                'expected_revision' => (int) $policy->revision,
            ]);
            app(DesignateIntakePublicPolicy::class)->handle($actor, $policy, [
                'request_id' => 'b742d123-2a20-4b61-b5bb-000000000003',
                'expected_revision' => (int) $policy->revision,
                'expected_intake_policy_id' => null,
            ]);
        });
    }

    private function ensurePermissions(): void
    {
        $capabilities = [
            CapabilityCatalog::COMMUNICATIONS_POLICIES_MANAGE,
            CapabilityCatalog::COMMUNICATIONS_POLICIES_PUBLISH,
        ];
        foreach ($capabilities as $capability) {
            Permission::findOrCreate($capability, 'web');
        }
        foreach (CapabilityCatalog::roleHelperGrants() as $roleName => $roleCapabilities) {
            $grants = array_values(array_intersect($roleCapabilities, $capabilities));
            if ($grants !== []) {
                Role::findOrCreate($roleName, 'web')->givePermissionTo($grants);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
