<?php

namespace App\Http\Controllers;

use App\Http\Requests\MarkSlipPrintedRequest;
use App\Models\AdmissionSlipTemplate;
use App\Models\Application;
use App\Services\AdmissionSlipPrintService;
use App\Services\AdmissionSlipTemplateService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdmissionSlipPrintController extends Controller
{
    public function __construct(
        private AdmissionSlipPrintService $printService,
        private AdmissionSlipTemplateService $templateService
    ) {}

    public function index(): Response
    {
        $applications = Application::query()
            ->where('status', 'accepted')
            ->whereHas('applicant')
            ->with(['applicant', 'coursePreference1', 'coursePreference2', 'coursePreference3'])
            ->orderBy('reference_number')
            ->get()
            ->map(function (Application $app) {
                $name = trim(implode(' ', array_filter([$app->first_name, $app->middle_name, $app->last_name, $app->suffix])));
                return [
                    'id' => $app->id,
                    'application_id' => $app->id,
                    'applicant_id' => $app->applicant?->id ?? null,
                    'name' => $name ?: '—',
                    'reference' => $app->reference_number ?? '—',
                    'printed' => (bool) $app->admission_slip_printed_at,
                ];
            })
            ->values()
            ->all();

        return Inertia::render('Applications/PrintSlips', [
            'applications' => $applications,
        ]);
    }

    public function markPrinted(MarkSlipPrintedRequest $request): RedirectResponse
    {
        $this->printService->markSlipPrinted(
            $request->validated('application_ids'),
            $request->validated('printed')
        );

        return redirect()->back()->with('success', 'Printed status updated.');
    }

    public function single(Application $application): Response
    {
        $application->load(['applicant', 'coursePreference1', 'coursePreference2', 'coursePreference3']);

        if ($application->status !== 'accepted' || ! $application->applicant) {
            return Inertia::render('Applications/AdmissionSlipSingle', [
                'applicationId' => (string) $application->id,
                'printed' => (bool) $application->admission_slip_printed_at,
                'applicant' => ['id' => null, 'name' => '—', 'reference' => '—'],
                'templateHtml' => null,
                'templateError' => 'Application not accepted or has no applicant. Admission slip is only available for accepted applications.',
                'paperSize' => 'a4',
                'orientation' => 'portrait',
                'logicalUnit' => 'full',
                'paperOptions' => [],
            ]);
        }

        $template = AdmissionSlipTemplate::where('is_active', true)->first();

        if (! $template) {
            return Inertia::render('Applications/AdmissionSlipSingle', [
                'applicationId' => (string) $application->id,
                'printed' => (bool) $application->admission_slip_printed_at,
                'applicant' => [
                    'id' => $application->applicant->id,
                    'name' => trim(implode(' ', array_filter([$application->first_name, $application->middle_name, $application->last_name, $application->suffix]))),
                    'reference' => $application->reference_number,
                ],
                'templateHtml' => null,
                'templateError' => 'No active admission slip template. Please create one in Admin > Admission slip templates.',
                'paperSize' => 'a4',
                'orientation' => 'portrait',
                'logicalUnit' => 'full',
                'paperOptions' => [],
            ]);
        }

        $data = $this->buildAdmissionSlipData($application);
        $templateHtml = $this->templateService->render($template, [$data], false);

        $paperOptions = [
            'a4' => ['portrait' => 'A4 Portrait', 'landscape' => 'A4 Landscape'],
            'legal' => ['portrait' => 'Legal Portrait', 'landscape' => 'Legal Landscape'],
            'letter' => ['portrait' => 'Letter Portrait', 'landscape' => 'Letter Landscape'],
        ];

        return Inertia::render('Applications/AdmissionSlipSingle', [
            'applicationId' => (string) $application->id,
            'printed' => (bool) $application->admission_slip_printed_at,
            'applicant' => [
                'id' => $application->applicant->id,
                'name' => trim(implode(' ', array_filter([$application->first_name, $application->middle_name, $application->last_name, $application->suffix]))),
                'reference' => $application->reference_number,
            ],
            'templateHtml' => $templateHtml,
            'templateError' => null,
            'paperSize' => $template->paper_size ?? 'a4',
            'orientation' => $template->orientation ?? 'portrait',
            'logicalUnit' => $template->logical_unit ?? 'full',
            'paperOptions' => $paperOptions,
        ]);
    }

    public function printBulk(): Response
    {
        $ids = array_filter(array_map('intval', explode(',', request()->query('ids', ''))));
        $template = AdmissionSlipTemplate::where('is_active', true)->first();

        $flatPaperOptions = ['a4' => 'A4', 'legal' => 'Legal', 'letter' => 'Letter'];

        if (! $template) {
            return Inertia::render('Applications/AdmissionSlipBulk', [
                'applicationIds' => $ids,
                'applicants' => [],
                'sheetsHtml' => [],
                'templateError' => 'No active admission slip template. Please create one in Admin > Admission slip templates.',
                'paperSize' => 'a4',
                'orientation' => 'portrait',
                'logicalUnit' => 'full',
                'paperOptions' => $flatPaperOptions,
            ]);
        }

        $applications = Application::query()
            ->whereIn('id', $ids)
            ->where('status', 'accepted')
            ->whereHas('applicant')
            ->with(['applicant', 'coursePreference1', 'coursePreference2', 'coursePreference3'])
            ->orderBy('reference_number')
            ->get();

        $applicantsData = [];
        $chunkSize = str_starts_with($template->logical_unit ?? 'full', 'half_') ? 2 : 1;
        $sheetsHtml = [];
        $chunks = $applications->chunk($chunkSize);

        foreach ($chunks as $chunk) {
            $dataBatch = $chunk->map(fn (Application $app) => $this->buildAdmissionSlipData($app))->values()->all();
            if (count($dataBatch) === 2) {
                // Half layout: render each slip separately and concatenate so the page has two root elements (mirror GradingPrintController).
                $html1 = $this->templateService->render($template, [$dataBatch[0]], false);
                $html2 = $this->templateService->render($template, [$dataBatch[1]], false);
                $sheetsHtml[] = $html1 . $html2;
            } else {
                $sheetsHtml[] = $this->templateService->render($template, $dataBatch, false);
            }
        }

        foreach ($applications as $app) {
            $applicantsData[] = [
                'id' => $app->id,
                'name' => trim(implode(' ', array_filter([$app->first_name, $app->middle_name, $app->last_name, $app->suffix]))) ?: '—',
                'reference' => $app->reference_number ?? '—',
            ];
        }

        return Inertia::render('Applications/AdmissionSlipBulk', [
            'applicationIds' => $ids,
            'applicants' => $applicantsData,
            'sheetsHtml' => $sheetsHtml,
            'templateError' => null,
            'paperSize' => $template->paper_size ?? 'a4',
            'orientation' => $template->orientation ?? 'portrait',
            'logicalUnit' => $template->logical_unit ?? 'full',
            'paperOptions' => $flatPaperOptions,
        ]);
    }

    /**
     * @return array{reference_number: string, full_name: string, birthdate: string, sex: string, course_1: string, course_2: string, course_3: string}
     */
    private function buildAdmissionSlipData(Application $application): array
    {
        $fullName = trim(implode(' ', array_filter([
            $application->first_name,
            $application->middle_name,
            $application->last_name,
            $application->suffix,
        ])));

        return [
            'reference_number' => $application->reference_number ?? '—',
            'full_name' => $fullName ?: '—',
            'birthdate' => $application->birthdate?->format('F j, Y') ?? '—',
            'sex' => ucfirst($application->sex ?? ''),
            'course_1' => $application->coursePreference1?->name ?? '—',
            'course_2' => $application->coursePreference2?->name ?? '—',
            'course_3' => $application->coursePreference3?->name ?? '—',
        ];
    }
}
