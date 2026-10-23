<?php

use App\Http\Controllers\Setup\AcademicCycleController;
use App\Http\Controllers\Setup\ApplicationWindowController;
use App\Http\Controllers\Setup\AptitudeAreaController;
use App\Http\Controllers\Setup\ProgramController;
use App\Http\Controllers\Setup\ProgramQuotaController;
use App\Http\Controllers\Setup\RoomController;
use App\Http\Controllers\Setup\SeatingArrangementController;
use App\Http\Controllers\Setup\SetupDashboardController;
use App\Http\Controllers\Setup\TestKitController;

test('each setup surface routes to its single-responsibility controller', function (): void {
    $expectations = [
        'setup.index' => SetupDashboardController::class,
        'setup.exam-materials.test-kits.index' => TestKitController::class,
        'setup.programs' => ProgramController::class,
        'setup.programs.store' => ProgramController::class,
        'setup.programs.update' => ProgramController::class,
        'setup.rooms' => RoomController::class,
        'setup.rooms.store' => RoomController::class,
        'setup.rooms.update' => RoomController::class,
        'setup.rooms.create' => RoomController::class,
        'setup.rooms.show' => RoomController::class,
        'setup.rooms.edit' => RoomController::class,
        'setup.rooms.activate' => RoomController::class,
        'setup.rooms.deactivate' => RoomController::class,
        'setup.rooms.default-seating-arrangement' => RoomController::class,
        'setup.seating-arrangements.index' => SeatingArrangementController::class,
        'setup.seating-arrangements.create' => SeatingArrangementController::class,
        'setup.seating-arrangements.store' => SeatingArrangementController::class,
        'setup.seating-arrangements.edit' => SeatingArrangementController::class,
        'setup.seating-arrangements.update' => SeatingArrangementController::class,
        'setup.seating-arrangements.activate' => SeatingArrangementController::class,
        'setup.seating-arrangements.deactivate' => SeatingArrangementController::class,
        'setup.cycles' => AcademicCycleController::class,
        'setup.cycles.create' => AcademicCycleController::class,
        'setup.cycles.store' => AcademicCycleController::class,
        'setup.cycles.edit' => AcademicCycleController::class,
        'setup.cycles.update' => AcademicCycleController::class,
        'setup.cycles.activate' => AcademicCycleController::class,
        'setup.cycles.deactivate' => AcademicCycleController::class,
        'setup.windows.store' => ApplicationWindowController::class,
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

    expect(Route::getRoutes()->getByName('setup.rooms.seating-profiles.create'))->toBeNull()
        ->and(Route::getRoutes()->getByName('setup.rooms.seating-profiles.store'))->toBeNull()
        ->and(Route::getRoutes()->getByName('setup.rooms.seating-profiles.edit'))->toBeNull()
        ->and(Route::getRoutes()->getByName('setup.rooms.seating-profiles.update'))->toBeNull()
        ->and(Route::getRoutes()->getByName('setup.privacy'))->toBeNull()
        ->and(Route::getRoutes()->getByName('setup.privacy.store'))->toBeNull()
        ->and(Route::getRoutes()->getByName('setup.privacy.activate'))->toBeNull()
        ->and(Route::getRoutes()->getByName('setup.grades'))->toBeNull()
        ->and(Route::getRoutes()->getByName('setup.grades.store'))->toBeNull()
        ->and(Route::getRoutes()->getByName('setup.grades.update'))->toBeNull();
});
