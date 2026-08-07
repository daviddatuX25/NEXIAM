<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSystemSettingsRequest;
use App\Models\AptitudeArea;
use App\Models\SystemSetting;
use App\Services\AdmissionSlipTemplateService;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Mews\Purifier\Facades\Purifier;

class SettingsController extends Controller
{
    /**
     * Show system settings (super_admin only). AI companion toggle and future persona/config.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', SystemSetting::class);

        return Inertia::render('Admin/Settings/Index', [
            'ai_exam_companion_enabled' => SystemSetting::aiCompanionEnabled(),
            'notify_on_publish' => SystemSetting::notifyOnPublish(),
            'release_mode' => SystemSetting::releaseMode(),
            'allow_direct_assessment' => SystemSetting::allowDirectAssessment(),
            'allow_flexible_applications' => SystemSetting::allowFlexibleApplications(),
            'enable_normalized_scores' => SystemSetting::enableNormalizedScores(),
            'admission_slip_enabled' => SystemSetting::admissionSlipEnabled(),
            'admission_slip_html_template' => SystemSetting::admissionSlipTemplate() ?? '',
        ]);
    }

    /**
     * Update system settings (e.g. AI exam companion enabled).
     */
    public function update(UpdateSystemSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if (array_key_exists('ai_exam_companion_enabled', $validated)) {
            SystemSetting::set('ai_exam_companion_enabled', (bool) $validated['ai_exam_companion_enabled']);
        }

        if (array_key_exists('notify_on_publish', $validated)) {
            SystemSetting::set('notify_on_publish', (bool) $validated['notify_on_publish']);
        }

        if (array_key_exists('ai_companion_persona', $validated)) {
            $persona = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $validated['ai_companion_persona'] ?? '');
            SystemSetting::set('ai_companion_persona', strip_tags($persona));
        }

        if (array_key_exists('release_mode', $validated)) {
            SystemSetting::set('release_mode', $validated['release_mode']);
        }

        if (array_key_exists('allow_direct_assessment', $validated)) {
            SystemSetting::set('allow_direct_assessment', (bool) $validated['allow_direct_assessment']);
        }

        if (array_key_exists('allow_flexible_applications', $validated)) {
            SystemSetting::set('allow_flexible_applications', (bool) $validated['allow_flexible_applications']);
        }

        if (array_key_exists('enable_normalized_scores', $validated)) {
            if ($validated['enable_normalized_scores']) {
                $areasWithoutScoring = AptitudeArea::where('is_active', true)
                    ->where(function ($q) {
                        $q->where(function ($q2) {
                            $q2->where('scoring_method', 'formula')
                                ->whereNull('formula');
                        })->orWhere(function ($q2) {
                            $q2->where('scoring_method', 'conversion_table')
                                ->whereDoesntHave('percentileConversions');
                        });
                    })
                    ->count();

                if ($areasWithoutScoring > 0) {
                    return redirect()->back()->withErrors([
                        'enable_normalized_scores' => 'Cannot enable auto-compute: All active aptitude areas must have either a formula or a populated conversion table.',
                    ]);
                }
            }
            SystemSetting::set('enable_normalized_scores', (bool) $validated['enable_normalized_scores']);
        }

        if (array_key_exists('admission_slip_enabled', $validated)) {
            SystemSetting::set('admission_slip_enabled', (bool) $validated['admission_slip_enabled']);
        }

        if (array_key_exists('admission_slip_html_template', $validated)) {
            $content = Purifier::clean($validated['admission_slip_html_template'] ?? '', 'admission_slip');
            SystemSetting::set('admission_slip_html_template', $content);
        }

        app(AuditService::class)->log('settings.updated', SystemSetting::class, 0, [], $validated);

        return redirect()->route('admin.settings.index')->with('success', 'Settings saved.');
    }

    public function admissionSlipPreview(Request $request): JsonResponse
    {
        $request->validate([
            'content' => ['required', 'string'],
        ]);

        $content = Purifier::clean($request->input('content'), 'admission_slip');
        $html = app(AdmissionSlipTemplateService::class)->renderHtmlContent($content, [], true);

        return response()->json(['html' => $html]);
    }
}
