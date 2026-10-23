<?php

namespace App\Notifications\Templates;

use App\Models\Notifications\CommunicationTemplate;
use Illuminate\Support\Facades\Route;

class CommunicationTemplateRegistry
{
    /**
     * @return list<array{
     *     event_key: string,
     *     slug: string,
     *     name: string,
     *     description: string,
     *     default_email_subject: string,
     *     default_email_body: string,
     *     default_in_app_title: string,
     *     default_in_app_body: string,
     *     default_action_label: string,
     *     default_action_route: string,
     *     channels: list<string>,
     *     tokens: list<array{token: string, label: string, sample: string}>
     * }>
     */
    public static function admissionsSlots(): array
    {
        return [
            [
                'event_key' => 'admission.submitted.v1',
                'slug' => 'submitted',
                'name' => 'Application Submission Receipt',
                'description' => 'Sent to applicants immediately upon submitting their admission application.',
                'default_email_subject' => 'Application Received — {{application_no}}',
                'default_email_body' => '<p>Dear <strong>{{applicant_name}}</strong>,</p><p>Thank you for submitting your application to <strong>{{institution_name}}</strong>. Your application reference code is <strong style="color: #1e40af;">{{application_no}}</strong> for the <strong>{{preferred_program}}</strong> program.</p><p>You may monitor the status of your application anytime by signing in to the Applicant Portal using your access key and date of birth.</p>',
                'default_in_app_title' => 'Application Received',
                'default_in_app_body' => 'Your application {{application_no}} has been submitted successfully.',
                'default_action_label' => 'Open Applicant Portal',
                'default_action_route' => 'applicant.portal',
                'channels' => ['in_app'],
                'tokens' => self::baseTokens(),
            ],
            [
                'event_key' => 'admission.new_submission.v1',
                'slug' => 'new-submission',
                'name' => 'Registrar Review Queue Alert',
                'description' => 'Internal alert to Admissions Evaluators when a new candidate dossier is queued for review.',
                'default_email_subject' => 'New Applicant Submission — {{application_no}}',
                'default_email_body' => '<p>A new applicant dossier (<strong>{{application_no}}</strong> — {{applicant_name}}) is ready for Registrar review.</p>',
                'default_in_app_title' => 'New Applicant Submission',
                'default_in_app_body' => 'Application {{application_no}} is ready for Registrar review.',
                'default_action_label' => 'Open Admissions Queue',
                'default_action_route' => 'admissions.index',
                'channels' => ['in_app'],
                'tokens' => [
                    ['token' => '{{applicant_name}}', 'label' => 'Applicant Name', 'sample' => 'Maria Santos'],
                    ['token' => '{{application_no}}', 'label' => 'Application Reference Code', 'sample' => 'AY26-00812'],
                    ['token' => '{{preferred_program}}', 'label' => 'Preferred Program', 'sample' => 'BS Computer Science'],
                    ['token' => '{{queue_url}}', 'label' => 'Admissions Queue URL', 'sample' => 'http://localhost:18000/admissions'],
                ],
            ],
            [
                'event_key' => 'admission.accepted.v1',
                'slug' => 'accepted',
                'name' => 'Official Offer of Admission',
                'description' => 'Sent to applicants who have been officially accepted for admission.',
                'default_email_subject' => 'Application Accepted — {{application_no}}',
                'default_email_body' => '<p>Dear <strong>{{applicant_name}}</strong>,</p><p>Your application (<strong>{{application_no}}</strong>) for <strong>{{preferred_program}}</strong> at <strong>{{institution_name}}</strong> has been accepted by Admissions.</p><p>Please sign in to the Applicant Portal to review your admission requirements and schedule.</p>',
                'default_in_app_title' => 'Application Accepted',
                'default_in_app_body' => 'Your application {{application_no}} has been accepted.',
                'default_action_label' => 'View Application Status',
                'default_action_route' => 'applicant.portal',
                'channels' => ['in_app', 'mail'],
                'tokens' => [
                    ['token' => '{{applicant_name}}', 'label' => 'Applicant Name', 'sample' => 'Maria Santos'],
                    ['token' => '{{application_no}}', 'label' => 'Application Reference Code', 'sample' => 'AY26-00812'],
                    ['token' => '{{preferred_program}}', 'label' => 'Admitted Program', 'sample' => 'BS Computer Science'],
                    ['token' => '{{institution_name}}', 'label' => 'Institution Name', 'sample' => 'Central State University'],
                    ['token' => '{{portal_url}}', 'label' => 'Portal URL', 'sample' => 'http://localhost:18000/applicant/login'],
                ],
            ],
            [
                'event_key' => 'admission.dismissed.v1',
                'slug' => 'dismissed',
                'name' => 'Notice of Non-Admission',
                'description' => 'Sent to applicants whose application was not accepted at this time.',
                'default_email_subject' => 'Application Decision — {{application_no}}',
                'default_email_body' => '<p>Dear <strong>{{applicant_name}}</strong>,</p><p>An evaluation decision has been recorded for application <strong>{{application_no}}</strong> at <strong>{{institution_name}}</strong>. Please sign in to the Applicant Portal to review the details of your decision.</p>',
                'default_in_app_title' => 'Application Decision',
                'default_in_app_body' => 'An evaluation decision has been recorded for application {{application_no}}. View the decision in the portal.',
                'default_action_label' => 'View Decision',
                'default_action_route' => 'applicant.portal',
                'channels' => ['in_app', 'mail'],
                'tokens' => [
                    ['token' => '{{applicant_name}}', 'label' => 'Applicant Name', 'sample' => 'Maria Santos'],
                    ['token' => '{{application_no}}', 'label' => 'Application Reference Code', 'sample' => 'AY26-00812'],
                    ['token' => '{{institution_name}}', 'label' => 'Institution Name', 'sample' => 'Central State University'],
                    ['token' => '{{portal_url}}', 'label' => 'Portal URL', 'sample' => 'http://localhost:18000/applicant/login'],
                ],
            ],
            [
                'event_key' => 'admission.correction_requested.v1',
                'slug' => 'correction-requested',
                'name' => 'Application Correction Notice',
                'description' => 'Sent to applicants when an evaluator requests updates or document re-uploads.',
                'default_email_subject' => 'Application Update Required — {{application_no}}',
                'default_email_body' => '<p>Dear <strong>{{applicant_name}}</strong>,</p><p>Our Admissions team has reviewed your application (<strong>{{application_no}}</strong>) and identified items that require your attention or correction.</p><p>Please log in to your Applicant Portal to review the evaluator notes and update your dossier promptly.</p>',
                'default_in_app_title' => 'Application Update Required',
                'default_in_app_body' => 'Your application requires updates or corrections before evaluation can proceed.',
                'default_action_label' => 'Review & Update Application',
                'default_action_route' => 'applicant.portal',
                'channels' => ['in_app', 'mail'],
                'tokens' => [
                    ['token' => '{{applicant_name}}', 'label' => 'Applicant Name', 'sample' => 'Maria Santos'],
                    ['token' => '{{application_no}}', 'label' => 'Application Reference Code', 'sample' => 'AY26-00812'],
                    ['token' => '{{correction_notes}}', 'label' => 'Correction Notes', 'sample' => 'Please re-upload a clear copy of your Form 138 (Grade 12 Report Card).'],
                    ['token' => '{{institution_name}}', 'label' => 'Institution Name', 'sample' => 'Central State University'],
                    ['token' => '{{portal_url}}', 'label' => 'Portal URL', 'sample' => 'http://localhost:18000/applicant/login'],
                ],
            ],
            [
                'event_key' => 'admission.document_correction_requested.v1',
                'slug' => 'document-correction-requested',
                'name' => 'Document Correction Request',
                'description' => 'Sent when Registrar explicitly requests correction of inspected document checklist items.',
                'default_email_subject' => 'Document Instructions — {{application_no}}',
                'default_email_body' => '<p>Dear <strong>{{applicant_name}}</strong>,</p><p>Registrar reviewed documents for application <strong>{{application_no}}</strong>. Please review these itemized instructions in the Applicant Portal:</p><p>{{document_requests}}</p>',
                'default_in_app_title' => 'Document Instructions',
                'default_in_app_body' => 'Please review the current document instructions: {{document_requests}}',
                'default_action_label' => 'Review Document Requests',
                'default_action_route' => 'applicant.portal',
                'channels' => ['in_app', 'mail'],
                'tokens' => self::tokens(['applicant_name', 'application_no', 'institution_name', 'document_requests', 'portal_url']),
            ],
            [
                'event_key' => 'application.opening_alert.verify.v1',
                'slug' => 'opening-alert-verify',
                'name' => 'Application Opening Alert Confirmation',
                'description' => 'Confirms mailbox ownership for an optional one-opening application alert.',
                'default_email_subject' => 'Confirm your application opening alert',
                'default_email_body' => '<p>Confirm that you want one email when applications next open at {{institution_name}}.</p><p><a href="{{confirmation_url}}">Confirm email address</a></p>',
                'default_in_app_title' => 'Confirm application opening alert',
                'default_in_app_body' => 'Confirm the email address to receive one application-opening alert.',
                'default_action_label' => 'Confirm email address',
                'default_action_route' => 'apply.form',
                'channels' => ['mail'],
                'tokens' => [
                    ['token' => '{{institution_name}}', 'label' => 'Institution Name', 'sample' => 'Central State University'],
                    ['token' => '{{confirmation_url}}', 'label' => 'Confirmation URL', 'sample' => 'https://example.test/apply/opening-alerts/example?purpose=verify'],
                ],
            ],
            [
                'event_key' => 'application.opening_alert.opened.v1',
                'slug' => 'opening-alert-opened',
                'name' => 'Applications Are Open',
                'description' => 'One-time notice that the next actual application intake is accepting applications.',
                'default_email_subject' => 'Applications are open at {{institution_name}}',
                'default_email_body' => '<p>Applications are now open at {{institution_name}} for {{academic_year}} {{semester}}. The current deadline is {{deadline}}.</p><p><a href="{{apply_url}}">Start your application</a></p><p><a href="{{prefill_url}}">Open Apply with your email filled in</a></p><p><a href="{{unsubscribe_url}}">Unsubscribe from this alert</a></p>',
                'default_in_app_title' => 'Applications are open',
                'default_in_app_body' => 'Applications are open. Start an application if you wish; this alert does not reserve a place.',
                'default_action_label' => 'Start your application',
                'default_action_route' => 'apply.form',
                'channels' => ['mail'],
                'tokens' => [
                    ['token' => '{{institution_name}}', 'label' => 'Institution Name', 'sample' => 'Central State University'],
                    ['token' => '{{academic_year}}', 'label' => 'Academic Year', 'sample' => '2026–2027'],
                    ['token' => '{{semester}}', 'label' => 'Semester', 'sample' => 'First Semester'],
                    ['token' => '{{deadline}}', 'label' => 'Application Deadline', 'sample' => 'November 30, 2026 UTC'],
                    ['token' => '{{apply_url}}', 'label' => 'Application URL', 'sample' => 'https://example.test/apply'],
                    ['token' => '{{prefill_url}}', 'label' => 'Editable email prefill URL', 'sample' => 'https://example.test/apply/opening-alerts/prefill/example'],
                    ['token' => '{{unsubscribe_url}}', 'label' => 'Unsubscribe URL', 'sample' => 'https://example.test/apply/opening-alerts/example?purpose=unsubscribe'],
                ],
            ],
            AdmissionClearanceNotificationTemplate::slotDefinition(),
        ];
    }

    /**
     * @return list<array{
     *     event_key: string,
     *     slug: string,
     *     name: string,
     *     description: string,
     *     default_email_subject: string,
     *     default_email_body: string,
     *     default_in_app_title: string,
     *     default_in_app_body: string,
     *     default_action_label: string,
     *     default_action_route: string,
     *     channels: list<string>,
     *     tokens: list<array{token: string, label: string, sample: string}>
     * }>
     */
    public static function examinationSlots(): array
    {
        return [
            [
                'event_key' => 'exam.session_published.v1',
                'slug' => 'session-published',
                'name' => 'Examination Schedule & Permit',
                'description' => 'Sent to candidates when their examination session schedule is published.',
                'default_email_subject' => 'Examination Schedule — {{session_code}}',
                'default_email_body' => '<p>Dear <strong>{{applicant_name}}</strong>,</p><p>Your entrance examination schedule for <strong>{{institution_name}}</strong> has been confirmed.</p><p><strong>Session Code:</strong> {{session_code}}<br><strong>Date:</strong> {{exam_date}}<br><strong>Time:</strong> {{exam_time}}<br><strong>Venue / Room:</strong> {{room_name}} ({{building}})<br><strong>Assigned Seat:</strong> {{seat_number}}</p><p>Sign in to the Applicant Portal to download your Examination Permit. Bring your printed permit and a valid photo ID on your examination day.</p>',
                'default_in_app_title' => 'Examination Schedule Confirmed',
                'default_in_app_body' => 'Your exam session has been scheduled for {{exam_date}} at {{room_name}}. Seat: {{seat_number}}.',
                'default_action_label' => 'View Examination Permit',
                'default_action_route' => 'applicant.schedule',
                'channels' => ['in_app', 'mail'],
                'tokens' => [
                    ['token' => '{{applicant_name}}', 'label' => 'Applicant Name', 'sample' => 'Maria Santos'],
                    ['token' => '{{application_no}}', 'label' => 'Application Reference Code', 'sample' => 'AY26-00812'],
                    ['token' => '{{session_code}}', 'label' => 'Session Code', 'sample' => 'EXAM-2026-001'],
                    ['token' => '{{exam_date}}', 'label' => 'Exam Date', 'sample' => 'October 12, 2026'],
                    ['token' => '{{exam_time}}', 'label' => 'Exam Time', 'sample' => '8:00 AM'],
                    ['token' => '{{room_name}}', 'label' => 'Room', 'sample' => 'Room 101'],
                    ['token' => '{{building}}', 'label' => 'Building', 'sample' => 'Main Building'],
                    ['token' => '{{seat_number}}', 'label' => 'Seat Number', 'sample' => 'A-12'],
                    ['token' => '{{institution_name}}', 'label' => 'Institution Name', 'sample' => 'Central State University'],
                    ['token' => '{{portal_url}}', 'label' => 'Portal URL', 'sample' => 'http://localhost:18000/applicant/login'],
                ],
            ],
            [
                'event_key' => 'exam.reminder.v1',
                'slug' => 'reminder',
                'name' => 'Examination Countdown Reminder',
                'description' => 'Automated countdown reminders dispatched at configured intervals (72h, 24h, 2h, 1h) before testing.',
                'default_email_subject' => 'Examination Reminder — {{exam_date}}',
                'default_email_body' => '<p>Dear <strong>{{applicant_name}}</strong>,</p><p>This is a reminder that your entrance examination at <strong>{{institution_name}}</strong> is scheduled for <strong>{{exam_date}} at {{exam_time}}</strong> in <strong>{{room_name}}</strong>.</p><p>Please ensure you have your printed Examination Permit and a valid photo ID ready before arriving at the venue.</p>',
                'default_in_app_title' => 'Examination Reminder',
                'default_in_app_body' => 'Your entrance examination is coming up on {{exam_date}} at {{exam_time}} in {{room_name}}.',
                'default_action_label' => 'Review Examination Details',
                'default_action_route' => 'applicant.schedule',
                'channels' => ['in_app', 'mail'],
                'tokens' => [
                    ['token' => '{{applicant_name}}', 'label' => 'Applicant Name', 'sample' => 'Maria Santos'],
                    ['token' => '{{session_code}}', 'label' => 'Session Code', 'sample' => 'EXAM-2026-001'],
                    ['token' => '{{exam_date}}', 'label' => 'Exam Date', 'sample' => 'October 12, 2026'],
                    ['token' => '{{exam_time}}', 'label' => 'Exam Time', 'sample' => '8:00 AM'],
                    ['token' => '{{room_name}}', 'label' => 'Room', 'sample' => 'Room 101'],
                    ['token' => '{{building}}', 'label' => 'Building', 'sample' => 'Main Building'],
                    ['token' => '{{seat_number}}', 'label' => 'Seat Number', 'sample' => 'A-12'],
                    ['token' => '{{institution_name}}', 'label' => 'Institution Name', 'sample' => 'Central State University'],
                    ['token' => '{{portal_url}}', 'label' => 'Portal URL', 'sample' => 'http://localhost:18000/applicant/login'],
                ],
            ],
            [
                'event_key' => 'exam.session_cancelled.v1',
                'slug' => 'session-cancelled',
                'name' => 'Exam Session Cancellation',
                'description' => 'Dispatched to candidates when their scheduled exam session is cancelled.',
                'default_email_subject' => 'Examination Session Cancelled — {{session_code}}',
                'default_email_body' => '<p>Dear <strong>{{applicant_name}}</strong>,</p><p>We regret to inform you that your scheduled examination session (<strong>{{session_code}}</strong> on {{exam_date}}) has been <strong>cancelled</strong> due to: {{cancellation_reason}}.</p><p>Our Testing Center is preparing rescheduled sessions. Please monitor your Applicant Portal for your new assignment notification.</p>',
                'default_in_app_title' => 'Examination Session Cancelled',
                'default_in_app_body' => 'Your exam session {{session_code}} on {{exam_date}} was cancelled: {{cancellation_reason}}.',
                'default_action_label' => 'Open Applicant Portal',
                'default_action_route' => 'applicant.schedule',
                'channels' => ['in_app', 'mail'],
                'tokens' => [
                    ['token' => '{{applicant_name}}', 'label' => 'Applicant Name', 'sample' => 'Maria Santos'],
                    ['token' => '{{session_code}}', 'label' => 'Session Code', 'sample' => 'EXAM-2026-001'],
                    ['token' => '{{exam_date}}', 'label' => 'Exam Date', 'sample' => 'October 12, 2026'],
                    ['token' => '{{cancellation_reason}}', 'label' => 'Cancellation Reason', 'sample' => 'venue maintenance'],
                    ['token' => '{{portal_url}}', 'label' => 'Portal URL', 'sample' => 'http://localhost:18000/applicant/login'],
                ],
            ],
            [
                'event_key' => 'exam.session_unpublished.v1',
                'slug' => 'session-unpublished',
                'name' => 'Exam Session Postponement',
                'description' => 'Dispatched when an exam session is returned to draft for rescheduling.',
                'default_email_subject' => 'Examination Schedule Update — {{session_code}}',
                'default_email_body' => '<p>Dear <strong>{{applicant_name}}</strong>,</p><p>Your examination session (<strong>{{session_code}}</strong>) has been postponed for administrative rescheduling. A revised examination schedule will be published shortly.</p>',
                'default_in_app_title' => 'Examination Schedule Update',
                'default_in_app_body' => 'Your exam session {{session_code}} has been postponed for rescheduling.',
                'default_action_label' => 'View in Portal',
                'default_action_route' => 'applicant.schedule',
                'channels' => ['in_app', 'mail'],
                'tokens' => self::tokens(['applicant_name', 'session_code', 'portal_url']),
            ],
            [
                'event_key' => 'exam.slot_reserved.v1',
                'slug' => 'slot-reserved',
                'name' => 'Self-Service Slot Booking Confirmation',
                'description' => 'Confirmation sent when candidate self-selects their exam session.',
                'default_email_subject' => 'Exam Slot Reservation Confirmed — {{session_code}}',
                'default_email_body' => '<p>Dear <strong>{{applicant_name}}</strong>,</p><p>Your exam slot reservation for session <strong>{{session_code}}</strong> on <strong>{{exam_date}} at {{exam_time}}</strong> in <strong>{{room_name}}</strong> has been confirmed.</p>',
                'default_in_app_title' => 'Exam Slot Reserved',
                'default_in_app_body' => 'You have reserved session {{session_code}} on {{exam_date}}.',
                'default_action_label' => 'View Reservation',
                'default_action_route' => 'applicant.schedule',
                'channels' => ['in_app'],
                'tokens' => self::tokens(['applicant_name', 'session_code', 'exam_date', 'exam_time', 'room_name', 'portal_url']),
            ],
            [
                'event_key' => 'exam.session_completed.v1',
                'slug' => 'session-completed',
                'name' => 'Exam Session Concluded Alert',
                'description' => 'Operational alert sent to test administrators when testing concludes.',
                'default_email_subject' => 'Exam Session Concluded — {{session_code}}',
                'default_email_body' => '<p>Examination session <strong>{{session_code}}</strong> has concluded and grading is open.</p>',
                'default_in_app_title' => 'Session Concluded',
                'default_in_app_body' => 'Session {{session_code}} has concluded and grading is open.',
                'default_action_label' => 'Open Grading',
                'default_action_route' => 'grading.index',
                'channels' => ['in_app'],
                'tokens' => self::tokens(['session_code']),
            ],
        ];
    }

    /**
     * @return list<array{
     *     event_key: string,
     *     slug: string,
     *     name: string,
     *     description: string,
     *     default_email_subject: string,
     *     default_email_body: string,
     *     default_in_app_title: string,
     *     default_in_app_body: string,
     *     default_action_label: string,
     *     default_action_route: string,
     *     channels: list<string>,
     *     tokens: list<array{token: string, label: string, sample: string}>
     * }>
     */
    public static function resultsSlots(): array
    {
        return [
            [
                'event_key' => 'result.released.online.v1',
                'slug' => 'released-online',
                'name' => 'Standard Score Release Notice',
                'description' => 'Sent to candidates when standardized entrance exam results are published online.',
                'default_email_subject' => 'Examination Results Available — {{institution_name}}',
                'default_email_body' => '<p>Dear <strong>{{applicant_name}}</strong>,</p><p>Your entrance examination results for <strong>{{institution_name}}</strong> are now available. Please sign in to the Applicant Portal to view your official scorecard.</p>',
                'default_in_app_title' => 'Examination Results Available',
                'default_in_app_body' => 'Your official entrance examination results are now available in the portal.',
                'default_action_label' => 'View Results Scorecard',
                'default_action_route' => 'applicant.portal',
                'channels' => ['in_app', 'mail'],
                'tokens' => [
                    ['token' => '{{applicant_name}}', 'label' => 'Applicant Name', 'sample' => 'Maria Santos'],
                    ['token' => '{{application_no}}', 'label' => 'Application Reference Code', 'sample' => 'AY26-00812'],
                    ['token' => '{{institution_name}}', 'label' => 'Institution Name', 'sample' => 'Central State University'],
                    ['token' => '{{portal_url}}', 'label' => 'Portal URL', 'sample' => 'http://localhost:18000/applicant/login'],
                ],
            ],
            [
                'event_key' => 'result.released.f2f.v1',
                'slug' => 'released-f2f',
                'name' => 'In-Person Guidance Consultation Notice',
                'description' => 'Sent to candidates whose profile requires an in-person guidance counseling session before score disclosure.',
                'default_email_subject' => 'Your Release Day Information',
                'default_email_body' => '<p>Dear <strong>{{applicant_name}}</strong>,</p><p>Your entrance examination assessment has been completed. In accordance with university guidance policies, your results will be reviewed with you in a face-to-face consultation with a university counselor.</p><p>Please check the Applicant Portal for details on available consultation dates, room venues, and office hours.</p>',
                'default_in_app_title' => 'Release Day Information',
                'default_in_app_body' => 'Your examination results are being prepared for your Release Day encounter.',
                'default_action_label' => 'View Consultation Info',
                'default_action_route' => 'applicant.portal',
                'channels' => ['in_app'],
                'tokens' => self::tokens(['applicant_name', 'application_no', 'institution_name', 'portal_url']),
            ],
            [
                'event_key' => 'result.unreleased.v1',
                'slug' => 'unreleased',
                'name' => 'Result Retraction Notice',
                'description' => 'Dispatched if results are temporarily withheld or recalled for scoring audit.',
                'default_email_subject' => 'Notice: Temporary Score Withholding for Administrative Review',
                'default_email_body' => '<p>Dear <strong>{{applicant_name}}</strong>,</p><p>Please be advised that your examination result is temporarily unavailable in the portal while an administrative review or scoring calibration is conducted. You will be notified once the final calibrated results are restored.</p>',
                'default_in_app_title' => 'Result Under Review',
                'default_in_app_body' => 'Your exam result is temporarily unavailable during an administrative review.',
                'default_action_label' => 'Check Portal',
                'default_action_route' => 'applicant.portal',
                'channels' => ['in_app', 'mail'],
                'tokens' => self::tokens(['applicant_name', 'application_no', 'institution_name', 'portal_url']),
            ],
            [
                'event_key' => 'consultation.session_published.v1',
                'slug' => 'consultation-published',
                'name' => 'Release Day Session Schedule Notice',
                'description' => 'Notifies assigned applicants about a scheduled Release Day session.',
                'default_email_subject' => 'Release Day Schedule — {{session_code}}',
                'default_email_body' => '<p>Dear <strong>{{applicant_name}}</strong>,</p><p>Your Release Day session <strong>{{session_code}}</strong> is scheduled for <strong>{{consultation_date}} at {{time_slot}}</strong> in <strong>{{room_name}}</strong>. Please sign in to the Applicant Portal to view your appointment details.</p>',
                'default_in_app_title' => 'Release Day Schedule Confirmed',
                'default_in_app_body' => 'Release Day session {{session_code}} is scheduled for {{consultation_date}} at {{time_slot}}.',
                'default_action_label' => 'View Release Day',
                'default_action_route' => 'applicant.consultation',
                'channels' => ['in_app', 'mail'],
                'tokens' => self::tokens(['applicant_name', 'application_no', 'institution_name', 'portal_url', 'session_code', 'consultation_date', 'time_slot', 'room_name']),
            ],
            [
                'event_key' => 'consultation.slot_reserved.v1',
                'slug' => 'consultation-reserved',
                'name' => 'Release Day Appointment Confirmation',
                'description' => 'Confirms an applicant’s individual Release Day appointment in a session.',
                'default_email_subject' => 'Your Release Day Appointment — {{consultation_date}} at {{time_slot}}',
                'default_email_body' => '<p>Dear <strong>{{applicant_name}}</strong>,</p><p>Your Release Day appointment on <strong>{{consultation_date}} at {{time_slot}}</strong> is confirmed. This confirms your reservation, not completion of the encounter or availability of results. Review your appointment details in the portal.</p>',
                'default_in_app_title' => 'Release Day Appointment Confirmed',
                'default_in_app_body' => 'Your Release Day appointment is confirmed for {{consultation_date}} at {{time_slot}}.',
                'default_action_label' => 'View Release Day Appointment',
                'default_action_route' => 'applicant.consultation',
                'channels' => ['in_app'],
                'tokens' => self::tokens(['applicant_name', 'application_no', 'institution_name', 'portal_url', 'session_code', 'consultation_date', 'time_slot', 'room_name']),
            ],
            [
                'event_key' => 'consultation.online_invited.v1',
                'slug' => 'consultation-online-invited',
                'name' => 'Online Consultation Invitation',
                'description' => 'Dispatched after a counselor starts an online consultation for an applicant.',
                'default_email_subject' => 'Your online consultation is ready',
                'default_email_body' => '<p>Your counselor has started an online consultation. Sign in to the NEXIAM portal to join.</p>',
                'default_in_app_title' => 'Online consultation ready',
                'default_in_app_body' => 'Your counselor has started an online consultation. Sign in to join.',
                'default_action_label' => 'Open NEXIAM portal',
                'default_action_route' => 'applicant.portal',
                'channels' => ['in_app'],
                'tokens' => self::tokens(['applicant_name', 'application_no', 'institution_name', 'portal_url', 'consultation_date', 'time_slot']),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function slotsFor(string $domain): array
    {
        $slots = match ($domain) {
            'admissions' => self::admissionsSlots(),
            'examination' => self::examinationSlots(),
            'results' => self::resultsSlots(),
            default => [],
        };

        return array_map(static fn (array $slot): array => [
            ...$slot,
            'authoring_mode' => self::authoringMode($domain, $slot['slug']),
        ], $slots);
    }

    public static function authoringMode(string $domain, string $slug): string
    {
        if (in_array([$domain, $slug], [
            ['admissions', 'submitted'],
            ['admissions', 'new-submission'],
            ['examination', 'slot-reserved'],
            ['examination', 'session-completed'],
            ['results', 'consultation-online-invited'],
        ], true)) {
            return 'builtin';
        }

        return in_array([$domain, $slug], [
            ['admissions', 'accepted'],
            ['examination', 'session-published'],
            ['results', 'released-online'],
        ], true) ? 'email_pdf' : 'email';
    }

    /** @return array<string, mixed>|null */
    public static function builtinMessageDefinition(string $domain, string $slug): ?array
    {
        if (self::authoringMode($domain, $slug) !== 'builtin'
            && ! ($domain === 'examination' && $slug === 'proctor-duty-assigned')) {
            return null;
        }

        $message = config('notification-messages.builtins')[$domain.'.'.$slug] ?? null;
        $supported = ['purpose', 'title', 'body', 'fallback_title', 'fallback_body'];
        abort_unless(is_array($message) && array_diff($supported, array_keys($message)) === []
            && array_diff(array_keys($message), [...$supported, 'variants']) === [], 500,
            'Built-in notification copy configuration is incomplete.');
        foreach ($supported as $key) {
            $limit = in_array($key, ['body', 'fallback_body'], true) ? 500 : 255;
            abort_unless(is_string($message[$key]) && $message[$key] !== ''
                && mb_strlen($message[$key]) <= $limit, 500,
                'Built-in notification copy configuration is invalid.');
        }
        $variants = $message['variants'] ?? [];
        $expectedVariants = $slug === 'consultation-online-invited' ? ['immediate', 'scheduled'] : [];
        abort_unless(is_array($variants) && array_diff(array_keys($variants), $expectedVariants) === []
            && array_diff($expectedVariants, array_keys($variants)) === [], 500,
            'Built-in notification variants are invalid.');
        foreach ($variants as $variant) {
            $variantFields = ['label', 'title', 'body', 'fallback_title', 'fallback_body'];
            abort_unless(is_array($variant) && array_diff($variantFields, array_keys($variant)) === []
                && array_diff(array_keys($variant), $variantFields) === [], 500,
                'Built-in notification variant content is incomplete.');
            foreach ($variantFields as $field) {
                $limit = $field === 'body' || $field === 'fallback_body' ? 500 : 255;
                abort_unless(is_string($variant[$field]) && $variant[$field] !== ''
                    && mb_strlen($variant[$field]) <= $limit, 500,
                    'Built-in notification variant content is invalid.');
            }
        }
        $keys = self::builtinContextKeys($domain, $slug);
        foreach ([$message, ...array_values($variants)] as $copy) {
            foreach (['title', 'body', 'fallback_title', 'fallback_body'] as $field) {
                if (! is_string($copy[$field] ?? null)) {
                    continue;
                }
                preg_match_all('/\\{\\{\\s*([^{}]+?)\\s*\\}\\}/', $copy[$field], $matches);
                abort_unless(array_diff(array_map('trim', $matches[1]), $keys) === [], 500,
                    'Built-in notification copy contains an unsupported fact.');
            }
            if (isset($copy['label'])) {
                abort_unless(is_string($copy['label']) && $copy['label'] !== '', 500,
                    'Built-in notification variant label is invalid.');
            }
        }

        return $message;
    }

    /** @return list<string> */
    public static function builtinContextKeys(string $domain, string $slug): array
    {
        return match ([$domain, $slug]) {
            ['admissions', 'new-submission'], ['admissions', 'submitted'] => ['application_no'],
            ['examination', 'slot-reserved'] => ['session_code', 'exam_date'],
            ['examination', 'proctor-duty-assigned'] => ['session_code', 'exam_date', 'staff_name'],
            ['examination', 'session-completed'] => ['session_code'],
            ['results', 'consultation-online-invited'] => ['scheduled_at'],
            ['results', 'consultation-reserved'] => ['consultation_date', 'counselor_name', 'session_code'],
            default => [],
        };
    }

    /** @return array{purpose:string,title:string,body:string,variants:list<array{key:string,label:string,title:string,body:string}>}|null */
    public static function builtinMessage(string $domain, string $slug): ?array
    {
        $message = self::builtinMessageDefinition($domain, $slug);
        if ($message === null) {
            return null;
        }

        $variants = [];
        foreach ($message['variants'] ?? [] as $key => $variant) {
            $variants[] = ['key' => (string) $key, 'label' => (string) $variant['label'],
                'title' => (string) $variant['title'], 'body' => (string) $variant['body']];
        }

        return ['purpose' => (string) $message['purpose'], 'title' => (string) $message['title'],
            'body' => (string) $message['body'], 'variants' => $variants];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findBySlug(string $domain, string $slug): ?array
    {
        foreach (self::slotsFor($domain) as $slot) {
            if ($slot['slug'] === $slug) {
                return $slot;
            }
        }

        return null;
    }

    /**
     * Lookup-only contract for rendering old delivered duty notices; this family is not selectable.
     *
     * @return array<string, mixed>|null
     */
    public static function historicalSlotBySlug(string $domain, string $slug): ?array
    {
        if ($domain !== 'examination' || $slug !== 'proctor-duty-assigned') {
            return null;
        }

        return [
            'event_key' => 'exam.proctor_duty_assigned.v1',
            'slug' => $slug,
            'default_action_route' => 'exams.monitoring',
            'tokens' => self::tokens(['staff_name', 'session_code', 'exam_date', 'exam_time', 'room_name']),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findByEventKey(string $eventKey): ?array
    {
        foreach (['admissions', 'examination', 'results'] as $domain) {
            foreach (self::slotsFor($domain) as $slot) {
                if ($slot['event_key'] === $eventKey) {
                    return $slot;
                }
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function deskNoticeSummaries(string $domain, int $institutionId): array
    {
        return CommunicationTemplate::query()
            ->where('institution_id', $institutionId)
            ->where('domain', $domain)
            ->where('slug', '!=', 'proctor-duty-assigned')
            ->with('versions')
            ->get()
            ->map(function (CommunicationTemplate $template): array {
                $slot = self::findBySlug($template->domain, $template->slug) ?? [];
                $activeVersion = $template->versions->firstWhere('version', $template->active_version);
                $actionRoute = $activeVersion?->action_route ?? $slot['default_action_route'] ?? null;
                $actionUrl = $actionRoute !== null && Route::has($actionRoute) ? AdmissionExaminationNoticeContext::url($actionRoute) : null;
                $supportsPdf = self::supportsPdf($template->domain, $template->slug, $template->event_key);

                return [
                    'key' => $template->slug,
                    'event_key' => $template->event_key,
                    'title' => $template->name,
                    'description' => $template->description,
                    'active_version' => $template->active_version,
                    'sample_subject' => $activeVersion?->email_subject ?? $slot['default_email_subject'] ?? null,
                    'sample_body' => $activeVersion?->email_body ?? $slot['default_email_body'] ?? null,
                    'in_app_title' => $activeVersion?->in_app_title ?? $slot['default_in_app_title'] ?? null,
                    'in_app_body' => $activeVersion?->in_app_body ?? $slot['default_in_app_body'] ?? null,
                    'action_label' => $activeVersion?->action_label ?? $slot['default_action_label'] ?? null,
                    'action_url' => $actionUrl,
                    'supports_pdf' => $supportsPdf,
                    'pdf_name' => match ($template->slug) {
                        'session-published' => 'Examination-Permit.pdf',
                        'released-online' => 'Official-Scorecard.pdf',
                        'accepted' => 'Official-Offer-Letter.pdf',
                        default => null,
                    },
                    'channels' => $slot['channels'] ?? ['in_app', 'mail'],
                    'tokens' => $slot['tokens'] ?? [],
                ];
            })
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function sampleContextFor(string $domain, string $slug): array
    {
        $slot = self::findBySlug($domain, $slug);

        if ($slot === null) {
            return [];
        }

        $context = [];

        foreach ($slot['tokens'] as $token) {
            $key = substr($token['token'], 2, -2);
            $context[$key] = match ($key) {
                'portal_url' => AdmissionExaminationNoticeContext::url($domain === 'examination' ? 'applicant.schedule'
                    : (str_starts_with($slug, 'consultation-') ? 'applicant.consultation' : 'applicant.portal')),
                'queue_url' => AdmissionExaminationNoticeContext::url('admissions.index'),
                default => $token['sample'],
            };
        }

        return $context;
    }

    public static function supportsPdf(string $domain, string $slug, string $eventKey): bool
    {
        return in_array([$domain, $slug, $eventKey], [
            ['admissions', 'accepted', 'admission.accepted.v1'],
            ['examination', 'session-published', 'exam.session_published.v1'],
            ['results', 'released-online', 'result.released.online.v1'],
        ], true);
    }

    /** @return list<array{token:string,label:string,sample:string}> */
    private static function baseTokens(): array
    {
        return [
            ['token' => '{{applicant_name}}', 'label' => 'Applicant Name', 'sample' => 'Maria Santos'],
            ['token' => '{{application_no}}', 'label' => 'Application Reference Code', 'sample' => 'AY26-00812'],
            ['token' => '{{preferred_program}}', 'label' => 'Preferred Program', 'sample' => 'BS Computer Science'],
            ['token' => '{{submission_date}}', 'label' => 'Submission Date', 'sample' => 'October 4, 2026'],
            ['token' => '{{institution_name}}', 'label' => 'Institution Name', 'sample' => 'Central State University'],
            ['token' => '{{portal_url}}', 'label' => 'Portal URL', 'sample' => 'http://localhost:18000/applicant/login'],
        ];
    }

    /** @param list<string> $keys
     * @return list<array{token:string,label:string,sample:string}>
     */
    private static function tokens(array $keys): array
    {
        $baseTokens = self::baseTokens();
        $catalog = array_combine(
            array_map(static fn (array $token): string => trim($token['token'], '{}'), $baseTokens),
            $baseTokens,
        );
        $catalog['document_requests'] = ['token' => '{{document_requests}}', 'label' => 'Itemized Document Instructions', 'sample' => 'Form 138: Please present Form 138 to the Registrar for inspection.'];
        foreach ([
            'staff_name' => ['Staff Name', 'Synthetic Staff'],
            'session_code' => ['Session Code', 'SYN-SESSION-001'],
            'exam_date' => ['Exam Date', 'October 12, 2026'],
            'exam_time' => ['Exam Time', '8:00 AM'],
            'room_name' => ['Room', 'Synthetic Room'],
            'consultation_date' => ['Consultation Date', 'October 12, 2026'],
            'time_slot' => ['Consultation Time', '8:00 AM'],
        ] as $key => [$label, $sample]) {
            $catalog[$key] = ['token' => '{{'.$key.'}}', 'label' => $label, 'sample' => $sample];
        }

        return array_map(static fn (string $key): array => $catalog[$key], $keys);
    }
}
