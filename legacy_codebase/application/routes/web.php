<?php

use App\Http\Controllers\Admin\AcademicYearController;
use App\Http\Controllers\Admin\AiCompanionAdminController;
use App\Http\Controllers\Admin\ApplicationImportController;
use App\Http\Controllers\Admin\AptitudeAreaController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CourseController;
use App\Http\Controllers\Admin\ExamSchedulingAssistantController;
use App\Http\Controllers\Admin\ExamSessionController;
use App\Http\Controllers\Admin\InstitutionController;
use App\Http\Controllers\Admin\KnowledgeDocumentController;
use App\Http\Controllers\Admin\PrivacyPolicyController;
use App\Http\Controllers\Admin\RatingScaleController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ResultSheetTemplateController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SetupController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DirectAssessmentController;
use App\Http\Controllers\Grading\GradingController;
use App\Http\Controllers\Grading\GradingScoreController;
use App\Http\Controllers\Grading\GradingSessionController;
use App\Http\Controllers\Grading\ScoreImportController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Portal\AiCompanionController;
use App\Http\Controllers\Portal\NotificationController as PortalNotificationController;
use App\Http\Controllers\PortalAuthController;
use App\Http\Controllers\Proctor\ProctorSessionController;
use App\Http\Controllers\Proctor\SessionRosterController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Release\ReleasePrintController;
use App\Http\Controllers\ReleaseController;
use App\Support\GoogleOAuthConfig;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/about', [HomeController::class, 'about'])->name('about');

if (GoogleOAuthConfig::isConfigured()) {
    Route::middleware('guest')->group(function () {
        Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])
            ->name('auth.google.redirect');
    });
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
        ->name('auth.google.callback');
}

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:login');
});

Route::get('/apply', [ApplicationController::class, 'create'])->name('applications.create');
Route::post('/applications', [ApplicationController::class, 'store'])->name('applications.store');
Route::get('/apply/success', [ApplicationController::class, 'success'])->name('applications.success');

// Public privacy policy endpoint (for apply page)
Route::get('/api/privacy-policy', [PrivacyPolicyController::class, 'active'])->name('privacy-policy.active');

// Applicant portal (guest: login, setup, forgot/reset password)
Route::middleware(['web', 'portal.guest'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('login', [PortalAuthController::class, 'create'])->name('login');
    Route::post('login', [PortalAuthController::class, 'store'])->middleware('throttle:5,15');
    Route::get('setup/{token}', [PortalAuthController::class, 'setupShow'])->name('setup.show');
    Route::post('setup/{token}', [PortalAuthController::class, 'setupStore'])->name('setup.store');
    Route::get('forgot-password', [PortalAuthController::class, 'forgotPasswordCreate'])->name('forgot-password');
    Route::post('forgot-password', [PortalAuthController::class, 'forgotPasswordStore'])->middleware('throttle:3,15');
    Route::get('reset/{token}', [PortalAuthController::class, 'resetShow'])->name('reset.show');
    Route::post('reset/{token}', [PortalAuthController::class, 'resetStore'])->name('reset.store');
});

// Applicant portal (authenticated)
Route::middleware(['web', 'auth:applicant'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('/', [PortalAuthController::class, 'dashboard'])->name('dashboard');
    Route::post('logout', [PortalAuthController::class, 'destroy'])->name('logout');
    Route::get('notifications', [PortalNotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/{id}/read', [PortalNotificationController::class, 'markRead'])->name('notifications.read');
    Route::get('ai-companion', [AiCompanionController::class, 'index'])->name('ai-companion.index');
    Route::post('ai-companion/chat', [AiCompanionController::class, 'chat'])->middleware('throttle:ai-companion')->name('ai-companion.chat');
    Route::post('ai-companion/clear-history', [AiCompanionController::class, 'clearHistory'])->middleware('throttle:ai-companion-clear')->name('ai-companion.clear-history');

    // Applicant's own application
    Route::get('application', [ApplicationController::class, 'portalShow'])->name('application.show');
    Route::get('application/edit', [ApplicationController::class, 'portalEdit'])->name('application.edit');
    Route::put('application', [ApplicationController::class, 'portalUpdate'])->name('application.update');
    Route::get('admission-slip', [PortalAuthController::class, 'admissionSlipPdf'])->name('admission-slip.pdf');
    Route::get('admission-slip/view', [PortalAuthController::class, 'admissionSlipView'])->name('admission-slip.view');
});

Route::middleware(['auth'])->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile management (self-service for all authenticated staff)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Authenticated user notifications (staff/admin/proctor etc.)
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');

    Route::middleware('role:super_admin')->prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', UserController::class)->except('show')->parameters(['users' => 'user']);
        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::post('settings/admission-slip-preview', [SettingsController::class, 'admissionSlipPreview'])->name('settings.admission-slip-preview');
        Route::get('logs', [AuditLogController::class, 'index'])->name('logs.index');
        Route::get('logs/export', [AuditLogController::class, 'export'])->name('logs.export');
        // Admission slip templates — deprecated; replaced by system_settings.admission_slip_html_template
        // Route::post('admission-slip-templates/preview', [AdmissionSlipTemplateController::class, 'preview'])->name('admission-slip-templates.preview');
        // Route::resource('admission-slip-templates', AdmissionSlipTemplateController::class)->except('show')->parameters(['admission_slip_templates' => 'admission_slip_template']);
        // AI Companion hub (replaces knowledge-documents index)
        Route::get('ai-companion', [AiCompanionAdminController::class, 'index'])->name('ai-companion.index');
        Route::put('ai-companion/persona', [AiCompanionAdminController::class, 'updatePersona'])->name('ai-companion.persona.update');
        // Redirect old knowledge-documents index → new hub
        Route::get('knowledge-documents', fn () => redirect()->route('admin.ai-companion.index'))->name('knowledge-documents.index');
        Route::get('knowledge-documents/create', [KnowledgeDocumentController::class, 'create'])->name('knowledge-documents.create');
        Route::post('knowledge-documents', [KnowledgeDocumentController::class, 'store'])->name('knowledge-documents.store');
        Route::get('knowledge-documents/import', [KnowledgeDocumentController::class, 'importForm'])->name('knowledge-documents.import');
        Route::post('knowledge-documents/import', [KnowledgeDocumentController::class, 'import'])->name('knowledge-documents.import.store');
        Route::get('knowledge-documents/{knowledge_document}/edit', [KnowledgeDocumentController::class, 'edit'])->name('knowledge-documents.edit');
        Route::put('knowledge-documents/{knowledge_document}', [KnowledgeDocumentController::class, 'update'])->name('knowledge-documents.update');
        Route::delete('knowledge-documents/{knowledge_document}', [KnowledgeDocumentController::class, 'destroy'])->name('knowledge-documents.destroy');
        Route::post('knowledge-documents/{knowledge_document}/retry-sync', [KnowledgeDocumentController::class, 'retrySync'])->name('knowledge-documents.retry-sync');
    });

    // Setup Hub — accessible to anyone who manages configuration
    Route::middleware('role:super_admin,registrar_administrator,test_administrator')->prefix('admin')->name('admin.')->group(function () {
        Route::get('setup', [SetupController::class, 'index'])->name('setup.index');
        Route::get('setup/institution', [InstitutionController::class, 'index'])->name('setup.institution.index');
        Route::put('setup/institution', [InstitutionController::class, 'update'])->name('setup.institution.update');
        Route::post('setup/institution/reset', [InstitutionController::class, 'resetDefaults'])->name('setup.institution.reset');
        Route::resource('setup/rating-scales', RatingScaleController::class)
            ->except('show')
            ->parameters(['rating-scales' => 'rating_scale']);
    });

    // Reports
    Route::middleware('role:super_admin,registrar_administrator,test_administrator')->prefix('admin')->name('admin.')->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export/{type}', [ReportController::class, 'export'])->name('reports.export');
    });

    // Exam scheduling: all specific routes before {exam_session} catch-all
    // Must be in ONE group so specific routes are matched before the wildcard
    Route::middleware('role:super_admin,registrar_administrator,test_administrator,proctor')->prefix('admin')->name('admin.')->group(function () {
        Route::get('exam-scheduling', [ExamSessionController::class, 'index'])->name('exam-scheduling.index');
        Route::get('exam-scheduling/create', [ExamSessionController::class, 'create'])->name('exam-scheduling.create');
        Route::get('exam-scheduling/schedule-assistant', fn () => redirect()->route('admin.exam-scheduling.index'))->name('exam-scheduling.schedule-assistant.index');
    });

    // Exam monitoring: separate top-level route, not nested under exam-scheduling
    Route::middleware('role:super_admin,test_administrator,proctor')->prefix('admin')->name('admin.')->group(function () {
        Route::get('exam-monitoring', [ExamSessionController::class, 'monitoring'])->name('exam-monitoring.index');
    });

    Route::middleware('role:super_admin,registrar_administrator')->prefix('admin')->name('admin.')->group(function () {
        Route::post('exam-scheduling/schedule-assistant/chat', [ExamSchedulingAssistantController::class, 'chat'])->name('exam-scheduling.schedule-assistant.chat');
        Route::delete('exam-scheduling/schedule-assistant/conversation', [ExamSchedulingAssistantController::class, 'clearConversation'])->name('exam-scheduling.schedule-assistant.clear');
        Route::post('exam-scheduling/schedule-assistant/apply-schedule', [ExamSchedulingAssistantController::class, 'applySchedule'])->name('exam-scheduling.schedule-assistant.apply');
        Route::post('exam-scheduling/{exam_session}/assign-applicants', [ExamSessionController::class, 'assignApplicants'])->name('exam-scheduling.assign-applicants');
        Route::post('exam-scheduling/{exam_session}/remove-applicant', [ExamSessionController::class, 'removeApplicant'])->name('exam-scheduling.remove-applicant');
        Route::post('exam-scheduling/{exam_session}/publish', [ExamSessionController::class, 'publish'])->name('exam-scheduling.publish');
        Route::post('exam-scheduling/{exam_session}/unpublish', [ExamSessionController::class, 'unpublish'])->name('exam-scheduling.unpublish');
        Route::post('exam-scheduling/{exam_session}/cancel', [ExamSessionController::class, 'cancel'])->name('exam-scheduling.cancel');
        Route::post('exam-scheduling/{exam_session}/start', [ExamSessionController::class, 'start'])->name('exam-scheduling.start');
        Route::post('exam-scheduling/{exam_session}/complete', [ExamSessionController::class, 'complete'])->name('exam-scheduling.complete');
        Route::post('exam-scheduling/{exam_session}/reopen', [ExamSessionController::class, 'reopen'])->name('exam-scheduling.reopen');
        Route::post('exam-scheduling/{exam_session}/backtrack', [ExamSessionController::class, 'backtrack'])->name('exam-scheduling.backtrack');
        Route::post('exam-scheduling', [ExamSessionController::class, 'store'])->name('exam-scheduling.store');
        Route::get('exam-scheduling/{exam_session}/edit', [ExamSessionController::class, 'edit'])->name('exam-scheduling.edit');
        Route::put('exam-scheduling/{exam_session}', [ExamSessionController::class, 'update'])->name('exam-scheduling.update');
        Route::delete('exam-scheduling/{exam_session}', [ExamSessionController::class, 'destroy'])->name('exam-scheduling.destroy');
        // Wildcard show: placed LAST so all specific routes (edit, publish, etc.) are matched first.
        // Accessible by test_administrator and proctor too (matching original group).
        Route::middleware('role:super_admin,registrar_administrator,test_administrator,proctor')->get('exam-scheduling/{exam_session}', [ExamSessionController::class, 'show'])->name('exam-scheduling.show');

        Route::resource('academic-years', AcademicYearController::class)->except('show', 'destroy')->parameters(['academic_years' => 'academic_year']);
        Route::post('academic-years/{academic_year}/activate', [AcademicYearController::class, 'activate'])->name('academic-years.activate');
        Route::post('academic-years/{academic_year}/deactivate', [AcademicYearController::class, 'deactivate'])->name('academic-years.deactivate');
        Route::resource('courses', CourseController::class)->except('show')->parameters(['courses' => 'course']);
        Route::post('courses/{course}/activate', [CourseController::class, 'activate'])->name('courses.activate');
        Route::post('courses/{course}/deactivate', [CourseController::class, 'deactivate'])->name('courses.deactivate');
        Route::post('courses/{course}/restore', [CourseController::class, 'restore'])->name('courses.restore');
        Route::resource('rooms', RoomController::class)->except('show')->parameters(['rooms' => 'room']);
        Route::post('rooms/{room}/activate', [RoomController::class, 'activate'])->name('rooms.activate');
        Route::post('rooms/{room}/deactivate', [RoomController::class, 'deactivate'])->name('rooms.deactivate');
        Route::post('rooms/{room}/restore', [RoomController::class, 'restore'])->name('rooms.restore');
    });

    Route::middleware('role:super_admin,registrar_administrator,test_administrator')->prefix('admin')->name('admin.')->group(function () {
        Route::get('direct-assessments/create', [DirectAssessmentController::class, 'create'])->name('direct-assessments.create');
        Route::post('direct-assessments', [DirectAssessmentController::class, 'store'])->name('direct-assessments.store');
    });

    Route::middleware('role:super_admin,test_administrator')->prefix('admin')->name('admin.')->group(function () {
        Route::resource('aptitude-areas', AptitudeAreaController::class)->except('show')->parameters(['aptitude_areas' => 'aptitude_area']);
        Route::post('aptitude-areas/test-formula', [AptitudeAreaController::class, 'testFormula'])->name('aptitude-areas.test-formula');
        Route::post('aptitude-areas/reorder', [AptitudeAreaController::class, 'reorder'])->name('aptitude-areas.reorder');
    });

    // Staff create/edit applications - bypasses application window restrictions
    Route::middleware('role:super_admin,staff,registrar_administrator')->prefix('admin')->name('admin.applications.')->group(function () {
        // Bulk applicant import - MUST come before {application} wildcard
        Route::get('applications/import', [ApplicationImportController::class, 'importForm'])->name('import');
        Route::post('applications/import', [ApplicationImportController::class, 'import'])->name('import.store');
        Route::post('applications/import/analyze', [ApplicationImportController::class, 'analyze'])->name('import.analyze');
        Route::post('applications/import/preview', [ApplicationImportController::class, 'preview'])->name('import.preview');
        Route::post('applications/import/confirm', [ApplicationImportController::class, 'confirm'])->name('import.confirm');
        Route::get('applications/import/template', [ApplicationImportController::class, 'template'])->name('import.template');

        Route::get('applications', [ApplicationController::class, 'index'])->name('index');
        Route::get('applications/create', [ApplicationController::class, 'create'])->name('create');
        Route::post('applications', [ApplicationController::class, 'storeAdmin'])->name('store');
        Route::get('applications/{application}', [ApplicationController::class, 'show'])->name('admin-show');
        Route::get('applications/{application}/edit', [ApplicationController::class, 'edit'])->name('edit');
        Route::put('applications/{application}', [ApplicationController::class, 'updateAdmin'])->name('update');
        Route::put('applications/{application}/accept', [ApplicationController::class, 'accept'])->name('accept');
        Route::post('applications/{application}/resend-setup-email', [ApplicationController::class, 'resendSetupEmail'])->name('resend-setup-email');
        Route::put('applications/{application}/dismiss', [ApplicationController::class, 'dismiss'])->name('dismiss');
        Route::post('applications/bulk-accept', [ApplicationController::class, 'bulkAccept'])->name('bulk-accept');
        Route::post('applications/bulk-dismiss', [ApplicationController::class, 'bulkDismiss'])->name('bulk-dismiss');
        Route::post('applications/bulk-reopen', [ApplicationController::class, 'bulkReopen'])->name('bulk-reopen');
        Route::put('applications/{application}/reopen', [ApplicationController::class, 'reopen'])->name('reopen');
        Route::delete('applications/{application}', [ApplicationController::class, 'destroy'])->name('destroy');
        Route::get('applications/{application}/admission-slip', [ApplicationController::class, 'admissionSlip'])->name('admission-slip');
        Route::get('applications/{application}/admission-slip/print', [ApplicationController::class, 'admissionSlipPrint'])->name('admission-slip-print');
        Route::post('applications/admission-slips/bulk-print', [ApplicationController::class, 'admissionSlipBulkPrint'])->name('admission-slip-bulk-print');
        Route::post('applications/admission-slips/bulk-pdf', [ApplicationController::class, 'admissionSlipBulkPdf'])->name('admission-slip-bulk-pdf');

        // Privacy Policy CRUD
        Route::get('privacy-policies', [PrivacyPolicyController::class, 'index'])->name('privacy-policies.index');
        Route::get('privacy-policies/create', [PrivacyPolicyController::class, 'create'])->name('privacy-policies.create');
        Route::post('privacy-policies', [PrivacyPolicyController::class, 'store'])->name('privacy-policies.store');
        Route::get('privacy-policies/{privacy_policy}/edit', [PrivacyPolicyController::class, 'edit'])->name('privacy-policies.edit');
        Route::put('privacy-policies/{privacy_policy}', [PrivacyPolicyController::class, 'update'])->name('privacy-policies.update');
        Route::delete('privacy-policies/{privacy_policy}', [PrivacyPolicyController::class, 'destroy'])->name('privacy-policies.destroy');
    });

    // Privacy policy activate/deactivate - restricted to super_admin and registrar_administrator
    Route::middleware('role:super_admin,registrar_administrator')->prefix('admin')->name('admin.applications.')->group(function () {
        Route::post('privacy-policies/{privacy_policy}/activate', [PrivacyPolicyController::class, 'activate'])->name('privacy-policies.activate');
        Route::post('privacy-policies/{privacy_policy}/deactivate', [PrivacyPolicyController::class, 'deactivate'])->name('privacy-policies.deactivate');
    });

    Route::get('/proctor', fn () => redirect()->route('dashboard'))->middleware('role:super_admin,proctor');
    Route::middleware('role:super_admin,registrar_administrator,proctor,test_administrator')->prefix('proctor')->name('proctor.')->group(function () {
        Route::get('my-sessions', [ProctorSessionController::class, 'mySessions'])->name('my-sessions');
        Route::get('sessions/{exam_session}', [SessionRosterController::class, 'show'])->name('sessions.show');
        Route::post('sessions/{exam_session}/attendance', [SessionRosterController::class, 'storeAttendance'])->name('sessions.attendance');
        Route::post('sessions/{exam_session}/submission', [SessionRosterController::class, 'storeSubmission'])->name('sessions.submission');
        Route::post('sessions/{exam_session}/submission-bulk', [SessionRosterController::class, 'storeSubmissionBulk'])->name('sessions.submission-bulk');
        Route::post('sessions/{exam_session}/start', [SessionRosterController::class, 'start'])->name('sessions.start');
        Route::post('sessions/{exam_session}/close', [SessionRosterController::class, 'close'])->name('sessions.close');
        Route::post('sessions/{exam_session}/bulk-attendance', [SessionRosterController::class, 'bulkAttendance'])->name('sessions.bulk-attendance');
        Route::post('sessions/{exam_session}/extend', [SessionRosterController::class, 'extend'])->name('sessions.extend');
    });

    // Test Admin session management — dedicated index + roster with full permissions
    Route::middleware('role:super_admin,registrar_administrator,test_administrator')->prefix('admin/test-admin')->name('admin.test-admin.')->group(function () {
        Route::get('sessions', [ExamSessionController::class, 'testAdminIndex'])->name('sessions.index');
        Route::get('sessions/{exam_session}/roster', [ExamSessionController::class, 'testAdminRoster'])->name('sessions.roster');
    });
    // Grading
    Route::middleware('role:super_admin,test_administrator')->prefix('admin')->name('admin.grading.')->group(function () {
        Route::get('grading', [GradingController::class, 'index'])->name('index');
        Route::post('grading', [GradingController::class, 'store']);
        Route::get('grading/sessions/{grading_session}', [GradingSessionController::class, 'show'])->name('sessions.show');
        Route::put('grading/sessions/{grading_session}/workflow', [GradingSessionController::class, 'updateWorkflowStatus'])->name('sessions.workflow');
        Route::get('grading/sessions/{grading_session}/applicants/{applicant}', [GradingScoreController::class, 'show'])->name('sessions.applicants.scores');
        Route::put('grading/sessions/{grading_session}/applicants/{applicant}/scores', [GradingScoreController::class, 'update'])->name('sessions.applicants.scores.update');
        Route::delete('grading/sessions/{grading_session}/applicants/{applicant}/scores', [GradingScoreController::class, 'destroy'])->name('sessions.applicants.scores.destroy');
        // Bulk score import
        Route::get('grading/import', [ScoreImportController::class, 'importForm'])->name('import');
        Route::post('grading/import', [ScoreImportController::class, 'import'])->name('import.store');
        Route::post('grading/import/analyze', [ScoreImportController::class, 'analyze'])->name('import.analyze');
        Route::get('grading/import/template', [ScoreImportController::class, 'template'])->name('import.template');
        // Preview flow
        Route::post('grading/import/preview', [ScoreImportController::class, 'preview'])->name('import.preview');
        Route::post('grading/import/confirm', [ScoreImportController::class, 'confirm'])->name('import.confirm');
        // GET fallbacks — redirect to import form on page refresh
        Route::get('grading/import/preview', fn () => redirect()->route('admin.grading.import'));
        Route::get('grading/import/confirm', fn () => redirect()->route('admin.grading.import'));
    });

    // Release Management
    Route::middleware('role:super_admin,test_administrator')
        ->prefix('admin/release')
        ->name('admin.release.')
        ->group(function () {
            Route::get('/', [ReleaseController::class, 'index'])->name('index');
            Route::post('/summaries/{summary}/release', [ReleaseController::class, 'release'])->name('summaries.release');
            Route::post('/summaries/{summary}/unrelease', [ReleaseController::class, 'unrelease'])->name('summaries.unrelease');
            Route::post('/summaries/bulk-release', [ReleaseController::class, 'releaseBulk'])->name('summaries.bulk-release');
            Route::post('/summaries/release-all', [ReleaseController::class, 'releaseAll'])->name('summaries.release-all');
            Route::put('/summaries/by-applicant/{applicantId}', [ReleaseController::class, 'storeOrUpdateByApplicant'])->name('summaries.storeOrUpdate');
            // Result Sheet Templates
            Route::post('result-templates/preview', [ResultSheetTemplateController::class, 'preview'])->name('result-templates.preview');
            Route::post('result-templates/validate-document', [ResultSheetTemplateController::class, 'validateDocument'])->name('result-templates.validate-document');
            Route::post('result-templates/{result_template}/activate', [ResultSheetTemplateController::class, 'activate'])->name('result-templates.activate');
            Route::post('result-templates/{result_template}/deactivate', [ResultSheetTemplateController::class, 'deactivate'])->name('result-templates.deactivate');
            Route::resource('result-templates', ResultSheetTemplateController::class)->except('show');

            // Print batch
            Route::prefix('print')->name('print.')->group(function () {
                Route::get('bulk', [ReleasePrintController::class, 'printBulkAgnostic'])->name('bulk');
                Route::get('bulk-pdf', [ReleasePrintController::class, 'printBulkAgnosticPdf'])->name('bulk-agnostic-pdf');
                Route::get('bulk-docx', [ReleasePrintController::class, 'downloadBulkAgnosticDocx'])->name('bulk-agnostic-docx');
                Route::get('{grading_session}', [ReleasePrintController::class, 'index'])->name('index');
                Route::post('{grading_session}/mark-printed', [ReleasePrintController::class, 'markPrinted'])->name('mark-printed');
                Route::get('{grading_session}/applicants/{applicant}', [ReleasePrintController::class, 'resultSheet'])->name('result-sheet');
                Route::get('{grading_session}/applicants/{applicant}/pdf', [ReleasePrintController::class, 'resultSheetPdf'])->name('result-sheet-pdf');
                Route::get('{grading_session}/applicants/{applicant}/docx', [ReleasePrintController::class, 'downloadDocx'])->name('result-sheet-docx');
                Route::get('{grading_session}/print-bulk', [ReleasePrintController::class, 'printBulk'])->name('print-bulk');
                Route::get('{grading_session}/print-bulk-pdf', [ReleasePrintController::class, 'printBulkPdf'])->name('print-bulk-pdf');
                Route::get('{grading_session}/print-bulk-docx', [ReleasePrintController::class, 'downloadBulkDocx'])->name('print-bulk-docx');
            });
        });
});
