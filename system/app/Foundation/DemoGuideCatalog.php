<?php

namespace App\Foundation;

final class DemoGuideCatalog
{
    /**
     * @var list<array{key: string, title: string, summary: string, diagram_ids: list<string>, fixture_stages: list<int>, steps: list<array{key: string, title: string, actor: string, before: string, actions: list<string>, expected: string, next_actor: string, url: string, workspace_label: string}>}>
     */
    private const WORKFLOWS = [
        [
            'key' => 'application-review',
            'title' => 'Application review',
            'summary' => 'Applicant intake and Registrar review remain separate, scoped tasks.',
            'diagram_ids' => ['AD-ADM-01'],
            'fixture_stages' => [1],
            'steps' => [
                ['key' => 'apply', 'title' => 'Submit intake', 'actor' => 'Applicant', 'before' => 'The application window is open; the applicant has their own identity and required intake details.', 'actions' => ['Open Apply and read the active privacy policy.', 'Enter the required intake data and submit the application.'], 'expected' => 'The application is submitted for review; submission does not mean acceptance.', 'next_actor' => 'Registrar staff with access to this application', 'url' => '/apply', 'workspace_label' => 'Apply'],
                ['key' => 'review', 'title' => 'Review application', 'actor' => 'Registrar staff', 'before' => 'The application is available in the current office and cycle scope.', 'actions' => ['Open Admissions and select the assigned application.', 'Check intake data and required evidence, then record an allowed decision.'], 'expected' => 'The permitted decision is recorded; navigation alone does not accept or finalize it.', 'next_actor' => 'Applicant, when the application state allows a next step', 'url' => '/admissions', 'workspace_label' => 'Admissions'],
                ['key' => 'check-outcome', 'title' => 'Check the recorded outcome', 'actor' => 'Applicant', 'before' => 'Registrar staff have recorded an outcome that is visible to this applicant.', 'actions' => ['Sign in to the applicant portal using your own credentials.', 'Check the application status and follow only the available next step.'], 'expected' => 'The portal shows the current recorded status; viewing it does not change the decision.', 'next_actor' => 'Applicant, if the current status permits another step', 'url' => '/applicant/portal', 'workspace_label' => 'Applicant portal'],
            ],
        ],
        [
            'key' => 'exam-scheduling-attendance',
            'title' => 'Exam scheduling and attendance',
            'summary' => 'Guidance schedules sessions; applicants reserve when eligible; an authorized Test Administrator records attendance.',
            'diagram_ids' => ['AD-EXM-01', 'AD-EXM-03'],
            'fixture_stages' => [2, 3],
            'steps' => [
                ['key' => 'schedule', 'title' => 'Prepare a session', 'actor' => 'Guidance staff with scheduling access', 'before' => 'The active cycle, room and approved exam materials are ready; required institutional OMR must be active and finalized for the demo fixture.', 'actions' => ['Open Exam Sessions and create a session in the assigned cycle.', 'Choose an available room and layout; check the schedule before publication.'], 'expected' => 'A session is prepared; it is not published or opened unless separately permitted.', 'next_actor' => 'Guidance staff, then eligible applicant', 'url' => '/exams/sessions', 'workspace_label' => 'Exam Sessions'],
                ['key' => 'reserve', 'title' => 'Reserve a place', 'actor' => 'Accepted applicant', 'before' => 'The applicant is eligible and a reservation-enabled session has an available place.', 'actions' => ['Sign in to the applicant portal and open the reservation calendar.', 'Select an available session and confirm the reservation.'], 'expected' => 'A reservation is recorded only after the applicant confirms it.', 'next_actor' => 'Test Administrator on examination day', 'url' => '/applicant/schedule', 'workspace_label' => 'Applicant schedule'],
                ['key' => 'attendance', 'title' => 'Record attendance', 'actor' => 'Test Administrator with access to the institution and workspace', 'before' => 'The session is in its allowed attendance window; no advance per-session staff assignment is required.', 'actions' => ['Open Exam Monitoring and select the in-scope session.', 'Record permitted attendance outcomes and close only when allowed.'], 'expected' => 'Attendance and session closure follow the authorized capability and session state; scoring remains separate.', 'next_actor' => 'Scoring staff after the session is closed', 'url' => '/exams/monitoring', 'workspace_label' => 'Exam Monitoring'],
            ],
        ],
        [
            'key' => 'scoring-results',
            'title' => 'Scoring and results',
            'summary' => 'Scoring, result-document preparation, printing and release are distinct actions.',
            'diagram_ids' => ['AD-GRD-01', 'AD-DOC-02'],
            'fixture_stages' => [3, 4],
            'steps' => [
                ['key' => 'score', 'title' => 'Review and finalize scores', 'actor' => 'Scoring staff with current access', 'before' => 'The exam session is closed and its roster is eligible for scoring.', 'actions' => ['Open OMR review or Manual Scoring for the assigned work.', 'Resolve reported errors and finalize only when required scores are complete.'], 'expected' => 'Finalized grading records the scoring decision; opening a page does not finalize it.', 'next_actor' => 'Guidance staff with result-document access', 'url' => '/grading', 'workspace_label' => 'Grading'],
                ['key' => 'prepare-results', 'title' => 'Prepare result documents', 'actor' => 'Guidance staff with result-document access', 'before' => 'A grading session is finalized and a current result document can be prepared.', 'actions' => ['Open Result Documents and select the finalized session.', 'Prepare the current document; request a print batch only when paper is needed.'], 'expected' => 'Preparation is not printing or release; record printing only after paper is printed.', 'next_actor' => 'Authorized release staff, only when the selected mode requires release', 'url' => '/release', 'workspace_label' => 'Result Documents'],
                ['key' => 'release-results', 'title' => 'Release when permitted', 'actor' => 'Authorized release staff', 'before' => 'The current result document and release mode permit this action.', 'actions' => ['Check the current release state and mode.', 'Use the permitted release action only when required by that mode.'], 'expected' => 'No mandatory online release follows a decision; the system state determines applicant visibility.', 'next_actor' => 'Applicant, when results are available to their account', 'url' => '/release', 'workspace_label' => 'Release'],
            ],
        ],
        [
            'key' => 'consultation-referral',
            'title' => 'Consultation and referral',
            'summary' => 'Guidance records permitted consultation decisions and routes referrals to authorized recipients.',
            'diagram_ids' => ['AD-CON-02'],
            'fixture_stages' => [5],
            'steps' => [
                ['key' => 'consult', 'title' => 'Review consultation', 'actor' => 'Guidance staff assigned to the consultation with required access', 'before' => 'The applicant is in the consultation queue and the current result and mode are available.', 'actions' => ['Open Consultation and review the current result and case context.', 'Record only a decision permitted for this consultation state and your access.', 'For face-to-face decisions, check the current ready result and required paper; record printing only after it exists.'], 'expected' => 'The decision follows its current requirements; it does not imply printing or online release.', 'next_actor' => 'Applicant or assigned Guidance staff, as the recorded outcome permits', 'url' => '/consultation', 'workspace_label' => 'Consultation'],
                ['key' => 'refer', 'title' => 'Refer when needed', 'actor' => 'Guidance staff with referral authority', 'before' => 'The recipient is assigned and eligible; required face-to-face paper uses a current ready result actually printed by authorized staff.', 'actions' => ['Use the permitted consultation referral action for the assigned recipient.', 'Check the recorded handoff and follow the permitted next step.'], 'expected' => 'Only the assigned recipient receives the scoped referral; other access is not implied.', 'next_actor' => 'Assigned Program Head or other authorized recipient', 'url' => '/consultation', 'workspace_label' => 'Consultation'],
                ['key' => 'academic-review', 'title' => 'Review assigned referral', 'actor' => 'Assigned Program Head or Dean with current clearance authority', 'before' => 'The referral is assigned to this academic leader; access to private case details is not assumed.', 'actions' => ['Open the assigned case in Program Workspace.', 'Review only the referred information and record an allowed clearance or follow-up.'], 'expected' => 'The assigned academic action is recorded separately; applicant program selection remains distinct.', 'next_actor' => 'Guidance staff for the next permitted case step', 'url' => '/staff/programs', 'workspace_label' => 'Program Workspace'],
            ],
        ],
        [
            'key' => 'case-coordination',
            'title' => 'Case coordination',
            'summary' => 'Guidance and a permitted academic leader exchange scoped case information; membership is not inferred.',
            'diagram_ids' => ['AD-COM-02'],
            'fixture_stages' => [],
            'steps' => [
                ['key' => 'send-update', 'title' => 'Send assigned case update', 'actor' => 'Guidance staff assigned to the consultation', 'before' => 'The assigned case has an approved staff thread and Guidance is a current thread party.', 'actions' => ['Open the assigned case in Consultation.', 'Send only the update needed for the authorized case purpose.'], 'expected' => 'The update is visible only to current approved parties; no Registrar or third-party access is implied.', 'next_actor' => 'Assigned Program Head or Dean, if they are a current thread party', 'url' => '/consultation', 'workspace_label' => 'Consultation'],
                ['key' => 'reply', 'title' => 'Reply to the same case', 'actor' => 'Assigned Program Head or Dean', 'before' => 'The academic leader is assigned to this same case and is an approved current thread party.', 'actions' => ['Open the assigned case in Program Workspace.', 'Read and reply only within the approved case thread and assigned scope.'], 'expected' => 'A reply does not add participants or confer access to other records.', 'next_actor' => 'Guidance staff who remains a current thread party', 'url' => '/staff/programs', 'workspace_label' => 'Program Workspace'],
                ['key' => 'review-reply', 'title' => 'Review the case reply', 'actor' => 'Guidance staff current on the same assigned case thread', 'before' => 'The case reply is available in the approved thread and Guidance remains a current party.', 'actions' => ['Return to the assigned case in Consultation.', 'Review the reply and take only the next action permitted to Guidance.'], 'expected' => 'Case coordination stays with these current parties; no triadic membership or role impersonation is inferred.', 'next_actor' => 'Guidance staff for the next assigned case action', 'url' => '/consultation', 'workspace_label' => 'Consultation'],
            ],
        ],
        [
            'key' => 'enrollment-verification',
            'title' => 'Enrollment verification',
            'summary' => 'Registrar staff compare required paper evidence with the digital record before stamping enrollment.',
            'diagram_ids' => ['AD-ENR-01'],
            'fixture_stages' => [6],
            'steps' => [
                ['key' => 'verify', 'title' => 'Compare enrollment evidence', 'actor' => 'Registrar staff with assigned verification access', 'before' => 'The applicant is eligible for enrollment review and required paper evidence is available.', 'actions' => ['Open Enrollment Eligibility and select the assigned applicant.', 'Compare the paper evidence with the current digital program record.'], 'expected' => 'No enrollment stamp is made until required checks are complete.', 'next_actor' => 'Registrar staff authorized to record the next enrollment state', 'url' => '/registrar/enrollment-eligibility', 'workspace_label' => 'Enrollment Eligibility'],
                ['key' => 'stamp', 'title' => 'Record permitted status', 'actor' => 'Registrar staff with the required capability', 'before' => 'Evidence has been checked and the requested transition is permitted.', 'actions' => ['Record Enrolling or Enrolled only after the required checks.', 'Review the resulting status in the applicant record.'], 'expected' => 'The digital stamp records the verified step; external SIS completion remains separate.', 'next_actor' => 'Registrar staff or scoped academic owner for the next assigned work', 'url' => '/registrar/enrollment-eligibility', 'workspace_label' => 'Enrollment Eligibility'],
            ],
        ],
        [
            'key' => 'staff-accounts-delegation',
            'title' => 'Staff accounts and delegation',
            'summary' => 'Personnel work follows office rank and explicit capability delegation; staff title is not duty assignment.',
            'diagram_ids' => ['AD-IDN-02'],
            'fixture_stages' => [],
            'steps' => [
                ['key' => 'personnel', 'title' => 'Manage assigned staff', 'actor' => 'HR or an office lead within their permitted rank and office', 'before' => 'The staff member is within the actor’s management scope; self-service belongs in Staff Profile.', 'actions' => ['Open Personnel and select an in-scope staff account.', 'Make only a permitted account or assignment change.'], 'expected' => 'The change follows office scope and rank; support roles are not freely selectable.', 'next_actor' => 'Managed staff member', 'url' => '/staff/personnel', 'workspace_label' => 'Personnel'],
                ['key' => 'delegate', 'title' => 'Delegate a capability set', 'actor' => 'Guidance Counselor with delegation authority', 'before' => 'The target is eligible for delegation and the delegator has the required authority.', 'actions' => ['Use the authorized delegation action for the eligible staff member.'], 'expected' => 'A capability grant does not create unrestricted record access; Test Administrators conduct in-scope sessions without a separate session duty.', 'next_actor' => 'Delegated staff member for work within the granted capabilities', 'url' => '/staff/personnel', 'workspace_label' => 'Personnel'],
            ],
        ],
        [
            'key' => 'account-technical-support',
            'title' => 'Account and technical support',
            'summary' => 'Staff account access issues use approved account support; infrastructure work uses a separate approved runbook.',
            'diagram_ids' => ['AD-IDN-01'],
            'fixture_stages' => [],
            'steps' => [
                ['key' => 'account', 'title' => 'Resolve account access', 'actor' => 'Staff member and authorized account-support staff', 'before' => 'The staff member uses their own identity and follows the approved account recovery process.', 'actions' => ['Open Staff Profile to review the current account details.', 'Ask authorized support for an approved recovery or account setup step.'], 'expected' => 'Support restores only authorized account access; it does not impersonate the staff member.', 'next_actor' => 'Staff member using their own account', 'url' => '/staff/profile', 'workspace_label' => 'Staff Profile'],
                ['key' => 'technical', 'title' => 'Follow approved maintenance', 'actor' => 'Assigned technical support staff', 'before' => 'An approved infrastructure task and current runbook are available.', 'actions' => ['Follow the approved infrastructure runbook outside this guide.', 'Use institutional data only for the authorized support purpose.'], 'expected' => 'This guide provides no infrastructure CLI or bypass procedure.', 'next_actor' => 'Responsible application office for any domain decision', 'url' => '/staff/profile', 'workspace_label' => 'Staff Profile'],
            ],
        ],
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function workflows(DemoGuideAssets $assets, ?DemoPersonaCatalog $personas = null): array
    {
        return array_map(function (array $workflow) use ($assets, $personas): array {
            $workflow['diagrams'] = $assets->diagrams($workflow['diagram_ids']);
            $workflow['cli_command'] = 'php artisan demo:stage '.$workflow['key'].' --operator=1';
            $workflow['scenario_key'] = $workflow['key'];

            if ($personas !== null) {
                $workflow['steps'] = array_map(function (array $step) use ($personas): array {
                    $step['actor_persona'] = $personas->resolveActorPersona($step['actor']);

                    return $step;
                }, $workflow['steps']);
            }

            return $workflow;
        }, self::WORKFLOWS);
    }
}
