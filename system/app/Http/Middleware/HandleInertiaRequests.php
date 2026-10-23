<?php

namespace App\Http\Middleware;

use App\Admission\Queries\CurrentIntakeQuery;
use App\Admission\Support\ApplicantQrCode;
use App\Admission\Support\ApplicantScope;
use App\Ai\Http\XiamSessionView;
use App\Ai\Http\XiamSessionViewBuilder;
use App\Applicant\Support\ApplicantNavigationBuilder;
use App\Applicant\Support\ApplicantPortalProjection;
use App\Authorization\CapabilityAuthorizer;
use App\DocumentTemplate\DocumentSurfaceRegistry;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Auth\StaffSessionLease;
use App\Models\Foundation\Institution;
use App\Models\Reports\ReportTemplate;
use App\Models\User;
use App\Notifications\NotificationProjection;
use App\Support\Navigation\NavigationBadgeProjector;
use App\Support\Navigation\StaffNavigationBuilder;
use App\Testing\PracticeWorkspace;
use App\Walkthrough\Queries\WalkthroughProgressProjection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $xiamView = app(XiamSessionViewBuilder::class)->build($request);
        $staffInactivityGeneration = null;
        $staffSessionLease = null;
        if ($user !== null && $request->session()->get('auth.surface') === 'staff') {
            $staffInactivityGeneration = $request->session()->get('auth.staff_inactivity_generation');
            $lease = StaffSessionLease::activeForSession((int) $user->id, $request->session()->getId(), now());

            if ($lease !== null && $staffInactivityGeneration === $lease->inactivity_generation) {
                $staffSessionLease = [
                    'server_now' => now()->utc()->toIso8601String(),
                    'idle_expires_at' => $lease->idle_expires_at->utc()->toIso8601String(),
                    'absolute_expires_at' => $lease->absolute_expires_at->utc()->toIso8601String(),
                    'effective_idle_minutes' => $lease->effective_idle_minutes,
                ];
            }
        }

        return [
            ...parent::share($request),
            ...($request->routeIs('demo.research.index', 'demo.research.instrument') ? [] : ['practice_workspace' => $this->practiceShare($request)]),
            'institution' => $this->institutionShare(),
            'has_submitted_application' => (bool) $request->cookie('nexiam_applicant_submitted', false),
            'public_intake_open' => fn () => $this->publicIntakeOpen($request),
            'opening_alerts_available' => (bool) config('application-opening-alerts.enabled', false),
            'auth' => [
                'user' => $user === null ? null : [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'capabilities' => $user->getAllPermissions()->pluck('name')->values(),
                    'scope' => $user->actorScope()->toArray(),
                    'is_applicant' => $user->applicantAccount()->exists(),
                    'is_staff' => $user->staffProfile()->exists(),
                    'avatar' => $user->staffProfile?->avatarProjection() ?? ['type' => 'initials', 'preset' => null, 'url' => null],
                    'office' => $user->staffProfile?->office,
                    'roles' => $user->roles->pluck('name')->values(),
                ],
                'staff_inactivity_generation' => $staffInactivityGeneration,
                'session_lease' => $staffSessionLease,
            ],
            'csrf_token' => fn () => csrf_token(),
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'success' => fn () => $request->session()->get('success'),
                'warning' => fn () => $request->session()->get('warning'),
                'error' => fn () => $request->session()->get('error'),
                'message' => fn () => $request->session()->get('message'),
                'direct_assessment_created' => fn () => $request->session()->get('direct_assessment_created'),
                'document_correction_request_outcome' => fn () => $request->session()->get('document_correction_request_outcome'),
                'document_correction_request_recorded' => fn () => $request->session()->get('document_correction_request_recorded'),
                'document_correction_request_delivery' => fn () => $request->session()->get('document_correction_request_delivery'),
            ],
            /*
             * Own-data notification bell share (GF-22 §5.5). The client polls
             * with usePoll(30_000, () => ({ only: ['notifications'] })) so the
             * badge/list refresh without a full navigation.
             */
            'notifications' => $user instanceof User
                ? fn () => app(NotificationProjection::class)->forUser($user)
                : null,

            'staff_navigation' => fn () => $this->staffNavigation($request),
            'navigation_badges' => $user instanceof User
                ? fn () => app(NavigationBadgeProjector::class)->forUser($user)
                : null,
            'applicant_navigation' => fn () => app(ApplicantNavigationBuilder::class)->for($user),
            'applicant_home' => fn () => $this->applicantHomeShare($request),
            'applicant_qr' => fn () => $this->applicantQrShare($user),
            'mounted_document_slots' => fn () => $this->mountedDocumentSlotsShare($user),
            'walkthroughProgress' => fn () => $this->walkthroughProgressShare($request),

            /*
             * Transitional Xiam share (OpenSpec ai-assistant-foundation):
             * - `session` is the typed XiamSessionView v1 contract
             * - top-level enabled/mode/disclosure string keep the locked chrome mounting
             *   until XiamDock lands (frontend tree currently permission-blocked for this agent)
             */
            'xiam' => $this->xiamShare($xiamView),
        ];
    }

    private function publicIntakeOpen(Request $request): bool
    {
        if (in_array('auth', $request->route()?->gatherMiddleware() ?? [], true)) {
            return false;
        }
        $institution = Institution::query()->where('code', 'SYN-INST-001')->first();

        return $institution !== null && app(CurrentIntakeQuery::class)->forInstitution($institution) !== null;
    }

    /**
     * The selected detail remains responsible for its own data and authorization.
     *
     * @return array<string, mixed>|null
     */
    private function applicantHomeShare(Request $request): ?array
    {
        $user = $request->user();
        if (! $user instanceof User
            || ! $request->routeIs('applicant.*')
            || $request->routeIs('applicant.portal', 'applicant.login', 'applicant.login.*')
            || $request->session()->get('auth.surface') !== 'applicant') {
            return null;
        }

        $projection = app(ApplicantPortalProjection::class)->for($user);

        return $projection === null
            ? ['page_state' => 'empty']
            : ['page_state' => 'ready', ...$projection];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function walkthroughProgressShare(Request $request): ?array
    {
        $user = $request->user();
        $surface = $request->session()->get('auth.surface');
        if (! $user instanceof User || ! in_array($surface, ['applicant', 'staff'], true)) {
            return null;
        }
        $institutionId = $surface === 'applicant'
            ? $user->applicantAccount?->identity?->institution_id
            : $user->staffProfile?->institution_id;
        if ($institutionId === null) {
            return null;
        }

        return app(WalkthroughProgressProjection::class)->for($user, $surface);
    }

    /**
     * @return array<string, mixed>
     */
    private function institutionShare(): array
    {
        try {
            $institution = Institution::query()->where('code', 'SYN-INST-001')->first();
            if ($institution !== null) {
                return $institution->toPublicArray();
            }
        } catch (\Throwable) {
            // Safe fallback
        }

        return [
            'name' => (string) config('public-site.institution.name', 'Ilocos Sur Polytechnic State College'),
            'short_name' => (string) config('public-site.institution.short_name', 'ISPSC'),
            'campus' => (string) config('public-site.institution.campus', 'Tagudin Campus'),
            'address' => 'Quirino, Tagudin, Ilocos Sur',
            'location' => (string) config('public-site.institution.location', 'Tagudin, Ilocos Sur'),
            'contact_number' => '+63 (77) 674-1234',
            'email' => 'admissions.tagudin@ispsc.edu.ph',
            'website' => 'https://ispsc.edu.ph',
            'logo_url' => file_exists(public_path('assets/branding/ispsc/logo/ispsc-seal.webp')) ? '/assets/branding/ispsc/logo/ispsc-seal.webp' : null,
            'fallback_monogram' => 'I',
        ];
    }

    /**
     * @return array<string, list<array{
     *     id: int,
     *     name: string,
     *     surface_key: string,
     *     grain: string,
     *     mode: string,
     *     paper_size: string,
     *     orientation: string
     * }>>
     */
    private function mountedDocumentSlotsShare(?User $user): array
    {
        if ($user === null || ! $user->staffProfile()->exists() || ! Schema::hasTable('report_templates')) {
            return [];
        }

        $authorizer = app(CapabilityAuthorizer::class);
        $slots = [];

        foreach (ReportTemplate::query()->where('is_active', true)->get() as $template) {
            if ($template->surface_key === 'custom_document'
                && $template->office !== null
                && $template->office !== $user->actorScope()->office) {
                continue;
            }
            if (! DocumentSurfaceRegistry::canViewSurface($authorizer, $user, $template->surface_key)) {
                continue;
            }

            $mountedSlots = is_array($template->mounted_slots)
                ? $template->mounted_slots
                : ($template->surface_key === 'course_quota_qualifiers' ? ['analytics_reports_hub'] : []);

            foreach ($mountedSlots as $slotKey) {
                $slots[$slotKey][] = [
                    'id' => (int) $template->id,
                    'name' => (string) $template->name,
                    'surface_key' => (string) $template->surface_key,
                    'grain' => $template->grain ?? (in_array($slotKey, [
                        'admissions_registry_bulk',
                        'admissions_applicant_show',
                        'applicant_qr_scan_modal',
                        'registrar_enrollment_clearance',
                    ], true) ? 'student' : 'cycle_program'),
                    'mode' => (string) $template->mode,
                    'paper_size' => (string) $template->paper_size,
                    'orientation' => (string) $template->orientation,
                ];
            }
        }

        return $slots;
    }

    private function practiceShare(Request $request): ?array
    {
        $set = app(PracticeWorkspace::class)->current();

        return $set === null ? null : [
            'id' => $set->id,
            'label' => $set->label,
            'purpose' => $set->purpose,
            'leave_url' => $request->user()?->applicantAccount !== null
                ? null
                : route('demo.index', ['practice_set' => 'leave'], false),
            'leave_note' => $request->user()?->applicantAccount !== null
                ? 'This practice account stays in its own workspace. Sign out through the normal portal to leave.'
                : null,
        ];
    }

    /**
     * @return list<array{label: string|null, items: list<array{href: string, label: string, icon: string, match: string}>}>
     */
    private function staffNavigation(Request $request): array
    {
        return app(StaffNavigationBuilder::class)->for($request->user());
    }

    /**
     * @return array<string, mixed>|null
     */
    private function xiamShare(?XiamSessionView $view): ?array
    {
        if ($view === null) {
            return null;
        }

        $session = $view->toArray();
        $visible = $session['availability'] !== 'hidden';

        return [
            'enabled' => $visible,
            'mode' => $visible ? 'degraded' : 'disabled',
            'disclosure' => $session['disclosure']['text'],
            'usage' => $session['usage'],
            'session' => $session,
        ];
    }

    /**
     * @return array{attempt_id: int, reference_code: string, qr: string}|null
     */
    private function applicantQrShare(?User $user): ?array
    {
        if ($user === null || ! $user->applicantAccount()->exists()) {
            return null;
        }

        $identityId = ApplicantScope::identityIdForUser($user);
        if ($identityId === null) {
            return null;
        }

        $attempt = AdmissionAttempt::query()
            ->where('applicant_identity_id', $identityId)
            ->orderByDesc('id')
            ->first();

        if ($attempt === null) {
            return null;
        }

        return [
            'attempt_id' => (int) $attempt->id,
            'reference_code' => (string) $attempt->reference_code,
            'qr' => ApplicantQrCode::issue($attempt),
            'entry_url' => ApplicantQrCode::entryUrl($attempt),
            'print_url' => route('applicant.qr.print'),
        ];
    }
}
