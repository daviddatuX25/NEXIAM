<?php

namespace App\Http\Controllers\Foundation;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\ResearchSessionGuideCatalog;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class ResearchSessionGuideController extends Controller
{
    public function index(Request $request, ResearchSessionGuideCatalog $catalog): Response
    {
        $this->assertDevelopmentEnvironment();
        $role = $request->query('role', 'student');
        abort_unless(is_string($role), 404);
        $task = $request->query('task');
        abort_unless($task === null || is_string($task), 404);
        $questionnaire = $catalog->questionnaire($role);

        return Inertia::render('Foundation/ResearchSessionGuide', [
            'groups' => $catalog->groups(),
            'selected_role' => $role,
            'participant' => $catalog->participant($role, $task),
            'questionnaire' => [
                'id' => $questionnaire['id'],
                'title' => $questionnaire['title'],
                'url' => route('demo.research.instrument', ['role' => $role], absolute: false),
                'kind' => 'material',
                'note' => 'Read-only reference. Questionnaire responses are not collected here.',
            ],
            'after_instructions' => $catalog->afterInstructions(),
            'xiam' => null,
            'notifications' => null,
        ]);
    }

    public function facilitator(Request $request, ResearchSessionGuideCatalog $catalog, CapabilityAuthorizer $authorizer): Response
    {
        $this->assertDevelopmentEnvironment();
        abort_unless($authorizer->decideFresh($request->user(), CapabilityCatalog::DEMO_STAGE_CONTROL)->allowed, 403);
        $role = $request->query('role');
        abort_unless($role === null || (is_string($role) && in_array($role, ['student', 'registrar', 'guidance', 'academic-leader'], true)), 404);

        return Inertia::render('Foundation/ResearchFacilitatorGuide', [
            'groups' => $catalog->groups(),
            'selected_role' => $role,
            'facilitator' => $catalog->facilitator(),
            'source_version' => $catalog->sourceVersion(),
            'can_control' => true,
            'preparation_url' => route('demo.preparation.index', ['purpose' => 'research', 'scenario' => $role ?? 'student'], absolute: false),
            'xiam' => null,
            'notifications' => null,
        ]);
    }

    public function instrument(string $role, ResearchSessionGuideCatalog $catalog): HttpResponse
    {
        $this->assertDevelopmentEnvironment();
        $instrument = $catalog->questionnaire($role);
        $filename = 'research-instrument-'.$role.'.md';

        return response($instrument['markdown'], 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    private function assertDevelopmentEnvironment(): void
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
    }
}
