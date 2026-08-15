<?php

use App\Http\Controllers\Setup\AcademicCycleController;
use App\Http\Controllers\Setup\ApplicationWindowController;
use App\Http\Controllers\Setup\AptitudeAreaController;
use App\Http\Controllers\Setup\IntakeGradeFieldController;
use App\Http\Controllers\Setup\PrivacyPolicyController;
use App\Http\Controllers\Setup\ProgramController;
use App\Http\Controllers\Setup\ProgramQuotaController;
use App\Http\Controllers\Setup\RoomController;
use App\Http\Controllers\Setup\SetupDashboardController;

test('each setup surface routes to its single-responsibility controller', function (): void {
    $expectations = [
        'setup.index' => SetupDashboardController::class,
        'setup.programs' => ProgramController::class,
        'setup.programs.store' => ProgramController::class,
        'setup.programs.update' => ProgramController::class,
        'setup.rooms' => RoomController::class,
        'setup.rooms.store' => RoomController::class,
        'setup.rooms.update' => RoomController::class,
        'setup.cycles' => AcademicCycleController::class,
        'setup.cycles.create' => AcademicCycleController::class,
        'setup.cycles.store' => AcademicCycleController::class,
        'setup.cycles.edit' => AcademicCycleController::class,
        'setup.cycles.update' => AcademicCycleController::class,
        'setup.cycles.activate' => AcademicCycleController::class,
        'setup.cycles.deactivate' => AcademicCycleController::class,
        'setup.windows.store' => ApplicationWindowController::class,
        'setup.privacy' => PrivacyPolicyController::class,
        'setup.privacy.store' => PrivacyPolicyController::class,
        'setup.privacy.activate' => PrivacyPolicyController::class,
        'setup.grades' => IntakeGradeFieldController::class,
        'setup.grades.store' => IntakeGradeFieldController::class,
        'setup.grades.update' => IntakeGradeFieldController::class,
        'setup.aptitude' => AptitudeAreaController::class,
        'setup.aptitude.store' => AptitudeAreaController::class,
        'setup.aptitude.update' => AptitudeAreaController::class,
        'setup.aptitude.scoring' => AptitudeAreaController::class,
        'setup.aptitude.scoring.update' => AptitudeAreaController::class,
        'setup.quotas' => ProgramQuotaController::class,
        'setup.quotas.store' => ProgramQuotaController::class,
    ];

    foreach ($expectations as $name => $controller) {
        $route = Route::getRoutes()->getByName($name);

        expect($route)->not->toBeNull()
            ->and($route->getActionName())->toStartWith($controller.'@');
    }
});
