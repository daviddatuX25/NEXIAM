<?php

use App\Http\Controllers\Admission\AdmissionActionController;
use App\Http\Controllers\Admission\AdmissionReviewController;
use App\Http\Controllers\Admission\ApplicantApplyController;
use Illuminate\Support\Facades\Route;

test('each admission surface routes to its single-responsibility controller', function (): void {
    $expectations = [
        'apply.form' => ApplicantApplyController::class,
        'apply.submit' => ApplicantApplyController::class,
        'apply.confirmation' => ApplicantApplyController::class,
        'admissions.index' => AdmissionReviewController::class,
        'admissions.show' => AdmissionReviewController::class,
        'admissions.accept' => AdmissionActionController::class,
        'admissions.dismiss' => AdmissionActionController::class,
        'admissions.correct' => AdmissionActionController::class,
        'admissions.request-correction' => AdmissionActionController::class,
        'admissions.authorize-amendment' => AdmissionActionController::class,
        'admissions.reconsider' => AdmissionActionController::class,
        'admissions.reopen' => AdmissionActionController::class,
        'admissions.archive' => AdmissionActionController::class,
        'admissions.reissue-access-key' => AdmissionActionController::class,
    ];

    foreach ($expectations as $name => $controller) {
        $route = Route::getRoutes()->getByName($name);

        expect($route)->not->toBeNull()
            ->and($route->getActionName())->toStartWith($controller.'@');
    }
});
