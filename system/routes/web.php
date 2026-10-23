<?php

use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Admission\AdmissionActionController;
use App\Http\Controllers\Admission\AdmissionDocumentCorrectionRequestController;
use App\Http\Controllers\Admission\AdmissionReviewController;
use App\Http\Controllers\Admission\AdmissionSupportController;
use App\Http\Controllers\Admission\ApplicantApplyController;
use App\Http\Controllers\Admission\ApplicantOfficeInquiryController;
use App\Http\Controllers\Admission\ApplicationOpeningAlertController;
use App\Http\Controllers\Admission\CaseConversationController;
use App\Http\Controllers\Admission\ProgramPoolController;
use App\Http\Controllers\Admission\PublicOfficeInquiryController;
use App\Http\Controllers\Applicant\ApplicantAdmissionClearanceController;
use App\Http\Controllers\Applicant\ApplicantAuditLogController;
use App\Http\Controllers\Applicant\ApplicantConsultationController;
use App\Http\Controllers\Applicant\ApplicantExamDayController;
use App\Http\Controllers\Applicant\ApplicantExamScoringController;
use App\Http\Controllers\Applicant\ApplicantPortalController;
use App\Http\Controllers\Applicant\ApplicantProgramChoiceController;
use App\Http\Controllers\Applicant\ApplicantQrController;
use App\Http\Controllers\Applicant\ApplicantReleaseDayController;
use App\Http\Controllers\Applicant\ApplicantResultController;
use App\Http\Controllers\Applicant\ApplicantScheduleController;
use App\Http\Controllers\Assessment\GradingController;
use App\Http\Controllers\Assessment\GradingScoreController;
use App\Http\Controllers\Assessment\OmrBlankInstanceController;
use App\Http\Controllers\Assessment\OmrCameraSensorController;
use App\Http\Controllers\Assessment\OmrDiagnosticWorkbenchController;
use App\Http\Controllers\Assessment\OmrGradingItemReviewController;
use App\Http\Controllers\Assessment\OmrProductionWorkbenchController;
use App\Http\Controllers\Assessment\OmrWorkbenchCaptureActionController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Communications\HomepageStudioController;
use App\Http\Controllers\Communications\PublicPoliciesController;
use App\Http\Controllers\Communications\StaffCommunicationsController;
use App\Http\Controllers\Communications\StaffPublicPoliciesController;
use App\Http\Controllers\Consultation\ConsultationDashboardController;
use App\Http\Controllers\Consultation\ConsultationDecisionController;
use App\Http\Controllers\Consultation\ConsultationEncounterController;
use App\Http\Controllers\Consultation\ConsultationEvidenceController;
use App\Http\Controllers\Consultation\ConsultationMediaController;
use App\Http\Controllers\Consultation\ConsultationSessionController;
use App\Http\Controllers\Consultation\OnlineConsultationStartController;
use App\Http\Controllers\Examination\DirectAssessmentController;
use App\Http\Controllers\Examination\ExamMonitoringAttendanceController;
use App\Http\Controllers\Examination\ExamMonitoringController;
use App\Http\Controllers\Examination\ExamOfflineAttendanceController;
use App\Http\Controllers\Examination\ExamSessionController;
use App\Http\Controllers\Examination\SessionSearchSuggestionController;
use App\Http\Controllers\Foundation\DemoGuideController;
use App\Http\Controllers\Foundation\DemoStageController;
use App\Http\Controllers\Foundation\DocsStateController;
use App\Http\Controllers\Foundation\GlossaryController;
use App\Http\Controllers\Foundation\ManuscriptAnnotationController;
use App\Http\Controllers\Foundation\PracticePreparationController;
use App\Http\Controllers\Foundation\PublicInstitutionLogoController;
use App\Http\Controllers\Foundation\ResearchSessionGuideController;
use App\Http\Controllers\Foundation\StatusController as FoundationStatusController;
use App\Http\Controllers\Foundation\TamEvaluationController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Ingestion\ImportAnalyzeController;
use App\Http\Controllers\Ingestion\ImportController;
use App\Http\Controllers\Ingestion\ImportTemplateController;
use App\Http\Controllers\Knowledge\KnowledgeSourceController;
use App\Http\Controllers\Notifications\NotificationController;
use App\Http\Controllers\Notifications\PushSubscriptionController;
use App\Http\Controllers\PrintingController;
use App\Http\Controllers\ProgramHead\ProgramHeadActionController;
use App\Http\Controllers\ProgramHead\ProgramHeadController;
use App\Http\Controllers\ProgramHead\ProgramHeadResultController;
use App\Http\Controllers\PublicSite\HomepageMediaController;
use App\Http\Controllers\PublicSite\ProgramPublicProfileMediaController;
use App\Http\Controllers\PublicSite\PublicCommunicationsController;
use App\Http\Controllers\PublicSite\PublicFaqController;
use App\Http\Controllers\PublicSite\WayfindingVenuesController;
use App\Http\Controllers\Registrar\AdmissionClearanceController;
use App\Http\Controllers\Registrar\AdmissionRequirementInternalNoteController;
use App\Http\Controllers\Registrar\EnrollmentEligibilityController;
use App\Http\Controllers\Release\ReleaseController;
use App\Http\Controllers\Release\ReleasePrintController;
use App\Http\Controllers\Reports\DocumentPrintController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Setup\AcademicCycleController;
use App\Http\Controllers\Setup\AdmissionsCommunicationTemplateController;
use App\Http\Controllers\Setup\AdmissionSupportSetupController;
use App\Http\Controllers\Setup\AiControlCenterController;
use App\Http\Controllers\Setup\ApplicationOpeningSubscriberController;
use App\Http\Controllers\Setup\ApplicationSuggestionListController;
use App\Http\Controllers\Setup\ApplicationWindowController;
use App\Http\Controllers\Setup\AptitudeAreaController;
use App\Http\Controllers\Setup\ConsultationPolicyController;
use App\Http\Controllers\Setup\CycleNotificationPolicyController;
use App\Http\Controllers\Setup\DepartmentController;
use App\Http\Controllers\Setup\ExaminationCommunicationTemplateController;
use App\Http\Controllers\Setup\ExamMaterialsController;
use App\Http\Controllers\Setup\InstitutionProfileController;
use App\Http\Controllers\Setup\IntakeFieldPolicyController;
use App\Http\Controllers\Setup\IntakeRequirementController;
use App\Http\Controllers\Setup\KitPairedAnswerKeyPreviewController;
use App\Http\Controllers\Setup\NormPackageController;
use App\Http\Controllers\Setup\NoticeDocumentStudioController;
use App\Http\Controllers\Setup\NoticePhysicalDocumentController;
use App\Http\Controllers\Setup\NotificationAdmissionsDeskController;
use App\Http\Controllers\Setup\NotificationExaminationDeskController;
use App\Http\Controllers\Setup\NotificationResultsDeskController;
use App\Http\Controllers\Setup\NotificationSetupController;
use App\Http\Controllers\Setup\OmrCanonicalTemplateController;
use App\Http\Controllers\Setup\OmrConfigurationController;
use App\Http\Controllers\Setup\OmrVerificationItemReviewController;
use App\Http\Controllers\Setup\ProgramController;
use App\Http\Controllers\Setup\ProgramGovernanceController;
use App\Http\Controllers\Setup\ProgramHubController;
use App\Http\Controllers\Setup\ProgramPublicProfileController;
use App\Http\Controllers\Setup\ProgramQuotaController;
use App\Http\Controllers\Setup\RegistrarQuotaGovernanceGrantController;
use App\Http\Controllers\Setup\ReportTemplateController;
use App\Http\Controllers\Setup\ResultsCommunicationTemplateController;
use App\Http\Controllers\Setup\ResultSheetTemplateController;
use App\Http\Controllers\Setup\RoomController;
use App\Http\Controllers\Setup\SeatingArrangementController;
use App\Http\Controllers\Setup\SetupDashboardController;
use App\Http\Controllers\Setup\TestInstrumentController;
use App\Http\Controllers\Setup\TestKitController;
use App\Http\Controllers\Staff\AccountSetupController;
use App\Http\Controllers\Staff\AdminDashboardController;
use App\Http\Controllers\Staff\ApplicantQrController as StaffQrController;
use App\Http\Controllers\Staff\ApplicantScanEntryController;
use App\Http\Controllers\Staff\AuditLogController;
use App\Http\Controllers\Staff\BatchVoucherController;
use App\Http\Controllers\Staff\HomeController;
use App\Http\Controllers\Staff\PersonnelController;
use App\Http\Controllers\Staff\ProfileController;
use App\Http\Controllers\Staff\SessionKeepaliveController;
use App\Http\Controllers\Staff\SetupDelegationController;
use App\Http\Controllers\Staff\StaffCycleDispositionController;
use App\Http\Controllers\Staff\TestAdministratorController;
use App\Http\Controllers\Staff\VoucherController;
use App\Http\Controllers\Xiam\XiamConversationController;
use App\Http\Controllers\Xiam\XiamModeController;
use App\Http\Controllers\Xiam\XiamProposalController;
use App\Http\Controllers\Xiam\XiamRunController;
use App\Http\Controllers\Xiam\XiamTurnController;
use App\Http\Middleware\EnsureDemoEnvironment;
use App\Http\Middleware\EnsurePersonnelAccess;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrQuestionBooklet;
use App\PublicSite\Queries\PublicHomeProjection;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController as FortifyAuthenticatedSessionController;

Route::get('/', function (PublicHomeProjection $projection) {
    return Inertia::render('Home', $projection->toArray());
})->name('home');
Route::get('/programs/{code}', [PublicCommunicationsController::class, 'program'])->name('programs.public.show');
Route::get('/programs/{code}/announcements', [PublicCommunicationsController::class, 'programAnnouncements'])->name('programs.public.announcements');
Route::get('/programs/{code}/policies', [PublicPoliciesController::class, 'forProgram'])->name('programs.public.policies');
Route::get('/programs/{code}/photos/{photo}', [ProgramPublicProfileMediaController::class, 'show'])->whereNumber('photo')->name('programs.public.photos.show');
Route::get('/announcements', [PublicCommunicationsController::class, 'announcements'])->name('announcements.index');
Route::get('/announcements/{announcement}', [PublicCommunicationsController::class, 'announcement'])->whereNumber('announcement')->name('announcements.show');
Route::get('/faqs', [PublicFaqController::class, 'index'])->name('faqs.index');
Route::prefix('public/inquiries')->name('public.inquiries.')->group(function (): void {
    Route::middleware('throttle:public-inquiry-snapshot')->group(function (): void {
        Route::get('/modal', [PublicOfficeInquiryController::class, 'modal'])->name('modal');
        Route::get('/{inquiry}/snapshot', [PublicOfficeInquiryController::class, 'snapshot'])->whereNumber('inquiry')->name('snapshot');
        Route::post('/{inquiry}/read', [PublicOfficeInquiryController::class, 'read'])->whereNumber('inquiry')->name('read');
        Route::post('/clear-device', [PublicOfficeInquiryController::class, 'clearDevice'])->name('clear-device');
    });
    Route::post('/', [PublicOfficeInquiryController::class, 'store'])
        ->middleware('throttle:public-inquiry-create')
        ->name('store');
    Route::post('/{inquiry}/messages', [PublicOfficeInquiryController::class, 'send'])
        ->whereNumber('inquiry')
        ->middleware('throttle:public-inquiry-message')
        ->name('messages.store');
});
Route::get('/policies', [PublicCommunicationsController::class, 'policies'])->name('policies.index');
Route::get('/policies/privacy', [PublicCommunicationsController::class, 'privacyPolicy'])->name('policies.privacy');
Route::get('/policies/{policy}', [PublicPoliciesController::class, 'show'])->whereNumber('policy')->name('public-policies.show');
Route::get('/institution/logo', [PublicInstitutionLogoController::class, 'show'])->name('institution.logo.public');

Route::get('/api/wayfinding/venues', WayfindingVenuesController::class)->name('api.wayfinding.venues');
Route::get('/health', HealthController::class)->name('health');
Route::get('/omr-preview', function () {
    return response()->file(public_path('omr-split-preview.html'));
});
Route::get('/original-preview', function () {
    return response()->file(public_path('original-preview.html'));
});

Route::get('/scan/applicant/{code}', [ApplicantScanEntryController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('applicant.scan-entry');

Route::prefix('apply')->group(function () {
    Route::get('/', [ApplicantApplyController::class, 'applyForm'])->name('apply.form');
    Route::post('/', [ApplicantApplyController::class, 'submit'])->middleware('throttle:intake-apply')->name('apply.submit');
    Route::get('/confirmation', [ApplicantApplyController::class, 'confirmation'])->name('apply.confirmation');
    Route::post('/opening-alerts', [ApplicationOpeningAlertController::class, 'store'])
        ->middleware('throttle:application-opening-signup')->name('apply.opening-alerts.store');
    Route::get('/opening-alerts/prefill/{token}', [ApplicationOpeningAlertController::class, 'prefill'])
        ->middleware('throttle:application-opening-token')->name('apply.opening-alerts.prefill');
    Route::get('/opening-alerts/{token}', [ApplicationOpeningAlertController::class, 'show'])
        ->middleware('throttle:application-opening-token')->name('apply.opening-alerts.show');
    Route::post('/opening-alerts/{token}', [ApplicationOpeningAlertController::class, 'resolve'])
        ->middleware('throttle:application-opening-token')->name('apply.opening-alerts.resolve');
    Route::post('/confirmation/enter', [ApplicantPortalController::class, 'enterFromConfirmation'])
        ->middleware('throttle:10,1')
        ->name('apply.confirmation.enter');
    Route::get('/confirmation/print', [ApplicantApplyController::class, 'printConfirmationSlip'])->name('apply.confirmation.print');
});

Route::middleware('guest')->prefix('applicant')->group(function () {
    Route::get('/login', [ApplicantPortalController::class, 'loginForm'])->name('applicant.login');
    Route::post('/login', [ApplicantPortalController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('applicant.login.store');
    Route::post('/login/voucher', [ApplicantPortalController::class, 'redeemVoucher'])
        ->middleware('throttle:10,1')
        ->name('applicant.login.voucher');
    // D-085: password setup removed — keep alias for old links.
    Route::get('/setup', [ApplicantPortalController::class, 'setupForm'])->name('applicant.setup');
});

Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect']);
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [FortifyAuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('login.store');
    Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.login');
    Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('two-factor.login.store');
    Route::delete('/two-factor-challenge', [TwoFactorChallengeController::class, 'destroy'])->name('two-factor.cancel');

});
Route::get('/staff/activate', [AccountSetupController::class, 'activate'])->name('staff.activate');
Route::post('/staff/activate', [AccountSetupController::class, 'redeem'])->middleware('throttle:5,1')->name('staff.activate.redeem');
Route::get('/staff/account-setup', [AccountSetupController::class, 'setup'])->name('staff.account-setup');
Route::post('/staff/account-setup/email', [AccountSetupController::class, 'chooseEmail'])
    ->middleware('throttle:5,1')->name('staff.account-setup.email');
Route::post('/staff/account-setup/email/verification', [AccountSetupController::class, 'sendSetupEmailVerification'])
    ->middleware('throttle:5,1')->name('staff.account-setup.email.verification');
Route::get('/staff/account-setup/email/confirm/{token}', [AccountSetupController::class, 'showSetupEmailConfirmation'])
    ->middleware('throttle:10,1')->name('staff.account-setup.email.confirm');
Route::post('/staff/account-setup/email/confirm', [AccountSetupController::class, 'confirmSetupEmailVerification'])
    ->middleware('throttle:5,1')->name('staff.account-setup.email.confirm.store');
Route::get('/staff/account-setup/google', [GoogleAuthController::class, 'setupRedirect'])
    ->middleware('throttle:5,1')->name('staff.account-setup.google');
Route::post('/staff/account-setup/password', [AccountSetupController::class, 'password'])->middleware('throttle:5,1')->name('staff.account-setup.password');
Route::post('/staff/account-setup/two-factor', [AccountSetupController::class, 'beginTwoFactor'])->name('staff.account-setup.two-factor');
Route::post('/staff/account-setup/two-factor/confirm', [AccountSetupController::class, 'confirmTwoFactor'])->middleware('throttle:5,1')->name('staff.account-setup.two-factor.confirm');
Route::post('/staff/account-setup/complete', [AccountSetupController::class, 'complete'])->name('staff.account-setup.complete');
Route::get('/staff/profile/email/confirm/{token}', [ProfileController::class, 'confirmEmailChange'])
    ->name('staff.profile.email.confirm')
    ->middleware(['staff.surface:allow-guest', 'throttle:10,1']);

Route::get('/demo/diagrams/{diagram}', [DemoGuideController::class, 'diagram'])
    ->where('diagram', '[A-Za-z0-9-]+')
    ->middleware(EnsureDemoEnvironment::class)
    ->name('demo.diagrams.show');
Route::get('/demo/materials/{material}', [DemoGuideController::class, 'material'])
    ->where('material', '[A-Za-z0-9-]+')
    ->middleware(EnsureDemoEnvironment::class)
    ->name('demo.materials.show');
Route::get('/demo/research', [ResearchSessionGuideController::class, 'index'])
    ->middleware(EnsureDemoEnvironment::class)
    ->name('demo.research.index');
Route::get('/demo/research/instruments/{role}', [ResearchSessionGuideController::class, 'instrument'])
    ->where('role', '[a-z-]+')
    ->middleware(EnsureDemoEnvironment::class)
    ->name('demo.research.instrument');
Route::get('/demo/research/facilitator', [ResearchSessionGuideController::class, 'facilitator'])
    ->middleware([EnsureDemoEnvironment::class, 'auth'])
    ->name('demo.research.facilitator');

Route::get('/demo', [DemoStageController::class, 'index'])
    ->middleware(EnsureDemoEnvironment::class)
    ->name('demo.index');
Route::post('/demo/generate', [DemoStageController::class, 'generate'])
    ->middleware(EnsureDemoEnvironment::class)
    ->name('demo.generate');
Route::get('/manuscript', function () {
    $token = null;
    $tokenPath = storage_path('app/private/manuscript-review-token');
    if (file_exists($tokenPath)) {
        $candidate = trim((string) file_get_contents($tokenPath));
        if (preg_match('/^[a-f0-9]{48}$/', $candidate)) {
            $token = $candidate;
        }
    }

    if ($token === null) {
        $directories = glob(public_path('manuscript-review/*'), GLOB_ONLYDIR) ?: [];
        foreach ($directories as $dir) {
            $dirName = basename($dir);
            if (preg_match('/^[a-f0-9]{48}$/', $dirName) && file_exists("{$dir}/index.html")) {
                $token = $dirName;
                break;
            }
        }
    }
    if ($token === null && file_exists(public_path('manuscript-review/85733ec6bc596d730a1f49c07529efa40e5dc86980120222/index.html'))) {
        $token = '85733ec6bc596d730a1f49c07529efa40e5dc86980120222';
    }

    if ($token !== null) {
        return redirect("/manuscript-review/{$token}/index.html");
    }

    abort(404);
})->name('manuscript.index');

Route::get('/manuscript-review', function () {
    return redirect()->route('manuscript.index');
})->name('manuscript.review');
Route::prefix('/api/dev/manuscript-annotations')->group(function () {
    Route::get('/', [ManuscriptAnnotationController::class, 'index'])->name('dev.manuscript.annotations.index');
    Route::post('/', [ManuscriptAnnotationController::class, 'store'])->name('dev.manuscript.annotations.store');
    Route::patch('/{id}', [ManuscriptAnnotationController::class, 'update'])->name('dev.manuscript.annotations.update');
    Route::delete('/{id}', [ManuscriptAnnotationController::class, 'destroy'])->name('dev.manuscript.annotations.destroy');
});
Route::get('/homepage/images/{media}', [HomepageMediaController::class, 'show'])
    ->name('homepage.images.show');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/staff/session-keepalive', [SessionKeepaliveController::class, 'status'])
        ->name('staff.session.status');
    Route::post('/staff/session-keepalive', SessionKeepaliveController::class)
        ->name('staff.session.keepalive');
    Route::get('/staff', HomeController::class)->name('staff.home');
    Route::get('/admin', [AdminDashboardController::class, 'index'])
        ->middleware('capability:'.CapabilityCatalog::EXECUTIVE_DASHBOARD_VIEW)
        ->name('admin.dashboard');
    Route::prefix('communications')->name('communications.')->group(function (): void {
        Route::get('/', [StaffCommunicationsController::class, 'index'])->name('index');
        Route::get('/create', [StaffCommunicationsController::class, 'create'])->name('create');
        Route::get('/{announcement}/edit', [StaffCommunicationsController::class, 'edit'])->whereNumber('announcement')->name('edit');
        Route::post('/', [StaffCommunicationsController::class, 'store'])->name('store');
        Route::put('/{announcement}', [StaffCommunicationsController::class, 'update'])->whereNumber('announcement')->name('update');
        Route::post('/{announcement}/publish', [StaffCommunicationsController::class, 'publish'])->whereNumber('announcement')->name('publish');
        Route::post('/{announcement}/withdraw', [StaffCommunicationsController::class, 'withdraw'])->whereNumber('announcement')->name('withdraw');
    });
    Route::prefix('communications/policies')->name('communications.policies.')->group(function (): void {
        Route::get('/', [StaffPublicPoliciesController::class, 'index'])->name('index');
        Route::get('/create', [StaffPublicPoliciesController::class, 'create'])->name('create');
        Route::post('/', [StaffPublicPoliciesController::class, 'store'])->name('store');
        Route::post('/{policy}/designate-intake', [StaffPublicPoliciesController::class, 'designateIntake'])
            ->whereNumber('policy')->name('designate-intake');
        Route::get('/{policy}/edit', [StaffPublicPoliciesController::class, 'edit'])->whereNumber('policy')->name('edit');
        Route::put('/{policy}', [StaffPublicPoliciesController::class, 'update'])->whereNumber('policy')->name('update');
        Route::post('/{policy}/publish', [StaffPublicPoliciesController::class, 'publish'])->whereNumber('policy')->name('publish');
        Route::post('/{policy}/withdraw', [StaffPublicPoliciesController::class, 'withdraw'])->whereNumber('policy')->name('withdraw');
    });
    Route::prefix('communications/homepage')->name('communications.homepage.')->middleware(
        'capability:'.CapabilityCatalog::COMMUNICATIONS_HOMEPAGE_MANAGE,
    )->group(function (): void {
        Route::get('/', [HomepageStudioController::class, 'index'])->name('index');
        Route::get('/hero', [HomepageStudioController::class, 'hero'])->name('hero');
        Route::put('/hero', [HomepageStudioController::class, 'updateHero'])->name('hero.update');
        Route::post('/images', [HomepageStudioController::class, 'stageImage'])->name('images.store');
        Route::get('/images/{media}', [HomepageStudioController::class, 'staffImage'])->name('images.show');
        Route::get('/stories/create', [HomepageStudioController::class, 'createStory'])->name('stories.create');
        Route::post('/stories', [HomepageStudioController::class, 'storeStory'])->name('stories.store');
        Route::put('/stories/order', [HomepageStudioController::class, 'reorderStories'])->name('stories.order');
        Route::get('/stories/{story}/edit', [HomepageStudioController::class, 'editStory'])->name('stories.edit');
        Route::put('/stories/{story}', [HomepageStudioController::class, 'updateStory'])->name('stories.update');
        Route::patch('/stories/{story}/toggle-active', [HomepageStudioController::class, 'toggleStoryActive'])->name('stories.toggle-active');
    });
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export/{type}', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/reports/templates', [ReportController::class, 'templates'])->name('reports.templates.index');
    Route::post('/reports/templates', [ReportController::class, 'storeTemplate'])->name('reports.templates.store');
    Route::post('/reports/templates/{template}/activate', [ReportController::class, 'activateTemplate'])->name('reports.templates.activate');
    Route::post('/reports/templates/preview', [ReportController::class, 'previewTemplate'])->name('reports.templates.preview');
    Route::prefix('document-printing')->name('document-printing.')->group(function (): void {
        $controller = DocumentPrintController::class;
        Route::get('/scope-options', [$controller, 'scopeOptions'])->name('scope-options');
        Route::post('/preflight', [$controller, 'preflight'])->name('preflight');
        Route::post('/render', [$controller, 'render'])->name('render');
        Route::get('/jobs/{job}', [$controller, 'jobStatus'])->whereNumber('job')->name('jobs.status');
        Route::get('/jobs/{job}/download', [$controller, 'download'])->whereNumber('job')->name('jobs.download');
        Route::post('/jobs/{job}/mark-printed', [$controller, 'markJobPrinted'])->whereNumber('job')->name('jobs.mark-printed');
        Route::post('/mark-printed', [$controller, 'markPrinted'])->name('mark-printed');
    });

    $xiamRunDeadlineSeconds = max(1, (int) config('xiam.execution_budget.run_deadline_seconds', 30));
    $xiamSessionLockSeconds = $xiamRunDeadlineSeconds + 60;
    $xiamSessionLockWaitSeconds = $xiamRunDeadlineSeconds + 30;

    Route::prefix('xiam')->name('xiam.')->group(function () use ($xiamSessionLockSeconds, $xiamSessionLockWaitSeconds): void {
        Route::get('/mode', [XiamModeController::class, 'show'])->name('mode.show');
        Route::post('/mode', [XiamModeController::class, 'update'])->name('mode.update')->block($xiamSessionLockSeconds, $xiamSessionLockWaitSeconds);
        Route::post('/turns', [XiamTurnController::class, 'store'])->middleware('throttle:xiam-turns')->name('turns.store')->block($xiamSessionLockSeconds, $xiamSessionLockWaitSeconds);
        Route::post('/conversations/clear', [XiamConversationController::class, 'clear'])->name('conversations.clear');
        Route::get('/conversations/{conversation}/snapshot', [XiamConversationController::class, 'snapshot'])->name('conversations.snapshot');
        Route::get('/conversations/{conversation}/stream', [XiamConversationController::class, 'stream'])->name('conversations.stream');
        Route::post('/runs/{run}/cancel', [XiamRunController::class, 'cancel'])->name('runs.cancel');
        Route::post('/emergency-stop', [XiamRunController::class, 'emergencyStop'])->name('emergency-stop');
        Route::post('/proposals/{proposal}/confirm', [XiamProposalController::class, 'confirm'])->name('proposals.confirm')->block($xiamSessionLockSeconds, $xiamSessionLockWaitSeconds);
        Route::post('/proposals/{proposal}/cancel', [XiamProposalController::class, 'cancel'])->name('proposals.cancel');
    });

    Route::prefix('knowledge')->name('knowledge.')->group(function (): void {
        Route::get('/', [KnowledgeSourceController::class, 'index'])->name('index');
        Route::get('/create', [KnowledgeSourceController::class, 'create'])->name('create');
        Route::post('/', [KnowledgeSourceController::class, 'store'])->name('store');
        Route::get('/{source}', [KnowledgeSourceController::class, 'show'])->name('show');
        Route::post('/{source}/extract', [KnowledgeSourceController::class, 'extract'])->name('extract');
        Route::post('/{source}/approve', [KnowledgeSourceController::class, 'approve'])->name('approve');
        Route::post('/{source}/publish', [KnowledgeSourceController::class, 'publish'])->name('publish');
        Route::post('/{source}/cross-office', [KnowledgeSourceController::class, 'establishCrossOffice'])->name('cross-office');
        Route::post('/{source}/recertify', [KnowledgeSourceController::class, 'recertify'])->name('recertify');
        Route::post('/{source}/consensus-sign', [KnowledgeSourceController::class, 'consensusSign'])->name('consensus-sign');
        Route::post('/{source}/revoke', [KnowledgeSourceController::class, 'revoke'])->name('revoke');
    });

    Route::get('/setup/ai-center', [AiControlCenterController::class, 'index'])->name('setup.ai-center');
    Route::post('/setup/ai-center/emergency-stop', [AiControlCenterController::class, 'toggleEmergencyStop'])->name('setup.ai-center.emergency-stop');
    Route::post('/setup/ai-center/applicant-policy', [AiControlCenterController::class, 'updateApplicantPolicy'])->name('setup.ai-center.applicant-policy');
    Route::post('/setup/ai-center/budget', [AiControlCenterController::class, 'updateBudget'])->name('setup.ai-center.budget');
    Route::post('/setup/ai-center/extensions', [AiControlCenterController::class, 'grantExtension'])->name('setup.ai-center.extensions.grant');
    Route::post('/setup/ai-center/holds/{reservation}/reconcile', [AiControlCenterController::class, 'reconcileHold'])->name('setup.ai-center.holds.reconcile');
    Route::post('/setup/ai-center/provider/discover', [AiControlCenterController::class, 'discoverModels'])->name('setup.ai-center.provider.discover');
    Route::match(['GET', 'POST'], '/setup/ai-center/models/discover', [AiControlCenterController::class, 'discoverModels'])->name('setup.ai-center.models.discover');
    Route::post('/setup/ai-center/provider', [AiControlCenterController::class, 'updateProvider'])->name('setup.ai-center.provider.update');
    Route::post('/setup/ai-center/tariffs', [AiControlCenterController::class, 'upsertTariff'])->name('setup.ai-center.tariffs.upsert');
    Route::post('/setup/ai-center/tools/toggle', [AiControlCenterController::class, 'toggleTool'])->name('setup.ai-center.tools.toggle');
    Route::get('/setup/booklets', fn () => redirect()->route('setup.exam-materials.booklets.index'));
    Route::get('/setup/test-kits', fn () => redirect()->route('setup.exam-materials.test-kits.index'));

    Route::get('/setup/exam-materials/omr-test', [ExamMaterialsController::class, 'omrTest'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.exam-materials.omr-test');

    Route::redirect('/setup/exam-materials/answer-sheets/test', '/setup/exam-materials/omr-test')
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.exam-materials.answer-sheets.test');
    Route::get('/setup/exam-materials/omr-scoring-workbench', function (): RedirectResponse {
        $batch = OmrCaptureBatch::query()
            ->where('status', OmrCaptureBatch::STATUS_OPEN)
            ->whereHas('gradingSession', fn ($query) => $query->where('status', 'open'))
            ->orderByDesc('id')
            ->first();

        if ($batch !== null) {
            return redirect()->route('scoring.sessions.omr', [
                'examSession' => $batch->gradingSession->exam_session_id,
            ]);
        }

        $gradingSessionId = GradingSession::query()
            ->where('status', 'open')
            ->orderByDesc('id')
            ->value('id');

        return $gradingSessionId === null
            ? redirect()->route('grading.index')
            : redirect()->route('scoring.sessions.omr', [
                'examSession' => GradingSession::query()->findOrFail($gradingSessionId)->exam_session_id,
            ]);
    })
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.exam-materials.omr-scoring-workbench');

    Route::prefix('/setup/exam-materials/test-kits')
        ->name('setup.exam-materials.test-kits.')
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG)
        ->group(function (): void {
            Route::get('/', [TestKitController::class, 'index'])->name('index');
            Route::get('/create', [TestKitController::class, 'create'])->name('create');
            Route::post('/', [TestKitController::class, 'store'])->name('store');
            Route::post('/{testKit}/preparation', [TestKitController::class, 'startPreparation'])
                ->whereNumber('testKit')
                ->name('preparation.start');
            Route::put('/{testKit}/preparation', [TestKitController::class, 'savePreparation'])
                ->whereNumber('testKit')
                ->name('preparation.update');
            Route::get('/{testKit}/preparation/composition', [TestKitController::class, 'composition'])
                ->whereNumber('testKit')
                ->name('preparation.composition');
            Route::put('/{testKit}/preparation/composition', [TestKitController::class, 'saveComposition'])
                ->whereNumber('testKit')
                ->name('preparation.composition.update');
            Route::get('/{testKit}/preparation/composition/preview', [KitPairedAnswerKeyPreviewController::class, 'showPreparation'])
                ->whereNumber('testKit')
                ->name('preparation.composition.preview');
            Route::get('/{testKit}/preparation/composition/preview/pages/{pageNo}/raster', [KitPairedAnswerKeyPreviewController::class, 'preparationRaster'])
                ->whereNumber('testKit')
                ->whereNumber('pageNo')
                ->name('preparation.composition.preview.raster');
            Route::get('/{testKit}/{version}/composition/preview', [KitPairedAnswerKeyPreviewController::class, 'showVersion'])
                ->whereNumber('testKit')
                ->whereNumber('version')
                ->name('version.composition.preview');
            Route::get('/{testKit}/{version}/composition/preview/pages/{pageNo}/raster', [KitPairedAnswerKeyPreviewController::class, 'versionRaster'])
                ->whereNumber('testKit')
                ->whereNumber('version')
                ->whereNumber('pageNo')
                ->name('version.composition.preview.raster');
            Route::post('/{testKit}/preparation/assemble', [TestKitController::class, 'assemblePreparation'])
                ->whereNumber('testKit')
                ->name('preparation.assemble');
            Route::post('/{testKit}/preparation/discard', [TestKitController::class, 'discardPreparation'])
                ->whereNumber('testKit')
                ->name('preparation.discard');
            Route::get('/{testKit}/test-setup', [TestKitController::class, 'testSetup'])
                ->whereNumber('testKit')
                ->name('test-setup');
            Route::put('/{testKit}/test-setup', [TestKitController::class, 'saveTestSetup'])
                ->whereNumber('testKit')
                ->name('test-setup.update');
            Route::post('/{testKit}/{version}/select-receipt', [TestKitController::class, 'selectReceipt'])->name('select-receipt');
            Route::post('/{testKit}/{version}/activate', [TestKitController::class, 'activate'])->name('activate');
            Route::post('/{testKit}/{version}/retire', [TestKitController::class, 'retire'])->name('retire');
            Route::post('/{testKit}/{version}/revoke', [TestKitController::class, 'revoke'])->name('revoke');
            Route::get('/{testKit}/{version?}', [TestKitController::class, 'show'])
                ->whereNumber('testKit')
                ->whereNumber('version')
                ->name('show');
        });

    Route::prefix('/setup/exam-materials/omr-test/runs')
        ->name('setup.exam-materials.omr-test.runs.')
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG)
        ->group(function (): void {
            Route::post('/', [OmrDiagnosticWorkbenchController::class, 'start'])
                ->name('start');
            Route::get('/mock-guide', [OmrDiagnosticWorkbenchController::class, 'mockGuide'])
                ->name('mock-guide');
            Route::get('/reference-assets/{templateVersion}/{pageNo}', [OmrDiagnosticWorkbenchController::class, 'referenceAsset'])
                ->whereNumber('templateVersion')
                ->whereNumber('pageNo')
                ->name('reference-asset');
            Route::get('/{run}', [OmrDiagnosticWorkbenchController::class, 'show'])
                ->whereUuid('run')
                ->name('show');
            Route::post('/{run}/pending-capture', [OmrDiagnosticWorkbenchController::class, 'startPendingCapture'])
                ->whereUuid('run')
                ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
                ->name('pending-capture.start');
            Route::post('/{run}/pending-capture/identity', [OmrDiagnosticWorkbenchController::class, 'confirmPendingCaptureIdentity'])
                ->whereUuid('run')
                ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
                ->name('pending-capture.identity');
            Route::post('/{run}/capture', [OmrDiagnosticWorkbenchController::class, 'capture'])
                ->whereUuid('run')
                ->name('capture');
            Route::post('/{run}/capture-actions', [OmrWorkbenchCaptureActionController::class, 'diagnostic'])
                ->whereUuid('run')
                ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
                ->name('capture-actions');
            Route::post('/{run}/captures/{capture}/retry', [OmrDiagnosticWorkbenchController::class, 'retry'])
                ->whereUuid('run')
                ->whereNumber('capture')
                ->name('retry');
            Route::post('/{run}/captures/{capture}/discard', [OmrDiagnosticWorkbenchController::class, 'discard'])
                ->whereUuid('run')
                ->whereNumber('capture')
                ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
                ->name('captures.discard');
            Route::post('/{run}/evidence/{item}/review', [OmrDiagnosticWorkbenchController::class, 'review'])
                ->whereUuid('run')
                ->whereNumber('item')
                ->name('review');
            Route::post('/{run}/compare', [OmrDiagnosticWorkbenchController::class, 'compare'])
                ->whereUuid('run')
                ->name('compare');
            Route::post('/{run}/complete', [OmrDiagnosticWorkbenchController::class, 'complete'])
                ->whereUuid('run')
                ->name('complete');
            Route::post('/{run}/cancel', [OmrDiagnosticWorkbenchController::class, 'cancel'])
                ->whereUuid('run')
                ->name('cancel');
            Route::get('/{run}/assets/{asset}', [OmrDiagnosticWorkbenchController::class, 'asset'])
                ->whereUuid('run')
                ->whereNumber('asset')
                ->name('asset');
        });

    Route::prefix('/setup/exam-materials/booklets')
        ->name('setup.exam-materials.booklets.')
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE)
        ->group(function (): void {
            Route::get('/', [ExamMaterialsController::class, 'booklets'])->name('index');
            Route::post('/', [ExamMaterialsController::class, 'storeBooklet'])->name('store');
            Route::post('/set-policy', [ExamMaterialsController::class, 'updateSetAvailability'])->name('set-policy');
            Route::post('/{bookletVersion}/sets/mode', [ExamMaterialsController::class, 'setBookletSetsMode'])
                ->name('sets.mode');
            Route::post('/{version}/sets/{setCode}/keys', [ExamMaterialsController::class, 'saveSetKeys'])
                ->whereIn('setCode', ['A', 'B', 'C', 'D'])
                ->name('sets.keys');
            Route::put('/{version}', [ExamMaterialsController::class, 'saveBooklet'])->name('update');
            Route::post('/{version}/sets/generate', [ExamMaterialsController::class, 'generateSets'])->name('sets.generate');
            Route::post('/{version}/finalize', [ExamMaterialsController::class, 'finalizeBooklet'])->name('finalize');
            Route::post('/{version}/copy', [ExamMaterialsController::class, 'copyBooklet'])->name('copy');
            Route::delete('/{version}', [ExamMaterialsController::class, 'destroyDraft'])->name('destroy');
            Route::post('/{version}/archive', [ExamMaterialsController::class, 'archiveVersion'])->name('archive');
            Route::post('/{version}/restore', [ExamMaterialsController::class, 'restoreVersion'])->name('restore');
        });
    Route::get('/setup/exam-materials/booklets/{booklet}', [ExamMaterialsController::class, 'show'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE)
        ->name('setup.exam-materials.booklets.show');
    Route::get('setup/decision-support', [AdmissionSupportSetupController::class, 'decisionSupport'])
        ->name('setup.decision-support.index');
    Route::prefix('setup/admission-support')
        ->name('setup.admission-support.')
        ->group(function (): void {
            Route::get('/yukee', [AdmissionSupportSetupController::class, 'yukeeStudio'])
                ->middleware('capability:'.CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG)
                ->name('yukee');
            Route::post('/cycles/{cycle}/benchmarks/import', [AdmissionSupportSetupController::class, 'importBenchmarks'])
                ->middleware('capability:'.CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG)
                ->name('benchmarks.import');
            Route::get('/programs/{program}/cycles/{cycle}/governance', [ProgramGovernanceController::class, 'show'])->name('governance.show');
            Route::post('/programs/{program}/cycles/{cycle}/benchmarks/draft', [ProgramGovernanceController::class, 'saveBenchmark'])->name('benchmarks.draft.save');
            Route::post('/programs/{program}/cycles/{cycle}/benchmarks/draft/start', [ProgramGovernanceController::class, 'startBenchmark'])->name('benchmarks.draft.start');
            Route::post('/programs/{program}/cycles/{cycle}/benchmarks/draft/cutover', [ProgramGovernanceController::class, 'cutoverBenchmark'])->name('benchmarks.draft.cutover');
            Route::post('/programs/{program}/cycles/{cycle}/benchmarks/draft/recover', [ProgramGovernanceController::class, 'recoverBenchmark'])->name('benchmarks.draft.recover');
            Route::post('/programs/{program}/cycles/{cycle}/benchmarks/draft/restore', [ProgramGovernanceController::class, 'restoreBenchmark'])->name('benchmarks.draft.restore');
            Route::post('/programs/{program}/cycles/{cycle}/benchmarks/draft/rebase', [ProgramGovernanceController::class, 'rebaseBenchmark'])->name('benchmarks.draft.rebase');
            Route::post('/cycles/{cycle}/benchmarks/publish', [ProgramGovernanceController::class, 'publishBenchmark'])->name('benchmarks.publish');
            Route::post('/cycles/{cycle}/benchmarks/activate', [ProgramGovernanceController::class, 'activateBenchmark'])->name('benchmarks.activate');
            Route::get('/programs/{program}/cycles/{cycle}/benchmarks/history', [ProgramGovernanceController::class, 'benchmarkHistory'])->name('benchmarks.history');
            Route::get('/programs/{program}/cycles/{cycle}/benchmarks/comparison', [ProgramGovernanceController::class, 'benchmarkComparison'])->name('benchmarks.comparison');
            Route::post('/programs/{program}/cycles/{cycle}/sandbox/preview', [ProgramGovernanceController::class, 'preview'])->name('sandbox.governance-preview');
            Route::post('/programs/{program}/cycles/{cycle}/advising-note/draft', [ProgramGovernanceController::class, 'saveNote'])->name('advising-note.save');
            Route::post('/programs/{program}/cycles/{cycle}/advising-note/restore', [ProgramGovernanceController::class, 'restoreNote'])->name('advising-note.restore');
            Route::post('/programs/{program}/cycles/{cycle}/advising-note/publish', [ProgramGovernanceController::class, 'publishNote'])->name('advising-note.publish');
            Route::post('/programs/{program}/cycles/{cycle}/advising-note/retract', [ProgramGovernanceController::class, 'retractNote'])->name('advising-note.retract');
            Route::post('/programs/{program}/cycles/{cycle}/advising-note/copy', [ProgramGovernanceController::class, 'copyNote'])->name('advising-note.copy');
            Route::post('/applicants/{attempt}/advising-note/use', [ProgramGovernanceController::class, 'useNote'])->name('advising-note.use');
            Route::post('/programs/{program}/cycles/{cycle}/discussions', [ProgramGovernanceController::class, 'openDiscussion'])->name('discussions.open');
            Route::get('/programs/{program}/cycles/{cycle}/discussions/history', [ProgramGovernanceController::class, 'discussionHistory'])->name('discussions.history');
            Route::post('/programs/{program}/cycles/{cycle}/discussions/messages', [ProgramGovernanceController::class, 'sendDiscussion'])->name('discussions.send');
            Route::post('/programs/{program}/cycles/{cycle}/discussions/read', [ProgramGovernanceController::class, 'readDiscussion'])->name('discussions.read');
            Route::post('/programs/{program}/cycles/{cycle}/discussions/state', [ProgramGovernanceController::class, 'stateDiscussion'])->name('discussions.state');
        });
    foreach ([
        ['path' => 'setup/document-templates', 'name' => 'setup.document-templates.'],
        ['path' => 'setup/report-templates', 'name' => 'setup.report-templates.'],
    ] as $routeConfig) {
        Route::prefix($routeConfig['path'])
            ->name($routeConfig['name'])
            ->group(function (): void {
                Route::get('/', [ReportTemplateController::class, 'index'])->name('index');
                Route::get('/baselines/{baseline}/download', [ReportTemplateController::class, 'downloadBaseline'])->name('baselines.download');
                Route::post('/{surface}/validate-document', [ReportTemplateController::class, 'validateDocument'])->name('validate-document');
                Route::post('/{surface}/preview', [ReportTemplateController::class, 'preview'])->name('preview');
                Route::post('/{surface}/drafts', [ReportTemplateController::class, 'storeDraft'])->name('drafts.store');
                Route::put('/{surface}/drafts/{draft}', [ReportTemplateController::class, 'updateDraft'])->name('drafts.update');
                Route::delete('/{surface}/drafts/{draft}', [ReportTemplateController::class, 'destroyDraft'])->name('drafts.destroy');
                Route::get('/{surface}', [ReportTemplateController::class, 'surface'])->name('surface');
                Route::get('/{surface}/create', [ReportTemplateController::class, 'create'])->name('create');
                Route::post('/{surface}', [ReportTemplateController::class, 'store'])->name('store');
                Route::get('/{surface}/{reportTemplate}/edit', [ReportTemplateController::class, 'edit'])->name('edit');
                Route::put('/{surface}/{reportTemplate}', [ReportTemplateController::class, 'update'])->name('update');
                Route::post('/{surface}/{reportTemplate}/activate', [ReportTemplateController::class, 'activate'])->name('activate');
                Route::post('/{surface}/{reportTemplate}/deactivate', [ReportTemplateController::class, 'deactivate'])->name('deactivate');
                Route::delete('/{surface}/{reportTemplate}', [ReportTemplateController::class, 'destroy'])->name('destroy');
                Route::get('/{surface}/{reportTemplate}/thumbnail', [ReportTemplateController::class, 'thumbnail'])->name('thumbnail');
                Route::get('/{surface}/{reportTemplate}/download-source', [ReportTemplateController::class, 'downloadSource'])->name('download-source');
            });
    }
    Route::get('/setup', [SetupDashboardController::class, 'index'])->name('setup.index');
    Route::prefix('setup/institution')->name('setup.institution.')->middleware('capability:'.CapabilityCatalog::SETUP_INSTITUTION_MANAGE)->group(function (): void {
        Route::get('/', [InstitutionProfileController::class, 'index'])->name('index');
        Route::put('/', [InstitutionProfileController::class, 'update'])->name('update');
        Route::post('/logo', [InstitutionProfileController::class, 'uploadLogo'])->name('logo.upload');
        Route::delete('/logo', [InstitutionProfileController::class, 'deleteLogo'])->name('logo.delete');
        Route::post('/reset', [InstitutionProfileController::class, 'resetDefaults'])->name('reset');
    });
    Route::redirect('/admin/institution', '/setup/institution')->name('admin.institution.redirect');
    Route::get('/setup/programs', [ProgramController::class, 'programs'])->name('setup.programs');
    Route::post('/setup/programs', [ProgramController::class, 'storeProgram'])->name('setup.programs.store');
    Route::put('/setup/programs/{program}', [ProgramController::class, 'updateProgram'])->name('setup.programs.update');
    Route::get('/setup/programs/{program}/hub', [ProgramHubController::class, 'show'])->name('setup.programs.hub')->whereNumber('program');
    Route::prefix('setup/programs/{program}/public-details')->name('setup.programs.public-details.')->whereNumber('program')->group(function (): void {
        Route::get('/', [ProgramPublicProfileController::class, 'edit'])->name('edit');
        Route::put('/', [ProgramPublicProfileController::class, 'update'])->name('update');
        Route::post('/publish', [ProgramPublicProfileController::class, 'publish'])->name('publish');
        Route::post('/photos', [ProgramPublicProfileController::class, 'upload'])->name('photos.store');
        Route::get('/photos/{photo}', [ProgramPublicProfileController::class, 'staffPhoto'])->whereNumber('photo')->name('photos.show');
        Route::get('/preview', [ProgramPublicProfileController::class, 'preview'])->name('preview');
    });
    Route::get('/setup/departments', [DepartmentController::class, 'index'])->name('setup.departments');
    Route::post('/setup/departments', [DepartmentController::class, 'store'])->name('setup.departments.store');
    Route::put('/setup/departments/{department}', [DepartmentController::class, 'update'])->name('setup.departments.update');
    Route::post('/setup/departments/{department}/toggle', [DepartmentController::class, 'toggleActive'])->name('setup.departments.toggle');
    Route::get('/setup/rooms', [RoomController::class, 'index'])->name('setup.rooms');
    Route::get('/setup/rooms/create', [RoomController::class, 'create'])->name('setup.rooms.create');
    Route::post('/setup/rooms', [RoomController::class, 'store'])->name('setup.rooms.store');
    Route::get('/setup/rooms/{room}', [RoomController::class, 'show'])->name('setup.rooms.show');
    Route::get('/setup/rooms/{room}/edit', [RoomController::class, 'edit'])->name('setup.rooms.edit');
    Route::put('/setup/rooms/{room}', [RoomController::class, 'update'])->name('setup.rooms.update');
    Route::post('/setup/rooms/{room}/photo', [RoomController::class, 'uploadPhoto'])->name('setup.rooms.photo.upload');
    Route::delete('/setup/rooms/{room}/photo', [RoomController::class, 'deletePhoto'])->name('setup.rooms.photo.delete');
    Route::post('/setup/rooms/{room}/activate', [RoomController::class, 'activate'])->name('setup.rooms.activate');
    Route::post('/setup/rooms/{room}/deactivate', [RoomController::class, 'deactivate'])->name('setup.rooms.deactivate');
    Route::post('/setup/rooms/{room}/default-seating-arrangement', [RoomController::class, 'setDefaultSeatingArrangement'])->name('setup.rooms.default-seating-arrangement');
    Route::post('/setup/rooms/seating-policy', [RoomController::class, 'updateSeatingPolicy'])->name('setup.rooms.seating-policy');
    Route::get('/setup/seating-arrangements', [SeatingArrangementController::class, 'index'])->name('setup.seating-arrangements.index');
    Route::get('/setup/seating-arrangements/create', [SeatingArrangementController::class, 'create'])->name('setup.seating-arrangements.create');
    Route::post('/setup/seating-arrangements', [SeatingArrangementController::class, 'store'])->name('setup.seating-arrangements.store');
    Route::get('/setup/seating-arrangements/{arrangement}/edit', [SeatingArrangementController::class, 'edit'])->name('setup.seating-arrangements.edit');
    Route::put('/setup/seating-arrangements/{arrangement}', [SeatingArrangementController::class, 'update'])->name('setup.seating-arrangements.update');
    Route::post('/setup/seating-arrangements/{arrangement}/activate', [SeatingArrangementController::class, 'activate'])->name('setup.seating-arrangements.activate');
    Route::post('/setup/seating-arrangements/{arrangement}/deactivate', [SeatingArrangementController::class, 'deactivate'])->name('setup.seating-arrangements.deactivate');
    Route::get('/setup/cycles', [AcademicCycleController::class, 'cycles'])->name('setup.cycles');
    Route::get('/setup/application-opening-alerts', [ApplicationOpeningSubscriberController::class, 'index'])
        ->middleware('throttle:search-suggestions')->name('setup.application-opening-alerts.index');
    Route::get('/setup/cycles/create', [AcademicCycleController::class, 'createCycle'])->name('setup.cycles.create');
    Route::post('/setup/cycles', [AcademicCycleController::class, 'storeCycle'])->name('setup.cycles.store');
    Route::get('/setup/cycles/{cycle}/edit', [AcademicCycleController::class, 'editCycle'])->name('setup.cycles.edit');
    Route::put('/setup/cycles/{cycle}', [AcademicCycleController::class, 'updateCycle'])->name('setup.cycles.update');
    Route::post('/setup/cycles/{cycle}/application-window/extend', [AcademicCycleController::class, 'extendApplicationWindow'])
        ->middleware('throttle:20,1')->name('setup.cycles.application-window.extend');
    Route::post('/setup/cycles/{cycle}/activate', [AcademicCycleController::class, 'activateCycle'])->name('setup.cycles.activate');
    Route::post('/setup/cycles/{cycle}/deactivate', [AcademicCycleController::class, 'deactivateCycle'])->name('setup.cycles.deactivate');
    Route::get('/setup/cycles/{cycle}/notifications', [CycleNotificationPolicyController::class, 'edit'])
        ->name('setup.cycles.notifications.edit');
    Route::put('/setup/cycles/{cycle}/notifications/admissions', [CycleNotificationPolicyController::class, 'updateAdmissions'])
        ->name('setup.cycles.notifications.admissions');
    Route::put('/setup/cycles/{cycle}/notifications/examination', [CycleNotificationPolicyController::class, 'updateExamination'])
        ->name('setup.cycles.notifications.examination');
    Route::get('/setup/notifications', [NotificationSetupController::class, 'index'])
        ->name('setup.notifications');
    Route::post('/setup/notifications/controls', [NotificationSetupController::class, 'updateControls'])
        ->name('setup.notifications.controls.update');
    Route::get('/setup/consultation-policy', [ConsultationPolicyController::class, 'index'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN)
        ->name('setup.consultation-policy.index');
    Route::post('/setup/consultation-policy', [ConsultationPolicyController::class, 'updatePolicy'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN)
        ->name('setup.consultation-policy.update');
    Route::get('/setup/notifications/occurrences', [NotificationSetupController::class, 'occurrences'])
        ->name('setup.notifications.occurrences');
    Route::get('/setup/notifications/occurrences/{occurrence}', [NotificationSetupController::class, 'showOccurrence'])
        ->name('setup.notifications.occurrences.show');
    Route::post('/setup/notifications/occurrences/{occurrence}/deliveries/{delivery}/retry', [NotificationSetupController::class, 'retryDelivery'])
        ->name('setup.notifications.occurrences.deliveries.retry');
    Route::get('/setup/notifications/admissions', [NotificationAdmissionsDeskController::class, 'show'])
        ->name('setup.notifications.admissions');
    Route::put('/setup/notifications/admissions', [NotificationAdmissionsDeskController::class, 'update'])
        ->name('setup.notifications.admissions.update');
    Route::get('/setup/notifications/admissions/templates/{slug}', [AdmissionsCommunicationTemplateController::class, 'studio'])
        ->name('setup.notifications.admissions.templates.studio');
    Route::get('/setup/notifications/examination/templates/{slug}', [ExaminationCommunicationTemplateController::class, 'studio'])
        ->name('setup.notifications.examination.templates.studio');
    Route::get('/setup/notifications/results/templates/{slug}', [ResultsCommunicationTemplateController::class, 'studio'])
        ->name('setup.notifications.results.templates.studio');
    Route::prefix('/setup/notifications/{domain}/templates/{slug}')
        ->where(['domain' => 'admissions|examination|results'])
        ->name('setup.notifications.templates.')
        ->controller(NoticeDocumentStudioController::class)
        ->group(function (): void {
            Route::get('/edit', 'studio')->name('edit');
            Route::get('/editor-state', 'editorState')->name('editor-state');
            Route::post('/drafts', 'storeDraft')->name('drafts.store');
            Route::put('/drafts/{draft}', 'updateDraft')->whereUuid('draft')->name('drafts.update');
            Route::delete('/drafts/{draft}', 'discardDraft')->whereUuid('draft')->name('drafts.destroy');
            Route::post('/images', 'storeMessageImage')->name('images.store');
            Route::get('/images/{asset}/content', 'messageImage')->whereUuid('asset')->name('images.content');
            Route::post('/preview', 'preview')->name('preview');
            Route::post('/attachment-check', 'attachmentCheck')->name('attachment-check');
            Route::post('/publish', 'publish')->name('publish');
            Route::post('/test-mail', 'testMail')->name('test-mail');
            Route::prefix('/document')->name('document.')->controller(NoticePhysicalDocumentController::class)->group(function (): void {
                Route::get('/edit', 'edit')->name('edit');
                Route::get('/editor-state', 'state')->name('editor-state');
                Route::get('/state', 'state')->name('state');
                Route::post('/drafts', 'store')->name('drafts.store');
                Route::put('/drafts/{draft}', 'update')->whereNumber('draft')->name('drafts.update');
                Route::delete('/drafts/{draft}', 'destroy')->whereNumber('draft')->name('drafts.destroy');
                Route::post('/upload', 'upload')->name('upload');
                Route::post('/preview', 'preview')->name('preview');
                Route::post('/images', 'storeImage')->name('images.store');
                Route::get('/images/{asset}', 'image')->whereUuid('asset')->name('images.show');
                Route::post('/versions', 'version')->name('versions.store');
                Route::get('/versions/{version}/pdf', 'pdf')->whereNumber('version')->name('versions.pdf');
                Route::get('/versions/{version}/thumbnail', 'thumbnail')->whereNumber('version')->name('versions.thumbnail');
                Route::get('/drafts/{draft}/thumbnail', 'draftThumbnail')->whereNumber('draft')->name('drafts.thumbnail');
            });
        });
    Route::get('/setup/notifications/examination', [NotificationExaminationDeskController::class, 'show'])
        ->name('setup.notifications.examination');
    Route::put('/setup/notifications/examination', [NotificationExaminationDeskController::class, 'update'])
        ->name('setup.notifications.examination.update');
    Route::get('/setup/notifications/results', [NotificationResultsDeskController::class, 'show'])
        ->name('setup.notifications.results');
    Route::put('/setup/notifications/results', [NotificationResultsDeskController::class, 'update'])
        ->name('setup.notifications.results.update');
    Route::post('/setup/windows', [ApplicationWindowController::class, 'storeWindow'])->name('setup.windows.store');
    Route::get('/setup/requirements', [IntakeRequirementController::class, 'index'])->name('setup.requirements');
    Route::get('/setup/requirements/create', [IntakeRequirementController::class, 'create'])->name('setup.requirements.create');
    Route::post('/setup/requirements', [IntakeRequirementController::class, 'store'])->name('setup.requirements.store');
    Route::get('/setup/requirements/{requirement}/edit', [IntakeRequirementController::class, 'edit'])->whereNumber('requirement')->name('setup.requirements.edit');
    Route::put('/setup/requirements/{requirement}', [IntakeRequirementController::class, 'update'])->whereNumber('requirement')->name('setup.requirements.update');
    Route::patch('/setup/requirements/{requirement}/toggle-active', [IntakeRequirementController::class, 'setActive'])->whereNumber('requirement')->name('setup.requirements.toggle-active');
    Route::put('/setup/cycles/{cycle}/guidance-delegation', [IntakeFieldPolicyController::class, 'toggleGuidanceDelegation'])->name('setup.cycles.guidance-delegation');
    Route::get('/setup/intake-fields', [IntakeFieldPolicyController::class, 'index'])->name('setup.intake-fields');
    Route::put('/setup/intake-fields', [IntakeFieldPolicyController::class, 'update'])->name('setup.intake-fields.update');
    Route::get('/setup/application-suggestions', [ApplicationSuggestionListController::class, 'index'])->name('setup.application-suggestions');
    Route::put('/setup/application-suggestions', [ApplicationSuggestionListController::class, 'update'])->name('setup.application-suggestions.update');

    // Dynamic Ingestion & Import Engine
    Route::get('/imports', [ImportController::class, 'index'])->name('imports.index');
    Route::post('/imports/analyze', [ImportAnalyzeController::class, 'analyze'])->name('imports.analyze');
    Route::get('/imports/templates/{type}/schema', [ImportTemplateController::class, 'schema'])->name('imports.templates.schema');
    Route::get('/imports/templates/{type}', [ImportTemplateController::class, 'export'])->name('imports.templates');
    Route::post('/imports/stage', [ImportController::class, 'stage'])->name('imports.stage');
    Route::get('/imports/{batch}/review', [ImportController::class, 'show'])->name('imports.review');
    Route::post('/imports/{batch}/approve', [ImportController::class, 'approve'])->name('imports.approve');
    Route::post('/imports/{batch}/resume', [ImportController::class, 'resume'])->name('imports.resume');
    Route::post('/imports/{batch}/reject', [ImportController::class, 'reject'])->name('imports.reject');
    Route::post('/imports/{batch}/commit', [ImportController::class, 'commit'])->name('imports.commit');
    Route::get('/imports/{batch}/errors', [ImportController::class, 'downloadErrors'])->name('imports.errors');
    Route::get('/setup/result-templates', [ResultSheetTemplateController::class, 'index'])->name('setup.result-templates.index');
    Route::get('/setup/result-templates/create', [ResultSheetTemplateController::class, 'create'])->name('setup.result-templates.create');
    Route::post('/setup/result-templates/drafts', [ResultSheetTemplateController::class, 'storeDraft'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG)
        ->name('setup.result-templates.drafts.store');
    Route::put('/setup/result-templates/drafts/{draft}', [ResultSheetTemplateController::class, 'updateDraft'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG)
        ->name('setup.result-templates.drafts.update');
    Route::delete('/setup/result-templates/drafts/{draft}', [ResultSheetTemplateController::class, 'destroyDraft'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG)
        ->name('setup.result-templates.drafts.destroy');
    Route::post('/setup/result-templates', [ResultSheetTemplateController::class, 'store'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG)
        ->name('setup.result-templates.store');
    Route::get('/setup/result-templates/{resultTemplate}/edit', [ResultSheetTemplateController::class, 'edit'])->name('setup.result-templates.edit');
    Route::put('/setup/result-templates/{resultTemplate}', [ResultSheetTemplateController::class, 'update'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG)
        ->name('setup.result-templates.update');
    Route::delete('/setup/result-templates/{resultTemplate}', [ResultSheetTemplateController::class, 'destroy'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG)
        ->name('setup.result-templates.destroy');
    Route::get('/setup/result-templates/{resultTemplate}/thumbnail', [ResultSheetTemplateController::class, 'thumbnail'])
        ->name('setup.result-templates.thumbnail');
    Route::post('/setup/result-templates/{resultTemplate}/activate', [ResultSheetTemplateController::class, 'activate'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG)
        ->name('setup.result-templates.activate');
    Route::post('/setup/result-templates/{resultTemplate}/deactivate', [ResultSheetTemplateController::class, 'deactivate'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG)
        ->name('setup.result-templates.deactivate');
    Route::post('/setup/result-templates/validate-document', [ResultSheetTemplateController::class, 'validateDocument'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG)
        ->name('setup.result-templates.validate-document');
    Route::post('/setup/result-templates/preview', [ResultSheetTemplateController::class, 'preview'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG)
        ->name('setup.result-templates.preview');
    Route::get('/setup/aptitude', [AptitudeAreaController::class, 'aptitude'])->name('setup.aptitude');
    Route::post('/setup/aptitude', [AptitudeAreaController::class, 'storeAptitude'])->name('setup.aptitude.store');
    Route::put('/setup/aptitude/{area}', [AptitudeAreaController::class, 'updateAptitude'])->name('setup.aptitude.update');
    Route::get('/setup/aptitude/{area}/scoring', [AptitudeAreaController::class, 'scoring'])->name('setup.aptitude.scoring');
    Route::put('/setup/aptitude/{area}/scoring', [AptitudeAreaController::class, 'storeScoring'])->name('setup.aptitude.scoring.update');

    Route::prefix('setup/test-instruments')
        ->name('setup.test-instruments.')
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG)
        ->group(function (): void {
            Route::get('/{instrument}/setup', [TestInstrumentController::class, 'setup'])
                ->whereNumber('instrument')
                ->name('setup');
            Route::put('/{instrument}/setup', [TestInstrumentController::class, 'saveSetup'])
                ->whereNumber('instrument')
                ->name('setup.update');
            Route::get('/', [TestInstrumentController::class, 'index'])->name('index');
            Route::get('/create', [TestInstrumentController::class, 'create'])->name('create');
            Route::post('/', [TestInstrumentController::class, 'store'])->name('store');
            Route::get('/{instrument}', [TestInstrumentController::class, 'show'])->whereNumber('instrument')->name('show');
            Route::get('/{instrument}/edit', [TestInstrumentController::class, 'edit'])->whereNumber('instrument')->name('edit');
            Route::put('/{instrument}/versions/{version}', [TestInstrumentController::class, 'update'])
                ->whereNumber('instrument')
                ->whereNumber('version')
                ->name('versions.update');
            Route::post('/{instrument}/versions/{version}/successor', [TestInstrumentController::class, 'createSuccessor'])
                ->whereNumber('instrument')
                ->whereNumber('version')
                ->name('versions.successor');
            Route::post('/{instrument}/versions/{version}/approve', [TestInstrumentController::class, 'approve'])
                ->whereNumber('instrument')
                ->whereNumber('version')
                ->name('versions.approve');
            Route::post('/{instrument}/versions/{version}/retire', [TestInstrumentController::class, 'retire'])
                ->whereNumber('instrument')
                ->whereNumber('version')
                ->name('versions.retire');
        });
    Route::prefix('setup/norm-packages')
        ->name('setup.norm-packages.')
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG)
        ->group(function (): void {
            Route::get('/', [NormPackageController::class, 'index'])->name('index');
            Route::get('/create', [NormPackageController::class, 'create'])->name('create');
            Route::post('/', [NormPackageController::class, 'store'])->name('store');
            Route::get('/{normPackage}/edit', [NormPackageController::class, 'edit'])
                ->whereNumber('normPackage')
                ->name('edit');
            Route::put('/{normPackage}', [NormPackageController::class, 'update'])
                ->whereNumber('normPackage')
                ->name('update');
            Route::post('/{normPackage}/versions/{version}/approve', [NormPackageController::class, 'approve'])
                ->whereNumber('normPackage')
                ->whereNumber('version')
                ->name('versions.approve');
            Route::post('/{normPackage}/versions/{version}/retire', [NormPackageController::class, 'retire'])
                ->whereNumber('normPackage')
                ->whereNumber('version')
                ->name('versions.retire');
            Route::get('/{normPackage}', [NormPackageController::class, 'show'])
                ->whereNumber('normPackage')
                ->name('show');
        });
    Route::get('/setup/quotas', [ProgramQuotaController::class, 'quotas'])->name('setup.quotas');
    Route::post('/setup/quotas', [ProgramQuotaController::class, 'storeQuota'])->name('setup.quotas.store');
    Route::post('/setup/quotas/bulk', [ProgramQuotaController::class, 'bulkStore'])->name('setup.quotas.bulk-store');
    Route::post('/setup/quotas/programs/{program}/cycles/{cycle}/registrar-grants', [RegistrarQuotaGovernanceGrantController::class, 'issue'])
        ->name('setup.quotas.registrar-grants.issue');
    Route::post('/setup/quotas/programs/{program}/cycles/{cycle}/registrar-grants/revoke', [RegistrarQuotaGovernanceGrantController::class, 'revoke'])
        ->name('setup.quotas.registrar-grants.revoke');

    Route::middleware('staff.surface')->group(function (): void {
        Route::get('/staff/profile', [ProfileController::class, 'show'])->name('staff.profile');
        Route::post('/staff/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('staff.profile.avatar.update');
        Route::post('/staff/profile/directory', [ProfileController::class, 'updateDirectory'])->name('staff.profile.directory.update');
        Route::put('/staff/profile/name', [ProfileController::class, 'updateName'])->name('staff.profile.name');
        Route::put('/staff/profile/password', [ProfileController::class, 'updatePassword'])->middleware('throttle:5,1')->name('staff.profile.password');
        Route::post('/staff/profile/email/request', [ProfileController::class, 'requestEmailChange'])->middleware('throttle:5,1')->name('staff.profile.email.request');
        Route::delete('/staff/profile/email/cancel', [ProfileController::class, 'cancelEmailChange'])->name('staff.profile.email.cancel');
        Route::post('/staff/profile/email/confirm/{token}', [ProfileController::class, 'confirmEmailChange'])
            ->middleware('throttle:5,1')
            ->name('staff.profile.email.confirm');
        Route::post('/staff/profile/mfa/enroll/begin', [ProfileController::class, 'beginProfileMfaEnrollment'])
            ->middleware('throttle:5,1')
            ->name('staff.profile.mfa.enroll.begin');
        Route::post('/staff/profile/mfa/enroll/confirm', [ProfileController::class, 'confirmProfileMfaEnrollment'])
            ->middleware('throttle:5,1')
            ->name('staff.profile.mfa.enroll.confirm');
        Route::post('/staff/profile/mfa/recovery-codes', [ProfileController::class, 'regenerateRecoveryCodes'])
            ->middleware('throttle:5,1')
            ->name('staff.profile.mfa.recovery-codes');
        Route::post('/staff/profile/mfa/re-enroll/begin', [ProfileController::class, 'beginTwoFactorReEnrollment'])
            ->middleware('throttle:5,1')
            ->name('staff.profile.mfa.re-enroll.begin');
        Route::post('/staff/profile/mfa/re-enroll/confirm', [ProfileController::class, 'confirmTwoFactorReEnrollment'])
            ->middleware('throttle:5,1')
            ->name('staff.profile.mfa.re-enroll.confirm');
        Route::post('/staff/profile/mfa/require', [ProfileController::class, 'requireMfa'])
            ->name('staff.profile.mfa.require');
        Route::post('/staff/profile/mfa/disable', [ProfileController::class, 'disableMfa'])
            ->name('staff.profile.mfa.disable');
        Route::post('/staff/profile/google/unlink', [ProfileController::class, 'unlinkGoogle'])
            ->name('staff.profile.google.unlink');
    });

    Route::get('/staff/logs', [AuditLogController::class, 'index'])->name('staff.logs.index');
    Route::get('/staff/logs/export', [AuditLogController::class, 'export'])->name('staff.logs.export');

    Route::get('/staff/personnel', [PersonnelController::class, 'index'])->name('staff.personnel.index');
    Route::prefix('staff/personnel')->name('staff.personnel.')
        ->middleware('capability:'.CapabilityCatalog::SETUP_INSTITUTION_DELEGATE)
        ->group(function (): void {
            Route::post('/{user}/grant-setup', [SetupDelegationController::class, 'grant'])->name('grant-setup');
            Route::post('/{user}/revoke-setup', [SetupDelegationController::class, 'revoke'])->name('revoke-setup');
        });

    Route::middleware(EnsurePersonnelAccess::class)->prefix('staff/personnel')->name('staff.personnel.')->group(function () {
        Route::get('/prepare', [PersonnelController::class, 'prepare'])->name('prepare');
        Route::post('/prepare/preview', [PersonnelController::class, 'previewPreparation'])->name('prepare.preview');
        Route::post('/prepare/commit', [PersonnelController::class, 'commitPreparation'])->name('prepare.commit');
        Route::get('/rollover', [StaffCycleDispositionController::class, 'index'])->name('rollover');
        Route::post('/cycle-dispositions', [StaffCycleDispositionController::class, 'transition'])
            ->middleware('throttle:10,1')
            ->name('cycle-dispositions');
        Route::post('/fast-provision', [StaffCycleDispositionController::class, 'fastProvision'])
            ->middleware('throttle:10,1')
            ->name('fast-provision');
        Route::post('/full-reset/batch', [PersonnelController::class, 'resetBatch'])
            ->middleware('throttle:5,1')
            ->name('full-reset.batch');
        Route::post('/vouchers/batch', [BatchVoucherController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('vouchers.batch.store');
        Route::post('/vouchers/batch/print', [BatchVoucherController::class, 'print'])
            ->middleware('throttle:10,1')
            ->name('vouchers.batch.print');
        Route::post('/{user}/full-reset', [PersonnelController::class, 'reset'])
            ->middleware('throttle:5,1')
            ->name('full-reset');
        Route::get('/create', [PersonnelController::class, 'create'])->name('create');
        Route::post('/', [PersonnelController::class, 'store'])->name('store');
        Route::get('/{user}/edit', [PersonnelController::class, 'edit'])->name('edit');
        Route::put('/{user}', [PersonnelController::class, 'update'])->name('update');
        Route::post('/{user}/require-mfa', [PersonnelController::class, 'requireMfa'])->name('require-mfa');
        Route::post('/{user}/disable-mfa', [PersonnelController::class, 'disableMfa'])->name('disable-mfa');
        Route::post('/{user}/deactivate', [PersonnelController::class, 'deactivate'])->name('deactivate');
        Route::post('/{user}/vouchers', [VoucherController::class, 'store'])->name('vouchers.store');
        Route::post('/{user}/vouchers/reissue', [VoucherController::class, 'reissue'])->name('vouchers.reissue');
        Route::delete('/{user}/vouchers/{voucher}', [VoucherController::class, 'destroy'])->name('vouchers.destroy');
        Route::post('/{user}/vouchers/print', [VoucherController::class, 'printSlip'])->name('vouchers.print');
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
    Route::post('/admissions/bulk-accept', [AdmissionReviewController::class, 'bulkAccept'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_APPLICATIONS_ACCEPT)
        ->name('admissions.bulk-accept');
    Route::post('/admissions/bulk-request-correction', [AdmissionReviewController::class, 'bulkRequestCorrection'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_APPLICATIONS_CORRECT)
        ->name('admissions.bulk-request-correction');
    Route::post('/admissions/bulk-direct-assessment', [DirectAssessmentController::class, 'bulkStore'])
        ->name('admissions.bulk-direct-assessment');
    Route::get('/admissions/create', [AdmissionReviewController::class, 'create'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_APPLICATIONS_CORRECT)
        ->name('admissions.create');
    Route::post('/admissions', [AdmissionReviewController::class, 'store'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_APPLICATIONS_CORRECT)
        ->name('admissions.store');
    Route::get('/admissions/{attempt}', [AdmissionReviewController::class, 'show'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW)
        ->name('admissions.show');
    Route::get('/admissions/{attempt}/edit', [AdmissionReviewController::class, 'edit'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW)
        ->name('admissions.edit');
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
    Route::post('/admissions/{attempt}/document-correction-requests', [AdmissionDocumentCorrectionRequestController::class, 'store'])
        ->middleware(['capability:'.CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW, 'capability:'.CapabilityCatalog::REGISTRAR_DOCUMENTS_VERIFY])
        ->name('admissions.document-correction-requests.store');
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
    Route::post('/applicant/schedule/switch', [ApplicantScheduleController::class, 'switch'])
        ->name('applicant.schedule.switch');
    Route::get('/applicant/release-schedule', [ApplicantConsultationController::class, 'show'])
        ->name('applicant.consultation');
    Route::post('/applicant/release-schedule/reserve', [ApplicantConsultationController::class, 'reserve'])
        ->name('applicant.consultation.reserve');
    Route::post('/applicant/release-schedule/switch', [ApplicantConsultationController::class, 'switch'])
        ->name('applicant.consultation.switch');
    Route::get('/applicant/exam-day', [ApplicantExamDayController::class, 'show'])
        ->name('applicant.exam-day');
    Route::get('/applicant/exam-scoring', [ApplicantExamScoringController::class, 'show'])
        ->name('applicant.exam-scoring');
    Route::get('/applicant/release-day', [ApplicantReleaseDayController::class, 'show'])
        ->name('applicant.release-day');
    Route::get('/applicant/admission-clearance', [ApplicantAdmissionClearanceController::class, 'show'])
        ->name('applicant.admission-clearance');
    Route::get('/applicant/applications/{attempt}/correct', [ApplicantPortalController::class, 'correctionForm'])
        ->name('applicant.correction');
    Route::post('/applicant/applications/{attempt}/correct', [ApplicantPortalController::class, 'resubmitCorrection'])
        ->name('applicant.correction.store');
    Route::get('/applicant/applications/{attempt}', [ApplicantPortalController::class, 'applicationShow'])
        ->name('applicant.application');

    Route::get('/exams/search-suggestions', [SessionSearchSuggestionController::class, 'index'])
        ->middleware('throttle:search-suggestions')
        ->name('exams.search-suggestions');
    Route::get('/exams/monitoring', [ExamMonitoringController::class, 'monitoringIndex'])
        ->name('exams.monitoring');
    Route::get('/exams/sessions/{session}/monitoring', [ExamMonitoringController::class, 'monitoring'])
        ->name('exams.sessions.monitoring');
    Route::get('/exams/sessions/{session}/monitoring/distribution', [ExamMonitoringController::class, 'distribution'])
        ->name('exams.sessions.monitoring.distribution');
    Route::get('/exams/direct-assessment', [DirectAssessmentController::class, 'create'])
        ->name('exams.direct-assessment.create');
    Route::post('/exams/direct-assessment', [DirectAssessmentController::class, 'store'])
        ->name('exams.direct-assessment.store');
    Route::get('/exams/sessions', [ExamSessionController::class, 'index'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)
        ->name('exams.sessions.index');
    Route::get('/exams/sessions/create', [ExamSessionController::class, 'create'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)
        ->name('exams.sessions.create');
    Route::post('/exams/sessions', [ExamSessionController::class, 'store'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)
        ->name('exams.sessions.store');
    Route::get('/exams/sessions/waitlist', [ExamSessionController::class, 'waitlist'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)
        ->name('exams.sessions.waitlist');

    Route::get('/exams/sessions/{session}', [ExamSessionController::class, 'show'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)
        ->name('exams.sessions.show');
    Route::get('/exams/sessions/{session}/edit', [ExamSessionController::class, 'edit'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)
        ->name('exams.sessions.edit');
    Route::put('/exams/sessions/{session}', [ExamSessionController::class, 'update'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)
        ->name('exams.sessions.update');
    Route::post('/exams/sessions/{session}/snapshots/{snapshot}/interpretation', [ExamSessionController::class, 'resolveSnapshotInterpretation'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)
        ->name('exams.sessions.snapshots.interpretation');
    Route::post('/exams/sessions/{session}/assign', [ExamSessionController::class, 'assign'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)
        ->name('exams.sessions.assign');
    Route::post('/exams/sessions/{session}/bulk-assign', [ExamSessionController::class, 'bulkAssign'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)
        ->name('exams.sessions.bulk-assign');
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
    Route::post('/exams/sessions/{session}/start', [ExamSessionController::class, 'start'])
        ->name('exams.sessions.start');
    Route::post('/exams/sessions/{session}/revert-start', [ExamSessionController::class, 'revertStart'])
        ->name('exams.sessions.revert-start');
    Route::post('/exams/sessions/{session}/close', [ExamSessionController::class, 'close'])
        ->name('exams.sessions.close');
    Route::post('/exams/sessions/{session}/extend', [ExamSessionController::class, 'extend'])
        ->name('exams.sessions.extend');
    Route::post('/exams/sessions/{session}/open-reservation', [ExamSessionController::class, 'openReservation'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)
        ->name('exams.sessions.open-reservation');
    Route::post('/exams/sessions/{session}/close-reservation', [ExamSessionController::class, 'closeReservation'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)
        ->name('exams.sessions.close-reservation');
    Route::post('/exams/sessions/bulk-publish', [ExamSessionController::class, 'bulkPublish'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)
        ->name('exams.sessions.bulk-publish');
    Route::post('/exams/sessions/bulk-booking-mode', [ExamSessionController::class, 'bulkBookingMode'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)
        ->name('exams.sessions.bulk-booking-mode');
    Route::post('/exams/sessions/bulk-cancel', [ExamSessionController::class, 'bulkCancel'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)
        ->name('exams.sessions.bulk-cancel');
    Route::post('/exams/sessions/auto-distribute/preview', [ExamSessionController::class, 'previewDistribution'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)
        ->name('exams.sessions.auto-distribute.preview');
    Route::post('/exams/sessions/auto-distribute/commit', [ExamSessionController::class, 'commitDistribution'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)
        ->name('exams.sessions.auto-distribute.commit');
    Route::patch('/exams/sessions/{session}/toggle-auto-assign', [ExamSessionController::class, 'toggleAutoAssign'])
        ->name('exams.sessions.toggle-auto-assign');

    Route::get('/consultation/sessions', [ConsultationSessionController::class, 'index'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN)
        ->name('consultation.sessions.index');
    Route::get('/consultation/sessions/create', [ConsultationSessionController::class, 'create'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN)
        ->name('consultation.sessions.create');
    Route::post('/consultation/sessions', [ConsultationSessionController::class, 'store'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN)
        ->name('consultation.sessions.store');
    Route::post('/consultation/sessions/bulk-publish', [ConsultationSessionController::class, 'bulkPublish'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN)
        ->name('consultation.sessions.bulk-publish');
    Route::post('/consultation/sessions/bulk-booking-mode', [ConsultationSessionController::class, 'bulkBookingMode'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN)
        ->name('consultation.sessions.bulk-booking-mode');
    Route::post('/consultation/sessions/bulk-cancel', [ConsultationSessionController::class, 'bulkCancel'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN)
        ->name('consultation.sessions.bulk-cancel');
    Route::get('/consultation/sessions/waitlist', [ConsultationSessionController::class, 'waitlist'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN)
        ->name('consultation.sessions.waitlist');

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
    Route::post('/consultation/sessions/{session}/bulk-assign', [ConsultationSessionController::class, 'bulkAssign'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN)
        ->name('consultation.sessions.bulk-assign');
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
    Route::post('/consultation/sessions/{session}/open', [ConsultationSessionController::class, 'open'])
        ->name('consultation.sessions.open');
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
    Route::post('/consultation/sessions/auto-distribute/preview', [ConsultationSessionController::class, 'previewDistribution'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN)
        ->name('consultation.sessions.auto-distribute.preview');
    Route::post('/consultation/sessions/auto-distribute/commit', [ConsultationSessionController::class, 'commitDistribution'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN)
        ->name('consultation.sessions.auto-distribute.commit');
    Route::patch('/consultation/sessions/{session}/toggle-auto-assign', [ConsultationSessionController::class, 'toggleAutoAssign'])
        ->name('consultation.sessions.toggle-auto-assign');

    Route::get('/registrar/enrollment-eligibility', [EnrollmentEligibilityController::class, 'index'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_ENROLLMENT_ELIGIBILITY)
        ->name('registrar.enrollment-eligibility.index');
    Route::get('/registrar/enrollment-eligibility/{attempt}', [EnrollmentEligibilityController::class, 'show'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_ENROLLMENT_ELIGIBILITY)
        ->name('registrar.enrollment-eligibility.show');

    Route::post('/exams/sessions/{session}/monitoring/offline-attendance', ExamOfflineAttendanceController::class)
        ->name('exams.sessions.monitoring.offline-attendance');
    Route::get('/exams/sessions/{session}/monitoring/lookup-applicant', [ExamMonitoringAttendanceController::class, 'lookupApplicant'])
        ->name('exams.sessions.monitoring.lookup-applicant');
    Route::post('/exams/sessions/{session}/monitoring/accommodate', [ExamMonitoringAttendanceController::class, 'accommodateApplicant'])
        ->name('exams.sessions.monitoring.accommodate');
    Route::post('/exams/sessions/{session}/monitoring/assignments/{assignment}/attendance', [ExamMonitoringAttendanceController::class, 'recordAttendance'])
        ->name('exams.sessions.monitoring.attendance');
    Route::post('/exams/sessions/{session}/monitoring/assignments/{assignment}/submission', [ExamMonitoringAttendanceController::class, 'recordSubmission'])
        ->name('exams.sessions.monitoring.submission');
    Route::post('/exams/sessions/{session}/monitoring/qr-checkin', [ExamMonitoringAttendanceController::class, 'qrCheckin'])
        ->name('exams.sessions.monitoring.qr-checkin');
    Route::post('/exams/sessions/{session}/monitoring/submissions/bulk', [ExamMonitoringAttendanceController::class, 'recordSubmissionBulk'])
        ->name('exams.sessions.monitoring.submissions.bulk');

    Route::get('/scoring', [GradingController::class, 'index'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_MANUAL)
        ->name('scoring.index');
    Route::get('/scoring/sessions/{examSession}', [GradingController::class, 'show'])
        ->whereNumber('examSession')
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_MANUAL)
        ->name('scoring.sessions.show');
    Route::post('/scoring/sessions/{examSession}/finalize', [GradingController::class, 'finalize'])
        ->whereNumber('examSession')
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_MANUAL)
        ->name('scoring.sessions.finalize');
    Route::get('/scoring/sessions/{examSession}/attempts/{attempt}', [GradingScoreController::class, 'show'])
        ->whereNumber('examSession')
        ->whereNumber('attempt')
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_MANUAL)
        ->name('scoring.sessions.attempts.scores');
    Route::put('/scoring/sessions/{examSession}/attempts/{attempt}/scores', [GradingScoreController::class, 'update'])
        ->whereNumber('examSession')
        ->whereNumber('attempt')
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_MANUAL)
        ->name('scoring.sessions.attempts.scores.update');
    Route::delete('/scoring/sessions/{examSession}/attempts/{attempt}/scores', [GradingScoreController::class, 'destroy'])
        ->whereNumber('examSession')
        ->whereNumber('attempt')
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_MANUAL)
        ->name('scoring.sessions.attempts.scores.destroy');

    Route::get('/grading', [GradingController::class, 'index'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_MANUAL)
        ->name('grading.index');
    Route::get('/scoring/omr', [GradingController::class, 'omrScanner'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('scoring.omr.scanner');
    Route::post('/scoring/omr/captures', [GradingController::class, 'storeOmrScannerCapture'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('scoring.omr.scanner.capture');
    Route::post('/scoring/omr/captures/{pending}/retry', [GradingController::class, 'retryOmrScannerCapture'])
        ->whereNumber('pending')
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('scoring.omr.scanner.capture.retry');
    Route::post('/scoring/omr/captures/{pending}/resolve-applicant', [GradingController::class, 'resolveOmrPendingApplicant'])
        ->whereNumber('pending')
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('scoring.omr.scanner.capture.resolve-applicant');
    Route::post('/scoring/omr/captures/{pending}/select-session', [GradingController::class, 'selectOmrPendingSession'])
        ->whereNumber('pending')
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('scoring.omr.scanner.capture.select-session');
    Route::post('/scoring/omr/captures/{pending}/resolve-display-code', [GradingController::class, 'resolveOmrPendingDisplayCode'])
        ->whereNumber('pending')
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('scoring.omr.scanner.capture.resolve-display-code');
    Route::post('/scoring/omr/captures/{pending}/adopt', [GradingController::class, 'adoptOmrPendingCapture'])
        ->whereNumber('pending')
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('scoring.omr.scanner.capture.adopt');
    Route::prefix('/grading/omr/sensor')
        ->name('grading.omr.sensor.')
        ->middleware(['capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR, 'throttle:120,1'])
        ->group(function (): void {
            Route::get('/', [OmrCameraSensorController::class, 'show'])->name('show');
            Route::post('/pair', [OmrCameraSensorController::class, 'createPair'])->name('pair.create');
            Route::post('/pair/join', [OmrCameraSensorController::class, 'joinPair'])
                ->middleware('throttle:10,1')
                ->name('pair.join');
            Route::get('/pair/{pairing}', [OmrCameraSensorController::class, 'poll'])->whereUuid('pairing')->name('pair.poll');
            Route::post('/pair/{pairing}/command', [OmrCameraSensorController::class, 'sendCommand'])->whereUuid('pairing')->name('pair.command');
            Route::post('/pair/{pairing}/ack', [OmrCameraSensorController::class, 'acknowledge'])->whereUuid('pairing')->name('pair.ack');
            Route::post('/pair/{pairing}/signal', [OmrCameraSensorController::class, 'signal'])->whereUuid('pairing')->name('pair.signal');
            Route::post('/pair/{pairing}/heartbeat', [OmrCameraSensorController::class, 'heartbeat'])->whereUuid('pairing')->name('pair.heartbeat');
            Route::get('/pair/{pairing}/status', [OmrCameraSensorController::class, 'status'])->whereUuid('pairing')->name('pair.status');
            Route::post('/pair/{pairing}/revoke', [OmrCameraSensorController::class, 'revoke'])->whereUuid('pairing')->name('pair.revoke');
            Route::post('/pair/{pairing}/node/rejoin', [OmrCameraSensorController::class, 'rejoinNode'])
                ->whereUuid('pairing')->name('pair.node.rejoin');
            Route::post('/pair/{pairing}/frames', [OmrCameraSensorController::class, 'stageFrame'])->whereUuid('pairing')->name('pair.frames.store');
            Route::get('/pair/{pairing}/frames/{frame}', [OmrCameraSensorController::class, 'showFrame'])->whereUuid('pairing')->whereUuid('frame')->name('pair.frames.show');
        });
    Route::get('/grading/sessions/{gradingSession}', [GradingController::class, 'show'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_MANUAL)
        ->name('grading.sessions.show');
    Route::get('/scoring/sessions/{examSession}/omr', [GradingController::class, 'omr'])
        ->whereNumber('examSession')
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('scoring.sessions.omr');
    Route::get('/scoring/sessions/{examSession}/omr/batches/{batch}/workbench', [OmrProductionWorkbenchController::class, 'show'])
        ->whereNumber('examSession')
        ->whereNumber('batch')
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('scoring.sessions.omr.batches.workbench');
    Route::post('/scoring/sessions/{examSession}/omr/batches/{batch}/workbench/capture', [OmrProductionWorkbenchController::class, 'captureStart'])
        ->whereNumber('examSession')
        ->whereNumber('batch')
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('scoring.sessions.omr.batches.workbench.capture_start');
    Route::post('/scoring/sessions/{examSession}/omr/batches/{batch}/workbench/capture-actions', [OmrWorkbenchCaptureActionController::class, 'production'])
        ->whereNumber('examSession')
        ->whereNumber('batch')
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('scoring.sessions.omr.batches.workbench.capture_actions');
    Route::post('/scoring/sessions/{examSession}/omr/batches/{batch}/workbench/pending-capture', [OmrProductionWorkbenchController::class, 'startPendingCapture'])
        ->whereNumber('examSession')
        ->whereNumber('batch')
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('scoring.sessions.omr.batches.workbench.pending-capture.start');
    Route::post('/scoring/sessions/{examSession}/omr/batches/{batch}/workbench/pending-capture/adopt', [OmrProductionWorkbenchController::class, 'adoptPendingCapture'])
        ->whereNumber('examSession')
        ->whereNumber('batch')
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('scoring.sessions.omr.batches.workbench.pending-capture.adopt');
    Route::post('/scoring/sessions/{examSession}/omr/batches/{batch}/workbench/pending-capture/identity', [OmrProductionWorkbenchController::class, 'confirmPendingCaptureIdentity'])
        ->whereNumber('examSession')
        ->whereNumber('batch')
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('scoring.sessions.omr.batches.workbench.pending-capture.identity');
    Route::post('/scoring/sessions/{examSession}/omr/batches/{batch}/workbench/supplier-stock/allocate', [OmrProductionWorkbenchController::class, 'allocateSupplierStock'])
        ->whereNumber('examSession')
        ->whereNumber('batch')
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('scoring.sessions.omr.batches.workbench.supplier-stock.allocate');
    Route::post('/scoring/sessions/{examSession}/omr/batches/{batch}/workbench/sheets/{sheet}/retry', [OmrProductionWorkbenchController::class, 'captureRetry'])
        ->whereNumber('examSession')
        ->whereNumber('batch')
        ->whereNumber('sheet')
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('scoring.sessions.omr.batches.workbench.capture_retry');
    Route::post('/scoring/sessions/{examSession}/omr/batches/{batch}/workbench/sheets/{sheet}/review', [OmrProductionWorkbenchController::class, 'reviewSave'])
        ->whereNumber('examSession')
        ->whereNumber('batch')
        ->whereNumber('sheet')
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('scoring.sessions.omr.batches.workbench.review_save');
    Route::post('/scoring/sessions/{examSession}/omr/batches/{batch}/workbench/sheets/{sheet}/resolve-applicant', [OmrProductionWorkbenchController::class, 'resolveApplicant'])
        ->whereNumber('examSession')
        ->whereNumber('batch')
        ->whereNumber('sheet')
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('scoring.sessions.omr.batches.workbench.resolve_applicant');
    Route::post('/scoring/sessions/{examSession}/omr/batches/{batch}/workbench/sheets/{sheet}/resolve-duplicate', [OmrProductionWorkbenchController::class, 'resolveDuplicate'])
        ->whereNumber('examSession')
        ->whereNumber('batch')
        ->whereNumber('sheet')
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('scoring.sessions.omr.batches.workbench.resolve_duplicate');
    Route::post('/scoring/sessions/{examSession}/omr/batches/{batch}/workbench/complete', [OmrProductionWorkbenchController::class, 'productionComplete'])
        ->whereNumber('examSession')
        ->whereNumber('batch')
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('scoring.sessions.omr.batches.workbench.production_complete');

    Route::prefix('/scoring/sessions/{examSession}/omr')
        ->whereNumber('examSession')
        ->name('scoring.sessions.omr.')
        ->group(function (): void {
            Route::get('/batches/{batch}/status', [GradingController::class, 'omrStatus'])
                ->whereNumber('batch')
                ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
                ->name('batches.status');
            Route::get('/sheets/{sheet}/evidence', [GradingController::class, 'omrEvidence'])
                ->whereNumber('sheet')
                ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
                ->name('sheets.evidence');
            Route::get('/sheets/{sheet}/image/{sha256?}', [GradingController::class, 'omrImage'])
                ->whereNumber('sheet')
                ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
                ->name('sheets.image');
            Route::get('/sheets/{sheet}/pages/{page}/image/{sha256?}', [GradingController::class, 'omrPageImage'])
                ->whereNumber('sheet')
                ->whereNumber('page')
                ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
                ->name('sheets.pages.image');
            Route::post('/sheets/{sheet}/pages/{page}/replace', [GradingController::class, 'replaceOmrSheetPage'])
                ->whereNumber('sheet')
                ->whereNumber('page')
                ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
                ->name('sheets.pages.replace');
            Route::post('/batches', [GradingController::class, 'storeOmrBatch'])
                ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
                ->name('batches.store');
            Route::post('/reproduction-authorizations', [GradingController::class, 'authorizeOmrPaperReproduction'])
                ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
                ->name('reproduction-authorizations.store');
            Route::post('/batches/{batch}/preflight', [GradingController::class, 'preflightCapture'])
                ->whereNumber('batch')
                ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
                ->name('batches.preflight');
            Route::post('/sheets', [GradingController::class, 'storeOmrSheet'])
                ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
                ->name('sheets.store');
            Route::post('/sheets/{sheet}/review', [GradingController::class, 'reviewOmrSheet'])
                ->whereNumber('sheet')
                ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
                ->name('sheets.review');
            Route::post('/sheets/{sheet}/resolve-applicant', [GradingController::class, 'resolveOmrApplicant'])
                ->whereNumber('sheet')
                ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
                ->name('sheets.resolve-applicant');
            Route::post('/sheets/{sheet}/resolve-duplicate', [GradingController::class, 'resolveOmrDuplicate'])
                ->whereNumber('sheet')
                ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
                ->name('sheets.resolve-duplicate');
            Route::post('/sheets/{sheet}/items/{item}/review', [OmrGradingItemReviewController::class, 'store'])
                ->whereNumber('sheet')
                ->where('item', '[A-Za-z0-9._-]+')
                ->middleware(['auth', 'capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR])
                ->name('sheets.items.review');
            Route::post('/sheets/{sheet}/issues/{issue}/resolve', [GradingController::class, 'resolveOmrIssue'])
                ->whereNumber('sheet')
                ->whereNumber('issue')
                ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
                ->name('sheets.issues.resolve');
            Route::post('/batches/{batch}/confirm', [GradingController::class, 'confirmOmrBatch'])
                ->whereNumber('batch')
                ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
                ->name('batches.confirm');
            Route::post('/batches/{batch}/reject', [GradingController::class, 'rejectOmrBatch'])
                ->whereNumber('batch')
                ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
                ->name('batches.reject');
            Route::post('/sheets/{sheet}/retry', [GradingController::class, 'retryOmrSheet'])
                ->whereNumber('sheet')
                ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
                ->name('sheets.retry');
        });
    Route::post('/grading/omr/blank-instance/register', [OmrBlankInstanceController::class, 'register'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('grading.omr.blank_instance.register');
    Route::post('/grading/omr/blank-instance/manual-resolve', [OmrBlankInstanceController::class, 'manualResolve'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('grading.omr.blank_instance.manual_resolve');
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

    Route::get('/consultation/lookup', [ConsultationDashboardController::class, 'lookup'])
        ->middleware(['capability:'.CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE, 'throttle:consultation-lookup'])
        ->name('consultation.lookup');
    Route::get('/consultation', [ConsultationDashboardController::class, 'index'])
        ->middleware(['capability:'.CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE, 'throttle:consultation-lookup'])
        ->name('consultation.index');
    Route::post('/consultation/{attempt}/decision', [ConsultationDecisionController::class, 'store'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE)
        ->name('consultation.decision');
    Route::post('/consultation/{attempt}/evidence', [ConsultationEvidenceController::class, 'store'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE)
        ->name('consultation.evidence.store');
    // Shared per-applicant admission-support workspace (Guidance + PH).
    Route::get('/consultation/applicants/{attempt}', [AdmissionSupportController::class, 'show'])
        ->middleware('auth')
        ->name('admission-support.show');
    Route::get('/consultation/applicants/{attempt}/support', [AdmissionSupportController::class, 'supportJson'])
        ->middleware('auth')
        ->name('admission-support.support');
    Route::post('/release/{attempt}/prepare', [ReleaseController::class, 'prepareDocument'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_RESULTS_PREPARE)
        ->name('release.prepare');
    $releaseOrPrepareMiddleware = 'capability:'.CapabilityCatalog::GUIDANCE_RESULTS_PREPARE.','.CapabilityCatalog::GUIDANCE_RESULTS_RELEASE;
    Route::get('/release', [ReleaseController::class, 'index'])
        ->middleware($releaseOrPrepareMiddleware)
        ->name('release.index');
    // Print routes MUST precede /release/{attempt} so `print` never binds as an attempt.
    Route::get('/release/print/sessions/{examSession}', [ReleasePrintController::class, 'index'])
        ->whereNumber('examSession')
        ->middleware($releaseOrPrepareMiddleware)
        ->name('release.print.sessions.index');
    Route::get('/release/print/sessions/{examSession}/preview', [ReleasePrintController::class, 'bulkPreview'])
        ->whereNumber('examSession')
        ->middleware($releaseOrPrepareMiddleware)
        ->name('release.print.sessions.preview');
    Route::get('/release/print/sessions/{examSession}/applicants/{attempt}', [ReleasePrintController::class, 'showApplicant'])
        ->whereNumber('examSession')
        ->whereNumber('attempt')
        ->middleware($releaseOrPrepareMiddleware)
        ->name('release.print.sessions.applicant');
    Route::get('/release/print/batches/{gradingSession}', [ReleasePrintController::class, 'index'])
        ->middleware($releaseOrPrepareMiddleware)
        ->name('release.print.batches.index');
    Route::get('/release/print/batches/{gradingSession}/preview', [ReleasePrintController::class, 'bulkPreview'])
        ->middleware($releaseOrPrepareMiddleware)
        ->name('release.print.batches.preview');
    Route::get('/release/print/batches/{gradingSession}/applicants/{attempt}', [ReleasePrintController::class, 'showApplicant'])
        ->middleware($releaseOrPrepareMiddleware)
        ->name('release.print.batches.applicant');
    Route::post('/release/print', [ReleasePrintController::class, 'request'])
        ->middleware($releaseOrPrepareMiddleware)
        ->name('release.print.request');
    Route::post('/release/print/mark-printed', [ReleasePrintController::class, 'markPrinted'])
        ->middleware($releaseOrPrepareMiddleware)
        ->name('release.print.mark-printed');
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
    Route::get('/printing/jobs/{printJob}/items/{printJobItem}/download', [PrintingController::class, 'downloadItem'])
        ->middleware('auth')
        ->name('printing.jobs.items.download');

    Route::redirect('/setup/omr', '/setup/exam-materials/answer-sheets')->name('setup.omr.index');
    Route::get('/setup/omr/intake', [OmrConfigurationController::class, 'intake'])->name('setup.omr.intake');
    Route::post('/setup/omr/bundles', [OmrConfigurationController::class, 'storeBundle'])->name('setup.omr.bundles.store');

    Route::redirect('/setup/exam-materials/answer-sheet', '/setup/exam-materials/answer-sheets')
        ->name('setup.exam-materials.answer-sheet');
    Route::get('/setup/exam-materials/answer-sheet/{any}', function (string $any) {
        return redirect('/setup/exam-materials/answer-sheets/'.$any);
    })->where('any', '.*');
    Route::prefix('/setup/exam-materials/answer-sheets')
        ->name('setup.exam-materials.answer-sheets.')
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->group(function (): void {
            Route::get('/', [OmrCanonicalTemplateController::class, 'index'])->name('index');
            Route::post('/', [OmrCanonicalTemplateController::class, 'create'])->name('store');
            Route::post('/import', [OmrCanonicalTemplateController::class, 'import'])->name('import');
            Route::get('/imports/{operation}', [OmrCanonicalTemplateController::class, 'importStatusPage'])
                ->whereUuid('operation')
                ->name('imports.show');
            Route::get('/imports/{operation}/status', [OmrCanonicalTemplateController::class, 'importStatus'])
                ->whereUuid('operation')
                ->name('imports.status');
            Route::post('/imports/{operation}/confirm', [OmrCanonicalTemplateController::class, 'confirmImport'])
                ->whereUuid('operation')
                ->name('imports.confirm');
            Route::post('/imports/{operation}/retry', [OmrCanonicalTemplateController::class, 'retryImport'])
                ->whereUuid('operation')
                ->name('imports.retry');
            Route::post('/imports/{operation}/cancel', [OmrCanonicalTemplateController::class, 'cancelImport'])
                ->whereUuid('operation')
                ->name('imports.cancel');
            Route::post('/imports/{operation}/replace', [OmrCanonicalTemplateController::class, 'replaceImportSource'])
                ->whereUuid('operation')
                ->name('imports.replacement');
            Route::post('/imports/{operation}/replace-source', [OmrCanonicalTemplateController::class, 'replaceImportSource'])
                ->whereUuid('operation')
                ->name('imports.replace');
            Route::post('/{draft}/editable-copy', [OmrCanonicalTemplateController::class, 'createEditableCopy'])->name('editable-copy');
            Route::post('/{draft}/unlock', [OmrCanonicalTemplateController::class, 'unlockDraft'])->name('unlock');
            Route::get('/{draft}/edit', [OmrCanonicalTemplateController::class, 'show'])->name('edit');
            Route::get('/{draft}/export', [OmrCanonicalTemplateController::class, 'exportDraft'])->name('export.draft');
            Route::get('/versions/{templateVersion}/export', [OmrCanonicalTemplateController::class, 'exportVersion'])->name('export.version');
            Route::put('/{draft}', [OmrCanonicalTemplateController::class, 'studio'])->name('update');
            Route::delete('/{draft}', [OmrCanonicalTemplateController::class, 'destroy'])->name('destroy');
        });

    Route::get('/setup/omr/studio/{draft?}', [OmrCanonicalTemplateController::class, 'studioDirect'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.studio');
    Route::redirect('/setup/omr/templates/canonical', '/setup/exam-materials/answer-sheets')
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR);
    Route::get('/setup/omr/templates/canonical/diagnostics', [OmrCanonicalTemplateController::class, 'diagnostics'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.diagnostics');
    Route::get('/setup/omr/templates/canonical/drafts/{draft}', [OmrCanonicalTemplateController::class, 'studioDirect'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR);
    Route::get('/setup/omr/templates/canonical/drafts/{draft}/assets/{asset}', [OmrCanonicalTemplateController::class, 'asset'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.drafts.assets.show');
    Route::get('/setup/omr/templates/{templateVersion}/native/template.json', [OmrCanonicalTemplateController::class, 'nativeTemplate'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.native_template');
    Route::get('/setup/omr/templates/canonical/drafts/{draft}/booklet-options', [OmrCanonicalTemplateController::class, 'bookletOptions'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.drafts.booklet_options');
    Route::post('/setup/omr/templates/canonical/drafts/{draft}/source/ingest', [OmrCanonicalTemplateController::class, 'ingest'])
        ->middleware(['capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR, 'throttle:10,1'])
        ->name('setup.omr.templates.canonical.drafts.source.ingest');
    Route::get('/setup/omr/templates/canonical/drafts/{draft}/source/ingest/run', [OmrCanonicalTemplateController::class, 'sourceRun'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.drafts.source.run');
    Route::post('/setup/omr/templates/canonical/drafts/{draft}/source/ingest/cancel', [OmrCanonicalTemplateController::class, 'cancelSourceRun'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.drafts.source.cancel');
    Route::post('/setup/omr/templates/canonical/drafts/{draft}/source/rectify', [OmrCanonicalTemplateController::class, 'rectify'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.drafts.source.rectify');
    Route::post('/setup/omr/templates/canonical/drafts/{draft}/compose', [OmrCanonicalTemplateController::class, 'compose'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.drafts.compose');
    Route::post('/setup/omr/templates/canonical/drafts/{draft}/source/pages/confirm', [OmrCanonicalTemplateController::class, 'confirmPages'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.drafts.source.pages.confirm');
    Route::put('/setup/omr/templates/canonical/drafts/{draft}/pages', [OmrCanonicalTemplateController::class, 'updatePages'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.drafts.pages.update');
    Route::put('/setup/omr/templates/canonical/drafts/{draft}/overlay', [OmrCanonicalTemplateController::class, 'overlay'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.drafts.overlay');
    Route::put('/setup/omr/templates/canonical/drafts/{draft}/mapping', [OmrCanonicalTemplateController::class, 'mapping'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.drafts.mapping');
    Route::put('/setup/omr/templates/canonical/drafts/{draft}/studio', [OmrCanonicalTemplateController::class, 'studio'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.drafts.studio');
    Route::post('/setup/omr/templates/canonical/drafts/{draft}/materialize', [OmrCanonicalTemplateController::class, 'materialize'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.drafts.materialize');
    Route::post('/setup/omr/templates/canonical/drafts/{draft}/preview', [OmrCanonicalTemplateController::class, 'preview'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.drafts.preview');
    Route::put('/setup/omr/templates/canonical/drafts/{draft}/marker-profile/{profile}', [OmrCanonicalTemplateController::class, 'selectMarkerProfile'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.drafts.marker_profile');
    Route::post('/setup/omr/templates/canonical/drafts/{draft}/compile', [OmrCanonicalTemplateController::class, 'compile'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.drafts.compile');
    Route::post('/setup/omr/templates/canonical/drafts/{draft}/verification-runs', [OmrCanonicalTemplateController::class, 'digitalVerification'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.drafts.verification_runs.start');
    Route::get('/setup/omr/templates/verification-runs/{run}', [OmrCanonicalTemplateController::class, 'verificationRunStatus'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.verification_runs.status');
    Route::get('/setup/omr/templates/verification-runs/{run}/evidence', [OmrCanonicalTemplateController::class, 'verificationEvidence'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.verification_runs.evidence');
    Route::get('/setup/omr/templates/verification-runs/{run}/diagnostic-report', [OmrCanonicalTemplateController::class, 'diagnosticReport'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.verification_runs.diagnostic_report');
    Route::get('/setup/omr/templates/verification-runs/{run}/diagnostic-report/export', [OmrCanonicalTemplateController::class, 'exportDiagnosticReport'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.verification_runs.diagnostic_report.export');
    Route::post('/setup/omr/templates/verification-runs/{run}/retry', [OmrCanonicalTemplateController::class, 'retryVerificationRun'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.verification_runs.retry');
    Route::get('/setup/omr/templates/verification-runs/{run}/assets/{asset}/{sha256?}', [OmrCanonicalTemplateController::class, 'verificationRunAsset'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.verification_runs.assets.show');
    Route::post('/setup/omr/templates/canonical/drafts/{draft}/challenge', [OmrCanonicalTemplateController::class, 'createPhysicalChallenge'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.drafts.challenge.create');
    Route::post('/setup/omr/templates/verification-runs/{run}/challenge/regenerate', [OmrCanonicalTemplateController::class, 'regeneratePhysicalChallenge'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.verification_runs.challenge.regenerate');
    Route::post('/setup/omr/templates/verification-runs/{run}/challenge/restart', [OmrCanonicalTemplateController::class, 'restartPhysicalChallenge'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.verification_runs.challenge.restart');
    Route::post('/setup/omr/templates/verification-runs/{run}/prepare-print', [OmrCanonicalTemplateController::class, 'preparePrint'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.verification_runs.prepare_print');
    Route::post('/setup/omr/templates/verification-runs/{run}/print', [OmrCanonicalTemplateController::class, 'markChallengePrinted'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.verification_runs.print');
    Route::post('/setup/omr/templates/verification-runs/{run}/capture', [OmrCanonicalTemplateController::class, 'submitPhysicalCapture'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.verification_runs.capture');
    Route::post('/setup/omr/templates/verification-runs/{run}/review', [OmrCanonicalTemplateController::class, 'reviewPhysicalCapture'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.verification_runs.review');
    Route::post('/setup/omr/templates/verification-runs/{run}/methods/{method}/pages/{page}/retake', [OmrCanonicalTemplateController::class, 'retakePhysicalPage'])
        ->whereIn('method', ['pre_shaded', 'hand_shaded'])
        ->whereNumber('page')
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.verification_runs.pages.retake');
    Route::post('/setup/omr/templates/verification-runs/{run}/captures/{capture}/select', [OmrCanonicalTemplateController::class, 'selectPhysicalAttempt'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.verification_runs.captures.select');
    Route::post('/setup/omr/templates/verification-runs/{run}/captures/{capture}/items/{item}/review', [OmrVerificationItemReviewController::class, 'store'])
        ->whereNumber('run')
        ->whereNumber('capture')
        ->whereNumber('item')
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.verification_runs.captures.items.review');
    Route::post('/setup/omr/templates/verification-runs/{run}/captures/{capture}/discard', [OmrCanonicalTemplateController::class, 'discardPhysicalAttempt'])
        ->whereNumber('run')
        ->whereNumber('capture')
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.verification_runs.captures.discard');
    Route::post('/setup/omr/templates/verification-runs/{run}/qualify', [OmrCanonicalTemplateController::class, 'physicalQualification'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.verification_runs.qualify');
    Route::get('/setup/omr/templates/{templateVersion}/blank/instances', [OmrCanonicalTemplateController::class, 'blankInstances'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.blank.instances');
    Route::post('/setup/omr/templates/{templateVersion}/blank/instances/{instance}/supersede', [OmrCanonicalTemplateController::class, 'supersedeBlankInstance'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.blank.instances.supersede');
    Route::post('/setup/omr/templates/{templateVersion}/blank/instances/{instance}/void', [OmrCanonicalTemplateController::class, 'voidBlankInstance'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.blank.instances.void');

    Route::post('/setup/omr/templates/canonical/marker-profiles/{profile}/calibration-artifact', [OmrCanonicalTemplateController::class, 'calibrationArtifact'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.marker_profiles.calibration_artifact');
    Route::post('/setup/omr/templates/canonical/marker-profiles/{profile}/qualify', [OmrCanonicalTemplateController::class, 'qualifyMarkerProfile'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.canonical.marker_profiles.qualify');
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
    Route::post('/setup/omr/templates/{version}/test-capture', [OmrConfigurationController::class, 'testCapture'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.test_capture');
    Route::get('/setup/omr/templates/test-capture/{token}/{file}', [OmrConfigurationController::class, 'testCaptureAsset'])
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->name('setup.omr.templates.test_capture_asset');
    Route::get('/setup/omr/booklets', fn () => redirect()->route('setup.exam-materials.booklets.index'))
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE)
        ->name('setup.omr.booklets');
    Route::get('/setup/omr/booklets/create', fn () => redirect()->route('setup.exam-materials.booklets.index'))
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE)
        ->name('setup.omr.booklets.create');
    Route::get('/setup/omr/booklets/{booklet}', fn (OmrQuestionBooklet $booklet) => redirect()->route('setup.exam-materials.booklets.show', $booklet))
        ->middleware('capability:'.CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE)
        ->name('setup.omr.booklets.show');

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
    Route::get('/staff/programs/{program:code}/applicants/{attempt}/result-sheet', ProgramHeadResultController::class)
        ->middleware(['capability:'.CapabilityCatalog::PROGRAM_HEAD_VIEW, 'throttle:10,1'])
        ->name('program-head.applicants.result-sheet');
    Route::post('/staff/programs/{program:code}/applicants/{attempt}/decline', [ProgramHeadActionController::class, 'declineSelection'])
        ->middleware('capability:'.CapabilityCatalog::PROGRAM_HEAD_SELECTION_DECLINE)
        ->name('program-head.applicants.decline');
    Route::post('/staff/programs/{program:code}/follow-ups/{followUp}/resolve', [ProgramHeadActionController::class, 'resolveFollowUp'])
        ->middleware('capability:'.CapabilityCatalog::PROGRAM_HEAD_FOLLOW_UP_RESOLVE)
        ->name('program-head.follow-ups.resolve');
    Route::post('/staff/programs/{program:code}/applicants/{attempt}/threads', [ProgramHeadActionController::class, 'openInternalThread'])
        ->middleware(['capability:'.CapabilityCatalog::PROGRAM_HEAD_MESSAGE, 'throttle:30,1'])
        ->name('program-head.threads.open');
    Route::post('/threads/{thread}/messages', [CaseConversationController::class, 'sendMessage'])
        ->middleware(['capability:'.CapabilityCatalog::RECORD_THREAD_PARTICIPATE, 'throttle:30,1'])
        ->name('threads.messages.store');

    Route::post('/threads/{thread}/read', [CaseConversationController::class, 'markRead'])
        ->middleware(['capability:'.CapabilityCatalog::RECORD_THREAD_PARTICIPATE, 'throttle:60,1'])
        ->name('threads.read');

    Route::get('/case-conversations/{thread}', [CaseConversationController::class, 'show'])
        ->middleware(['capability:'.CapabilityCatalog::RECORD_THREAD_PARTICIPATE, 'throttle:60,1'])
        ->name('case-conversations.show');

    Route::post('/case-conversations/{thread}/messages', [CaseConversationController::class, 'sendMessage'])
        ->middleware(['capability:'.CapabilityCatalog::RECORD_THREAD_PARTICIPATE, 'throttle:30,1'])
        ->name('case-conversations.messages.store');

    Route::post('/case-conversations/{thread}/read', [CaseConversationController::class, 'markRead'])
        ->middleware(['capability:'.CapabilityCatalog::RECORD_THREAD_PARTICIPATE, 'throttle:60,1'])
        ->name('case-conversations.read');

    Route::post('/staff/programs/{program:code}/applicants/{attempt}/support-conversations', [CaseConversationController::class, 'openSupport'])
        ->middleware(['capability:'.CapabilityCatalog::PROGRAM_HEAD_MESSAGE, 'throttle:30,1'])
        ->name('case-conversations.applicant.open');

    // Registrar admission clearance & requirement verification (OFFICE duty).
    Route::post('/registrar/enrollment-eligibility/{attempt}/requirements/initialize', [AdmissionClearanceController::class, 'initializeRequirements'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_DOCUMENTS_VERIFY)
        ->name('registrar.clearance.requirements.initialize');
    Route::post('/registrar/enrollment-eligibility/{attempt}/documents/verify', [AdmissionClearanceController::class, 'verifyDocument'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_DOCUMENTS_VERIFY)
        ->name('registrar.clearance.documents.verify');
    Route::post('/registrar/enrollment-eligibility/{attempt}/documents/bulk-verify', [AdmissionClearanceController::class, 'bulkVerifyDocuments'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_DOCUMENTS_VERIFY)
        ->name('registrar.clearance.documents.bulk-verify');
    Route::post('/registrar/enrollment-eligibility/{attempt}/requirements/{requirementItem}/internal-notes', [AdmissionRequirementInternalNoteController::class, 'store'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_DOCUMENTS_VERIFY)
        ->whereNumber('attempt')
        ->whereNumber('requirementItem')
        ->name('registrar.clearance.requirements.internal-notes.store');
    Route::post('/registrar/enrollment-eligibility/{attempt}/requirements/internal-notes', [AdmissionRequirementInternalNoteController::class, 'store'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_DOCUMENTS_VERIFY)
        ->whereNumber('attempt')
        ->name('registrar.clearance.requirements.internal-notes.catalog.store');
    Route::post('/registrar/enrollment-eligibility/{attempt}/clearance/confirm', [AdmissionClearanceController::class, 'confirm'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_ADMISSION_CLEARANCE_CONFIRM)
        ->name('registrar.clearance.confirm');
    Route::post('/registrar/enrollment-eligibility/{attempt}/clearance/revoke', [AdmissionClearanceController::class, 'revoke'])
        ->middleware('capability:'.CapabilityCatalog::REGISTRAR_ADMISSION_CLEARANCE_REOPEN)
        ->name('registrar.clearance.revoke');

    // Universal applicant QR (D-092).
    Route::get('/staff/scan', [StaffQrController::class, 'scan'])
        ->middleware('capability:'.CapabilityCatalog::APPLICANT_QR_RESOLVE)
        ->name('staff.scan');
    Route::get('/staff/scan/search', [StaffQrController::class, 'search'])
        ->middleware(['capability:'.CapabilityCatalog::APPLICANT_QR_RESOLVE, 'throttle:60,1'])
        ->name('staff.scan.search');
    Route::get('/staff/scan/applicants/{attempt}/actions', [StaffQrController::class, 'actions'])
        ->middleware(['capability:'.CapabilityCatalog::APPLICANT_QR_RESOLVE, 'throttle:60,1'])
        ->whereNumber('attempt')
        ->name('staff.scan.actions');
    Route::post('/staff/scan/resolve', [StaffQrController::class, 'resolve'])
        ->middleware('capability:'.CapabilityCatalog::APPLICANT_QR_RESOLVE)
        ->name('staff.scan.resolve');
    Route::get('/applicant/qr', [ApplicantQrController::class, 'show'])
        ->middleware('capability:'.CapabilityCatalog::APPLICANT_PROGRAM_SELECT)
        ->name('applicant.qr');
    Route::get('/applicant/qr/print', [ApplicantQrController::class, 'print'])
        ->middleware('capability:'.CapabilityCatalog::APPLICANT_PROGRAM_SELECT)
        ->name('applicant.qr.print');
    Route::get('/applicant/admission-support', [ApplicantQrController::class, 'admissionSupport'])
        ->middleware('auth')
        ->name('applicant.admission-support');
    Route::get('/messages', function (Request $request) {
        $user = $request->user();
        if ($user !== null && $user->staffProfile()->exists()) {
            return redirect()->route('staff.inquiries.index');
        }

        return redirect()->route('applicant.admission-support');
    })->middleware('auth')->name('messages');
    Route::get('/applicant/messages', fn () => redirect()->route('applicant.admission-support'))
        ->middleware('auth')
        ->name('applicant.messages');

    Route::get('/applicant/results', [ApplicantResultController::class, 'show'])
        ->middleware('auth')
        ->name('applicant.results.show');
    Route::get('/applicant/results/pdf', [ApplicantResultController::class, 'downloadPdf'])
        ->middleware(['auth', 'throttle:10,1'])
        ->name('applicant.results.download-pdf');

    Route::get('/applicant/access-history', [ApplicantAuditLogController::class, 'index'])
        ->middleware('auth')
        ->name('applicant.access-history');

    Route::get('/ui-kit', function () {
        return Inertia::render('UiKit/Gallery');
    })->name('ui-kit.gallery');

    Route::middleware('capability:'.CapabilityCatalog::NOTIFY_READ_OWN)->prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('/{delivery}/read', [NotificationController::class, 'markRead'])->name('read');
        Route::post('/read-all', [NotificationController::class, 'readAll'])->name('read-all');
        Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
        Route::get('/push-subscriptions/vapid-key', [PushSubscriptionController::class, 'vapidKey'])->name('push-subscriptions.vapid-key');
        Route::put('/push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
        Route::delete('/push-subscriptions/{subscription}', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');
    });

    Route::get('/foundation/capability-probe', function () {
        return response()->json(['ok' => true, 'capability' => 'foundation.probe']);
    })->middleware('capability:foundation.probe')->name('foundation.capability-probe');

});

Route::middleware([
    EnsureDemoEnvironment::class,
    'auth',
    'capability:'.CapabilityCatalog::DEMO_STAGE_CONTROL,
])->prefix('demo')->name('demo.')->group(function (): void {
    Route::get('/preparation', [PracticePreparationController::class, 'index'])
        ->name('preparation.index');
    Route::post('/preparation/preview', [PracticePreparationController::class, 'preview'])
        ->name('preparation.preview');
    Route::post('/preparation/operations/{operation}/confirm', [PracticePreparationController::class, 'confirm'])
        ->whereUuid('operation')
        ->name('preparation.confirm');
});
// Clean route aliases for Day of Release and Printing of Results
Route::middleware(['auth', 'active.session', 'practice.workspace'])->group(function () {
    Route::get('/day-of-release', [ConsultationDashboardController::class, 'index'])->name('day-of-release.index');
    Route::get('/day-of-release/scheduling', [ConsultationSessionController::class, 'index'])->name('day-of-release.scheduling');
    Route::get('/printing-of-results', [ReleaseController::class, 'index'])->name('printing-of-results.index');
});

Route::get('/consultation/encounters/{encounter}', [ConsultationEncounterController::class, 'show'])
    ->middleware('auth')
    ->name('consultation.encounter.show');
Route::post('/consultation/applicants/{attempt}/online/start', OnlineConsultationStartController::class)
    ->middleware('auth')
    ->name('consultation.applicants.online.start');
Route::post('/consultation/encounters/{encounter}/token', [ConsultationMediaController::class, 'generateToken'])
    ->middleware('auth')
    ->name('consultation.encounter.token');
Route::post('/consultation/encounters/{encounter}/close', [ConsultationMediaController::class, 'closeEncounter'])
    ->middleware('auth')
    ->name('consultation.encounter.close');
Route::post('/consultation/internal/jitsi/admission', [ConsultationMediaController::class, 'admissionCallback'])
    ->withoutMiddleware(PreventRequestForgery::class)
    ->name('consultation.jitsi.admission');
Route::post('/consultation/internal/jitsi/presence', [ConsultationMediaController::class, 'presenceCallback'])
    ->withoutMiddleware(PreventRequestForgery::class)
    ->name('consultation.jitsi.presence');

// =========================================================================
// Research: Isolated TAM Evaluation (Dedicated SQLite DB, Zero Postgres touch)
// =========================================================================
// Public instrument & responses for First-Year College Students
Route::get('/tam-evaluation/first-years', [TamEvaluationController::class, 'create'])->name('tam.first-years');
Route::get('/tam/first-years', fn () => redirect()->route('tam.first-years'));
Route::post('/tam-answers/first-years', [TamEvaluationController::class, 'store'])->name('tam.first-years.store');
Route::get('/tam-answers/first-years', fn () => redirect()->route('tam.first-years'));

// Management review & CSV export (Staff authenticated or ?key= access)
Route::get('/staff/tam-responses', [TamEvaluationController::class, 'admin'])->name('staff.tam-responses.index');
Route::get('/staff/tam-responses/export', [TamEvaluationController::class, 'exportCsv'])->name('staff.tam-responses.export');
Route::get('/tam-responses', [TamEvaluationController::class, 'admin'])->name('tam.responses.index');
Route::get('/tam-responses/export', [TamEvaluationController::class, 'exportCsv'])->name('tam.responses.export');
Route::post('/staff/tam-responses/reset', [TamEvaluationController::class, 'reset'])->name('staff.tam-responses.reset');
Route::post('/tam-responses/reset', [TamEvaluationController::class, 'reset'])->name('tam.responses.reset');

// Dev-only capstone workspace: roadmap, client brief review surface + separate SQLite store.
// Ungated on purpose (dev + test only); the routes and the `docs` SQLite
// connection do not exist outside the local/testing environments.
if (app()->environment(['local', 'testing'])) {

    Route::get('/capstone/status', FoundationStatusController::class)->name('capstone.status');
    Route::get('/capstone/demo-guide', [DemoGuideController::class, 'index'])
        ->middleware(EnsureDemoEnvironment::class)
        ->name('capstone.demo-guide');
    Route::get('/capstone/glossary', GlossaryController::class)->name('capstone.glossary');
    Route::get('/capstone/client-brief', fn () => view('capstone.client-brief'))->name('capstone.client-brief');
    Route::get('/capstone/office-guide', fn () => response()->file(public_path('capstone-office-field-guide.html')))->name('capstone.office-guide');
    Route::get('/capstone/prototype', fn () => response()->file(public_path('capstone-admission-support-prototype.html')))->name('capstone.prototype');
    Route::get('/capstone/docs/state', [DocsStateController::class, 'index'])->name('capstone.docs.state');
    Route::post('/capstone/docs/state', [DocsStateController::class, 'store'])->name('capstone.docs.state.store');
}

Route::middleware('auth')->group(function (): void {
    Route::middleware('throttle:60,1')->group(function (): void {
        Route::get('/applicant/inquiries', [ApplicantOfficeInquiryController::class, 'applicantIndex'])->name('applicant.inquiries.index');
        Route::get('/applicant/inquiries/{inquiry}', [ApplicantOfficeInquiryController::class, 'applicantShow'])->name('applicant.inquiries.show');
        Route::get('/staff/inquiries', [ApplicantOfficeInquiryController::class, 'staffIndex'])->name('staff.inquiries.index');
        Route::get('/staff/inquiries/case-applicants', [CaseConversationController::class, 'applicants'])
            ->middleware('capability:'.CapabilityCatalog::PROGRAM_HEAD_MESSAGE)
            ->name('staff.inquiries.case-applicants');
        Route::get('/staff/inquiries/{inquiry}', [ApplicantOfficeInquiryController::class, 'staffShow'])->name('staff.inquiries.show');
        Route::get('/inquiries/{inquiry}/snapshot', [ApplicantOfficeInquiryController::class, 'snapshot'])->name('inquiries.snapshot');
    });
    Route::middleware('throttle:30,1')->group(function (): void {
        Route::post('/applicant/inquiries', [ApplicantOfficeInquiryController::class, 'store'])->name('applicant.inquiries.store');
        Route::post('/inquiries/{inquiry}/messages', [ApplicantOfficeInquiryController::class, 'send'])->name('inquiries.messages.store');
        Route::post('/inquiries/{inquiry}/read', [ApplicantOfficeInquiryController::class, 'read'])->name('inquiries.read');
        Route::post('/inquiries/{inquiry}/resolve', [ApplicantOfficeInquiryController::class, 'resolve'])->name('inquiries.resolve');
    });
});

require __DIR__.'/walkthrough.php';
