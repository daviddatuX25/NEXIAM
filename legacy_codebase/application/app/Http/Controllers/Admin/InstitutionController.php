<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InstitutionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $isSuperOrRegistrar = $user->hasAnyRole(['super_admin', 'registrar_administrator']);

        $profileKeys = ['name', 'campus', 'address', 'contact_number', 'email', 'website', 'exam_name', 'exam_acronym'];
        $profile = [];
        if ($isSuperOrRegistrar) {
            foreach ($profileKeys as $key) {
                $profile[$key] = [
                    'value' => SystemSetting::institution($key, ''),
                    'env_default' => config("institution.{$key}", ''),
                    'overridden' => SystemSetting::get("institution.{$key}") !== null,
                ];
            }
        }

        $personnelRoles = array_keys(config('institution.personnel', []));
        if (! $isSuperOrRegistrar) {
            $personnelRoles = array_intersect($personnelRoles, ['guidance_counselor', 'testing_coordinator']);
            // Convert to a zero-indexed array to serialize correctly as list in JSON
            $personnelRoles = array_values($personnelRoles);
        }

        $personnel = [];
        foreach ($personnelRoles as $role) {
            foreach (['name', 'title', 'credentials'] as $field) {
                $dotKey = "personnel.{$role}.{$field}";
                $personnel[$role][$field] = [
                    'value' => SystemSetting::institution($dotKey, ''),
                    'env_default' => config("institution.{$dotKey}", ''),
                    'overridden' => SystemSetting::get("institution.{$dotKey}") !== null,
                ];
            }
        }

        return Inertia::render('Admin/Institution/Index', [
            'profile' => $profile,
            'personnel' => $personnel,
            'personnelRoles' => $personnelRoles,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'profile' => ['nullable', 'array'],
            'profile.*' => ['nullable', 'string', 'max:500'],
            'personnel' => ['nullable', 'array'],
            'personnel.*.*' => ['nullable', 'string', 'max:500'],
        ]);

        $changed = 0;
        $user = $request->user();
        $isSuperOrRegistrar = $user->hasAnyRole(['super_admin', 'registrar_administrator']);

        if ($isSuperOrRegistrar) {
            foreach ($request->input('profile', []) as $key => $value) {
                $settingKey = "institution.{$key}";
                $envDefault = config("institution.{$key}", '');
                if ((string) $value !== (string) $envDefault) {
                    SystemSetting::set($settingKey, $value);
                    $changed++;
                } else {
                    SystemSetting::where('key', $settingKey)->delete();
                }
            }
        }

        $allowedPersonnelRoles = array_keys(config('institution.personnel', []));
        if (! $isSuperOrRegistrar) {
            $allowedPersonnelRoles = array_intersect($allowedPersonnelRoles, ['guidance_counselor', 'testing_coordinator']);
        }

        foreach ($request->input('personnel', []) as $role => $fields) {
            if (! in_array($role, $allowedPersonnelRoles, true)) {
                continue;
            }
            foreach ($fields as $field => $value) {
                $settingKey = "institution.personnel.{$role}.{$field}";
                $envDefault = config("institution.personnel.{$role}.{$field}", '');
                if ((string) $value !== (string) $envDefault) {
                    SystemSetting::set($settingKey, $value);
                    $changed++;
                } else {
                    SystemSetting::where('key', $settingKey)->delete();
                }
            }
        }

        app(AuditService::class)->log('institution.updated', SystemSetting::class, null, [], [
            'fields_changed' => $changed,
        ]);

        return back()->with('success', "Institution settings saved ({$changed} override(s) updated).");
    }

    public function resetDefaults(Request $request): RedirectResponse
    {
        $user = $request->user();
        $isSuperOrRegistrar = $user->hasAnyRole(['super_admin', 'registrar_administrator']);

        if ($isSuperOrRegistrar) {
            $deleted = SystemSetting::where('key', 'like', 'institution.%')->delete();
        } else {
            $keysToDelete = [];
            foreach (['guidance_counselor', 'testing_coordinator'] as $role) {
                foreach (['name', 'title', 'credentials'] as $field) {
                    $keysToDelete[] = "institution.personnel.{$role}.{$field}";
                }
            }
            $deleted = SystemSetting::whereIn('key', $keysToDelete)->delete();
        }

        app(AuditService::class)->log('institution.reset', SystemSetting::class, null, [], [
            'overrides_deleted' => $deleted,
        ]);

        return back()->with('success', "All institution overrides cleared ({$deleted} removed). Using .env defaults.");
    }
}
