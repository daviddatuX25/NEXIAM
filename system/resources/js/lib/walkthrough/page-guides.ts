import type { TourDefinition, TourDomain, TourPersona, TourStep } from './types';
import { applicantAccessKeySafetyTour } from './tours/applicant-access-key-safety';
import { applicantApplyGuideTour } from './tours/applicant-apply-guide';
import { applicantConsultationTour } from './tours/applicant-consultation';
import { applicantExamScheduleTour } from './tours/applicant-exam-schedule';
import { applicantMyApplicationTour } from './tours/applicant-my-application';
import { applicantPortalTour } from './tours/applicant-portal';
import { applicantProgramChoiceTour } from './tours/applicant-program-choice';
import { applicantQrTour } from './tours/applicant-qr';
import { assessmentGradingDashboardTour } from './tours/assessment-grading-dashboard';
import { assessmentOmrCameraScannerTour } from './tours/assessment-omr-camera-scanner';
import { consultationHubTour } from './tours/consultation-hub';
import { consultationSchedulingTour } from './tours/consultation-scheduling';
import { dataIngestionTour } from './tours/data-ingestion';
import { enrollmentEligibilityTour } from './tours/enrollment-eligibility';
import { examMaterialsTour } from './tours/exam-materials';
import { examMonitoringTour } from './tours/exam-monitoring';
import { examSessionsTour } from './tours/exam-sessions';
import { programHeadOverviewTour } from './tours/program-head-overview';
import { releaseBatchReleasingTour } from './tours/release-batch-releasing';
import { setupReadinessHubTour } from './tours/setup-readiness-hub';
import { staffOperationsDashboardTour } from './tours/staff-operations-dashboard';
import { xiamKnowledgeTour } from './tours/xiam-knowledge';

export type PageGuideStatus = 'covered' | 'deferred' | 'excluded';
export interface PageGuideCoverageEntry {
    readonly componentName: string;
    readonly routePatterns: readonly string[];
    readonly status: PageGuideStatus;
    readonly tourKey: string;
    readonly reason?: string;
}

type GuideInput = readonly [component: string, routes: readonly string[], key: string, title: string, domain: TourDomain, text: string, persona?: TourPersona, deferred?: string];

interface ApplicantPageGuideProps {
    readonly page_state?: 'ready' | 'empty' | 'denied' | 'no_attempt';
    readonly release_day?: {
        readonly appointment: { readonly status: 'assigned' | 'consulted' } | null;
        readonly decision: { readonly state: 'completed' | 'deferred' | 'referred' | 'unrecorded'; readonly recorded_at: string | null };
        readonly follow_ups: readonly {
            readonly status: 'review_pending' | 'appearance_requested';
            readonly program: string | null;
            readonly office: { readonly id: number; readonly name: string; readonly building: string | null; readonly floor: string | null; readonly coordinates_available: boolean } | null;
            readonly instruction_recorded: false;
        }[];
        readonly result: { readonly available: boolean; readonly review_available: boolean; readonly href: string | null };
        readonly program_choice: { readonly available: boolean; readonly href: string | null };
    } | null;
    readonly clearance?: {
        readonly status: 'not_ready' | 'ready' | 'confirmed' | 'revoked';
        readonly requirements: { readonly mandatory_pending_count: number };
        readonly correction_requests: readonly unknown[];
    } | null;
    readonly scoring?: {
        readonly status: 'not_started' | 'in_progress' | 'finalized';
        readonly recordings: readonly unknown[];
        readonly finalization: { readonly finalized_at: string | null } | null;
    };
    readonly exam_day?: {
        readonly page_state: 'ready' | 'empty' | 'denied';
        readonly session: { readonly code: string; readonly status: string } | null;
        readonly attendance: { readonly outcome: string | null } | null;
    };
}
const complete = { kind: 'complete' } as const;
const staff: TourPersona = 'staff-copilot';
const applicant: TourPersona = 'applicant-mentor';
const pages: readonly GuideInput[] = [
['Applicant/Portal',['/applicant/portal'],'applicant-portal','Your Admission Home','applicant','Your Home keeps the current six grouped milestones and official progress. Open a milestone for details; optional program acknowledgement is not required to continue.',applicant],
['Applicant/Schedule',['/applicant/schedule'],'applicant-exam-schedule','Exam Scheduling','applicant','Review the current scheduling status. Reserve only from slots shown as available; appointment status follows the recorded assignment.',applicant],
['Applicant/ReleaseSchedule',['/applicant/release-schedule'],'applicant-consultation','Release Day Appointment','applicant','Review your recorded Release Day appointment and status. Appointment, result delivery and later clearance are separate records.',applicant],
['Applicant/ProgramChoice',['/applicant/program-choice'],'applicant-program-choice','Program Choice','applicant','Review the current selection and available alternatives. Supplemental acknowledgement is optional; a plan does not change official selection.',applicant],
['Applicant/Results',['/applicant/results'],'page:Applicant/Results','My Results','applicant','This page reports only result information currently released to your account. Decision and document delivery may have different statuses.',applicant],
['Applicant/ReleaseDay',['/applicant/release-day'],'page:Applicant/ReleaseDay','Release Day','applicant','Your recorded outcome, appointment, result availability and current instructions are separate facts. This page does not establish clearance or external enrollment.',applicant],
['Applicant/AdmissionClearance',['/applicant/admission-clearance'],'page:Applicant/AdmissionClearance','Admission Clearance','applicant','Review document checks and current clearance. Submitted documents may await review; only explicitly requested corrections need action. Clearance is not external enrollment.',applicant],
['Applicant/MyApplication',['/applicant/applications'],'applicant-my-application','My Application','applicant','Review submitted application, document-check status and program preferences. Follow a correction only when the Registrar explicitly requested one.',applicant],
['Applicant/Correction',['/applicant/correction'],'page:Applicant/Correction','Application Correction','applicant','Review the requested items and submit only information asked for by this correction form.',applicant],
['Applicant/ExamDay',['/applicant/exam-day'],'page:Applicant/ExamDay','Exam Day','applicant','Use current exam-day information and check-in instructions shown. Guidance does not replace active staff instructions.',applicant,'Do not autoplay during check-in or examination.'],
['Applicant/ExamScoring',['/applicant/exam-scoring'],'page:Applicant/ExamScoring','Exam Scoring Status','applicant','Review scoring or result status currently available for your application. The guide does not expose unissued scores or predict release dates.',applicant],
['Applicant/AdmissionSupport',['/applicant/admission-support'],'page:Applicant/AdmissionSupport','FAQs & Office Inquiry','applicant','Browse published FAQs and office information or use visible inquiry controls. Submission is not proof of response or delivery.',applicant],
['Applicant/AccessHistory',['/applicant/access-history'],'page:Applicant/AccessHistory','Access History','applicant','This is event history available for your own account. Filters narrow displayed entries; they do not grant access to another record.',applicant],
['Applicant/MyQr',['/applicant/qr'],'applicant-qr','QR Pass','applicant','Present the pass shown for your account when requested. Printing or downloading uses only the controls currently displayed.',applicant],
['Applicant/Login',['/applicant/login'],'page:Applicant/Login','Applicant Sign In','applicant','Sign in with applicant access key and birthdate using the fields on this page. Never share the key.',applicant,'Do not autoplay while credentials are entered.'],
['Applicant/Inquiries/Index',['/applicant/inquiries'],'page:Applicant/Inquiries/Index','Your Office Inquiries','applicant','Review inquiry threads available to your account. Thread status is not a promise of reply.',applicant],
['Applicant/Inquiries/Show',['/applicant/inquiries/{inquiry}'],'page:Applicant/Inquiries/Show','Office Inquiry','applicant','Review messages and status in this thread. Send only requested information; never include credentials.',applicant],
['Admission/Apply',['/apply'],'applicant-apply-guide','Application Form','applicant','Complete the sections shown and review entries before submission. Confirmation provides next instructions after successful submission.',applicant],
['Admission/Confirmation',['/apply/confirmation'],'applicant-access-key-safety','Application Confirmation','applicant','This page may show a one-time access key. Keep it private and use visible print or continuation controls carefully.',applicant,'Wait until one-time credential display/printing is no longer active.'],
['Auth/Login',['/login'],'page:Auth/Login','Staff Sign In','setup','Enter staff credentials only on the institutional sign-in page. A walkthrough never asks for a password.',staff,'Do not autoplay on a credential-entry page.'],
['Auth/TwoFactorChallenge',['/two-factor-challenge'],'page:Auth/TwoFactorChallenge','Two-Factor Challenge','setup','Complete the current verification challenge using the configured method. Never share a verification code.',staff,'Do not interrupt authentication or MFA.'],
['Staff/Home',['/staff','/admin'],'staff-operations-dashboard','Staff Workspace','admissions','This dashboard summarizes areas available to your account. Workspace access follows capabilities and assigned scope.'],
['Staff/Profile/Show',['/staff/profile'],'page:Staff/Profile/Show','Staff Profile','setup','Review profile information and account preferences. Security credentials and recovery secrets use dedicated controls.'],
['Staff/Activate',['/staff/activate'],'page:Staff/Activate','Staff Account Activation','setup','Use activation instructions supplied by your institution and continue through secure setup.',staff,'Do not autoplay while redeeming an activation voucher.'],
['Staff/AccountSetup',['/staff/account-setup'],'page:Staff/AccountSetup','Staff Account Setup','setup','Complete displayed setup steps in order. Keep verification links and authentication details private.',staff,'Do not interrupt email verification, password setup or MFA enrollment.'],
['Staff/SetupEmailConfirmation',['/staff/account-setup/email/confirm/{token}'],'page:Staff/SetupEmailConfirmation','Email Confirmation','setup','Review the email setup state and controls available for the current verification.',staff,'Defer while a one-time verification token is consumed.'],
['Staff/ApplicantScan',['/scan/applicant/{code}'],'page:Staff/ApplicantScan','Applicant QR Lookup','admissions','Use the current scan result and authorized applicant actions. A scan code does not grant additional access.',staff,'Wait until QR capture/lookup task is complete.'],
['Staff/Logs/Index',['/staff/logs'],'page:Staff/Logs/Index','Audit History','setup','Use authorized filters to review recorded institutional activity. This page does not reveal records outside your scope.'],
['Staff/Personnel/Index',['/staff/personnel'],'page:Staff/Personnel/Index','Personnel Directory','setup','Review staff records visible to your authority. Office and rank scope remain server-controlled.'],
['Staff/Personnel/Prepare',['/staff/personnel/prepare'],'page:Staff/Personnel/Prepare','Prepare Staff Access','setup','Review readiness and personnel setup options available to you. This page does not issue credentials.'],
['Staff/Personnel/Create',['/staff/personnel/create'],'page:Staff/Personnel/Create','Create Staff Record','setup','Enter requested staff details and review office and access scope before saving.'],
['Staff/Personnel/Edit',['/staff/personnel/{staff}/edit'],'page:Staff/Personnel/Edit','Edit Staff Record','setup','Change only fields allowed by the visible form; rank and office safeguards remain in force.'],
['Staff/Personnel/Rollover',['/staff/personnel/rollover'],'page:Staff/Personnel/Rollover','Academic Cycle Rollover','setup','Review staff retention and deactivations for the target cycle. Archived accounts preserve past records while revoking current access. Incoming staff can be provisioned with single-use vouchers.'],
['Setup/Index',['/setup'],'setup-readiness-hub','Setup Readiness','setup','Review readiness areas available to your account and open a configuration page for an indicated gap.'],
['Setup/Cycles',['/setup/cycles'],'page:Setup/Cycles','Academic Cycles','setup','Review Academic Years and Semesters and use visible controls to manage cycle records.'],
['Setup/Cycles/Create',['/setup/cycles/create'],'page:Setup/Cycles/Create','Create Academic Cycle','setup','Enter requested Academic Year and Semester details, then review before saving.'],
['Setup/Cycles/Edit',['/setup/cycles/{cycle}/edit'],'page:Setup/Cycles/Edit','Edit Academic Cycle','setup','Review the selected cycle and change only currently available fields.'],
['Setup/Programs',['/setup/programs'],'page:Setup/Programs','Program Catalog','setup','Browse the institution program catalog and open a program for details.'],
['Setup/Programs/Hub',['/setup/programs/{program}'],'page:Setup/Programs/Hub','Program Governance','setup','Review the selected program’s catalog and governance information.'],
['Setup/Departments',['/setup/departments'],'page:Setup/Departments','Departments','setup','Review institution departments and use the visible controls to configure an office location where available. A configured destination is shown only through the authorized location context.'],
['Setup/Quotas',['/setup/quotas'],'page:Setup/Quotas','Program Quotas','admissions','Review cycle and program quota information. Capacity is not an applicant decision.'],
['Setup/ApplicationSuggestions',['/setup/application-suggestions'],'page:Setup/ApplicationSuggestions','Application Suggestions','admissions','Review configured suggestions and their listed scope before an authorized update.'],
['Setup/AiCenter',['/setup/ai-center'],'page:Setup/AiCenter','AI Control Center','setup','Review status and controls presented for your authority. Provider availability grants no permissions.'],
['Setup/Notifications',['/setup/notifications'],'page:Setup/Notifications','Notification Setup','setup','Review notification policy and configuration sections. A configured message is not proof of delivery.'],
['Setup/Notifications/Admissions',['/setup/notifications/admissions'],'page:Setup/Notifications/Admissions','Admissions Notifications','admissions','Review admissions notification settings and recorded delivery history.'],
['Setup/Notifications/Examination',['/setup/notifications/examination'],'page:Setup/Notifications/Examination','Examination Notifications','examinations','Review examination notification settings and recorded delivery history.'],
['Setup/Notifications/Results',['/setup/notifications/results'],'page:Setup/Notifications/Results','Results Notifications','admissions','Review result notification settings; result availability is a separate record.'],
['Setup/Notifications/Occurrence',['/setup/notifications/occurrences/{occurrence}'],'page:Setup/Notifications/Occurrence','Notification Occurrence','setup','Review recorded status and authorized recipient evidence. An attempt is not confirmed delivery.'],
['Setup/Notifications/Templates/Message',['/setup/notifications/{domain}/{slug}/message'],'page:Setup/Notifications/Templates/Message','Message Template','setup','Review the selected message template and version before editing.'],
['Setup/Notifications/Templates/Studio',['/setup/notifications/{domain}/{slug}/studio'],'page:Setup/Notifications/Templates/Studio','Notification Template Studio','setup','Review template family and preview controls. Preview does not send a message.'],
['Setup/Notifications/Templates/PhysicalDocument',['/setup/notifications/{domain}/{slug}/physical-document'],'page:Setup/Notifications/Templates/PhysicalDocument','Physical Document Template','setup','Review the selected document template and version. Preview does not issue a document.'],
['Setup/OmrTest',['/setup/omr-test'],'page:Setup/OmrTest','OMR Readiness Check','examinations','Review readiness and Test Kit context. A readiness result is not physical qualification.'],
['Setup/BookletsIndex',['/setup/booklets'],'page:Setup/BookletsIndex','Booklets','setup','Browse booklet inventory and open one to inspect versioned content.'],
['Setup/Booklets',['/setup/booklets/{booklet}'],'page:Setup/Booklets','Booklet Details','setup','Review this booklet and available version controls; changes follow draft lifecycle.'],
['Setup/TestKits/Index',['/setup/exam-materials/test-kits'],'exam-materials','Test Kits','setup','Browse Test Kits and select one to review exact setup context.'],
['Setup/TestKits/Create',['/setup/exam-materials/test-kits/create'],'page:Setup/TestKits/Create','Create Test Kit','setup','Enter requested kit details and review selected materials before saving.'],
['Setup/TestKits/Show',['/setup/exam-materials/test-kits/{kit}'],'page:Setup/TestKits/Show','Test Kit Details','setup','Review selected kit and version history; opening details does not activate it.'],
['Setup/TestKits/TestSetup',['/setup/exam-materials/test-kits/{kit}/setup'],'page:Setup/TestKits/TestSetup','Test Setup','setup','Review selected Test Setup and paper-pair context. Save and Lock follow the visible lifecycle.'],
['Setup/TestInstruments/Index',['/setup/test-instruments'],'page:Setup/TestInstruments/Index','Test Instruments','setup','Browse instrument definitions and inspect subtests and current versions.'],
['Setup/TestInstruments/Create',['/setup/test-instruments/create'],'page:Setup/TestInstruments/Create','Create Test Instrument','setup','Enter requested instrument details; its definition is separate from a kit setup.'],
['Setup/TestInstruments/Show',['/setup/test-instruments/{instrument}'],'page:Setup/TestInstruments/Show','Test Instrument Details','setup','Review instrument version and structure; this does not establish scoring norms.'],
['Setup/TestInstruments/Edit',['/setup/test-instruments/{instrument}/edit'],'page:Setup/TestInstruments/Edit','Edit Test Instrument','setup','Review instrument version and edit only the draft shown.'],
['Setup/Aptitude',['/setup/aptitude'],'page:Setup/Aptitude','Aptitude Areas','setup','Review aptitude-area catalog and available definition controls.'],
['Setup/AptitudeScoring',['/setup/aptitude/{area}/scoring'],'page:Setup/AptitudeScoring','Aptitude Scoring Configuration','setup','Review selected area configuration; this is not an approved norm package.'],
['Setup/ConsultationPolicy',['/setup/consultation-policy'],'page:Setup/ConsultationPolicy','Release Day Policy','consultation','Review Release Day policy for the selected cycle and available controls.'],
['Setup/IntakeFields',['/setup/intake-fields'],'page:Setup/IntakeFields','Intake Fields','admissions','Review configured applicant intake fields and cycle applicability.'],
['Setup/Requirements/Index',['/setup/requirements'],'page:Setup/Requirements/Index','Admission Requirements','admissions','Browse configured admission requirements and open one for details.'],
['Setup/Requirements/Create',['/setup/requirements/create'],'page:Setup/Requirements/Create','Create Admission Requirement','admissions','Enter requested requirement details and review track and kind before saving.'],
['Setup/Requirements/Edit',['/setup/requirements/{requirement}/edit'],'page:Setup/Requirements/Edit','Edit Admission Requirement','admissions','Review selected requirement and update fields available to your account.'],
['Setup/Rooms/Index',['/setup/rooms'],'page:Setup/Rooms/Index','Rooms and Spaces','setup','Browse configured rooms. A room is not an applicant destination without an authorized mapping.'],
['Setup/Rooms/Create',['/setup/rooms/create'],'page:Setup/Rooms/Create','Create Room','setup','Enter requested room details; location is not automatically assigned to a department.'],
['Setup/Rooms/Show',['/setup/rooms/{room}'],'page:Setup/Rooms/Show','Room Details','setup','Review configured room information and associated seating controls.'],
['Setup/Rooms/Edit',['/setup/rooms/{room}/edit'],'page:Setup/Rooms/Edit','Edit Room','setup','Review selected room details and make authorized changes.'],
['Setup/SeatingArrangements/Index',['/setup/seating-arrangements'],'page:Setup/SeatingArrangements/Index','Seating Arrangements','examinations','Browse arrangements and inspect configured layouts.'],
['Setup/SeatingArrangements/Create',['/setup/seating-arrangements/create'],'page:Setup/SeatingArrangements/Create','Create Seating Arrangement','examinations','Use form and preview to configure layout; preview is not a live assignment.'],
['Setup/SeatingArrangements/Edit',['/setup/seating-arrangements/{arrangement}/edit'],'page:Setup/SeatingArrangements/Edit','Edit Seating Arrangement','examinations','Review selected layout and preview before saving an authorized draft.'],
['Setup/AdmissionSupport/TabFmStudio',['/setup/admission-support/tabfm'],'admission-support','Admission Decision Support','setup','Review benchmark and analysis provenance. Decision support is advisory; staff review recorded evidence.'],
['Setup/AdmissionSupport/YukeeStudio',['/setup/admission-support/yukee'],'page:Setup/AdmissionSupport/YukeeStudio','Admission Support Studio','setup','Review support configuration; a model output is not an admission decision.'],
['Setup/NormPackages/Index',['/setup/norm-packages'],'page:Setup/NormPackages/Index','Norm Packages','setup','Browse authorized norm packages and review their version and status.'],
['Setup/NormPackages/Create',['/setup/norm-packages/create'],'page:Setup/NormPackages/Create','Create Norm Package','setup','Enter requested package information and review provenance before saving.'],
['Setup/NormPackages/Edit',['/setup/norm-packages/{package}/edit'],'page:Setup/NormPackages/Edit','Edit Norm Package','setup','Review selected version and edit available draft fields.'],
['Setup/NormPackages/Show',['/setup/norm-packages/{package}'],'page:Setup/NormPackages/Show','Norm Package Details','setup','Review version and provenance; presence does not prove applicability to a result.'],
['Setup/ResultSheetTemplates/Index',['/setup/result-sheet-templates'],'page:Setup/ResultSheetTemplates/Index','Result Sheet Templates','setup','Browse templates and review their current versions and statuses.'],
['Setup/ResultSheetTemplates/Create',['/setup/result-sheet-templates/create'],'page:Setup/ResultSheetTemplates/Create','Create Result Sheet Template','setup','Review placeholders and template fields before saving a draft.'],
['Setup/ResultSheetTemplates/Edit',['/setup/result-sheet-templates/{template}/edit'],'page:Setup/ResultSheetTemplates/Edit','Edit Result Sheet Template','setup','Review selected template and version; editing does not issue documents.'],
['Setup/OmrConfiguration/Index',['/setup/omr-configuration'],'page:Setup/OmrConfiguration/Index','OMR Configuration','setup','Review templates, booklets and pairings; inventory does not establish physical qualification.'],
['Setup/OmrConfiguration/Intake',['/setup/omr-configuration/intake'],'page:Setup/OmrConfiguration/Intake','OMR Intake Configuration','setup','Review intake settings and available configuration controls.'],
['Setup/OmrConfiguration/Templates/Index',['/setup/omr-configuration/templates'],'page:Setup/OmrConfiguration/Templates/Index','Answer Sheet Templates','setup','Browse answer-sheet templates and review selected version and state.'],
['Setup/OmrConfiguration/Templates/Show',['/setup/omr-configuration/templates/{template}'],'page:Setup/OmrConfiguration/Templates/Show','Answer Sheet Template','setup','Review selected template version, status and available actions.'],
['Setup/OmrConfiguration/Templates/Builder',['/setup/omr-configuration/templates/{template}/builder'],'page:Setup/OmrConfiguration/Templates/Builder','Answer Sheet Template Builder','setup','Work with the current draft; preview does not establish physical qualification.',staff,'Wait until active template editing is complete.'],
['Setup/OmrConfiguration/Booklets/Index',['/setup/omr-configuration/booklets'],'page:Setup/OmrConfiguration/Booklets/Index','OMR Booklets','setup','Browse booklets and review versioned questions.'],
['Setup/OmrConfiguration/Booklets/Show',['/setup/omr-configuration/booklets/{booklet}'],'page:Setup/OmrConfiguration/Booklets/Show','OMR Booklet Details','setup','Review this booklet version and draft actions.'],
['Setup/OmrConfiguration/Booklets/Create',['/setup/omr-configuration/booklets/create'],'page:Setup/OmrConfiguration/Booklets/Create','Create OMR Booklet','setup','Use authoring controls to prepare a draft and review selected aptitude areas.'],
['Setup/OmrConfiguration/Pairs/Create',['/setup/omr-configuration/pairs/create'],'page:Setup/OmrConfiguration/Pairs/Create','Pair Template and Booklet','setup','Review exact template and booklet versions selected before saving.'],
['Setup/OmrConfiguration/Canonical/Index',['/setup/omr-configuration/canonical'],'page:Setup/OmrConfiguration/Canonical/Index','Canonical Templates','setup','Browse canonical template references and review workflow state.'],
['Setup/OmrConfiguration/Canonical/CanonicalWorkflow',['/setup/omr-configuration/canonical/{template}'],'page:Setup/OmrConfiguration/Canonical/CanonicalWorkflow','Canonical Template Workflow','setup','Review selected canonical workflow and version-specific actions.'],
['Setup/OmrConfiguration/Canonical/Import',['/setup/omr-configuration/canonical/{template}/import'],'page:Setup/OmrConfiguration/Canonical/Import','Canonical Template Import','setup','Review import operation and status; import does not activate or qualify a template.',staff,'Wait until import/review operation finishes.'],
['Setup/ReportTemplates/Index',['/setup/report-templates'],'page:Setup/ReportTemplates/Index','Report Templates','setup','Browse available report-template surfaces and their configuration.'],
['Setup/ReportTemplates/Surface',['/setup/report-templates/{surface}'],'page:Setup/ReportTemplates/Surface','Report Template Surface','setup','Review selected report surface and templates; preview is not issuance.'],
['Setup/ReportTemplates/Create',['/setup/report-templates/{surface}/create'],'page:Setup/ReportTemplates/Create','Create Report Template','setup','Use visible field policy and form to prepare a draft.'],
['Setup/ReportTemplates/Edit',['/setup/report-templates/{surface}/{template}/edit'],'page:Setup/ReportTemplates/Edit','Edit Report Template','setup','Review selected template and field policy before authorized change.'],
['Setup/Institution/Index',['/setup/institution'],'page:Setup/Institution/Index','Institution Profile','setup','Review institution details and profile controls currently available.'],
['Communications/Index',['/communications'],'page:Communications/Index','Announcements','setup','Browse announcement records and available create, edit or publication controls. Draft is not published.'],
['Communications/Create',['/communications/create'],'page:Communications/Create','Create Announcement','setup','Enter requested content and audience, then review before saving.'],
['Communications/Edit',['/communications/{announcement}/edit'],'page:Communications/Edit','Edit Announcement','setup','Review announcement state and change only fields enabled in the form.'],
['Communications/Policies/Index',['/communications/policies'],'page:Communications/Policies/Index','Public Policies','setup','Browse policy records and publication states within your scope.'],
['Communications/Policies/Create',['/communications/policies/create'],'page:Communications/Policies/Create','Create Public Policy','setup','Enter policy content and publication details requested, then review before saving.'],
['Communications/Policies/Edit',['/communications/policies/{policy}/edit'],'page:Communications/Policies/Edit','Edit Public Policy','setup','Review selected policy and publication state before authorized change.'],
['Communications/Homepage/Index',['/communications/homepage'],'page:Communications/Homepage/Index','Homepage Content','setup','Review current homepage content and editing controls. Preview does not publish changes.'],
['Knowledge/Index',['/knowledge'],'xiam-knowledge','Knowledge Catalog','setup','Browse knowledge sources visible to your account. Source access and audience follow displayed scope.'],
['Knowledge/Create',['/knowledge/create'],'page:Knowledge/Create','Create Knowledge Source','setup','Choose source type and audience shown as available, then review before saving.'],
['Knowledge/Show',['/knowledge/{source}'],'page:Knowledge/Show','Knowledge Source Details','setup','Review source metadata and available controls. Extracted material is not necessarily approved for every audience.'],
['Admission/Index',['/admissions'],'page:Admission/Index','Admission Review Queue','admissions','Review records and filters available within authority. Open a record for its scoped checklist and review state.'],
['Admission/Show',['/admissions/{attempt}'],'page:Admission/Show','Admission Record','admissions','Review the authorized applicant record and document checklist. Explicit Registrar instructions determine required follow-up.'],
['Admission/Create',['/admissions/create'],'page:Admission/Create','Create Admission Record','admissions','Use visible intake fields and validation feedback, then review before saving.'],
['Admission/Edit',['/admissions/{attempt}/edit'],'page:Admission/Edit','Edit Admission Record','admissions','Review selected record and change only fields enabled for your authority.'],
['Admission/ApplicantAdmissionSupport',['/admissions/support/{attempt}'],'page:Admission/ApplicantAdmissionSupport','Applicant Admission Support','admissions','Review support information and visible controls. Private notes and protected assessment details are not guide content.'],
['Admission/Inquiries/Index',['/admission/inquiries'],'page:Admission/Inquiries/Index','Office Inquiry Queue','admissions','Review inquiry threads visible to your office and use available filters.'],
['Admission/Inquiries/Show',['/admission/inquiries/{inquiry}'],'page:Admission/Inquiries/Show','Office Inquiry Thread','admissions','Review authorized thread history and reply controls. Avoid credentials and unrelated sensitive data.'],
['Registrar/EnrollmentEligibility',['/registrar/enrollment-eligibility'],'enrollment-eligibility','Admission Clearance','admissions','Review prerequisites, document checks and recorded state. Confirm Admission Clearance is the final NEXIAM action; external enrollment is not tracked.'],
['ProgramHead/Index',['/staff/programs'],'program-head-overview','Assigned Programs','admissions','Review programs within assigned academic scope and open one for its authorized dashboard.'],
['ProgramHead/ProgramShow',['/staff/programs/{program}'],'program-head-overview','Program Dashboard','admissions','Review authorized applicant and quota summaries. Raw answer sheets and protected scoring material are unavailable through this guide.'],
['Examination/Index',['/exams/sessions'],'exam-sessions','Exam Session Planning','examinations','Review sessions and authorized planning controls. Assignments and publication follow actual session state.'],
['Examination/Create',['/exams/sessions/create'],'page:Examination/Create','Create Exam Session','examinations','Enter session details and review cycle, room and seating selections before saving.'],
['Examination/Edit',['/exams/sessions/{session}/edit'],'page:Examination/Edit','Edit Exam Session','examinations','Review selected session and assignments before available edits.'],
['Examination/Show',['/exams/sessions/{session}'],'page:Examination/Show','Exam Session Details','examinations','Review selected session, roster and publication state; actions depend on actual state.'],
['Examination/Waitlist',['/exams/waitlist'],'page:Examination/Waitlist','Exam Waitlist','examinations','Review eligible waitlist and filters. A listed applicant is not assigned until authorized assignment.'],
['Examination/MonitoringIndex',['/exams/monitoring'],'exam-monitoring','Exam Monitoring','examinations','Review displayed session activity and attendance evidence. Monitoring does not replace Test Administrator live-conduct procedure.'],
['Examination/Monitoring',['/exams/sessions/{session}/monitoring'],'exam-monitoring','Live Exam Monitoring','examinations','Use current attendance and session controls as part of active Test Administrator procedure.',staff,'Defer autoplay during live examination conduct.'],
['Examination/DirectAssessment/Create',['/exams/direct-assessment/create'],'page:Examination/DirectAssessment/Create','Direct Assessment Session','examinations','Review available cycle and session options and use displayed configuration controls.'],
['Assessment/Grading',['/grading'],'assessment-grading-dashboard','Scoring Dashboard','examinations','Review scoring sessions and status. Open a session to enter or review data only when authorized.'],
['Assessment/GradingSession',['/grading/sessions/{session}'],'page:Assessment/GradingSession','Scoring Session','examinations','Review session and scoring lifecycle. Finalized or frozen values remain protected by session state.'],
['Assessment/GradingScoreInput',['/grading/sessions/{session}/scores'],'page:Assessment/GradingScoreInput','Score Entry','examinations','Use visible score-entry fields and validation. Review before saving; locked values remain protected.',staff,'Defer autoplay during score entry or finalization.'],
['Assessment/Omr/Capture',['/scoring/omr/capture'],'assessment-omr-camera-scanner','OMR Capture','examinations','Follow capture and recovery instructions displayed. The guide never authorizes capture or bypasses evidence checks.',staff,'Do not autoplay during camera, capture or review.'],
['Assessment/Omr/Scanner',['/scoring/omr'],'assessment-omr-camera-scanner','OMR Scanner','examinations','Use displayed capture and recovery controls. Identity and recovery follow scanner evidence rules.',staff,'Do not autoplay during capture or recovery.'],
['Assessment/Omr/CameraSensor',['/scoring/omr/camera-sensor'],'page:Assessment/Omr/CameraSensor','Camera Pairing','examinations','Follow on-screen pairing state. Pairing codes and connection details are never repeated.',staff,'Defer while pairing or camera connection is active.'],
['Import/Index',['/imports'],'data-ingestion','Data Ingestion','setup','Choose an available import path and review the staged preview before confirming.',staff,'Defer while specialized import walkthrough, builder or review task is open.'],
['Import/Review',['/imports/{batch}/review'],'data-ingestion','Import Review','setup','Review staged rows, validation and resolution controls before confirmation.',staff,'Do not autoplay while resolving staged rows.'],
['Consultation/Dashboard',['/consultation'],'consultation-hub','Release Day Workspace','consultation','Review Release Day queues and recorded outcomes. Appointment, decision, result delivery and clearance are distinct states.'],
['Consultation/ConsultationSessions',['/consultation/sessions'],'consultation-scheduling','Release Day Scheduling','consultation','Review sessions, capacity and available scheduling controls. Appointment status follows current session record.'],
['Consultation/ConsultationSessionCreate',['/consultation/sessions/create'],'page:Consultation/ConsultationSessionCreate','Create Release Day Session','consultation','Enter requested session details and review cycle, venue and capacity before saving.'],
['Consultation/ConsultationSessionEdit',['/consultation/sessions/{session}/edit'],'page:Consultation/ConsultationSessionEdit','Edit Release Day Session','consultation','Review selected session state and assignments before available controls.'],
['Consultation/ConsultationSessionShow',['/consultation/sessions/{session}'],'page:Consultation/ConsultationSessionShow','Release Day Session','consultation','Review session and applicant assignments visible within your authority.'],
['Consultation/Waitlist',['/consultation/waitlist'],'page:Consultation/Waitlist','Release Day Waitlist','consultation','Review eligible applicants and filters. A waitlist entry is not a confirmed appointment.'],
['Consultation/OnlineEncounter',['/consultation/encounters/{encounter}'],'page:Consultation/OnlineEncounter','Online Encounter','consultation','Follow on-screen encounter controls. This guide does not expose tokens or interrupt a consultation.',staff,'Do not autoplay during a live encounter.'],
['Release/Index',['/release'],'release-batch-releasing','Result Release Workspace','admissions','Review readiness and available result-release operations. Preparation, release and printing are distinct states.'],
['Release/PrintBatch',['/release/sessions/{session}/print'],'page:Release/PrintBatch','Print Result Batch','admissions','Review batch and print status; the guide does not claim delivery.',staff,'Wait until print/receipt task completes.'],
['Release/ResultSheet',['/release/sessions/{session}/applicants/{applicant}'],'page:Release/ResultSheet','Result Sheet','admissions','Review authorized preview and issuance state. Preview does not establish issue or receipt.',staff,'Defer during result review or printing.'],
['Release/ResultSheetBulk',['/release/sessions/{session}/bulk'],'page:Release/ResultSheetBulk','Bulk Result Sheets','admissions','Review selected batch and previews before an available print action.',staff,'Defer during bulk printing.'],
['Reports/Index',['/reports'],'page:Reports/Index','Reports','setup','Review reports and filters available to your account. Aggregates remain within authorized scope.'],
['Notifications/Index',['/notifications'],'page:Notifications/Index','Notifications','setup','Review notifications addressed to your account and follow links to current pages.'],
['Home',['/'],'page:Home','Institution Home','setup','Browse public information currently published; this page does not show account-specific applicant status.'],
['Public/Faqs',['/faqs'],'page:Public/Faqs','Frequently Asked Questions','setup','Browse published answers and use category or office filters.'],
['Public/PrivacyPolicy',['/policies/privacy'],'page:Public/PrivacyPolicy','Privacy Policy','setup','Review the institution’s published privacy notice and current version details.'],
['Public/Policy',['/policies/{policy}'],'page:Public/Policy','Public Policy','setup','Review published policy content and current effective details.'],
['Public/Policies',['/policies'],'page:Public/Policies','Institution Policies','setup','Browse policies currently published for public access.'],
['Public/ProgramPolicies',['/programs/{code}/policies'],'page:Public/ProgramPolicies','Program Policies','setup','Review policies published for this program; these may differ from institution-wide policies.'],
['Public/Announcements',['/announcements','/programs/{code}/announcements'],'page:Public/Announcements','Announcements','setup','Browse published announcements and use filters or pagination.'],
['Public/Announcement',['/announcements/{announcement}'],'page:Public/Announcement','Announcement','setup','Review the published announcement and linked public information.'],
['Public/Program',['/programs/{code}'],'page:Public/Program','Program Information','setup','Review program information and public profile. Contact the listed office with questions not answered here.'],
['Foundation/DemoGuide',['/demo'],'page:Foundation/DemoGuide','Demonstration Guide','setup','Use demonstration materials for the bounded workflow; demo content is not a live admission decision.',staff,'Defer while a demo action or viewer task is active.'],
['Foundation/ResearchSessionGuide',['/demo/research','/demo/research/instruments/{role}'],'page:Foundation/ResearchSessionGuide','Research Session Guide','setup','Review selected participant instructions and stay within the named evaluation task.'],
['Foundation/ResearchFacilitatorGuide',['/demo/research/facilitator'],'page:Foundation/ResearchFacilitatorGuide','Research Facilitator Guide','setup','Review facilitator instructions and use controls shown for the research session.',staff,'Defer during active facilitation.'],
['Foundation/Glossary',['/glossary'],'page:Foundation/Glossary','Glossary','setup','Review current terminology and follow source references where available.'],
['Foundation/Status',['/status'],'page:Foundation/Status','Project Status','setup','Review displayed project status; it is not operational readiness authorization.'],
['Foundation/PracticePreparation',['/practice/preparation'],'page:Foundation/PracticePreparation','Practice Preparation','setup','Review isolated practice preparation state and visible controls.',staff,'Defer during preparation or preview.'],
['UiKit/Gallery',['/ui-kit'],'page:UiKit/Gallery','UI Kit Gallery','setup','Browse component examples and controls in this local interface gallery.'],
['Setup/ProgramPublicDetails',['/setup/programs/{program}/public-details'],'page:Setup/ProgramPublicDetails','Program Public Details','setup','Review the selected program public profile fields and edit only information available in this editor.'],
['Setup/ProgramPublicDetailsPreview',['/setup/programs/{program}/public-details/preview'],'page:Setup/ProgramPublicDetailsPreview','Program Profile Preview','setup','Review the public-facing profile preview; preview does not publish changes.'],
];

const existingFactories: Record<string, () => TourDefinition> = {
    'applicant-access-key-safety': applicantAccessKeySafetyTour,
    'applicant-apply-guide': applicantApplyGuideTour,
    'applicant-consultation': applicantConsultationTour,
    'applicant-exam-schedule': applicantExamScheduleTour,
    'applicant-my-application': applicantMyApplicationTour,
    'applicant-portal': applicantPortalTour,
    'applicant-program-choice': applicantProgramChoiceTour,
    'applicant-qr': applicantQrTour,
    'assessment-grading-dashboard': assessmentGradingDashboardTour,
    'assessment-omr-camera-scanner': assessmentOmrCameraScannerTour,
    'consultation-hub': consultationHubTour,
    'consultation-scheduling': consultationSchedulingTour,
    'data-ingestion': dataIngestionTour,
    'enrollment-eligibility': enrollmentEligibilityTour,
    'exam-materials': examMaterialsTour,
    'exam-monitoring': examMonitoringTour,
    'exam-sessions': examSessionsTour,
    'program-head-overview': programHeadOverviewTour,
    'release-batch-releasing': releaseBatchReleasingTour,
    'setup-readiness-hub': setupReadinessHubTour,
    'staff-operations-dashboard': staffOperationsDashboardTour,
    'xiam-knowledge': xiamKnowledgeTour,
};

const coverage: readonly PageGuideCoverageEntry[] = pages.map(([componentName, routePatterns, tourKey, _title, _domain, _text, _persona, reason]) => {
    const isPublic = componentName === 'Home' || componentName.startsWith('Public/');
    return {
        componentName,
        routePatterns,
        tourKey,
        status: isPublic ? 'excluded' : reason ? 'deferred' : 'covered',
        ...(isPublic ? { reason: 'Public visitors browse published content without automatic guides or floating launchers.' } : reason ? { reason } : {}),
    };
});

export const PAGE_GUIDE_COVERAGE = coverage;

export function getPageGuideCoverage(componentName: string, _url: string): PageGuideCoverageEntry | undefined {
    return coverage.find((entry) => entry.componentName === componentName);
}


function outcomeGuide(id: string, title: string, steps: readonly TourStep[]): TourDefinition {
    return {
        id,
        revision: 1,
        title: `${title} Guide`,
        domain: 'applicant',
        persona: applicant,
        start: steps[0]?.id ?? 'overview',
        steps,
    };
}

function resolveApplicantOutcomeGuide(componentName: string, props: unknown): TourDefinition | undefined {
    if (props === null || typeof props !== 'object' || Array.isArray(props)) return undefined;
    // These are the server-owned Inertia projection shapes declared by the four applicant pages.
    const pageProps = props as unknown as ApplicantPageGuideProps;

    if (componentName === 'Applicant/ReleaseDay') {
        const releaseDay = pageProps.release_day;
        if (!releaseDay) {
            if (pageProps.page_state !== 'empty') return undefined;
            return outcomeGuide('page:Applicant/ReleaseDay', 'Release Day', [
                {
                    id: 'no-release-day-record',
                    phase: 'overview',
                    title: 'No Release Day details',
                    text: 'No Release Day details are available for your account on this page right now. This page does not indicate a required visit or additional appointment.',
                    emotion: 'hi',
                    speechMode: 'typewriter',
                    advance: { kind: 'next', destination: { kind: 'step', stepId: 'check-home' } },
                },
                {
                    id: 'check-home',
                    phase: 'guidance',
                    title: 'Check your current status',
                    text: 'Return to Home to review the current status and any available detail links. Result availability and admission clearance remain separate.',
                    emotion: 'thinking',
                    speechMode: 'typewriter',
                    advance: { kind: 'next', destination: { kind: 'complete' } },
                },
            ]);
        }
        const followUps = releaseDay.follow_ups;
        const decisionText = releaseDay.decision.state === 'completed'
            ? 'Your Release Day decision is recorded as complete.'
            : releaseDay.decision.state === 'deferred'
                ? `Your Release Day decision is recorded as deferred.${followUps.length === 0 ? ' No open follow-up is listed, so do not infer a visit or instruction.' : ''}`
                : releaseDay.decision.state === 'referred'
                    ? `Your Release Day decision is recorded as referred.${followUps.length === 0 ? ' No open follow-up is listed, so do not infer a visit or instruction.' : ''}`
                    : 'No Release Day decision is recorded here.';
        const appointmentText = releaseDay.appointment
            ? releaseDay.appointment.status === 'consulted'
                ? 'Appointment details are in the expandable Appointment details section; attendance is recorded.'
                : 'Appointment details are in the expandable Appointment details section.'
            : 'No appointment details are shown.';
        const requestedAppearances = followUps.filter((item) => item.status === 'appearance_requested');
        const pendingReviews = followUps.filter((item) => item.status === 'review_pending').length;
        const missingOfficeCount = requestedAppearances.filter((item) => item.office === null).length;
        const unlocatedOfficeCount = requestedAppearances.filter((item) => item.office !== null && !item.office.coordinates_available).length;
        const followUpText = followUps.length === 0
            ? 'No current follow-up is listed. Do not infer an office visit or additional appointment.'
            : requestedAppearances.length > 0
                ? `The follow-up list includes ${requestedAppearances.length} requested appearance${requestedAppearances.length === 1 ? '' : 's'}. Use the office details shown; “Find department office” appears only where a map location is configured.${unlocatedOfficeCount > 0 ? ' Some office details have no map pin; use the displayed office information.' : ''}${missingOfficeCount > 0 ? ' One or more office locations are not recorded; contact the department through FAQs & Office Inquiry.' : ''}`
                : 'The follow-up list shows program review in progress. The page does not request an office visit or additional appointment.';
        const additionalReviewText = requestedAppearances.length > 0 && pendingReviews > 0
            ? ` ${pendingReviews} program review follow-up${pendingReviews === 1 ? ' is' : 's are'} also listed; those review states do not request a visit.`
            : '';
        const instructionText = requestedAppearances.length > 0
            ? ' No visit time or additional instruction is recorded.'
            : '';
        const resultText = releaseDay.result.review_available && releaseDay.result.href
            ? 'The secondary Results section has a Review My Results link. Result availability and delivery remain separate from the Release Day decision.'
            : releaseDay.result.available
                ? 'A result is available; use the Results section when its review link is shown.'
                : 'The Results section shows whether result review is currently available. A pending or unavailable result does not change the recorded Release Day decision.';
        const choiceText = releaseDay.program_choice.available && releaseDay.program_choice.href
            ? 'The optional Your program plans section has a Review program plans link. A plan report does not change the official selection.'
            : 'Your program plans is optional and appears only when its Review program plans link is available.';
        return outcomeGuide('page:Applicant/ReleaseDay', 'Release Day', [
            {
                id: 'recorded-outcome',
                phase: 'overview',
                title: 'Your Release Day record',
                text: `${decisionText} ${appointmentText} This decision is separate from result delivery and admission clearance; external enrollment is not tracked here.`,
                emotion: 'hi',
                speechMode: 'typewriter',
                advance: { kind: 'next', destination: { kind: 'step', stepId: 'current-follow-up' } },
            },
            {
                id: 'current-follow-up',
                phase: 'guidance',
                title: 'Review or requested appearance',
                text: `${followUpText}${additionalReviewText}${instructionText}`,
                emotion: 'thinking',
                speechMode: 'typewriter',
                advance: { kind: 'next', destination: { kind: 'step', stepId: 'results-and-choice' } },
            },
            {
                id: 'results-and-choice',
                phase: 'workflow',
                title: 'Results and Your program plans',
                text: `${resultText} ${choiceText} Admission clearance is a separate section below.`,
                emotion: 'talking-presenting',
                speechMode: 'typewriter',
                advance: { kind: 'next', destination: { kind: 'complete' } },
            },
        ]);
    }

    if (componentName === 'Applicant/AdmissionClearance') {
        const clearance = pageProps.clearance;
        if (!clearance) {
            if (pageProps.page_state !== 'no_attempt') return undefined;
            return outcomeGuide('page:Applicant/AdmissionClearance', 'Admission Clearance', [
                {
                    id: 'no-application',
                    phase: 'overview',
                    title: 'No application linked',
                    text: 'This account has no application record linked to the clearance page, so there are no current document checks to review here.',
                    emotion: 'hi',
                    speechMode: 'typewriter',
                    advance: { kind: 'next', destination: { kind: 'step', stepId: 'find-help' } },
                },
                {
                    id: 'find-help',
                    phase: 'guidance',
                    title: 'Where to go next',
                    text: 'Use Home to check your current admission status or the visible FAQs & Office Inquiry controls for help. This guide does not create or change an application.',
                    emotion: 'thinking',
                    speechMode: 'typewriter',
                    advance: { kind: 'next', destination: { kind: 'complete' } },
                },
            ]);
        }
        const status = clearance.status;
        const pendingCount = clearance.requirements.mandatory_pending_count;
        const statusText = status === 'confirmed'
            ? 'Admission Clearance is confirmed in NEXIAM. This completes NEXIAM admission processing; external enrollment is not tracked here.'
            : status === 'ready'
                ? 'The current prerequisites are ready for Registrar review. The Registrar still needs to confirm clearance.'
                : status === 'revoked'
                    ? 'Clearance has been reopened for correction. Review only the current requested items and instructions shown.'
                    : 'Clearance is not ready yet. The Registrar will confirm it after the required records and checks are complete.';
        const requirementsText = clearance.correction_requests.length > 0
            ? 'The page lists current requested document corrections and applicant-safe instructions. Follow only those explicit requests.'
            : pendingCount === 0
                ? 'No mandatory document checks are currently pending. Keep unreviewed documents distinct from a requested correction.'
                : `The page shows ${pendingCount} mandatory document check${pendingCount === 1 ? '' : 's'} still pending. Unreviewed documents do not by themselves request resubmission.`;
        const nextText = status === 'confirmed'
            ? 'No further clearance action is needed on this page. Any historical sheet issue is shown separately and does not prove external enrollment.'
            : status === 'ready'
                ? 'This ready state is awaiting Registrar confirmation; it is not a completed clearance.'
                : status === 'revoked'
                    ? 'If the request does not explain what to provide, use the Registrar support link visible on the page.'
                    : 'Review the visible requirement statuses and follow up only when an explicit correction is requested.';
        return outcomeGuide('page:Applicant/AdmissionClearance', 'Admission Clearance', [
            {
                id: 'clearance-state',
                phase: 'overview',
                title: 'Current clearance state',
                text: statusText,
                emotion: 'hi',
                speechMode: 'typewriter',
                advance: { kind: 'next', destination: { kind: 'step', stepId: 'documents' } },
            },
            {
                id: 'documents',
                phase: 'concept',
                title: 'Document checks',
                text: requirementsText,
                emotion: 'thinking',
                speechMode: 'typewriter',
                advance: { kind: 'next', destination: { kind: 'step', stepId: 'what-to-do' } },
            },
            {
                id: 'what-to-do',
                phase: 'guidance',
                title: 'What needs your attention',
                text: nextText,
                emotion: 'talking-presenting',
                speechMode: 'typewriter',
                advance: { kind: 'next', destination: { kind: 'complete' } },
            },
        ]);
    }

    if (componentName === 'Applicant/ExamScoring') {
        const scoring = pageProps.scoring;
        if (pageProps.page_state === 'denied' || !scoring) {
            if (pageProps.page_state !== 'denied') return undefined;
            return outcomeGuide('page:Applicant/ExamScoring', 'Exam Scoring Status', [
                {
                    id: 'scoring-unavailable',
                    phase: 'overview',
                    title: 'Scoring details unavailable',
                    text: 'No application record is linked to this account, so this page cannot show scoring status or recorded activity.',
                    emotion: 'hi',
                    speechMode: 'typewriter',
                    advance: { kind: 'next', destination: { kind: 'step', stepId: 'return-home' } },
                },
                {
                    id: 'return-home',
                    phase: 'guidance',
                    title: 'Return to Home',
                    text: 'Use Home to review the admission status and detail links currently available to your account.',
                    emotion: 'thinking',
                    speechMode: 'typewriter',
                    advance: { kind: 'next', destination: { kind: 'complete' } },
                },
            ]);
        }
        const statusText = scoring.status === 'finalized'
            ? 'The current scoring status is Finalized.'
            : scoring.status === 'in_progress'
                ? 'The current scoring status is In progress.'
                : 'The current scoring status is Not started.';
        const activityText = scoring.recordings.length > 0
            ? `The page lists ${scoring.recordings.length} recorded scoring entr${scoring.recordings.length === 1 ? 'y' : 'ies'} and shows attribution when recorded.`
            : 'The page shows that no current scoring activity has been recorded.';
        const finalizationText = scoring.finalization !== null
            ? 'A scoring finalization record is shown here. Score details are not displayed on this page.'
            : 'No scoring finalization record is shown yet. This page does not predict when scoring will finish.';
        return outcomeGuide('page:Applicant/ExamScoring', 'Exam Scoring Status', [
            {
                id: 'processing-status',
                phase: 'overview',
                title: 'Current scoring status',
                text: `${statusText} This page reports processing status, not score values.`,
                emotion: 'hi',
                speechMode: 'typewriter',
                advance: { kind: 'next', destination: { kind: 'step', stepId: 'recorded-activity' } },
            },
            {
                id: 'recorded-activity',
                phase: 'concept',
                title: 'Recorded scoring activity',
                text: activityText,
                emotion: 'thinking',
                speechMode: 'typewriter',
                advance: { kind: 'next', destination: { kind: 'step', stepId: 'finalization' } },
            },
            {
                id: 'finalization',
                phase: 'guidance',
                title: 'Finalization and results',
                text: `${finalizationText} Review My Results from Home only when the result is available there.`,
                emotion: 'talking-presenting',
                speechMode: 'typewriter',
                advance: { kind: 'next', destination: { kind: 'complete' } },
            },
        ]);
    }

    if (componentName === 'Applicant/ExamDay') {
        const examDay = pageProps.exam_day;
        if (!examDay) return undefined;
        const session = examDay.session;
        const attendance = examDay.attendance;
        const sessionText = examDay.page_state === 'denied'
            ? 'No application record is linked to this account, so session details are unavailable.'
            : examDay.page_state === 'empty'
                ? 'No exam session is assigned here yet. Session information will appear when it is available for your application.'
                : session
                    ? 'The exam session section shows its current status and scheduled time facts available to your account.'
                    : 'No session details are available yet.';
        const attendanceText = examDay.page_state === 'denied'
            ? 'Attendance details are unavailable because no application record is linked to this account.'
            : attendance
                ? 'An attendance record is shown separately from session timing. Review the recorded outcome and contact staff if it does not match your experience.'
                : 'Attendance has not been recorded on this page. This does not by itself change the session details.';
        return outcomeGuide('page:Applicant/ExamDay', 'Exam Day', [
            {
                id: 'session-details',
                phase: 'overview',
                title: 'Exam session details',
                text: sessionText,
                emotion: 'hi',
                speechMode: 'typewriter',
                advance: { kind: 'next', destination: { kind: 'step', stepId: 'attendance-record' } },
            },
            {
                id: 'attendance-record',
                phase: 'concept',
                title: 'Attendance is separate',
                text: attendanceText,
                emotion: 'thinking',
                speechMode: 'typewriter',
                advance: { kind: 'next', destination: { kind: 'step', stepId: 'get-help' } },
            },
            {
                id: 'get-help',
                phase: 'guidance',
                title: 'During an exam',
                text: 'For an urgent issue, speak with Test Administrator staff. Use the visible “Message staff about exam timing” link for a timing question. This guide does not replace active exam instructions.',
                emotion: 'talking-presenting',
                speechMode: 'typewriter',
                advance: { kind: 'next', destination: { kind: 'complete' } },
            },
        ]);
    }

    return undefined;
}

export function resolvePageGuide(componentName: string, _url: string, props: unknown): TourDefinition | undefined {
    const entry = getPageGuideCoverage(componentName, _url);
    if (!entry) return undefined;
    const outcomeGuideDefinition = resolveApplicantOutcomeGuide(componentName, props);
    if (outcomeGuideDefinition) return outcomeGuideDefinition;
    const page = pages.find(([name]) => name === componentName);
    if (!page) return undefined;
    const [, , key, title, domain, text, guidePersona = staff] = page;
    return existingFactories[key]?.() ?? {
        id: key,
        revision: 1,
        title: `${title} Guide`,
        domain,
        persona: guidePersona,
        start: 'overview',
        steps: [{
            id: 'overview',
            phase: 'overview',
            title,
            text,
            emotion: 'hi',
            speechMode: 'typewriter',
            advance: { kind: 'next', destination: complete },
        }],
    };
}
