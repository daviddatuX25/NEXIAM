<?php

use App\Http\Controllers\Examination\ExamMonitoringController;
use App\Http\Controllers\Examination\ExamSessionController;
use App\Http\Controllers\Examination\ProctorDutyController;
use Illuminate\Support\Facades\Route;

test('each exam surface routes to its single-responsibility controller', function (): void {
    $expectations = [
        // Scheduling surface
        'exams.sessions.index' => ExamSessionController::class,
        'exams.sessions.create' => ExamSessionController::class,
        'exams.sessions.store' => ExamSessionController::class,
        'exams.sessions.show' => ExamSessionController::class,
        'exams.sessions.edit' => ExamSessionController::class,
        'exams.sessions.update' => ExamSessionController::class,
        'exams.sessions.assign' => ExamSessionController::class,
        'exams.sessions.remove' => ExamSessionController::class,
        'exams.sessions.reassign' => ExamSessionController::class,
        'exams.sessions.publish' => ExamSessionController::class,
        'exams.sessions.unpublish' => ExamSessionController::class,
        'exams.sessions.cancel' => ExamSessionController::class,
        'exams.sessions.close' => ExamSessionController::class,
        'exams.sessions.extend' => ExamSessionController::class,
        'exams.sessions.open-reservation' => ExamSessionController::class,
        'exams.sessions.close-reservation' => ExamSessionController::class,
        // Monitoring (conduct) surface
        'exams.monitoring' => ExamMonitoringController::class,
        'exams.sessions.monitoring' => ExamMonitoringController::class,
        // Duty-scoped conduct
        'proctor-duty.assign' => ProctorDutyController::class,
        'proctor-duty.revoke' => ProctorDutyController::class,
        'exams.sessions.monitoring.attendance' => ProctorDutyController::class,
        'exams.sessions.monitoring.submission' => ProctorDutyController::class,
    ];

    foreach ($expectations as $name => $controller) {
        $route = Route::getRoutes()->getByName($name);

        expect($route)->not->toBeNull()
            ->and($route->getActionName())->toStartWith($controller.'@');
    }
});
