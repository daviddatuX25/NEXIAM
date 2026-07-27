<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use Carbon\Carbon;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index()
    {
        $activeAcademicYear = AcademicYear::where('is_active', true)->first();

        return Inertia::render('Home/Index', [
            'activeSeason' => $activeAcademicYear ? [
                'name' => 'A.Y. '.$activeAcademicYear->academic_year,
                'application_start' => $activeAcademicYear->application_start_date ? Carbon::parse($activeAcademicYear->application_start_date)->format('M d, Y') : null,
                'application_end' => $activeAcademicYear->application_end_date ? Carbon::parse($activeAcademicYear->application_end_date)->format('M d, Y') : null,
            ] : null,
            'systemName' => 'SecureCAT',
        ]);
    }

    public function about()
    {
        return Inertia::render('Home/About', [
            'systemName' => 'SecureCAT',
        ]);
    }
}
