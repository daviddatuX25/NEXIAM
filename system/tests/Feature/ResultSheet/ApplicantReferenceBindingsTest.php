<?php

use App\Models\Admission\AdmissionAttempt;
use App\Models\Reports\ReportTemplate;
use App\Reports\Services\AdmissionSlipPrintService;
use App\ResultSheet\Support\ResultSheetPlaceholderService;
use Illuminate\Http\Request;
use Tests\TestCase;

function applicantReferenceBindingAttempt(TestCase $test): AdmissionAttempt
{
    $fixture = buildGradingFixture($test, applicantCount: 1);
    $attempt = $fixture['attempts'][0];
    $attempt->update(['reference_code' => 'AY26-00007']);

    return $attempt->fresh(['academicCycle', 'identity']);
}

test('result sheet placeholder renders canonical, padded, and compact applicant references', function (): void {
    $attempt = applicantReferenceBindingAttempt($this);
    $replacements = app(ResultSheetPlaceholderService::class)->buildReplacements([[
        'reference' => $attempt->reference_code,
        'name' => 'Applicant',
        'scores' => [],
    ]]);
    $html = app(ResultSheetPlaceholderService::class)->renderRaw(
        '{{reference_code}} {{applicant_reference}} {{examinee_number}} {{sequence_number}}',
        $replacements,
    );

    expect($html)->toContain('AY26-00007 AY26-00007 00007 7');
});

test('admission slip context renders references and optional native and abbreviated academic history', function (): void {
    $attempt = applicantReferenceBindingAttempt($this);
    $attempt->forceFill([
        'last_school_enrolled' => 'Ilocos Sur College',
        'strand' => 'Humanities and Social Sciences',
    ])->save();
    $attempt->loadMissing('academicCycle.institution');
    ReportTemplate::query()->create([
        'institution_id' => $attempt->institution_id,
        'surface_key' => 'admission_slip',
        'name' => 'Compact Slip Test',
        'source_kind' => ReportTemplate::SOURCE_HTML,
        'paper_size' => 'a4',
        'orientation' => 'portrait',
        'is_active' => true,
        'content' => '<div>{{reference_code}} | {{examinee_number}} | {{sequence_number}} | {{last_school_enrolled}} | {{last_school_short}} | {{strand_or_prev_course}} | {{strand_or_prev_course_short}} | {{history_short}}</div>',
        'compiled_html' => '<div>{{reference_code}} | {{examinee_number}} | {{sequence_number}} | {{last_school_enrolled}} | {{last_school_short}} | {{strand_or_prev_course}} | {{strand_or_prev_course_short}} | {{history_short}}</div>',
    ]);
    $service = app(AdmissionSlipPrintService::class);

    $response = $service->renderResponse($attempt, Request::create('/'));
    expect($response->getContent())->toContain(
        'AY26-00007 | 00007 | 7 | Ilocos Sur College | ISC | Humanities and Social Sciences | HUMSS | HUMSS',
    );
});
