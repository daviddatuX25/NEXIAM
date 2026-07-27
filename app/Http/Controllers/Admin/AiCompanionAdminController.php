<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePersonaRequest;
use App\Models\KnowledgeDocument;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AiCompanionAdminController extends Controller
{
    public function index(): Response
    {
        if (! SystemSetting::aiCompanionEnabled()) {
            abort(403, 'AI Companion is disabled.');
        }

        $this->authorize('viewAny', KnowledgeDocument::class);

        return Inertia::render('Admin/AiCompanion/Index', [
            'documents'            => KnowledgeDocument::orderBy('created_at', 'desc')->get(),
            'ai_companion_persona' => SystemSetting::personaPrompt(),
        ]);
    }

    public function updatePersona(UpdatePersonaRequest $request): RedirectResponse
    {
        SystemSetting::set('ai_companion_persona', $request->validated('ai_companion_persona'));

        return back()->with('success', 'Persona updated.');
    }
}
