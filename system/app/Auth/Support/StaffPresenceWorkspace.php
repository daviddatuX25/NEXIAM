<?php

declare(strict_types=1);

namespace App\Auth\Support;

use Illuminate\Http\Request;

final class StaffPresenceWorkspace
{
    /** @return array{path: ?string, area: ?string, background: bool} */
    public function resolve(Request $request): array
    {
        $path = '/'.ltrim($request->path(), '/');
        $background = $request->is('notifications', 'notifications/*', 'xiam', 'xiam/*')
            || $request->is('staff/logs/presence', 'staff/logs/summary')
            || $request->is('staff/session-keepalive')
            || $request->is('logout')
            || $request->header('Purpose') === 'prefetch'
            || $request->header('X-Inertia-Prefetch') !== null
            || $request->isMethod('HEAD');

        if ($background) {
            return ['path' => null, 'area' => null, 'background' => true];
        }

        $routes = [
            ['admin', '/admin', 'Executive Dashboard'],
            ['setup', '/setup', 'Institutional Setup'],
            ['admissions', 'admissions', 'Admissions'],
            ['exams/sessions', '/exams/sessions', 'Exam Scheduling'],
            ['exam', '/exams', 'Assessment'],
            ['consultation', '/consultation', 'Consultation'],
            ['staff/logs', '/staff/logs', 'Audit Logs'],
            ['staff/personnel', '/staff/personnel', 'Personnel'],
            ['programs', '/programs', 'Academic Programs'],
            ['staff', '/staff', 'Staff Workspace'],
        ];

        foreach ($routes as [$prefix, $template, $area]) {
            if ($request->is($prefix) || $request->is($prefix.'/*')) {
                return ['path' => $template, 'area' => $area, 'background' => false];
            }
        }

        return ['path' => null, 'area' => null, 'background' => false];
    }
}
