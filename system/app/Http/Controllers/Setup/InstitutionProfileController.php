<?php

declare(strict_types=1);

namespace App\Http\Controllers\Setup;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\DeleteInstitutionLogo;
use App\Foundation\Actions\ResetInstitutionProfileDefaults;
use App\Foundation\Actions\UpdateInstitutionProfile;
use App\Foundation\Actions\UploadInstitutionLogo;
use App\Http\Controllers\Controller;
use App\Models\Foundation\Institution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

final class InstitutionProfileController extends Controller
{
    public function index(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $institution = Institution::current();
        $canManage = $authorizer->allows($request->user(), CapabilityCatalog::SETUP_INSTITUTION_MANAGE, [
            'institution_id' => (int) $institution->id,
        ]);

        abort_unless($canManage, 403);

        $envDefaults = config('public-site.institution', []);

        return Inertia::render('Setup/Institution/Index', [
            'institution' => [
                'id' => (int) $institution->id,
                'code' => (string) $institution->code,
                'name' => (string) ($institution->name ?? ''),
                'short_name' => (string) ($institution->short_name ?? ''),
                'campus_name' => (string) ($institution->campus_name ?? ''),
                'address' => (string) ($institution->address ?? ''),
                'location' => (string) ($institution->location ?? ''),
                'contact_number' => (string) ($institution->contact_number ?? ''),
                'email' => (string) ($institution->email ?? ''),
                'website' => (string) ($institution->website ?? ''),
                'logo_url' => $institution->logoUrl(),
                'logo_byte_size' => $institution->logo_byte_size,
                'logo_mime' => $institution->logo_mime,
                'fallback_monogram' => $institution->fallbackMonogram(),
            ],
            'env_defaults' => [
                'name' => (string) ($envDefaults['name'] ?? 'Ilocos Sur Polytechnic State College'),
                'short_name' => (string) ($envDefaults['short_name'] ?? 'ISPSC'),
                'campus_name' => (string) ($envDefaults['campus'] ?? 'Tagudin Campus'),
                'address' => 'Quirino, Tagudin, Ilocos Sur',
                'location' => (string) ($envDefaults['location'] ?? 'Tagudin, Ilocos Sur'),
                'contact_number' => '+63 (77) 674-1234',
                'email' => 'admissions.tagudin@ispsc.edu.ph',
                'website' => 'https://ispsc.edu.ph',
            ],
            'can_manage' => $canManage,
        ]);
    }

    public function update(Request $request, UpdateInstitutionProfile $update): RedirectResponse
    {
        $institution = Institution::current();
        $update->handle($institution, $request->all(), $request->user());

        return Redirect::back()->with('success', 'Institution profile updated successfully.');
    }

    public function uploadLogo(Request $request, UploadInstitutionLogo $upload): RedirectResponse
    {
        $institution = Institution::current();
        $request->validate(['logo' => ['required', 'file', 'mimes:jpeg,png,webp', 'max:2048']]);
        $upload->handle($institution, $request->file('logo'), $request->user());

        return Redirect::back()->with('success', 'Official institution logo uploaded successfully.');
    }

    public function deleteLogo(Request $request, DeleteInstitutionLogo $delete): RedirectResponse
    {
        $institution = Institution::current();
        $delete->handle($institution, $request->user());

        return Redirect::back()->with('success', 'Official institution logo removed. Monogram fallback is now active.');
    }

    public function resetDefaults(Request $request, ResetInstitutionProfileDefaults $reset): RedirectResponse
    {
        $institution = Institution::current();
        $reset->handle($institution, $request->user());

        return Redirect::back()->with('success', 'Institution profile reset to environment defaults.');
    }
}
