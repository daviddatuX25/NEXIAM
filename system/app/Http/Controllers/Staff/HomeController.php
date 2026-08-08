<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user !== null && $user->applicantAccount()->exists() && ! $user->staffProfile()->exists()) {
            return redirect()->route('applicant.schedule');
        }

        return Inertia::render('Staff/Home', [
            'staff' => [
                'name' => $user?->name,
                'email' => $user?->email,
                'capabilities' => $user?->getAllPermissions()->pluck('name')->values() ?? [],
                'scope' => $user?->actorScope()->toArray() ?? null,
            ],
        ]);
    }
}
