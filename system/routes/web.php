<?php

use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Admission\AdmissionActionController;
use App\Http\Controllers\Admission\AdmissionReviewController;
use App\Http\Controllers\Admission\ApplicantApplyController;
use App\Http\Controllers\Admission\ProgramPoolController;
use App\Http\Controllers\Applicant\ApplicantConsultationController;
use App\Http\Controllers\Applicant\ApplicantPortalController;
use App\Http\Controllers\Applicant\ApplicantProgramChoiceController;
use App\Http\Controllers\Applicant\ApplicantQrController;
use App\Http\Controllers\Applicant\ApplicantScheduleController;
use App\Http\Controllers\Assessment\GradingController;
use App\Http\Controllers\Assessment\GradingScoreController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Consultation\ConsultationSessionController;
use App\Http\Controllers\Examination\ExamMonitoringController;
use App\Http\Controllers\Examination\ExamSessionController;
use App\Http\Controllers\Examination\ProctorDutyController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Knowledge\KnowledgeSourceController;
use App\Http\Controllers\Nexi\NexiConversationController;
use App\Http\Controllers\Nexi\NexiProposalController;
use App\Http\Controllers\Nexi\NexiRunController;
use App\Http\Controllers\Nexi\NexiSkillController;
use App\Http\Controllers\Nexi\NexiTurnController;
use App\Http\Controllers\Notifications\NotificationController;
use App\Http\Controllers\PrintingController;
use App\Http\Controllers\ProgramHead\ProgramHeadActionController;
use App\Http\Controllers\ProgramHead\ProgramHeadController;
use App\Http\Controllers\Registrar\EnrollmentEligibilityController;
use App\Http\Controllers\Registrar\EnrollmentStampController;
use App\Http\Controllers\Release\ReleaseController;
use App\Http\Controllers\Setup\AcademicCycleController;
use App\Http\Controllers\Setup\ApplicationWindowController;
use App\Http\Controllers\Setup\AptitudeAreaController;
use App\Http\Controllers\Setup\IntakeGradeFieldController;
use App\Http\Controllers\Setup\OmrConfigurationController;
use App\Http\Controllers\Setup\PrivacyPolicyController;
use App\Http\Controllers\Setup\ProgramController;
use App\Http\Controllers\Setup\ProgramQuotaController;
use App\Http\Controllers\Setup\RoomController;
use App\Http\Controllers\Setup\SetupDashboardController;
use App\Http\Controllers\Staff\ApplicantQrController as StaffQrController;
use App\Http\Controllers\Staff\HomeController;
use App\Http\Controllers\Staff\PersonnelController;
use App\Http\Controllers\Staff\ProfileController;
use App\Http\Controllers\Staff\TestAdministratorController;
use App\Http\Middleware\EnsurePersonnelAccess;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    $cycle = \App\Models\Foundation\AcademicCycle::active();

    return Inertia::render('Home', [
        'institutionName' => 'ISPSC Tagudin',
        'activeSeason' => $cycle === null ? null : [
            'name' => $cycle->staffLabel(),
            'window' => $cycle->applicationWindowLabel(),
        ],
    ]);
})->name('home');

Route::get('/health', HealthController::class)->name('health');



Route::prefix('apply')->group(function () {
    Route::get('/', [ApplicantApplyController::class, 'applyForm'])->name('apply.form');
    Route::post('/', [ApplicantApplyController::class, 'submit'])->name('apply.submit');
    Route::get('/confirmation', [ApplicantApplyController::class, 'confirmation'])->name('apply.confirmation');
});

Route::middleware('guest')->prefix('applicant')->group(function () {
    Route::get('/login', [ApplicantPortalController::class, 'loginForm'])->name('applicant.login');
    Route::post('/login', [ApplicantPortalController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('applicant.login.store');
    // D-085: password setup removed — keep alias for old links.
    Route::get('/setup', [ApplicantPortalController::class, 'setupForm'])->name('applicant.setup');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/staff', HomeController::class)->name('staff.home');

    Route::prefix('nexi')->name('nexi.')->group(function () {
        Route::post('/turns', [NexiTurnController::class, 'store'])->name('turns.store');
        Route::post('/conversations/clear', [NexiConversationController::class, 'clear'])->name('conversations.clear');
        Route::get('/conversations/{conversation}/snapshot', [NexiConversationController::class, 'snapshot'])->name('conversations.snapshot');
        Route::get('/conversations/{conversation}/stream', [NexiConversationController::class, 'stream'])->name('conversations.stream');
        Route::post('/runs/{run}/cancel', [NexiRunController::class, 'cancel'])->name('runs.cancel');
        Route::post('/emergency-stop', [NexiRunController::class, 'emergencyStop'])->name('emergency-stop');
        Route::post('/skills/start', [NexiSkillController::class, 'start'])->name('skills.start');
        Route::post('/skills/invoke', [NexiSkillController::class, 'invoke'])->name('skills.invoke');
        Route::post('/proposals/{proposal}/confirm', [NexiProposalController::class, 'confirm'])->name('proposals.confirm');
        Route::post('/proposals/{proposal}/cancel', [NexiProposalController::class, 'cancel'])->name('proposals.cancel');
    });

    Route::prefix('knowledge')->name('knowledge.')->group(function () {
        Route::get('/', [KnowledgeSourceController::class, 'index'])->name('index');
        Route::get('/create', [KnowledgeSourceController::class, 'create'])->name('create');
        Route::post('/', [KnowledgeSourceController::class, 'store'])->name('store');
        Route::get('/{source}', [KnowledgeSourceController::class, 'show'])->name('show');
        Route::post('/{source}/extract', [KnowledgeSourceController::class, 'extract'])->name('extract');
        Route::post('/{source}/approve', [KnowledgeSourceController::class, 'approve'])->name('approve');
        Route::post('/{source}/publish', [KnowledgeSourceController::class, 'publish'])->name('publish');
        Route::post('/{source}/revoke', [KnowledgeSourceController::class, 'revoke'])->name('revoke');
    });

    Route::get('/setup', [SetupDashboardController::class, 'index'])->name('setup.index');
    Route::get('/setup/programs', [ProgramController::class, 'programs'])->name('setup.programs');
    Route::post('/setup/programs', [ProgramController::class, 'storeProgram'])->name('setup.programs.store');
    Route::put('/setup/programs/{program}', [ProgramController::class, 'updateProgram'])->name('setup.programs.update');
    Route::get('/setup/rooms', [RoomController::class, 'rooms'])->name('setup.rooms');
    Route::post('/setup/rooms', [RoomController::class, 'storeRoom'])->name('setup.rooms.store');
    Route::put('/setup/rooms/{room}', [RoomController::class, 'updateRoom'])->name('setup.rooms.update');
    Route::get('/setup/cycles', [AcademicCycleController::class, 'cycles'])->name('setup.cycles');
    Route::get('/setup/cycles/create', [AcademicCycleController::class, 'createCycle'])->name('setup.cycles.create');
    Route::post('/setup/cycles', [AcademicCycleController::class, 'storeCycle'])->name('setup.cycles.store');
    Route::get('/setup/cycles/{cycle}/edit', [AcademicCycleController::class, 'editCycle'])->name('setup.cycles.edit');
    Route::put('/setup/cycles/{cycle}', [AcademicCycleController::class, 'updateCycle'])->name('setup.cycles.update');
    Route::post('/setup/cycles/{cycle}/activate', [AcademicCycleController::class, 'activateCycle'])->name('setup.cycles.activate');
    Route::post('/setup/cycles/{cycle}/deactivate', [AcademicCycleController::class, 'deactivateCycle'])->name('setup.cycles.deactivate');
    Route::post('/setup/windows', [ApplicationWindowController::class, 'storeWindow'])->name('setup.windows.store');
    Route::get('/setup/privacy', [PrivacyPolicyController::class, 'privacy'])->name('setup.privacy');
    Route::post('/setup/privacy', [PrivacyPolicyController::class, 'storePrivacy'])->name('setup.privacy.store');
    Route::post('/setup/privacy/{policy}/activate', [PrivacyPolicyController::class, 'activatePrivacy'])->name('setup.privacy.activate');
    Route::get('/setup/grades', [IntakeGradeFieldController::class, 'grades'])->name('setup.grades');
    Route::post('/setup/grades', [IntakeGradeFieldController::class, 'storeGrade'])->name('setup.grades.store');
    Route::put('/setup/grades/{field}', [IntakeGradeFieldController::class, 'updateGrade'])->name('setup.grades.update');
    Route::get('/setup/aptitude', [AptitudeAreaController::class, 'aptitude'])->name('setup.aptitude');
    Route::post('/setup/aptitude', [AptitudeAreaController::class, 'storeAptitude'])->name('setup.aptitude.store');
    Route::put('/setup/aptitude/{area}', [AptitudeAreaController::class, 'updateAptitude'])->name('setup.aptitude.update');
    Route::get('/setup/aptitude/{area}/scoring', [AptitudeAreaController::class, 'scoring'])->name('setup.aptitude.scoring');
    Route::put('/setup/aptitude/{area}/scoring', [AptitudeAreaController::class, 'storeScoring'])->name('setup.aptitude.scoring.update');
    Route::get('/setup/quotas', [ProgramQuotaController::class, 'quotas'])->name('setup.quotas');
    Route::post('/setup/quotas', [ProgramQuotaController::class, 'storeQuota'])->name('setup.quotas.store');

    Route::get('/staff/profile', [ProfileController::class, 'show'])->name('staff.profile');

    Route::get('/staff/personnel', [PersonnelController::class, 'index'])->name('staff.personnel.index');

    Route::middleware(EnsurePersonnelAccess::class)->prefix('staff/personnel')->name('staff.personnel.')->group(function () {
        Route::get('/create', [PersonnelController::class, 'create'])->name('create');
        Route::post('/', [PersonnelController::class, 'store'])->name('store');
        Route::get('/{user}/edit', [PersonnelController::class, 'edit'])->name('edit');
        Route::put('/{user}', [PersonnelController::class, 'update'])->name('update');
        Route::post('/{user}/deactivate', [PersonnelController::class, 'deactivate'])->name('deactivate');
        Route::post('/{user}/activate', [PersonnelController::class, 'activate'])->name('activate');
        Route::post('/{user}/grant-ta', [TestAdministratorController::class, 'grant'])
            ->middleware('capability:'.CapabilityCatalog::GUIDANCE_TA_DELEGATE)
            ->name('grant-ta');
        Route::post('/{user}/revoke-ta', [TestAdministratorController::class, 'revoke'])
            ->middleware('capability:'.CapabilityCatalog::GUIDANCE_TA_DELEGATE)
            ->name('revoke-ta');
    });

    Route::get('/admissions', [AdmissionReviewController::class, 'index'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW)
        ->name('admissions.index');
    Route::get('/admissions/{attempt}', [AdmissionReviewController::class, 'show'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW)
        ->name('admissions.show');
    Route::post('/admissions/{attempt}/accept', [AdmissionActionController::class, 'accept'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_APPLICATIONS_ACCEPT)
        ->name('admissions.accept');
    Route::post('/admissions/{attempt}/dismiss', [AdmissionActionController::class, 'dismiss'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_APPLICATIONS_DISMISS)
        ->name('admissions.dismiss');
    Route::post('/admissions/{attempt}/correct', [AdmissionActionController::class, 'correct'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_APPLICATIONS_CORRECT)
        ->name('admissions.correct');
    Route::post('/admissions/{attempt}/request-correction', [AdmissionActionController::class, 'requestCorrection'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_APPLICATIONS_CORRECT)
        ->name('admissions.request-correction');
    Route::post('/admissions/{attempt}/authorize-amendment', [AdmissionActionController::class, 'authorizeAmendment'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_APPLICATIONS_ACCEPT)
        ->name('admissions.authorize-amendment');
    Route::post('/admissions/{attempt}/reconsider', [AdmissionActionController::class, 'reconsider'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_APPLICATIONS_ACCEPT)
        ->name('admissions.reconsider');
    Route::post('/admissions/{attempt}/reopen', [AdmissionActionController::class, 'reopen'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_APPLICATIONS_ACCEPT)
        ->name('admissions.reopen');
    Route::post('/admissions/{attempt}/archive', [AdmissionActionController::class, 'archive'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_APPLICATIONS_DISMISS)
        ->name('admissions.archive');
    Route::post('/admissions/{attempt}/reissue-access-key', [AdmissionActionController::class, 'reissueAccessKey'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_APPLICATIONS_CORRECT)
        ->name('admissions.reissue-access-key');

    Route::get('/applicant/portal', [ApplicantPortalController::class, 'dashboard'])->name('applicant.portal');
    Route::get('/applicant/schedule', [ApplicantScheduleController::class, 'show'])
        ->name('applicant.schedule');
    Route::post('/applicant/schedule/reserve', [ApplicantScheduleController::class, 'reserve'])
        ->name('applicant.schedule.reserve');
    Route::get('/applicant/consultation', [ApplicantConsultationController::class, 'show'])
        ->name('applicant.consultation');
    Route::post('/applicant/consultation/reserve', [ApplicantConsultationController::class, 'reserve'])
        ->name('applicant.consultation.reserve');
    Route::get('/applicant/applications/{attempt}/correct', [ApplicantPortalController::class, 'correctionForm'])
        ->name('applicant.correction');
    Route::post('/applicant/applications/{attempt}/correct', [ApplicantPortalController::class, 'resubmitCorrection'])
        ->name('applicant.correction.store');
    Route::get('/applicant/applications/{attempt}', [ApplicantPortalController::class, 'applicationShow'])
        ->name('applicant.application');

    Route::get('/exams/monitoring', [ExamMonitoringController::class, 'monitoringIndex'])
        ->name('exams.monitoring');
    Route::get('/exams/sessions/{session}/monitoring', [ExamMonitoringController::class, 'monitoring'])
        ->name('exams.sessions.monitoring');
    Route::get('/exams/sessions', [ExamSessionController::class, 'index'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)
        ->name('exams.sessions.index');
    Route::get('/exams/sessions/create', [ExamSessionController::class, 'create'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)
        ->name('exams.sessions.create');
    Route::post('/exams/sessions', [ExamSessionController::class, 'store'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)
        ->name('exams.sessions.store');
    Route::get('/exams/sessions/{session}', [ExamSessionController::class, 'show'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)
        ->name('exams.sessions.show');
    Route::get('/exams/sessions/{session}/edit', [ExamSessionController::class, 'edit'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)
        ->name('exams.sessions.edit');
    Route::put('/exams/sessions/{session}', [ExamSessionController::class, 'update'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)
        ->name('exams.sessions.update');
    Route::post('/exams/sessions/{session}/assign', [ExamSessionController::class, 'assign'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)
        ->name('exams.sessions.assign');
    Route::post('/exams/sessions/{session}/assignments/{assignment}/remove', [ExamSessionController::class, 'remove'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)
        ->name('exams.sessions.remove');
    Route::post('/exams/sessions/{session}/reassign', [ExamSessionController::class, 'reassign'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)
        ->name('exams.sessions.reassign');
    Route::post('/exams/sessions/{session}/publish', [ExamSessionController::class, 'publish'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)
        ->name('exams.sessions.publish');
    Route::post('/exams/sessions/{session}/unpublish', [ExamSessionController::class, 'unpublish'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)
        ->name('exams.sessions.unpublish');
    Route::post('/exams/sessions/{session}/cancel', [ExamSessionController::class, 'cancel'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)
        ->name('exams.sessions.cancel');
    Route::post('/exams/sessions/{session}/close', [ExamSessionController::class, 'close'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)
        ->name('exams.sessions.close');
    Route::post('/exams/sessions/{session}/extend', [ExamSessionController::class, 'extend'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)
        ->name('exams.sessions.extend');
    Route::post('/exams/sessions/{session}/open-reservation', [ExamSessionController::class, 'openReservation'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)
        ->name('exams.sessions.open-reservation');
    Route::post('/exams/sessions/{session}/close-reservation', [ExamSessionController::class, 'closeReservation'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)
        ->name('exams.sessions.close-reservation');

    Route::get('/consultation/sessions', [ConsultationSessionController::class, 'index'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN)
        ->name('consultation.sessions.index');
    Route::get('/consultation/sessions/create', [ConsultationSessionController::class, 'create'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN)
        ->name('consultation.sessions.create');
    Route::post('/consultation/sessions', [ConsultationSessionController::class, 'store'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN)
        ->name('consultation.sessions.store');
    Route::get('/consultation/sessions/{session}', [ConsultationSessionController::class, 'show'])
        ->name('consultation.sessions.show');
    Route::get('/consultation/sessions/{session}/edit', [ConsultationSessionController::class, 'edit'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN)
        ->name('consultation.sessions.edit');
    Route::put('/consultation/sessions/{session}', [ConsultationSessionController::class, 'update'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN)
        ->name('consultation.sessions.update');
    Route::post('/consultation/sessions/{session}/assign', [ConsultationSessionController::class, 'assign'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN)
        ->name('consultation.sessions.assign');
    Route::post('/consultation/sessions/{session}/assignments/{assignment}/cancel', [ConsultationSessionController::class, 'cancelAssignment'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN)
        ->name('consultation.sessions.assignments.cancel');
    Route::post('/consultation/sessions/{session}/reassign', [ConsultationSessionController::class, 'reassign'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN)
        ->name('consultation.sessions.reassign');
    Route::post('/consultation/sessions/{session}/publish', [ConsultationSessionController::class, 'publish'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN)
        ->name('consultation.sessions.publish');
    Route::post('/consultation/sessions/{session}/unpublish', [ConsultationSessionController::class, 'unpublish'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN)
        ->name('consultation.sessions.unpublish');
    Route::post('/consultation/sessions/{session}/complete', [ConsultationSessionController::class, 'complete'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN)
        ->name('consultation.sessions.complete');
    Route::post('/consultation/sessions/{session}/cancel', [ConsultationSessionController::class, 'cancel'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN)
        ->name('consultation.sessions.cancel');
    Route::post('/consultation/sessions/{session}/open-reservation', [ConsultationSessionController::class, 'openReservation'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN)
        ->name('consultation.sessions.reservation.open');
    Route::post('/consultation/sessions/{session}/close-reservation', [ConsultationSessionController::class, 'closeReservation'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN)
        ->name('consultation.sessions.reservation.close');

    Route::get('/registrar/enrollment-eligibility', [EnrollmentEligibilityController::class, 'index'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_ENROLLMENT_ELIGIBILITY)
        ->name('registrar.enrollment-eligibility.index');
    Route::get('/registrar/enrollment-eligibility/{attempt}', [EnrollmentEligibilityController::class, 'show'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_ENROLLMENT_ELIGIBILITY)
        ->name('registrar.enrollment-eligibility.show');

    Route::post('/exams/sessions/{session}/proctor-duty', [ProctorDutyController::class, 'assignDuty'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_PROCTOR_ASSIGN)
        ->name('proctor-duty.assign');
    Route::post('/proctor-duty/{duty}/revoke', [ProctorDutyController::class, 'revoke'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_PROCTOR_ASSIGN)
        ->name('proctor-duty.revoke');
    Route::post('/exams/sessions/{session}/monitoring/assignments/{assignment}/attendance', [ProctorDutyController::class, 'recordAttendance'])
        ->name('exams.sessions.monitoring.attendance');
    Route::post('/exams/sessions/{session}/monitoring/assignments/{assignment}/submission', [ProctorDutyController::class, 'recordSubmission'])
        ->name('exams.sessions.monitoring.submission');

    Route::get('/grading', [GradingController::class, 'index'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_MANUAL)
        ->name('grading.index');
    Route::post('/grading', [GradingController::class, 'store'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_MANUAL)
        ->name('grading.store');
    Route::get('/grading/sessions/{gradingSession}', [GradingController::class, 'show'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_MANUAL)
        ->name('grading.sessions.show');
    Route::get('/grading/sessions/{gradingSession}/omr', [GradingController::class, 'omr'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('grading.sessions.omr');
    Route::get('/grading/sessions/{gradingSession}/omr/sheets/{sheet}/image', [GradingController::class, 'omrImage'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('grading.sessions.omr.sheets.image');
    Route::get('/grading/sessions/{gradingSession}/omr/sheets/{sheet}/pages/{page}/image', [GradingController::class, 'omrImage'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('grading.sessions.omr.sheets.pages.image');
    Route::post('/grading/sessions/{gradingSession}/omr/sheets/{sheet}/pages/{page}/replace', [GradingController::class, 'replaceOmrSheetPage'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('grading.sessions.omr.sheets.pages.replace');
    Route::post('/grading/sessions/{gradingSession}/omr/batches', [GradingController::class, 'storeOmrBatch'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('grading.sessions.omr.batches.store');
    Route::post('/grading/sessions/{gradingSession}/omr/sheets', [GradingController::class, 'storeOmrSheet'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('grading.sessions.omr.sheets.store');
    Route::post('/grading/sessions/{gradingSession}/omr/sheets/{sheet}/review', [GradingController::class, 'reviewOmrSheet'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('grading.sessions.omr.sheets.review');
    Route::post('/grading/sessions/{gradingSession}/omr/batches/{batch}/confirm', [GradingController::class, 'confirmOmrBatch'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('grading.sessions.omr.batches.confirm');
    Route::post('/grading/sessions/{gradingSession}/omr/batches/{batch}/reject', [GradingController::class, 'rejectOmrBatch'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('grading.sessions.omr.batches.reject');
    Route::post('/grading/sessions/{gradingSession}/omr/sheets/{sheet}/retry', [GradingController::class, 'retryOmrSheet'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('grading.sessions.omr.sheets.retry');
    Route::get('/grading/sessions/{gradingSession}/attempts/{attempt}', [GradingScoreController::class, 'show'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_MANUAL)
        ->name('grading.sessions.attempts.scores');
    Route::put('/grading/sessions/{gradingSession}/attempts/{attempt}/scores', [GradingScoreController::class, 'update'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_MANUAL)
        ->name('grading.sessions.attempts.scores.update');
    Route::delete('/grading/sessions/{gradingSession}/attempts/{attempt}/scores', [GradingScoreController::class, 'destroy'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_MANUAL)
        ->name('grading.sessions.attempts.scores.destroy');
    Route::post('/grading/sessions/{gradingSession}/finalize', [GradingController::class, 'finalize'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_MANUAL)
        ->name('grading.finalize');

    Route::get('/consultation', [ReleaseController::class, 'consultation'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE)
        ->name('consultation.index');
    Route::post('/consultation/{attempt}/decision', [ReleaseController::class, 'recordDecision'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE)
        ->name('consultation.decision');
    // Shared per-applicant admission-support workspace (Guidance + PH).
    Route::get('/consultation/applicants/{attempt}', [\App\Http\Controllers\Admission\AdmissionSupportController::class, 'show'])
        ->middleware('auth')
        ->name('admission-support.show');
    Route::get('/consultation/applicants/{attempt}/support', [\App\Http\Controllers\Admission\AdmissionSupportController::class, 'supportJson'])
        ->middleware('auth')
        ->name('admission-support.support');
    Route::post('/release/{attempt}/prepare', [ReleaseController::class, 'prepareDocument'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_RESULTS_PREPARE)
        ->name('release.prepare');
    Route::get('/release', [ReleaseController::class, 'index'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_RESULTS_RELEASE)
        ->name('release.index');
    Route::post('/release/{attempt}', [ReleaseController::class, 'release'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_RESULTS_RELEASE)
        ->name('release.store');
    Route::post('/release/records/{release}/unrelease', [ReleaseController::class, 'unrelease'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_RESULTS_RELEASE)
        ->name('release.unrelease');

    Route::get('/printing/jobs/{printJob}', [PrintingController::class, 'show'])
        ->middleware('auth')
        ->name('printing.jobs.show');
    Route::get('/printing/jobs/{printJob}/download', [PrintingController::class, 'download'])
        ->middleware('auth')
        ->name('printing.jobs.download');

    Route::get('/setup/omr', [OmrConfigurationController::class, 'index'])->name('setup.omr.index');
    Route::get('/setup/omr/intake', [OmrConfigurationController::class, 'intake'])->name('setup.omr.intake');
    Route::post('/setup/omr/bundles', [OmrConfigurationController::class, 'storeBundle'])->name('setup.omr.bundles.store');
    Route::get('/setup/omr/templates', [OmrConfigurationController::class, 'templatesIndex'])->name('setup.omr.templates.index');
    Route::get('/setup/omr/templates/builder', [OmrConfigurationController::class, 'builder'])->name('setup.omr.templates.builder');
    Route::post('/setup/omr/templates', [OmrConfigurationController::class, 'storeAuthoredTemplate'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.store');
    Route::get('/setup/omr/templates/{template}', [OmrConfigurationController::class, 'showTemplate'])->name('setup.omr.templates.show');
    Route::get('/setup/omr/templates/{version}/preview', [OmrConfigurationController::class, 'previewVersion'])->name('setup.omr.templates.preview');
    Route::post('/setup/omr/templates/preview-draft', [OmrConfigurationController::class, 'previewDraft'])->name('setup.omr.templates.preview_draft');
    Route::post('/setup/omr/layout/preview', [OmrConfigurationController::class, 'layoutPreview'])->name('setup.omr.layout.preview');
    Route::post('/setup/omr/templates/{version}/overlay', [OmrConfigurationController::class, 'storeOverlay'])->name('setup.omr.templates.overlay');
    Route::get('/setup/omr/booklets', [OmrConfigurationController::class, 'booklets'])->name('setup.omr.booklets');
    Route::get('/setup/omr/booklets/create', [OmrConfigurationController::class, 'bookletCreate'])->name('setup.omr.booklets.create');
    Route::get('/setup/omr/booklets/{booklet}', [OmrConfigurationController::class, 'bookletShow'])->name('setup.omr.booklets.show');
    Route::post('/setup/omr/booklets', [OmrConfigurationController::class, 'storeBooklet'])->name('setup.omr.booklets.store');
    Route::post('/setup/omr/booklets/{version}/copy', [OmrConfigurationController::class, 'copyBooklet'])->name('setup.omr.booklets.copy');
    Route::get('/setup/omr/pairs', [OmrConfigurationController::class, 'pairs'])->name('setup.omr.pairs');

    // Admission support: Guidance pool + targeted follow-up (D-090/D-089).
    Route::post('/consultation/{attempt}/program-pool', [ProgramPoolController::class, 'setPool'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SELECTION_MANAGE)
        ->name('program-pool.set');
    Route::post('/consultation/{attempt}/follow-up', [ProgramPoolController::class, 'openFollowUp'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_FOLLOW_UP_MANAGE)
        ->name('program-follow-up.open');

    // Applicant program choice (OWN scope).
    Route::get('/applicant/program-choice', [ApplicantProgramChoiceController::class, 'show'])
        ->middleware('capability:'.CapabilityCatalog::APPLICANT_PROGRAM_SELECT)
        ->name('applicant.program-choice');
    Route::post('/applicant/program-choice/acknowledge', [ApplicantProgramChoiceController::class, 'acknowledge'])
        ->middleware('capability:'.CapabilityCatalog::APPLICANT_PROGRAM_SELECT)
        ->name('applicant.program-choice.acknowledge');
    Route::post('/applicant/program-choice/select', [ApplicantProgramChoiceController::class, 'select'])
        ->middleware('capability:'.CapabilityCatalog::APPLICANT_PROGRAM_SELECT)
        ->name('applicant.program-choice.select');
    Route::post('/applicant/program-choice/transfer', [ApplicantProgramChoiceController::class, 'transfer'])
        ->middleware('capability:'.CapabilityCatalog::APPLICANT_PROGRAM_SELECT)
        ->name('applicant.program-choice.transfer');
    Route::post('/applicant/program-choice/cancel', [ApplicantProgramChoiceController::class, 'cancel'])
        ->middleware('capability:'.CapabilityCatalog::APPLICANT_PROGRAM_SELECT)
        ->name('applicant.program-choice.cancel');

    // Program Head assigned-program workspace (PROGRAM scope).
    Route::get('/staff/programs', [ProgramHeadController::class, 'index'])
        ->middleware('capability:'.CapabilityCatalog::PROGRAM_HEAD_VIEW)
        ->name('program-head.index');
    Route::get('/staff/programs/{program:code}', [ProgramHeadController::class, 'show'])
        ->middleware('capability:'.CapabilityCatalog::PROGRAM_HEAD_VIEW)
        ->name('program-head.show');
    Route::get('/staff/programs/{program:code}/applicants/{attempt}', [ProgramHeadController::class, 'applicantShow'])
        ->middleware('capability:'.CapabilityCatalog::PROGRAM_HEAD_VIEW)
        ->name('program-head.applicants.show');
    Route::post('/staff/programs/{program:code}/applicants/{attempt}/decline', [ProgramHeadActionController::class, 'declineSelection'])
        ->middleware('capability:'.CapabilityCatalog::PROGRAM_HEAD_SELECTION_DECLINE)
        ->name('program-head.applicants.decline');
    Route::post('/staff/programs/{program:code}/follow-ups/{followUp}/resolve', [ProgramHeadActionController::class, 'resolveFollowUp'])
        ->middleware('capability:'.CapabilityCatalog::PROGRAM_HEAD_FOLLOW_UP_RESOLVE)
        ->name('program-head.follow-ups.resolve');
    Route::post('/staff/programs/{program:code}/applicants/{attempt}/threads', [ProgramHeadActionController::class, 'openInternalThread'])
        ->middleware('capability:'.CapabilityCatalog::PROGRAM_HEAD_MESSAGE)
        ->name('program-head.threads.open');
    Route::post('/threads/{thread}/messages', [ProgramHeadActionController::class, 'sendMessage'])
        ->middleware('capability:'.CapabilityCatalog::RECORD_THREAD_PARTICIPATE)
        ->name('threads.messages.store');
    Route::post('/threads/{thread}/read', [ProgramHeadActionController::class, 'markRead'])
        ->middleware('capability:'.CapabilityCatalog::RECORD_THREAD_PARTICIPATE)
        ->name('threads.read');

    // Registrar enrollment stamps (OFFICE duty).
    Route::post('/registrar/enrollment-eligibility/{attempt}/enrolling', [EnrollmentStampController::class, 'stampEnrolling'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_ENROLLMENT_STAMP)
        ->name('enrollment.stamp-enrolling');
    Route::post('/registrar/enrollment-eligibility/{attempt}/enrolled', [EnrollmentStampController::class, 'stampEnrolled'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_ENROLLMENT_STAMP)
        ->name('enrollment.stamp-enrolled');
    Route::post('/registrar/enrollment-eligibility/{attempt}/reverse', [EnrollmentStampController::class, 'reverse'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_ENROLLMENT_STAMP)
        ->name('enrollment.reverse');

    // Universal applicant QR (D-092).
    Route::get('/staff/scan', [StaffQrController::class, 'scan'])
        ->middleware('capability:'.CapabilityCatalog::APPLICANT_QR_RESOLVE)
        ->name('staff.scan');
    Route::post('/staff/scan/resolve', [StaffQrController::class, 'resolve'])
        ->middleware('capability:'.CapabilityCatalog::APPLICANT_QR_RESOLVE)
        ->name('staff.scan.resolve');
    Route::get('/applicant/qr', [ApplicantQrController::class, 'show'])
        ->middleware('capability:'.CapabilityCatalog::APPLICANT_PROGRAM_SELECT)
        ->name('applicant.qr');
    Route::get('/applicant/admission-support', [ApplicantQrController::class, 'admissionSupport'])
        ->middleware('capability:'.CapabilityCatalog::RECORD_THREAD_PARTICIPATE)
        ->name('applicant.admission-support');

    Route::get('/ui-kit', function () {
        return Inertia::render('UiKit/Gallery');
    })->name('ui-kit.gallery');

    Route::middleware('capability:'.CapabilityCatalog::NOTIFY_READ_OWN)->prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('/{delivery}/read', [NotificationController::class, 'markRead'])->name('read');
        Route::post('/read-all', [NotificationController::class, 'readAll'])->name('read-all');
        Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
    });

    Route::get('/foundation/capability-probe', function () {
        return response()->json(['ok' => true, 'capability' => 'foundation.probe']);
    })->middleware('capability:foundation.probe')->name('foundation.capability-probe');
});
