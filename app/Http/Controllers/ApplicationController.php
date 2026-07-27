<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesAcademicYear;
use App\Http\Requests\DismissApplicationRequest;
use App\Http\Requests\StoreApplicationRequest;
use App\Http\Requests\UpdateApplicationRequest;
use App\Jobs\SendApplicantSetupEmail;
use App\Models\AcademicYear;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Appointment;
use App\Models\Course;
use App\Notifications\ApplicationStatusChanged;
use App\Services\AdmissionSlipService;
use App\Services\ApplicationPipelineService;
use App\Services\AuditService;
use App\Services\PrintTemplateCssService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicationController extends Controller
{
    use ResolvesAcademicYear;

    /**
     * List applications with filters. Per 08-API-SPEC-PHASE1 and 09-UI-ROUTES-PHASE1.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Application::class);

        [$activeAcademicYear, $queryAcademicYearId] = $this->resolveAcademicYear($request);

        $query = Application::query()
            ->with([
                'coursePreference1:id,name,code',
                'coursePreference2:id,name,code',
                'coursePreference3:id,name,code',
                'academicYear:id,academic_year,semester',
            ]);

        if ($queryAcademicYearId !== null) {
            $query->forAcademicYear($queryAcademicYearId);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', '%'.$search.'%')
                    ->orWhere('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('submitted_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('submitted_at', '<=', $dateTo);
        }

        $pipelineStatus = $request->input('pipeline_status');
        $sortField = $request->input('sort', 'submitted_at');
        $sortDirection = $request->input('direction', 'desc');

        $transformApp = function (Application $app) {
            $parts = array_filter([$app->first_name, $app->middle_name, $app->last_name, $app->suffix]);
            $fullName = implode(' ', $parts);
            $courses = [
                ['rank' => 1, 'course' => $app->coursePreference1 ? ['id' => $app->coursePreference1->id, 'code' => $app->coursePreference1->code, 'name' => $app->coursePreference1->name] : null],
                ['rank' => 2, 'course' => $app->coursePreference2 ? ['id' => $app->coursePreference2->id, 'code' => $app->coursePreference2->code, 'name' => $app->coursePreference2->name] : null],
                ['rank' => 3, 'course' => $app->coursePreference3 ? ['id' => $app->coursePreference3->id, 'code' => $app->coursePreference3->code, 'name' => $app->coursePreference3->name] : null],
            ];

            return [
                'id' => $app->id,
                'reference_number' => $app->reference_number,
                'full_name' => $fullName,
                'email' => $app->email,
                'status' => $app->status,
                'pipeline_status' => $app->pipeline_status ?? 'pending',
                'submitted_at' => $app->submitted_at?->toIso8601String(),
                'course_preferences' => $courses,
            ];
        };

        // Native DB pipeline_status filter and sort — no in-memory collection processing
        // pipeline_status is nullable; NULL semantically equals 'pending' (pre-backfill rows).
        if ($pipelineStatus === 'pending') {
            $query->where(function ($q) {
                $q->where('pipeline_status', 'pending')
                    ->orWhereNull('pipeline_status');
            });
        } elseif ($pipelineStatus) {
            $query->where('pipeline_status', $pipelineStatus);
        }

        $sortableColumns = ['submitted_at', 'pipeline_status', 'last_name', 'first_name'];
        $resolvedSort = in_array($sortField, $sortableColumns, true) ? $sortField : 'submitted_at';
        $resolvedDir = $sortDirection === 'asc' ? 'asc' : 'desc';

        // Coalesce NULL pipeline_status to 'pending' for correct sort order
        if ($resolvedSort === 'pipeline_status') {
            $query->orderByRaw("COALESCE(pipeline_status, 'pending') {$resolvedDir}");
        } else {
            $query->orderBy($resolvedSort, $resolvedDir);
        }

        $applications = $query
            ->paginate(15)
            ->withQueryString();

        $applications->getCollection()->transform($transformApp);

        return Inertia::render('Applications/Index', [
            'applications' => $applications,
            'filters' => $request->only(['search', 'pipeline_status', 'date_from', 'date_to', 'academic_year_id']),
            'seasons' => $this->academicYearOptions(),
            'active_season_id' => $activeAcademicYear?->id,
            'admission_slip_enabled' => AdmissionSlipService::isEnabled(),
            'statuses' => [
                ['value' => 'pending', 'label' => 'Pending'],
                ['value' => 'accepted', 'label' => 'Accepted'],
                ['value' => 'draft_scheduled', 'label' => 'Draft Scheduled'],
                ['value' => 'scheduled', 'label' => 'Scheduled'],
                ['value' => 'printed', 'label' => 'Printed'],
                ['value' => 'attended', 'label' => 'Attended'],
                ['value' => 'submitted', 'label' => 'Submitted'],
                ['value' => 'scored', 'label' => 'Scored'],
                ['value' => 'graded', 'label' => 'Graded'],
                ['value' => 'released', 'label' => 'Released'],
                ['value' => 'dismissed', 'label' => 'Dismissed'],
            ],
        ]);
    }

    /**
     * Show single application. Per 08-API-SPEC-PHASE1 and 09-UI-ROUTES-PHASE1.
     */
    public function show(Application $application): Response
    {
        $this->authorize('view', $application);

        $application->load([
            'coursePreference1:id,name,code',
            'coursePreference2:id,name,code',
            'coursePreference3:id,name,code',
            'appointment',
            'academicYear:id,application_start_date,application_end_date',
            'applicant.examSessions',
            'applicant.applicantScores',
            'applicant.consultationSummary:id,applicant_id,status,released_at',
        ]);

        $courses = $this->getCourses();

        $appointmentLabel = null;
        if ($application->appointment) {
            $apt = $application->appointment;
            $appointmentLabel = $apt->date->format('Y-m-d').' '.substr($apt->time_slot, 0, 5);
        }

        $academicYear = $application->academicYear;
        $withinApplicationWindow = $academicYear?->isApplicationWindowOpen() ?? false;
        $applicationWindowLabel = $academicYear?->applicationWindowLabel() ?? null;

        $applicationData = [
            'id' => $application->id,
            'reference_number' => $application->reference_number,
            'first_name' => $application->first_name,
            'middle_name' => $application->middle_name,
            'last_name' => $application->last_name,
            'suffix' => $application->suffix,
            'birthdate' => $application->birthdate->format('Y-m-d'),
            'age' => $application->age,
            'sex' => $application->sex,
            'email' => $application->email,
            'phone' => $application->phone,
            'address_line' => $application->address_line,
            'city' => $application->city,
            'province' => $application->province,
            'zip_code' => $application->zip_code,
            'gwa' => $application->gwa,
            'applicant_type' => $application->applicant_type ?? 'new',
            'last_school_enrolled' => $application->last_school_enrolled,
            'course_preference_1' => $application->course_preference_1,
            'course_preference_2' => $application->course_preference_2,
            'course_preference_3' => $application->course_preference_3,
            'course_preference_1_label' => $application->coursePreference1?->name,
            'course_preference_2_label' => $application->coursePreference2?->name,
            'course_preference_3_label' => $application->coursePreference3?->name,
            'status' => $application->status,
            'processed_at' => $application->processed_at?->toIso8601String(),
            'rejection_reason' => $application->rejection_reason,
            'appointment_id' => $application->appointment_id,
            'appointment_label' => $appointmentLabel,
            'submitted_at' => $application->submitted_at?->toIso8601String(),
            'created_at' => $application->created_at?->toIso8601String(),
        ];

        $milestones = $application->pipeline_milestones ?? [];
        $pipelineStatus = $application->pipeline_status ?? 'pending';
        $isF2f = isset($milestones['scheduled']) || isset($milestones['printed']) || isset($milestones['attended']);
        $isDirect = isset($milestones['scored']) && ! $isF2f;
        $pipelineDetails = [
            'status' => $pipelineStatus,
            'milestones' => $milestones,
            'is_f2f' => $isF2f,
            'is_direct' => $isDirect,
        ];

        return Inertia::render('Applications/Show', [
            'application' => $applicationData,
            'courses' => $courses,
            'within_application_window' => $withinApplicationWindow,
            'application_window_label' => $applicationWindowLabel,
            'pipeline_status' => $pipelineStatus,
            'pipeline_details' => $pipelineDetails,
        ]);
    }

    public function create(): Response
    {
        $activeAcademicYear = AcademicYear::active();
        $courses = $this->getCourses();
        $appointments = $this->getAppointments();

        // Public users: check application window; staff (authenticated): bypass window check
        $allowApply = auth()->check()
            ? true
            : ($activeAcademicYear !== null && $activeAcademicYear->isApplicationWindowOpen());

        // Check if user is staff (for showing auto-accept toggle)
        $isStaff = auth()->check() && auth()->user()->hasAnyRole(['super_admin', 'staff', 'registrar_administrator']);

        return Inertia::render('Applications/Apply', [
            'courses' => $courses,
            'appointments' => $appointments,
            'active_season' => $activeAcademicYear ? ['id' => $activeAcademicYear->id, 'academic_year' => $activeAcademicYear->academic_year, 'semester' => $activeAcademicYear->semester, 'semester_label' => $activeAcademicYear->semesterLabel()] : null,
            'allow_apply' => $allowApply,
            'is_staff' => $isStaff,
        ]);
    }

    /**
     * Staff create application on behalf of applicant - bypasses application window.
     */
    public function storeAdmin(StoreApplicationRequest $request): RedirectResponse
    {
        $this->authorize('create', Application::class);

        $activeAcademicYear = AcademicYear::active();

        $validated = $request->validated();

        $referenceNumber = Application::nextReferenceNumber();

        // Identity-based duplicate check (name + birthdate + sex)
        $identityDuplicate = null;
        if (!empty($validated['birthdate']) && !empty($validated['sex'])) {
            $identityDuplicate = Application::where('academic_year_id', $activeAcademicYear->id)
                ->whereRaw('LOWER(first_name) = ?', [strtolower($validated['first_name'])])
                ->whereRaw('LOWER(last_name) = ?', [strtolower($validated['last_name'])])
                ->where('birthdate', $validated['birthdate'])
                ->where('sex', $validated['sex'])
                ->first();
        }

        if ($identityDuplicate) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', "A person with the same name, birthdate, and sex already has an application ({$identityDuplicate->reference_number}) for this academic year.");
        }

        $birthdateVal = !empty($validated['birthdate']) ? $validated['birthdate'] : '2005-01-01';
        $birthdate = Carbon::parse($birthdateVal);
        $age = (int) $birthdate->diffInYears(now());

        // Email fallback
        $email = $validated['email'] ?? null;
        if (empty($email)) {
            $safeFirstName = preg_replace('/[^a-z0-9]/', '', strtolower($validated['first_name']));
            $safeLastName = preg_replace('/[^a-z0-9]/', '', strtolower($validated['last_name']));
            $email = "{$safeFirstName}.{$safeLastName}.{$referenceNumber}@securecat.local";
        }

        $fallbackCourseId = Course::where('is_active', true)->first()?->id ?? Course::first()?->id;

        // Handle auto-accept toggle
        $acceptImmediately = $validated['accept_immediately'] ?? false;
        $status = $acceptImmediately ? 'accepted' : ($validated['status'] ?? 'pending');

        $application = Application::create([
            'academic_year_id' => $activeAcademicYear->id,
            'reference_number' => $referenceNumber,
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'],
            'suffix' => $validated['suffix'] ?? null,
            'birthdate' => $birthdateVal,
            'age' => $age,
            'sex' => $validated['sex'] ?? 'male',
            'applicant_type' => $validated['applicant_type'] ?? 'new',
            'last_school_enrolled' => $validated['last_school_enrolled'] ?? null,
            'email' => $email,
            'phone' => $validated['phone'] ?? null,
            'address_line' => $validated['address_line'] ?? null,
            'city' => $validated['city'] ?? null,
            'province' => $validated['province'] ?? null,
            'zip_code' => $validated['zip_code'] ?? null,
            'gwa' => $validated['gwa'] ?? null,
            'course_preference_1' => $validated['course_preference_1'] ?? $fallbackCourseId,
            'course_preference_2' => ! empty($validated['course_preference_2']) ? (int) $validated['course_preference_2'] : null,
            'course_preference_3' => ! empty($validated['course_preference_3']) ? (int) $validated['course_preference_3'] : null,
            'status' => $status,
            'appointment_id' => $validated['appointment_id'] ?? null,
            'submitted_at' => now(),
            'processed_by' => auth()->id(),
            'processed_at' => now(),
        ]);

        // If auto-accept is enabled, safely create or re-link applicant
        if ($acceptImmediately) {
            $this->ensureApplicantForAcceptance($application);
        }

        if (! empty($validated['appointment_id'])) {
            Appointment::where('id', $validated['appointment_id'])->increment('booked_count');
        }

        app(AuditService::class)->log('application.created_by_staff', Application::class, $application->id, [], [
            'reference_number' => $application->reference_number,
            'accept_immediately' => $acceptImmediately,
        ]);

        // Pipeline hook: set initial accepted status when auto-accepted
        if ($acceptImmediately) {
            app(ApplicationPipelineService::class)->transition($application->fresh(), 'accepted');
        }

        return redirect()
            ->route('admin.applications.admin-show', $application)
            ->with('success', 'Application created successfully.');
    }

    /**
     * Staff edit application form.
     */
    public function edit(Application $application): Response
    {
        $this->authorize('create', Application::class);

        $activeAcademicYear = AcademicYear::active();
        $courses = $this->getCourses();
        $appointments = $this->getAppointments();

        $application->load(['coursePreference1', 'coursePreference2', 'coursePreference3', 'appointment', 'academicYear']);

        return Inertia::render('Admin/Applications/Edit', [
            'application' => [
                'id' => $application->id,
                'reference_number' => $application->reference_number,
                'first_name' => $application->first_name,
                'middle_name' => $application->middle_name,
                'last_name' => $application->last_name,
                'suffix' => $application->suffix,
                'birthdate' => $application->birthdate->format('Y-m-d'),
                'sex' => $application->sex,
                'applicant_type' => $application->applicant_type ?? 'new',
                'last_school_enrolled' => $application->last_school_enrolled,
                'email' => $application->email,
                'phone' => $application->phone,
                'address_line' => $application->address_line,
                'city' => $application->city,
                'province' => $application->province,
                'zip_code' => $application->zip_code,
                'gwa' => $application->gwa,
                'course_preference_1' => $application->course_preference_1,
                'course_preference_2' => $application->course_preference_2,
                'course_preference_3' => $application->course_preference_3,
                'status' => $application->status,
                'rejection_reason' => $application->rejection_reason,
                'appointment_id' => $application->appointment_id,
                'submitted_at' => $application->submitted_at?->toIso8601String(),
            ],
            'courses' => $courses,
            'appointments' => $appointments,
            'active_season' => $activeAcademicYear ? ['id' => $activeAcademicYear->id, 'academic_year' => $activeAcademicYear->academic_year, 'semester' => $activeAcademicYear->semester, 'semester_label' => $activeAcademicYear->semesterLabel()] : null,
            'statuses' => [
                ['value' => 'pending', 'label' => 'Pending'],
                ['value' => 'accepted', 'label' => 'Accepted'],
                ['value' => 'dismissed', 'label' => 'Dismissed'],
            ],
        ]);
    }

    /**
     * Staff update application - bypasses application window.
     */
    public function updateAdmin(UpdateApplicationRequest $request, Application $application): RedirectResponse
    {
        $validated = $request->validated();

        // Handle appointment changes
        $oldAppointmentId = $application->appointment_id;
        $newAppointmentId = $validated['appointment_id'] ?? null;

        if ($oldAppointmentId && $oldAppointmentId !== $newAppointmentId) {
            Appointment::where('id', $oldAppointmentId)->where('booked_count', '>', 0)->decrement('booked_count');
        }
        if ($newAppointmentId && $newAppointmentId !== $oldAppointmentId) {
            Appointment::where('id', $newAppointmentId)->increment('booked_count');
        }

        // Build update data - only include provided fields
        $updateData = [];
        $fillable = [
            'first_name', 'middle_name', 'last_name', 'suffix', 'birthdate',
            'sex', 'applicant_type', 'last_school_enrolled',
            'email', 'phone', 'address_line', 'city', 'province', 'zip_code',
            'gwa',
            'course_preference_1', 'course_preference_2', 'course_preference_3',
            'appointment_id', 'status', 'rejection_reason',
        ];

        foreach ($fillable as $field) {
            if (array_key_exists($field, $validated)) {
                $updateData[$field] = $validated[$field];
            }
        }

        // Update age if birthdate changed
        if (isset($updateData['birthdate'])) {
            $updateData['age'] = (int) Carbon::parse($updateData['birthdate'])->diffInYears(now());
        }

        $updateData['processed_by'] = auth()->id();
        $updateData['processed_at'] = now();

        $application->update($updateData);

        app(AuditService::class)->log('application.updated', Application::class, $application->id, [], [
            'reference_number' => $application->reference_number,
            'changes' => array_keys($updateData),
        ]);

        return redirect()
            ->route('admin.applications.admin-show', $application)
            ->with('success', 'Application updated successfully.');
    }

    public function store(StoreApplicationRequest $request): RedirectResponse
    {
        $activeAcademicYear = AcademicYear::active();
        if ($activeAcademicYear === null || ! $activeAcademicYear->isApplicationWindowOpen()) {
            abort(422, 'The application window is currently closed. Please try again later or contact the office.');
        }

        $validated = $request->validated();

        // Identity-based duplicate check (name + birthdate + sex)
        $identityDuplicate = null;
        if (!empty($validated['birthdate']) && !empty($validated['sex'])) {
            $identityDuplicate = Application::where('academic_year_id', $activeAcademicYear->id)
                ->whereRaw('LOWER(first_name) = ?', [strtolower($validated['first_name'])])
                ->whereRaw('LOWER(last_name) = ?', [strtolower($validated['last_name'])])
                ->where('birthdate', $validated['birthdate'])
                ->where('sex', $validated['sex'])
                ->first();
        }

        if ($identityDuplicate) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', "A person with the same name, birthdate, and sex already has an application ({$identityDuplicate->reference_number}) for this academic year.");
        }

        $birthdateVal = !empty($validated['birthdate']) ? $validated['birthdate'] : '2005-01-01';
        $birthdate = Carbon::parse($birthdateVal);
        $age = (int) $birthdate->diffInYears(now());

        $referenceNumber = Application::nextReferenceNumber();

        // Email fallback
        $email = $validated['email'] ?? null;
        if (empty($email)) {
            $safeFirstName = preg_replace('/[^a-z0-9]/', '', strtolower($validated['first_name']));
            $safeLastName = preg_replace('/[^a-z0-9]/', '', strtolower($validated['last_name']));
            $email = "{$safeFirstName}.{$safeLastName}.{$referenceNumber}@securecat.local";
        }

        $fallbackCourseId = Course::where('is_active', true)->first()?->id ?? Course::first()?->id;

        $application = Application::create([
            'academic_year_id' => $activeAcademicYear->id,
            'reference_number' => $referenceNumber,
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'],
            'suffix' => $validated['suffix'] ?? null,
            'birthdate' => $birthdateVal,
            'age' => $age,
            'sex' => $validated['sex'] ?? 'male',
            'applicant_type' => $validated['applicant_type'] ?? 'new',
            'last_school_enrolled' => $validated['last_school_enrolled'] ?? null,
            'email' => $email,
            'phone' => $validated['phone'] ?? null,
            'address_line' => $validated['address_line'] ?? null,
            'city' => $validated['city'] ?? null,
            'province' => $validated['province'] ?? null,
            'zip_code' => $validated['zip_code'] ?? null,
            'gwa' => $validated['gwa'] ?? null,
            'course_preference_1' => $validated['course_preference_1'] ?? $fallbackCourseId,
            'course_preference_2' => ! empty($validated['course_preference_2']) ? (int) $validated['course_preference_2'] : null,
            'course_preference_3' => ! empty($validated['course_preference_3']) ? (int) $validated['course_preference_3'] : null,
            'status' => 'pending',
            'appointment_id' => $validated['appointment_id'] ?? null,
            'submitted_at' => now(),
        ]);

        if (! empty($validated['appointment_id'])) {
            Appointment::where('id', $validated['appointment_id'])->increment('booked_count');
        }

        $appointmentDetails = null;
        if ($application->appointment_id) {
            $apt = $application->appointment;
            $appointmentDetails = $apt ? $apt->date->format('Y-m-d').' '.substr($apt->time_slot, 0, 5) : null;
        }

        return redirect()
            ->route('applications.success')
            ->with('reference_number', $application->reference_number)
            ->with('appointment_details', $appointmentDetails);
    }

    public function success(Request $request): Response
    {
        return Inertia::render('Applications/Success', [
            'reference_number' => $request->session()->get('reference_number', 'APP-MOCK-00001'),
            'appointment_details' => $request->session()->get('appointment_details'),
        ]);
    }

    /**
     * Accept application. Per 08-API-SPEC-PHASE1: create applicant, send setup email, audit.
     * Allowed from pending or dismissed only when within application window.
     */
    public function accept(Application $application): RedirectResponse
    {
        $this->authorize('accept', $application);

        $allowedStatuses = ['pending', 'dismissed'];
        if (! in_array($application->status, $allowedStatuses, true)) {
            return redirect()
                ->back()
                ->with('error', 'Application has already been accepted.');
        }

        $withinWindow = $application->academicYear?->isApplicationWindowOpen() ?? false;
        if (! $withinWindow) {
            return redirect()
                ->back()
                ->with('error', 'Status can only be changed while the application window is open.');
        }

        DB::transaction(function () use ($application) {
            $oldStatus = $application->status;
            $application->update([
                'status' => 'accepted',
                'processed_by' => auth()->id(),
                'processed_at' => now(),
            ]);

            $this->ensureApplicantForAcceptance($application);

            // Notify applicant of status change
            if ($oldStatus !== 'accepted') {
                $application->applicant?->notify(new ApplicationStatusChanged($application, $oldStatus, 'accepted'));
            }

            // Pipeline hook: advance to accepted
            app(ApplicationPipelineService::class)->transition($application->fresh(), 'accepted');

            app(AuditService::class)->log('application.accepted', Application::class, $application->id, [], [
                'reference_number' => $application->reference_number,
                'processed_by' => auth()->id(),
            ]);
        });

        return redirect()
            ->route('admin.applications.admin-show', $application)
            ->with('success', 'Application accepted. Setup email has been sent to the applicant.');
    }

    /**
     * Resend portal setup email for an accepted application (e.g. applicant didn't receive it).
     */
    public function resendSetupEmail(Application $application): RedirectResponse
    {
        $this->authorize('resendSetupEmail', $application);

        if ($application->status !== 'accepted') {
            return redirect()
                ->back()
                ->with('error', 'Only accepted applications can have the setup email resent.');
        }

        $applicant = Applicant::where('application_id', $application->id)->first();

        if (! $applicant) {
            return redirect()
                ->back()
                ->with('error', 'No applicant record found for this application.');
        }

        if ($applicant->hasCompletedSetup()) {
            return redirect()
                ->route('admin.applications.admin-show', $application)
                ->with('success', 'This applicant has already set up their portal account. They can sign in at the portal.');
        }

        $applicant->setup_token = Applicant::generateSetupToken();
        $applicant->setup_token_expires_at = now()->addHours(config('auth.setup_token_expires_hours', 72));
        $applicant->save();

        SendApplicantSetupEmail::dispatch($applicant);

        app(AuditService::class)->log('application.setup_email_resent', Application::class, $application->id, [], [
            'reference_number' => $application->reference_number,
        ]);

        return redirect()
            ->route('admin.applications.admin-show', $application)
            ->with('success', 'Portal setup email has been resent to the applicant.');
    }

    /**
     * Dismiss application. Allowed only when within application window.
     */
    public function dismiss(DismissApplicationRequest $request, Application $application): RedirectResponse
    {
        $withinWindow = $application->academicYear?->isApplicationWindowOpen() ?? false;
        if (! $withinWindow) {
            return redirect()
                ->back()
                ->with('error', 'Dismiss is only allowed while the application window is open.');
        }

        $allowedStatuses = ['pending'];
        if (! in_array($application->status, $allowedStatuses, true)) {
            return redirect()
                ->back()
                ->with('error', 'Application cannot be dismissed from its current status.');
        }

        if ($application->appointment_id) {
            Appointment::where('id', $application->appointment_id)->where('booked_count', '>', 0)->decrement('booked_count');
        }

        $oldStatus = $application->status;
        $application->update([
            'status' => 'dismissed',
            'rejection_reason' => $request->validated('reason'),
            'processed_by' => auth()->id(),
            'processed_at' => now(),
        ]);

        // Notify applicant of status change
        $application->applicant?->notify(new ApplicationStatusChanged($application, $oldStatus, 'dismissed'));

        // Pipeline hook: mark dismissed (always overrides)
        app(ApplicationPipelineService::class)->transition($application->fresh(), 'dismissed');

        app(AuditService::class)->log('application.dismissed', Application::class, $application->id, [], [
            'reference_number' => $application->reference_number,
            'reason' => $request->validated('reason'),
        ]);

        return redirect()
            ->route('admin.applications.admin-show', $application)
            ->with('success', 'Application has been dismissed.');
    }

    /**
     * Download admission slip PDF. Per 08-API-SPEC-PHASE1: 403 if not accepted.
     */
    public function admissionSlip(Application $application): StreamedResponse|\Illuminate\Http\Response
    {
        $this->authorize('admissionSlip', $application);

        if ($application->status !== 'accepted') {
            abort(403, 'Admission slip is only available for accepted applications.');
        }

        $pdf = app(AdmissionSlipService::class)->generatePdf($application);
        $filename = "admission-slip-{$application->reference_number}.pdf";

        return $pdf->download($filename);
    }

    public function admissionSlipPrint(Application $application): Response
    {
        $this->authorize('admissionSlip', $application);

        if (! AdmissionSlipService::isEnabled()) {
            abort(403, 'Admission slip distribution is not enabled.');
        }

        if ($application->status !== 'accepted') {
            abort(403, 'Admission slip is only available for accepted applications.');
        }

        $service = app(AdmissionSlipService::class);
        $templateHtml = $service->renderHtml($application);

        $application->load(['applicant', 'coursePreference1', 'coursePreference2', 'coursePreference3']);

        return Inertia::render('Applications/AdmissionSlipSingle', [
            'applicationId' => (string) $application->id,
            'printed' => (bool) $application->admission_slip_printed_at,
            'applicant' => [
                'id' => $application->applicant?->id ?? null,
                'name' => trim(implode(' ', array_filter([$application->first_name, $application->middle_name, $application->last_name, $application->suffix]))),
                'reference' => $application->reference_number,
            ],
            'templateHtml' => $templateHtml,
            'templateError' => null,
            'paperSize' => 'a4',
            'orientation' => 'portrait',
            'logicalUnit' => 'full',
            'paperOptions' => [],
        ]);
    }

    public function admissionSlipBulkPrint(Request $request): Response
    {
        if (! AdmissionSlipService::isEnabled()) {
            abort(403, 'Admission slip distribution is not enabled.');
        }

        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];

        $applications = Application::query()
            ->whereIn('id', $ids)
            ->where('status', 'accepted')
            ->whereHas('applicant')
            ->with(['applicant', 'coursePreference1', 'coursePreference2', 'coursePreference3'])
            ->orderBy('reference_number')
            ->get();

        $service = app(AdmissionSlipService::class);
        $sheetsHtml = [];
        $applicantsData = [];

        foreach ($applications as $app) {
            $sheetsHtml[] = $service->renderHtml($app);
            $applicantsData[] = [
                'id' => $app->id,
                'name' => trim(implode(' ', array_filter([$app->first_name, $app->middle_name, $app->last_name, $app->suffix]))) ?: '—',
                'reference' => $app->reference_number ?? '—',
            ];
        }

        $flatPaperOptions = ['a4' => 'A4', 'legal' => 'Legal', 'letter' => 'Letter'];

        return Inertia::render('Applications/AdmissionSlipBulk', [
            'applicationIds' => $ids,
            'applicants' => $applicantsData,
            'sheetsHtml' => $sheetsHtml,
            'templateError' => null,
            'paperSize' => 'a4',
            'orientation' => 'portrait',
            'logicalUnit' => 'full',
            'paperOptions' => $flatPaperOptions,
        ]);
    }

    public function admissionSlipBulkPdf(Request $request): StreamedResponse
    {
        if (! AdmissionSlipService::isEnabled()) {
            abort(403, 'Admission slip distribution is not enabled.');
        }

        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];

        $applications = Application::query()
            ->whereIn('id', $ids)
            ->where('status', 'accepted')
            ->whereHas('applicant')
            ->orderBy('reference_number')
            ->get();

        $service = app(AdmissionSlipService::class);
        $pdfHtml = '';

        foreach ($applications as $app) {
            $pdfHtml .= $service->renderHtml($app);
            $pdfHtml .= '<div style="page-break-after: always;"></div>';
        }

        $pdf = \Pdf::loadHTML(app(PrintTemplateCssService::class)->wrapForPdf(rtrim($pdfHtml, '<div style="page-break-after: always;"></div>')));

        return $pdf->download('admission-slips-bulk.pdf');
    }

    /**
     * Bulk accept pending applications. Non-pending rows are silently skipped.
     */
    public function bulkAccept(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];

        $applications = Application::whereIn('id', $ids)
            ->where('status', 'pending')
            ->get();

        $emailFailures = 0;

        foreach ($applications as $application) {
            $oldStatus = $application->status;
            $application->update([
                'status' => 'accepted',
                'processed_by' => auth()->id(),
                'processed_at' => now(),
            ]);

            // Safely create or re-link applicant (same as single accept method)
            $this->ensureApplicantForAcceptance($application);

            // Notify applicant of status change — don't let mail failures crash acceptance
            try {
                $application->applicant?->notify(new ApplicationStatusChanged($application, $oldStatus, 'accepted'));
            } catch (\Throwable $e) {
                $emailFailures++;
                \Log::warning('Bulk accept: failed to send status notification', [
                    'application_id' => $application->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Pipeline hook: advance to accepted
            app(ApplicationPipelineService::class)->transition($application->fresh(), 'accepted');
        }

        app(AuditService::class)->log('application.bulk_accepted', null, null, [], [
            'total' => count($ids),
            'accepted' => $applications->count(),
        ], "Bulk accepted {$applications->count()} applications");

        if ($emailFailures > 0) {
            return back()->with('warning', "Selected applications accepted. However, {$emailFailures} notification email(s) could not be sent — they will need to be resent.");
        }

        return back()->with('success', 'Selected applications accepted. Setup emails have been sent.');
    }

    /**
     * Bulk dismiss pending applications. Non-pending rows are silently skipped.
     */
    public function bulkDismiss(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];

        $applications = Application::whereIn('id', $ids)
            ->where('status', 'pending')
            ->get();

        foreach ($applications as $application) {
            $oldStatus = $application->status;
            $application->update([
                'status' => 'dismissed',
                'processed_by' => auth()->id(),
                'processed_at' => now(),
            ]);

            // Notify applicant of status change
            $application->applicant?->notify(new ApplicationStatusChanged($application, $oldStatus, 'dismissed'));

            // Pipeline hook: mark dismissed
            app(ApplicationPipelineService::class)->transition($application->fresh(), 'dismissed');
        }

        app(AuditService::class)->log('application.bulk_dismissed', null, null, [], [
            'total' => count($ids),
            'dismissed' => $applications->count(),
        ], "Bulk dismissed {$applications->count()} applications");

        return back()->with('success', 'Selected applications dismissed.');
    }

    /**
     * Bulk revert accepted/dismissed applications back to pending.
     */
    public function bulkReopen(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];

        $applications = Application::whereIn('id', $ids)
            ->whereIn('status', ['accepted', 'dismissed'])
            ->get();

        $count = 0;
        foreach ($applications as $application) {
            $application->update([
                'status' => 'pending',
                'processed_by' => null,
                'processed_at' => null,
            ]);

            // Delete applicant record if exists (will be recreated if accepted again)
            $application->applicant?->delete();

            // Pipeline hook: force-reset pipeline status back to pending
            app(ApplicationPipelineService::class)->forceSet($application->fresh(), 'pending');
            $count++;
        }

        app(AuditService::class)->log('application.bulk_reopened', null, null, [], [
            'count' => $count,
        ], "Bulk reopened {$count} applications");

        return back()->with('success', "{$count} application(s) reverted to pending.");
    }

    /**
     * Revert application status to pending. Allowed from accepted or dismissed.
     * Gated: application window of the linked academic year must still be open.
     */
    public function reopen(Application $application): RedirectResponse
    {
        $this->authorize('update', $application);

        $allowedStatuses = ['accepted', 'dismissed'];
        if (! in_array($application->status, $allowedStatuses, true)) {
            return back()->withErrors(['error' => 'Only accepted or dismissed applications can be reverted to pending.']);
        }

        if (! $application->academicYear?->isApplicationWindowOpen()) {
            return back()->withErrors(['error' => 'The application window is closed.']);
        }

        $oldStatus = $application->status;
        $application->update([
            'status' => 'pending',
            'processed_by' => null,
            'processed_at' => null,
        ]);

        // Delete applicant record if exists (will be recreated if accepted again)
        $application->applicant?->delete();

        // Pipeline hook: force-reset pipeline status back to pending on reopen
        app(ApplicationPipelineService::class)->forceSet($application->fresh(), 'pending');

        app(AuditService::class)->log('application.reopened', Application::class, $application->id, [
            'old_status' => $oldStatus,
        ], [
            'reference_number' => $application->reference_number,
        ]);

        return back()->with('success', 'Application reverted to pending.');
    }

    /**
     * Hard-delete an application (any status). Requires admin or super_admin.
     */
    public function destroy(Application $application): RedirectResponse
    {
        $this->authorize('delete', $application);

        app(AuditService::class)->log('application.deleted', Application::class, $application->id, [], [
            'reference_number' => $application->reference_number,
        ]);

        $application->delete();

        return redirect('/applications')->with('success', 'Application deleted.');
    }

    /**
     * Portal: Show applicant's own application.
     */
    public function portalShow(): Response
    {
        /** @var Applicant $applicant */
        $applicant = auth()->user();
        $application = $applicant->application;

        if (! $application) {
            abort(404, 'Application not found.');
        }

        $this->authorize('viewApplicant', $application);

        $application->load(['coursePreference1:id,name,code', 'coursePreference2:id,name,code', 'coursePreference3:id,name,code', 'academicYear']);

        $courses = $this->getCourses();

        $applicationData = [
            'id' => $application->id,
            'reference_number' => $application->reference_number,
            'first_name' => $application->first_name,
            'middle_name' => $application->middle_name,
            'last_name' => $application->last_name,
            'suffix' => $application->suffix,
            'birthdate' => $application->birthdate?->format('Y-m-d'),
            'age' => $application->age,
            'sex' => $application->sex,
            'email' => $application->email,
            'phone' => $application->phone,
            'address_line' => $application->address_line,
            'city' => $application->city,
            'province' => $application->province,
            'zip_code' => $application->zip_code,
            'gwa' => $application->gwa,
            'applicant_type' => $application->applicant_type ?? 'new',
            'last_school_enrolled' => $application->last_school_enrolled,
            'course_preference_1' => $application->course_preference_1,
            'course_preference_2' => $application->course_preference_2,
            'course_preference_3' => $application->course_preference_3,
            'course_preference_1_label' => $application->coursePreference1?->name,
            'course_preference_2_label' => $application->coursePreference2?->name,
            'course_preference_3_label' => $application->coursePreference3?->name,
            'status' => $application->status,
            'submitted_at' => $application->submitted_at?->toIso8601String(),
            'is_editable' => $application->isEditableByApplicant(),
            'assigned_session_status' => $application->assignedSessionStatus(),
        ];

        return Inertia::render('Portal/ApplicationShow', [
            'application' => $applicationData,
            'courses' => $courses,
            'admission_slip_enabled' => AdmissionSlipService::isEnabled(),
        ]);
    }

    /**
     * Portal: Edit applicant's own application.
     */
    public function portalEdit(): Response
    {
        /** @var Applicant $applicant */
        $applicant = auth()->user();
        $application = $applicant->application;

        if (! $application) {
            abort(404, 'Application not found.');
        }

        $this->authorize('editApplicant', $application);

        $application->load(['coursePreference1', 'coursePreference2', 'coursePreference3', 'academicYear']);

        $courses = $this->getCourses();

        $applicationData = [
            'id' => $application->id,
            'reference_number' => $application->reference_number,
            'first_name' => $application->first_name,
            'middle_name' => $application->middle_name,
            'last_name' => $application->last_name,
            'suffix' => $application->suffix,
            'birthdate' => $application->birthdate?->format('Y-m-d'),
            'sex' => $application->sex,
            'email' => $application->email,
            'phone' => $application->phone,
            'address_line' => $application->address_line,
            'city' => $application->city,
            'province' => $application->province,
            'zip_code' => $application->zip_code,
            'gwa' => $application->gwa,
            'applicant_type' => $application->applicant_type ?? 'new',
            'last_school_enrolled' => $application->last_school_enrolled,
            'course_preference_1' => $application->course_preference_1,
            'course_preference_2' => $application->course_preference_2,
            'course_preference_3' => $application->course_preference_3,
            'is_editable' => $application->isEditableByApplicant(),
            'assigned_session_status' => $application->assignedSessionStatus(),
        ];

        return Inertia::render('Portal/ApplicationEdit', [
            'application' => $applicationData,
            'courses' => $courses,
        ]);
    }

    /**
     * Portal: Update applicant's own application.
     */
    public function portalUpdate(UpdateApplicationRequest $request): RedirectResponse
    {
        /** @var Applicant $applicant */
        $applicant = auth()->user();
        $application = $applicant->application;

        if (! $application) {
            abort(404, 'Application not found.');
        }

        $this->authorize('editApplicant', $application);

        $validated = $request->validated();

        // Build update data - only include provided fields
        $updateData = [];
        $fillable = [
            'first_name', 'middle_name', 'last_name', 'suffix', 'birthdate',
            'sex', 'applicant_type', 'last_school_enrolled',
            'email', 'phone', 'address_line', 'city', 'province', 'zip_code',
            'gwa',
            'course_preference_1', 'course_preference_2', 'course_preference_3',
        ];

        foreach ($fillable as $field) {
            if (array_key_exists($field, $validated)) {
                $updateData[$field] = $validated[$field];
            }
        }

        // Update age if birthdate changed
        if (isset($updateData['birthdate'])) {
            $updateData['age'] = (int) Carbon::parse($updateData['birthdate'])->diffInYears(now());
        }

        $application->update($updateData);

        app(AuditService::class)->log('application.portal_updated', Application::class, $application->id, [], [
            'reference_number' => $application->reference_number,
        ]);

        return redirect()
            ->route('portal.application.show')
            ->with('success', 'Application updated successfully.');
    }

    private function getCourses(): array
    {
        $defaults = [
            ['id' => 1, 'name' => 'Bachelor of Science in Information Technology', 'code' => 'BSIT'],
            ['id' => 2, 'name' => 'Bachelor of Science in Computer Science', 'code' => 'BSCS'],
            ['id' => 3, 'name' => 'Bachelor of Science in Data Science', 'code' => 'BSDS'],
        ];

        if (DB::getSchemaBuilder()->hasTable('courses')) {
            $rows = Course::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
            if ($rows->isNotEmpty()) {
                return $rows->unique('id')->values()->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'code' => $r->code])->all();
            }
        }

        return $defaults;
    }

    private function getAppointments(): array
    {
        if (DB::getSchemaBuilder()->hasTable('appointments')) {
            $rows = Appointment::query()
                ->where('is_active', true)
                ->whereColumn('booked_count', '<', 'max_slots')
                ->where('date', '>=', now()->toDateString())
                ->orderBy('date')
                ->orderBy('time_slot')
                ->limit(50)
                ->get();

            return $rows->map(fn ($r) => [
                'id' => $r->id,
                'date' => $r->date->format('Y-m-d'),
                'time_slot' => $r->time_slot,
                'duration_minutes' => $r->duration_minutes,
                'max_slots' => $r->max_slots,
                'booked_count' => $r->booked_count,
                'label' => $r->date->format('Y-m-d').' '.substr($r->time_slot, 0, 5),
            ])->all();
        }

        return [];
    }

    /**
     * Safely find or create an Applicant for an accepted application.
     *
     * Handles the case where an applicant with the same email already exists
     * (e.g. from a prior application). If found, re-links to the new application.
     * Only sends the setup email when the applicant hasn't completed account setup.
     */
    private function ensureApplicantForAcceptance(Application $application): Applicant
    {
        $applicant = Applicant::where('email', $application->email)->first();

        if ($applicant) {
            // Re-link existing applicant to the latest application
            $applicant->update(['application_id' => $application->id]);

            // If they haven't set up yet, refresh token and resend
            if (! $applicant->hasCompletedSetup()) {
                $applicant->update([
                    'setup_token' => Applicant::generateSetupToken(),
                    'setup_token_expires_at' => now()->addHours(config('auth.setup_token_expires_hours', 72)),
                ]);

                try {
                    SendApplicantSetupEmail::dispatch($applicant->fresh());
                } catch (\Throwable $e) {
                    \Log::warning('Failed to send setup email (existing applicant)', [
                        'applicant_id' => $applicant->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $applicant;
        }

        // No existing applicant — create a new one
        $applicant = Applicant::create([
            'application_id' => $application->id,
            'email' => $application->email,
            'setup_token' => Applicant::generateSetupToken(),
            'setup_token_expires_at' => now()->addHours(config('auth.setup_token_expires_hours', 72)),
        ]);

        try {
            SendApplicantSetupEmail::dispatch($applicant);
        } catch (\Throwable $e) {
            \Log::warning('Failed to send setup email (new applicant)', [
                'applicant_id' => $applicant->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $applicant;
    }
}
