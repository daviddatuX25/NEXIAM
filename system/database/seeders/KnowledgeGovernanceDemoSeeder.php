<?php

namespace Database\Seeders;

use App\Ai\Knowledge\Actions\ApproveKnowledgeSourceVersion;
use App\Ai\Knowledge\Actions\AttachFaqPolicyDependency;
use App\Ai\Knowledge\Actions\CreateKnowledgeDraft;
use App\Ai\Knowledge\Actions\EstablishCrossOfficeKnowledgeScope;
use App\Ai\Knowledge\Actions\PublishKnowledgeSourceVersion;
use App\Ai\Knowledge\Actions\SignKnowledgeSourceConsensus;
use App\Ai\Knowledge\Actions\StartKnowledgeExtraction;
use App\Models\Ai\KnowledgeSource;
use App\Models\Communications\PublicPolicyRevision;
use App\Models\User;
use Illuminate\Database\Seeder;

class KnowledgeGovernanceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
        $head = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
        $adminLead = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
        $guidanceClerk = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();
        $guidanceHead = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
        $institutionId = $clerk->actorScope()->institutionId;

        $previousInline = config('xiam.process_inline');
        config()->set('xiam.process_inline', true);

        try {
            if (! KnowledgeSource::query()->where('institution_id', $institutionId)->where('key', 'synthetic-application-status-faq')->exists()) {
                $version = app(CreateKnowledgeDraft::class)->handle($clerk, [
                    'key' => 'synthetic-application-status-faq',
                    'title' => 'Synthetic applicant status FAQ',
                    'source_class' => KnowledgeSource::SOURCE_CLASS_PUBLIC_FAQ,
                    'audience' => KnowledgeSource::AUDIENCE_STAFF_AND_APPLICANT_INQUIRY,
                    'category' => KnowledgeSource::CATEGORY_ADMISSIONS,
                    'question' => 'Where can I check my application status in this demo?',
                    'answer' => 'Sign in to the applicant portal and open My Application to see the status currently shown by the system. Contact the Registrar for a correction.',
                ]);
                app(StartKnowledgeExtraction::class)->handle($clerk, $version->source, $version);
                app(ApproveKnowledgeSourceVersion::class)->handle($head, $version->source, $version->fresh());
                app(PublishKnowledgeSourceVersion::class)->handle($head, $version->source, $version->fresh());
            }
            $faq = KnowledgeSource::query()
                ->where('institution_id', $institutionId)
                ->where('key', 'synthetic-application-status-faq')
                ->first();
            if ($faq !== null) {
                $version = $faq->versions()->orderByDesc('version')->first();
                $revision = PublicPolicyRevision::query()
                    ->where('institution_id', $institutionId)
                    ->where('state', 'published')
                    ->whereHas('policy', fn ($query) => $query
                        ->where('status', 'published')
                        ->whereColumn('public_policies.published_revision_id', 'public_policy_revisions.id'))
                    ->latest('id')
                    ->first();
                if ($version !== null && $revision !== null) {
                    app(AttachFaqPolicyDependency::class)->handle($head, $version, $revision);
                }
            }

            if (! KnowledgeSource::query()->where('institution_id', $institutionId)->where('key', 'campus-health-and-visitor-protocol')->exists()) {
                $version = app(CreateKnowledgeDraft::class)->handle($adminLead, [
                    'key' => 'campus-health-and-visitor-protocol',
                    'title' => 'Campus health and visitor protocol',
                    'source_class' => KnowledgeSource::SOURCE_CLASS_PUBLIC_FAQ,
                    'audience' => KnowledgeSource::AUDIENCE_STAFF_AND_APPLICANT_INQUIRY,
                    'category' => KnowledgeSource::CATEGORY_CAMPUS,
                    'question' => 'What should visitors know before coming to campus?',
                    'answer' => 'Visitors should follow the campus health and safety instructions posted by the institution and check in at the designated campus reception point. Contact the Admin Office for current visitor arrangements.',
                ]);
                app(StartKnowledgeExtraction::class)->handle($adminLead, $version->source, $version);
            }

            if (! KnowledgeSource::query()->where('institution_id', $institutionId)->where('key', 'partner-school-waiver-and-testing-faq')->exists()) {
                $version = app(CreateKnowledgeDraft::class)->handle($clerk, [
                    'key' => 'partner-school-waiver-and-testing-faq',
                    'title' => 'Partner-school waiver and testing FAQ',
                    'source_class' => KnowledgeSource::SOURCE_CLASS_PUBLIC_FAQ,
                    'audience' => KnowledgeSource::AUDIENCE_STAFF_AND_APPLICANT_INQUIRY,
                    'question' => 'Can a partner-school applicant receive an entrance-test waiver?',
                    'answer' => 'Waiver eligibility and testing arrangements must be confirmed with both the Registrar and Admin Offices. This FAQ does not grant an automatic waiver; contact the Registrar for review of your individual circumstances.',
                ]);
                app(StartKnowledgeExtraction::class)->handle($clerk, $version->source, $version);
                app(SignKnowledgeSourceConsensus::class)->handle($adminLead, $version->source, $version->fresh(), ['admin', 'registrar']);
                app(SignKnowledgeSourceConsensus::class)->handle($head, $version->source, $version->fresh(), ['admin', 'registrar']);
                app(ApproveKnowledgeSourceVersion::class)->handle($head, $version->source, $version->fresh());
                app(PublishKnowledgeSourceVersion::class)->handle($head, $version->source, $version->fresh());
            }

            if (! KnowledgeSource::query()->where('institution_id', $institutionId)->where('key', 'synthetic-staff-readiness-faq')->exists()) {
                $version = app(CreateKnowledgeDraft::class)->handle($clerk, [
                    'key' => 'synthetic-staff-readiness-faq',
                    'title' => 'Synthetic staff readiness FAQ',
                    'source_class' => KnowledgeSource::SOURCE_CLASS_PUBLIC_FAQ,
                    'audience' => KnowledgeSource::AUDIENCE_STAFF_ONLY,
                    'question' => 'Where can staff inspect setup readiness?',
                    'answer' => 'Open the Setup dashboard and review the institutional setup checks for this synthetic demo.',
                ]);
                app(StartKnowledgeExtraction::class)->handle($clerk, $version->source, $version);
            }

            if (! KnowledgeSource::query()->where('institution_id', $institutionId)->where('key', 'synthetic-guidance-review-policy')->exists()) {
                $version = app(CreateKnowledgeDraft::class)->handle($guidanceClerk, [
                    'key' => 'synthetic-guidance-review-policy',
                    'title' => 'Synthetic Guidance review policy',
                    'source_class' => KnowledgeSource::SOURCE_CLASS_POLICY,
                    'audience' => KnowledgeSource::AUDIENCE_STAFF_ONLY,
                    'body_text' => 'Synthetic demo only: Guidance staff inspect setup readiness in the Setup dashboard before using this training environment. This is not an institutional policy for actual applicants.',
                ]);
                app(StartKnowledgeExtraction::class)->handle($guidanceClerk, $version->source, $version);
                app(ApproveKnowledgeSourceVersion::class)->handle($guidanceHead, $version->source, $version->fresh());
                app(PublishKnowledgeSourceVersion::class)->handle($guidanceHead, $version->source, $version->fresh());
            }

            if (! KnowledgeSource::query()->where('institution_id', $institutionId)->where('key', 'synthetic-cross-office-training-faq')->exists()) {
                $version = app(CreateKnowledgeDraft::class)->handle($guidanceClerk, [
                    'key' => 'synthetic-cross-office-training-faq',
                    'title' => 'Synthetic cross-office training FAQ',
                    'source_class' => KnowledgeSource::SOURCE_CLASS_PUBLIC_FAQ,
                    'audience' => KnowledgeSource::AUDIENCE_STAFF_ONLY,
                    'question' => 'How can staff inspect this synthetic FAQ review?',
                    'answer' => 'Open Knowledge, inspect the owning office and review-required status, then request separate Guidance and Registrar head approvals before publication.',
                ]);
                app(StartKnowledgeExtraction::class)->handle($guidanceClerk, $version->source, $version);
                app(EstablishCrossOfficeKnowledgeScope::class)->handle($guidanceHead, $version->source, $version->fresh());
            }

            if (! KnowledgeSource::query()->where('institution_id', $institutionId)->where('key', 'faq-exam-day-essentials')->exists()) {
                $version = app(CreateKnowledgeDraft::class)->handle($guidanceClerk, [
                    'key' => 'faq-exam-day-essentials',
                    'title' => 'Exam day essentials and required items FAQ',
                    'source_class' => KnowledgeSource::SOURCE_CLASS_PUBLIC_FAQ,
                    'audience' => KnowledgeSource::AUDIENCE_STAFF_AND_APPLICANT_INQUIRY,
                    'category' => KnowledgeSource::CATEGORY_TESTING,
                    'question' => 'What items must I bring on college entrance exam day?',
                    'answer' => 'Always bring: 1. Your official NEXIAM QR Test Pass (printed copy or digital screenshot), 2. Two sharpened Mongol #2 pencils with erasers, 3. Valid school ID or government ID, 4. Drinking water. Arrive at least 30 minutes before your scheduled session time. Calculators, smartwatches, and mobile devices are strictly prohibited unless explicitly specified.',
                ]);
                app(StartKnowledgeExtraction::class)->handle($guidanceClerk, $version->source, $version);
                app(ApproveKnowledgeSourceVersion::class)->handle($guidanceHead, $version->source, $version->fresh());
                app(PublishKnowledgeSourceVersion::class)->handle($guidanceHead, $version->source, $version->fresh());
            }

            if (! KnowledgeSource::query()->where('institution_id', $institutionId)->where('key', 'faq-access-key-safety')->exists()) {
                $version = app(CreateKnowledgeDraft::class)->handle($clerk, [
                    'key' => 'faq-access-key-safety',
                    'title' => 'Applicant access key safety and shared device FAQ',
                    'source_class' => KnowledgeSource::SOURCE_CLASS_PUBLIC_FAQ,
                    'audience' => KnowledgeSource::AUDIENCE_STAFF_AND_APPLICANT_INQUIRY,
                    'category' => KnowledgeSource::CATEGORY_ADMISSIONS,
                    'question' => 'How do I keep my 16-character applicant access key safe on shared computers?',
                    'answer' => 'Your access key is your permanent digital credential for admissions. On private phones, click "Save on this device" for convenience. When accessing the portal at an internet cafe or school computer lab, NEVER save the key to the browser. Write the key in a private notebook or take a photo on your personal phone, and always log out before leaving.',
                ]);
                app(StartKnowledgeExtraction::class)->handle($clerk, $version->source, $version);
                app(ApproveKnowledgeSourceVersion::class)->handle($head, $version->source, $version->fresh());
                app(PublishKnowledgeSourceVersion::class)->handle($head, $version->source, $version->fresh());
            }

            if (! KnowledgeSource::query()->where('institution_id', $institutionId)->where('key', 'faq-course-compatibility-human-authority')->exists()) {
                $version = app(CreateKnowledgeDraft::class)->handle($guidanceClerk, [
                    'key' => 'faq-course-compatibility-human-authority',
                    'title' => 'Course compatibility index and human counselor authority FAQ',
                    'source_class' => KnowledgeSource::SOURCE_CLASS_PUBLIC_FAQ,
                    'audience' => KnowledgeSource::AUDIENCE_STAFF_AND_APPLICANT_INQUIRY,
                    'category' => KnowledgeSource::CATEGORY_TESTING,
                    'question' => 'Who decides my college course placement, and how does the AI compatibility index work?',
                    'answer' => 'Guidance counselors and applicants retain 100% human decision-making authority over college admissions placement. The TabFM course match index is purely objective advisory evidence that evaluates your senior high school grades and entrance exam sub-test percentiles against historical degree exemplars to highlight your academic strengths. It has zero automated placement authority.',
                ]);
                app(StartKnowledgeExtraction::class)->handle($guidanceClerk, $version->source, $version);
                app(ApproveKnowledgeSourceVersion::class)->handle($guidanceHead, $version->source, $version->fresh());
                app(PublishKnowledgeSourceVersion::class)->handle($guidanceHead, $version->source, $version->fresh());
            }

            if (! KnowledgeSource::query()->where('institution_id', $institutionId)->where('key', 'faq-department-decline-readvising')->exists()) {
                $version = app(CreateKnowledgeDraft::class)->handle($guidanceClerk, [
                    'key' => 'faq-department-decline-readvising',
                    'title' => 'Department qualification review and re-advising FAQ',
                    'source_class' => KnowledgeSource::SOURCE_CLASS_PUBLIC_FAQ,
                    'audience' => KnowledgeSource::AUDIENCE_STAFF_AND_APPLICANT_INQUIRY,
                    'category' => KnowledgeSource::CATEGORY_TESTING,
                    'question' => 'What happens if an academic department declines an applicant during qualification review?',
                    'answer' => 'If a candidate does not meet specific department criteria (such as specialized physical fitness, audition, or interview requirements) or if the program quota is exhausted, the candidate is returned to the Guidance Consultation Hub with an explanatory note from the department head. Guidance counselors then conduct a re-advising session to help the student select an open alternative program.',
                ]);
                app(StartKnowledgeExtraction::class)->handle($guidanceClerk, $version->source, $version);
                app(ApproveKnowledgeSourceVersion::class)->handle($guidanceHead, $version->source, $version->fresh());
                app(PublishKnowledgeSourceVersion::class)->handle($guidanceHead, $version->source, $version->fresh());
            }

            if (! KnowledgeSource::query()->where('institution_id', $institutionId)->where('key', 'faq-exam-late-arrivals')->exists()) {
                $version = app(CreateKnowledgeDraft::class)->handle($guidanceClerk, [
                    'key' => 'faq-exam-late-arrivals',
                    'title' => 'Late arrivals and examination rescheduling FAQ',
                    'source_class' => KnowledgeSource::SOURCE_CLASS_PUBLIC_FAQ,
                    'audience' => KnowledgeSource::AUDIENCE_STAFF_AND_APPLICANT_INQUIRY,
                    'category' => KnowledgeSource::CATEGORY_TESTING,
                    'question' => 'What happens if I arrive late or miss my scheduled exam session?',
                    'answer' => 'Testing rooms close promptly at the designated session start time. Examinees who arrive after booklet distribution begins cannot enter the testing room. Report immediately to the Guidance Office Consultation Desk to request reassignment to a later standby or makeup session.',
                ]);
                app(StartKnowledgeExtraction::class)->handle($guidanceClerk, $version->source, $version);
                app(ApproveKnowledgeSourceVersion::class)->handle($guidanceHead, $version->source, $version->fresh());
                app(PublishKnowledgeSourceVersion::class)->handle($guidanceHead, $version->source, $version->fresh());
            }

            if (! KnowledgeSource::query()->where('institution_id', $institutionId)->where('key', 'faq-exam-single-attempt-policy')->exists()) {
                $version = app(CreateKnowledgeDraft::class)->handle($guidanceClerk, [
                    'key' => 'faq-exam-single-attempt-policy',
                    'title' => 'Single examination attempt policy per academic cycle FAQ',
                    'source_class' => KnowledgeSource::SOURCE_CLASS_PUBLIC_FAQ,
                    'audience' => KnowledgeSource::AUDIENCE_STAFF_AND_APPLICANT_INQUIRY,
                    'category' => KnowledgeSource::CATEGORY_TESTING,
                    'question' => 'Can I retake the college entrance examination in the same academic cycle?',
                    'answer' => 'No. Institutional testing policy permits only one (1) examination attempt per applicant per academic year. Duplicate applications or repeated test attempts within the same admission cycle are invalidated.',
                ]);
                app(StartKnowledgeExtraction::class)->handle($guidanceClerk, $version->source, $version);
                app(ApproveKnowledgeSourceVersion::class)->handle($guidanceHead, $version->source, $version->fresh());
                app(PublishKnowledgeSourceVersion::class)->handle($guidanceHead, $version->source, $version->fresh());
            }

            if (! KnowledgeSource::query()->where('institution_id', $institutionId)->where('key', 'faq-admissions-mandatory-documents')->exists()) {
                $version = app(CreateKnowledgeDraft::class)->handle($clerk, [
                    'key' => 'faq-admissions-mandatory-documents',
                    'title' => 'Mandatory freshman admission documents FAQ',
                    'source_class' => KnowledgeSource::SOURCE_CLASS_PUBLIC_FAQ,
                    'audience' => KnowledgeSource::AUDIENCE_STAFF_AND_APPLICANT_INQUIRY,
                    'category' => KnowledgeSource::CATEGORY_ADMISSIONS,
                    'question' => 'What documents are required for freshman admission intake?',
                    'answer' => 'Freshman applicants must present: 1. Original Senior High School Report Card (Form 138 / SF9), 2. Original PSA Birth Certificate on Security Paper (SecPa), 3. Original Certificate of Good Moral Character, 4. Two (2) 2x2 passport-sized photos with white background and standard nametag, and 5. One (1) long brown envelope (plus three photocopies of each document).',
                ]);
                app(StartKnowledgeExtraction::class)->handle($clerk, $version->source, $version);
                app(ApproveKnowledgeSourceVersion::class)->handle($head, $version->source, $version->fresh());
                app(PublishKnowledgeSourceVersion::class)->handle($head, $version->source, $version->fresh());
            }

            if (! KnowledgeSource::query()->where('institution_id', $institutionId)->where('key', 'faq-admissions-strand-alignment')->exists()) {
                $version = app(CreateKnowledgeDraft::class)->handle($clerk, [
                    'key' => 'faq-admissions-strand-alignment',
                    'title' => 'Senior High School strand alignment FAQ',
                    'source_class' => KnowledgeSource::SOURCE_CLASS_PUBLIC_FAQ,
                    'audience' => KnowledgeSource::AUDIENCE_STAFF_AND_APPLICANT_INQUIRY,
                    'category' => KnowledgeSource::CATEGORY_ADMISSIONS,
                    'question' => 'Can I apply for a degree program outside my Senior High School strand?',
                    'answer' => 'Yes. Qualified graduates from any Senior High School track or strand (STEM, HUMSS, ABM, GAS, TVL) may apply. Non-STEM students enrolling in STEM-aligned degrees (such as BSIT or BSEd-Math) may be assigned prerequisite bridging subjects during their first academic year.',
                ]);
                app(StartKnowledgeExtraction::class)->handle($clerk, $version->source, $version);
                app(ApproveKnowledgeSourceVersion::class)->handle($head, $version->source, $version->fresh());
                app(PublishKnowledgeSourceVersion::class)->handle($head, $version->source, $version->fresh());
            }

            if (! KnowledgeSource::query()->where('institution_id', $institutionId)->where('key', 'faq-admissions-clearance-enrollment-slip')->exists()) {
                $version = app(CreateKnowledgeDraft::class)->handle($clerk, [
                    'key' => 'faq-admissions-clearance-enrollment-slip',
                    'title' => 'Admission clearance and official enrollment slip FAQ',
                    'source_class' => KnowledgeSource::SOURCE_CLASS_PUBLIC_FAQ,
                    'audience' => KnowledgeSource::AUDIENCE_STAFF_AND_APPLICANT_INQUIRY,
                    'category' => KnowledgeSource::CATEGORY_ADMISSIONS,
                    'question' => 'When and where do I receive my official Enrollment Slip?',
                    'answer' => 'After completing Release Day advising and confirming your degree choice, proceed to Admission Clearance at the Registrar Office (Registrar Window 1). Once your physical Form 138 and PSA birth certificate are verified, the Registrar confirms clearance and issues your official Enrollment Slip.',
                ]);
                app(StartKnowledgeExtraction::class)->handle($clerk, $version->source, $version);
                app(ApproveKnowledgeSourceVersion::class)->handle($head, $version->source, $version->fresh());
                app(PublishKnowledgeSourceVersion::class)->handle($head, $version->source, $version->fresh());
            }

            if (! KnowledgeSource::query()->where('institution_id', $institutionId)->where('key', 'faq-campus-free-higher-education')->exists()) {
                $version = app(CreateKnowledgeDraft::class)->handle($clerk, [
                    'key' => 'faq-campus-free-higher-education',
                    'title' => 'Free higher education and entrance exam fee policy FAQ',
                    'source_class' => KnowledgeSource::SOURCE_CLASS_PUBLIC_FAQ,
                    'audience' => KnowledgeSource::AUDIENCE_STAFF_AND_APPLICANT_INQUIRY,
                    'category' => KnowledgeSource::CATEGORY_CAMPUS,
                    'question' => 'Is there a tuition fee or entrance examination fee at ISPSC?',
                    'answer' => 'No. Under Republic Act 10931 (Universal Access to Quality Tertiary Education Act), qualified undergraduate students in State Universities and Colleges are exempt from tuition and miscellaneous fees. The college entrance examination is completely free.',
                ]);
                app(StartKnowledgeExtraction::class)->handle($clerk, $version->source, $version);
                app(SignKnowledgeSourceConsensus::class)->handle($adminLead, $version->source, $version->fresh(), ['admin', 'registrar']);
                app(SignKnowledgeSourceConsensus::class)->handle($head, $version->source, $version->fresh(), ['admin', 'registrar']);
                app(ApproveKnowledgeSourceVersion::class)->handle($head, $version->source, $version->fresh());
                app(PublishKnowledgeSourceVersion::class)->handle($head, $version->source, $version->fresh());
            }

            KnowledgeSource::query()
                ->where('institution_id', $institutionId)
                ->whereIn('key', [
                    'faq-exam-day-essentials',
                    'faq-course-compatibility-human-authority',
                    'faq-department-decline-readvising',
                    'faq-exam-late-arrivals',
                    'faq-exam-single-attempt-policy',
                ])
                ->update([
                    'owning_office' => KnowledgeSource::OFFICE_GUIDANCE,
                    'owner_user_id' => $guidanceClerk->id,
                    'category' => KnowledgeSource::CATEGORY_TESTING,
                ]);

            if (! KnowledgeSource::query()->where('institution_id', $institutionId)->where('key', 'policy-omr-density-and-fiducials')->exists()) {
                $version = app(CreateKnowledgeDraft::class)->handle($guidanceClerk, [
                    'key' => 'policy-omr-density-and-fiducials',
                    'title' => 'OMR optical scanner density and fiducial geometry policy',
                    'source_class' => KnowledgeSource::SOURCE_CLASS_POLICY,
                    'audience' => KnowledgeSource::AUDIENCE_STAFF_ONLY,
                    'body_text' => 'OMR intake utilizes four corner black fiducial squares to establish an affine transformation matrix for deskewing and rotation calibration. Bubble darkness is measured via density thresholding against background paper tone; marks below the 25% darkness threshold are filtered out as eraser smudges. A physical sheet with an unreadable QR code may be recovered using the printed 8-digit Display Code under manual proctor verification, but tampered or invalid QR codes cannot be bypassed.',
                ]);
                app(StartKnowledgeExtraction::class)->handle($guidanceClerk, $version->source, $version);
                app(ApproveKnowledgeSourceVersion::class)->handle($guidanceHead, $version->source, $version->fresh());
                app(PublishKnowledgeSourceVersion::class)->handle($guidanceHead, $version->source, $version->fresh());
            }

            if (! KnowledgeSource::query()->where('institution_id', $institutionId)->where('key', 'policy-absentee-scoring-rules')->exists()) {
                $version = app(CreateKnowledgeDraft::class)->handle($clerk, [
                    'key' => 'policy-absentee-scoring-rules',
                    'title' => 'Absentee examinee scoring roster policy',
                    'source_class' => KnowledgeSource::SOURCE_CLASS_POLICY,
                    'audience' => KnowledgeSource::AUDIENCE_STAFF_ONLY,
                    'body_text' => 'Examinees marked Absent during Exam Day Monitoring are excluded from room scoring rosters. An absent candidate does not receive test scores and does not block batch finalization. Only candidates physically present and submitted constitute the scoring denominator for room finalization.',
                ]);
                app(StartKnowledgeExtraction::class)->handle($clerk, $version->source, $version);
                app(ApproveKnowledgeSourceVersion::class)->handle($head, $version->source, $version->fresh());
                app(PublishKnowledgeSourceVersion::class)->handle($head, $version->source, $version->fresh());
            }

            if (! KnowledgeSource::query()->where('institution_id', $institutionId)->where('key', 'policy-result-sheet-template-activation')->exists()) {
                $version = app(CreateKnowledgeDraft::class)->handle($clerk, [
                    'key' => 'policy-result-sheet-template-activation',
                    'title' => 'Official result sheet template activation policy',
                    'source_class' => KnowledgeSource::SOURCE_CLASS_POLICY,
                    'audience' => KnowledgeSource::AUDIENCE_STAFF_ONLY,
                    'body_text' => 'Batch printing of physical applicant result slips requires an approved, active result sheet template in System Setup. Generating result documents cannot proceed without an active template governing paper size, margins, seals, and signature blocks. Result printing is restricted to examinees whose scores are finalized and released.',
                ]);
                app(StartKnowledgeExtraction::class)->handle($clerk, $version->source, $version);
                app(ApproveKnowledgeSourceVersion::class)->handle($head, $version->source, $version->fresh());
                app(PublishKnowledgeSourceVersion::class)->handle($head, $version->source, $version->fresh());
            }
        } finally {
            config()->set('xiam.process_inline', $previousInline);
        }
    }
}
