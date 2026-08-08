<?php

use App\Http\Controllers\Admission\AdmissionController;
use App\Http\Controllers\Applicant\ApplicantPortalController;
use App\Http\Controllers\Assessment\GradingController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Applicant\ApplicantScheduleController;
use App\Http\Controllers\Examination\ExamSessionController;
use App\Http\Controllers\Examination\ProctorController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Release\ReleaseController;
use App\Http\Controllers\Setup\SetupController;
use App\Http\Controllers\Staff\HomeController;
use App\Http\Controllers\Staff\PersonnelController;
use App\Http\Middleware\EnsurePersonnelAccess;
use App\Authorization\CapabilityCatalog;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Home', [
        'institutionName' => 'ISPSC Tagudin',
    ]);
})->name('home');

Route::get('/health', HealthController::class)->name('health');

Route::prefix('apply')->group(function () {
    Route::get('/', [AdmissionController::class, 'applyForm'])->name('apply.form');
    Route::post('/', [AdmissionController::class, 'submit'])->name('apply.submit');
    Route::get('/confirmation', [AdmissionController::class, 'confirmation'])->name('apply.confirmation');
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

    Route::get('/setup', [SetupController::class, 'index'])->name('setup.index');
    Route::get('/setup/programs', [SetupController::class, 'programs'])->name('setup.programs');
    Route::post('/setup/programs', [SetupController::class, 'storeProgram'])->name('setup.programs.store');
    Route::put('/setup/programs/{program}', [SetupController::class, 'updateProgram'])->name('setup.programs.update');
    Route::get('/setup/rooms', [SetupController::class, 'rooms'])->name('setup.rooms');
    Route::post('/setup/rooms', [SetupController::class, 'storeRoom'])->name('setup.rooms.store');
    Route::put('/setup/rooms/{room}', [SetupController::class, 'updateRoom'])->name('setup.rooms.update');
    Route::get('/setup/cycles', [SetupController::class, 'cycles'])->name('setup.cycles');
    Route::get('/setup/cycles/create', [SetupController::class, 'createCycle'])->name('setup.cycles.create');
    Route::post('/setup/cycles', [SetupController::class, 'storeCycle'])->name('setup.cycles.store');
    Route::get('/setup/cycles/{cycle}/edit', [SetupController::class, 'editCycle'])->name('setup.cycles.edit');
    Route::put('/setup/cycles/{cycle}', [SetupController::class, 'updateCycle'])->name('setup.cycles.update');
    Route::post('/setup/cycles/{cycle}/activate', [SetupController::class, 'activateCycle'])->name('setup.cycles.activate');
    Route::post('/setup/cycles/{cycle}/deactivate', [SetupController::class, 'deactivateCycle'])->name('setup.cycles.deactivate');
    Route::post('/setup/windows', [SetupController::class, 'storeWindow'])->name('setup.windows.store');
    Route::get('/setup/privacy', [SetupController::class, 'privacy'])->name('setup.privacy');
    Route::post('/setup/privacy', [SetupController::class, 'storePrivacy'])->name('setup.privacy.store');
    Route::post('/setup/privacy/{policy}/activate', [SetupController::class, 'activatePrivacy'])->name('setup.privacy.activate');
    Route::get('/setup/grades', [SetupController::class, 'grades'])->name('setup.grades');
    Route::post('/setup/grades', [SetupController::class, 'storeGrade'])->name('setup.grades.store');
    Route::put('/setup/grades/{field}', [SetupController::class, 'updateGrade'])->name('setup.grades.update');
    Route::get('/setup/aptitude', [SetupController::class, 'aptitude'])->name('setup.aptitude');
    Route::post('/setup/aptitude', [SetupController::class, 'storeAptitude'])->name('setup.aptitude.store');
    Route::put('/setup/aptitude/{area}', [SetupController::class, 'updateAptitude'])->name('setup.aptitude.update');
    Route::get('/setup/quotas', [SetupController::class, 'quotas'])->name('setup.quotas');
    Route::post('/setup/quotas', [SetupController::class, 'storeQuota'])->name('setup.quotas.store');

    Route::middleware(EnsurePersonnelAccess::class)->prefix('staff/personnel')->name('staff.personnel.')->group(function () {
        Route::get('/', [PersonnelController::class, 'index'])->name('index');
        Route::get('/create', [PersonnelController::class, 'create'])->name('create');
        Route::post('/', [PersonnelController::class, 'store'])->name('store');
        Route::get('/{user}/edit', [PersonnelController::class, 'edit'])->name('edit');
        Route::put('/{user}', [PersonnelController::class, 'update'])->name('update');
        Route::post('/{user}/deactivate', [PersonnelController::class, 'deactivate'])->name('deactivate');
        Route::post('/{user}/activate', [PersonnelController::class, 'activate'])->name('activate');
    });

    Route::get('/admissions', [AdmissionController::class, 'index'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW)
        ->name('admissions.index');
    Route::get('/admissions/{attempt}', [AdmissionController::class, 'show'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW)
        ->name('admissions.show');
    Route::post('/admissions/{attempt}/accept', [AdmissionController::class, 'accept'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_APPLICATIONS_ACCEPT)
        ->name('admissions.accept');
    Route::post('/admissions/{attempt}/dismiss', [AdmissionController::class, 'dismiss'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_APPLICATIONS_DISMISS)
        ->name('admissions.dismiss');
    Route::post('/admissions/{attempt}/correct', [AdmissionController::class, 'correct'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_APPLICATIONS_CORRECT)
        ->name('admissions.correct');
    Route::post('/admissions/{attempt}/request-correction', [AdmissionController::class, 'requestCorrection'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_APPLICATIONS_CORRECT)
        ->name('admissions.request-correction');
    Route::post('/admissions/{attempt}/authorize-amendment', [AdmissionController::class, 'authorizeAmendment'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_APPLICATIONS_ACCEPT)
        ->name('admissions.authorize-amendment');
    Route::post('/admissions/{attempt}/reconsider', [AdmissionController::class, 'reconsider'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_APPLICATIONS_ACCEPT)
        ->name('admissions.reconsider');
    Route::post('/admissions/{attempt}/reopen', [AdmissionController::class, 'reopen'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_APPLICATIONS_ACCEPT)
        ->name('admissions.reopen');
    Route::post('/admissions/{attempt}/archive', [AdmissionController::class, 'archive'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_APPLICATIONS_DISMISS)
        ->name('admissions.archive');
    Route::post('/admissions/{attempt}/reissue-access-key', [AdmissionController::class, 'reissueAccessKey'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_APPLICATIONS_CORRECT)
        ->name('admissions.reissue-access-key');

    Route::get('/applicant/portal', [ApplicantPortalController::class, 'dashboard'])->name('applicant.portal');
    Route::get('/applicant/schedule', [ApplicantScheduleController::class, 'show'])
        ->name('applicant.schedule');
    Route::post('/applicant/schedule/reserve', [ApplicantScheduleController::class, 'reserve'])
        ->name('applicant.schedule.reserve');
    Route::get('/applicant/applications/{attempt}/correct', [ApplicantPortalController::class, 'correctionForm'])
        ->name('applicant.correction');
    Route::post('/applicant/applications/{attempt}/correct', [ApplicantPortalController::class, 'resubmitCorrection'])
        ->name('applicant.correction.store');

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


    Route::get('/proctor', [ProctorController::class, 'dashboard'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_PROCTOR_ASSIGN)
        ->name('proctor.dashboard');
    Route::post('/exams/sessions/{session}/proctor', [ProctorController::class, 'assignDuty'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_PROCTOR_ASSIGN)
        ->name('proctor.assign');
    Route::post('/proctor/duties/{duty}/revoke', [ProctorController::class, 'revoke'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_PROCTOR_ASSIGN)
        ->name('proctor.revoke');
    Route::post('/proctor/assignments/{assignment}/attendance', [ProctorController::class, 'recordAttendance'])
        ->name('proctor.attendance');
    Route::post('/proctor/assignments/{assignment}/submission', [ProctorController::class, 'recordSubmission'])
        ->name('proctor.submission');

    Route::get('/grading', [GradingController::class, 'index'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_MANUAL)
        ->name('grading.index');
    Route::post('/grading/sessions/{gradingSession}/scores', [GradingController::class, 'recordScore'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_MANUAL)
        ->name('grading.score');
    Route::post('/grading/sessions/{gradingSession}/finalize', [GradingController::class, 'finalize'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_MANUAL)
        ->name('grading.finalize');

    Route::get('/consultation', [ReleaseController::class, 'consultation'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE)
        ->name('consultation.index');
    Route::post('/consultation/{attempt}/decision', [ReleaseController::class, 'recordDecision'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE)
        ->name('consultation.decision');
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

    Route::get('/ui-kit', function () {
        return Inertia::render('UiKit/Gallery');
    })->name('ui-kit.gallery');

    Route::get('/foundation/capability-probe', function () {
        return response()->json(['ok' => true, 'capability' => 'foundation.probe']);
    })->middleware('capability:foundation.probe')->name('foundation.capability-probe');
});
