<?php

namespace App\Http\Controllers;

use App\Services\DashboardAnalyticsService;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboardService,
        private DashboardAnalyticsService $analyticsService
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Dashboard', [
            'user' => $user,
            'applicationStats' => $this->dashboardService->getApplicationStats($user),
            'pipelineDistribution' => $this->dashboardService->getPipelineDistribution($user),
            'sessionStats' => $this->dashboardService->getSessionStats($user),
            'gradingStats' => $this->dashboardService->getGradingStats($user),
            'analytics' => [
                'applications' => [
                    'trends' => $this->analyticsService->getApplicationTrends($user),
                    'statusDistribution' => $this->analyticsService->getApplicationStatusDistribution($user),
                    'coursePreferences' => $this->analyticsService->getCoursePreferenceDistribution($user),
                ],
                'sessions' => [
                    'trends' => $this->analyticsService->getSessionTrends($user),
                    'statusDistribution' => $this->analyticsService->getSessionStatusDistribution($user),
                    'attendance' => $this->analyticsService->getAttendanceTrends($user),
                ],
                'grading' => [
                    'statusDistribution' => $this->analyticsService->getGradingStatusDistribution($user),
                    'turnaround' => $this->analyticsService->getGradingTurnaround($user),
                ],
            ],
            'myActivity' => $this->analyticsService->getMyActivity($user),
        ]);
    }
}
