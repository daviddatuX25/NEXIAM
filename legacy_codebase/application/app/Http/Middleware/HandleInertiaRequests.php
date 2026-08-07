<?php

namespace App\Http\Middleware;

use App\Models\AcademicYear;
use App\Models\Applicant;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\GoogleOAuthConfig;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $authUser = null;
        if ($user instanceof User) {
            $user->load('roles');
            $authUser = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->map(fn ($r) => ['name' => $r->name])->values()->toArray(),
            ];
        } elseif ($user instanceof Applicant) {
            $authUser = [
                'id' => $user->id,
                'name' => $user->email,
                'email' => $user->email,
                'roles' => [],
            ];
        }

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $authUser,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'message' => $request->session()->get('message'),
            ],
            'csrf_token' => $request->session()->token(),
            'ai_exam_companion_enabled' => SystemSetting::aiCompanionEnabled(),
            'allow_flexible_applications' => SystemSetting::allowFlexibleApplications(),
            'googleOAuthEnabled' => GoogleOAuthConfig::isConfigured(),
            'release_mode' => SystemSetting::releaseMode(),
            'activeAcademicYear' => fn () => $this->resolveActiveAcademicYear(),
            'pageTitle' => $this->defaultPageTitle($request),
            'notifications' => $user instanceof User
                ? $user->notifications()
                    ->orderBy('created_at', 'desc')
                    ->limit(20)
                    ->get()
                    ->map(fn ($n) => [
                        'id' => $n->id,
                        'type' => $n->type,
                        'message' => $n->data['message'] ?? $n->data['title'] ?? class_basename($n->type),
                        'read' => $n->read_at !== null,
                        'created_at' => $n->created_at?->toIso8601String(),
                    ])
                : [],
        ]);
    }

    /**
     * Default page title from route name (controllers can override by passing pageTitle).
     */
    private function defaultPageTitle(Request $request): string
    {
        $name = $request->route()?->getName();
        if (! $name) {
            return 'Dashboard';
        }
        $titles = [
            'portal.dashboard' => 'Portal',
            'dashboard' => 'Overview',
            'grading.index' => 'Grading',
            'admin.exam-scheduling.index' => 'Exam Scheduling',
            'admin.exam-monitoring.index' => 'Exam Monitoring',
            'admin.courses.index' => 'Courses',
            'admin.rooms.index' => 'Rooms',
            'admin.users.index' => 'Users',
            'admin.settings.index' => 'Settings',
            'admin.logs.index' => 'Audit log',
            'admin.academic-years.index' => 'Academic Years',
            'admin.aptitude-areas.index' => 'Aptitude Areas',
            'admin.ai-companion.index' => 'AI Companion',
            'admin.setup.index' => 'Setup',
            'admin.admission-slip-templates.index' => 'Admission slip templates',
            'admin.release.index' => 'Release',
            'admin.release.result-templates.index' => 'Result templates',
            'applications.index' => 'Applications',
            'profile.edit' => 'Profile',
        ];

        return $titles[$name] ?? str_replace(['-', '.'], ' ', ucwords(implode(' ', preg_split('/[.-]/', $name))));
    }

    /**
     * Resolve the active academic year into a compact label for the layout.
     */
    private function resolveActiveAcademicYear(): ?array
    {
        $ay = AcademicYear::active();
        if (! $ay) {
            return null;
        }

        return [
            'id' => $ay->id,
            'label' => $ay->academic_year.' – '.$ay->semesterLabel(),
        ];
    }
}
