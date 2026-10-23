<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\DocumentTemplate\DocumentSurfaceRegistry;
use App\DocumentTemplate\Rendering\ReportHtmlContextFiller;
use App\Models\Reports\ReportTemplate;
use Database\Seeders\ReportTemplateSeeder;

it('renders applicant my-qr page with an own-scope print URL', function (): void {
    seedE2Foundation();
    $submission = submitSyntheticApplicant('applicant-qr-print-page@example.test');
    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $submission['access_token'],
        '2001-01-01',
    );

    $this->actingAs($applicant)
        ->get('/applicant/qr')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/MyQr')
            ->where('print_url', fn (string $url): bool => str_contains($url, '/applicant/qr/print'))
            ->where('page_state', 'ready')
        );
});

it('renders a usable standalone applicant QR pass without printing automatically', function (): void {
    seedE2Foundation();
    $submission = submitSyntheticApplicant('applicant-qr-print-html@example.test');
    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $submission['access_token'],
        '2001-01-01',
    );

    $response = $this->actingAs($applicant)->get('/applicant/qr/print');

    $response->assertOk()->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    expect($response->getContent())
        ->toContain('Applicant QR Pass')
        ->toContain('APPLICANT COPY')
        ->toContain('OFFICE / BACKUP COPY')
        ->toContain('data-applicant-qr-print="true"')
        ->toContain('aspect-ratio:1/1')
        ->toContain('CUT HERE')
        ->toContain('window.print()')
        ->not->toContain('window.addEventListener')
        ->not->toContain('window.open');
});

it('serves embedded applicant QR HTML without print controls or automatic printing', function (): void {
    seedE2Foundation();
    $submission = submitSyntheticApplicant('applicant-qr-embedded@example.test');
    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $submission['access_token'],
        '2001-01-01',
    );

    $response = $this->actingAs($applicant)->get('/applicant/qr/print?embedded=1');
    $html = $response->getContent();

    $response->assertOk();
    expect($html)
        ->toContain('data-applicant-qr-print="true"')
        ->toContain('Applicant copy QR code')
        ->toContain('Office backup copy QR code')
        ->not->toContain('<nav class="print-actions"')
        ->not->toContain('window.print()');
});

it('renders the active registrar HTML source without changing it', function (): void {
    seedE2Foundation();
    $submission = submitSyntheticApplicant('applicant-qr-custom-source@example.test');
    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $submission['access_token'],
        '2001-01-01',
    );
    $template = ReportTemplate::query()
        ->where('surface_key', 'admission_slip')
        ->where('is_active', true)
        ->firstOrFail();
    $template->update([
        'mode' => ReportTemplate::MODE_HTML,
        'source_kind' => ReportTemplate::SOURCE_HTML,
        'compiled_html' => '<section data-registrar-template="active">Registrar-authored pass <img src="{{applicant_locator_qr}}" alt="Custom pass QR"></section>',
        'content' => '<section data-registrar-template="active">Registrar-authored pass <img src="{{applicant_locator_qr}}" alt="Custom pass QR"></section>',
    ]);

    $response = $this->actingAs($applicant)->get('/applicant/qr/print?embedded=1');

    $response->assertOk()->assertSee('data-registrar-template="active"', false)->assertSee('Registrar-authored pass');
    expect($template->fresh()->source_kind)->toBe(ReportTemplate::SOURCE_HTML)
        ->and($template->fresh()->content)->toBe('<section data-registrar-template="active">Registrar-authored pass <img src="{{applicant_locator_qr}}" alt="Custom pass QR"></section>');
});

it('keeps default two-copy QR geometry inside selected paper bounds', function (): void {
    $surface = DocumentSurfaceRegistry::resolve('admission_slip');
    expect($surface)->not->toBeNull();

    foreach ([['a4', 'portrait'], ['letter', 'landscape'], ['legal', 'portrait']] as [$paper, $orientation]) {
        $blueprint = $surface->defaultBlueprint($paper, $orientation);
        $components = collect($blueprint['components'])->where('type', 'dynamic_qr')->values();
        $page = $blueprint['page'];
        $maxWidth = $page['width_mm'] - ($page['margin_mm'] * 2);
        $maxHeight = $page['height_mm'] - ($page['margin_mm'] * 2);

        expect($components)->toHaveCount(2);
        foreach ($components as $component) {
            expect($component['w'])->toBe($component['h'])
                ->and($component['x'] + $component['w'])->toBeLessThanOrEqual($maxWidth)
                ->and($component['y'] + $component['h'])->toBeLessThanOrEqual($maxHeight);
        }
    }
});

it('does not replace an existing customized builder when its cached HTML is stale', function (): void {
    seedE2Foundation();
    $template = ReportTemplate::query()
        ->where('surface_key', 'admission_slip')
        ->where('is_active', true)
        ->firstOrFail();
    $customBlueprint = $template->builder_json;
    $customBlueprint['components'][0]['props']['text'] = 'Registrar customization';
    $compiledHtml = $template->compiled_html;

    $template->update(['builder_json' => $customBlueprint]);
    (new ReportTemplateSeeder)->run();

    expect($template->fresh()->builder_json)->toBe($customBlueprint)
        ->and($template->fresh()->compiled_html)->toBe($compiledHtml);
});

it('prints applicant approved qr pass as downloadable pdf with format=pdf', function (): void {
    seedE2Foundation();
    $submission = submitSyntheticApplicant('applicant-qr-print-pdf@example.test');
    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $submission['access_token'],
        '2001-01-01',
    );

    $response = $this->actingAs($applicant)->get('/applicant/qr/print?format=pdf');

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF-');
});

it('denies unauthenticated access to applicant qr print', function (): void {
    seedE2Foundation();

    $response = $this->get('/applicant/qr/print');
    expect($response->status())->toBeIn([302, 401]);
});

it('authorizes applicant to run document printing preflight and render for own attempt', function (): void {
    seedE2Foundation();
    $submission = submitSyntheticApplicant('applicant-doc-print-self@example.test');
    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $submission['access_token'],
        '2001-01-01',
    );
    $attemptId = (int) $submission['attempt']->id;

    $this->actingAs($applicant)
        ->getJson(route('document-printing.scope-options', [
            'surface_key' => 'admission_slip',
            'grain' => 'student',
        ]))
        ->assertOk()
        ->assertJsonStructure(['cycles', 'programs', 'applicants', 'items']);

    $this->actingAs($applicant)
        ->postJson(route('document-printing.render'), [
            'surface_key' => 'admission_slip',
            'grain' => 'student',
            'attempt_ids' => [$attemptId],
        ])
        ->assertOk()
        ->assertJsonStructure(['status', 'job_id', 'download_url', 'pdf_base64']);
});

it('does not mutate active template when rendering applicant qr print', function (): void {
    seedE2Foundation();
    $submission = submitSyntheticApplicant('applicant-no-mutate-template@example.test');
    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $submission['access_token'],
        '2001-01-01',
    );

    $template = ReportTemplate::query()
        ->where('surface_key', 'admission_slip')
        ->where('is_active', true)
        ->firstOrFail();

    $originalUpdatedAt = $template->updated_at;
    $originalContent = $template->content;

    $this->actingAs($applicant)->get('/applicant/qr/print')->assertOk();

    $fresh = $template->fresh();
    expect($fresh->updated_at?->timestamp)->toBe($originalUpdatedAt?->timestamp)
        ->and($fresh->content)->toBe($originalContent);
});

it('prevents applicant from accessing another applicants print job', function (): void {
    seedE2Foundation();
    $victim = submitSyntheticApplicant('victim-job@example.test', 'Victim');
    $attacker = submitSyntheticApplicant('attacker-job@example.test', 'Attacker');

    $victimUser = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $victim['access_token'],
        '2001-01-01',
    );
    $attackerUser = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $attacker['access_token'],
        '2001-01-01',
    );

    $renderRes = $this->actingAs($victimUser)->postJson(route('document-printing.render'), [
        'surface_key' => 'admission_slip',
        'grain' => 'student',
        'attempt_ids' => [(int) $victim['attempt']->id],
    ])->assertOk();

    $jobId = $renderRes->json('job_id');
    expect($jobId)->not->toBeNull();

    // Attacker cannot view job status
    $this->actingAs($attackerUser)
        ->getJson("/document-printing/jobs/{$jobId}")
        ->assertForbidden();

    // Attacker cannot download job PDF
    $this->actingAs($attackerUser)
        ->get("/document-printing/jobs/{$jobId}/download")
        ->assertForbidden();

    // Attacker cannot mark victim job printed
    $this->actingAs($attackerUser)
        ->postJson("/document-printing/jobs/{$jobId}/mark-printed", ['job_id' => $jobId])
        ->assertForbidden();
});

it('prevents applicant from forging printed audit for victim attempt', function (): void {
    seedE2Foundation();
    $victim = submitSyntheticApplicant('victim-mark-printed@example.test', 'VictimMark');
    $attacker = submitSyntheticApplicant('attacker-mark-printed@example.test', 'AttackerMark');

    $attackerUser = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $attacker['access_token'],
        '2001-01-01',
    );

    $template = ReportTemplate::query()
        ->where('surface_key', 'admission_slip')
        ->where('is_active', true)
        ->firstOrFail();

    $this->actingAs($attackerUser)
        ->postJson('/document-printing/mark-printed', [
            'template_id' => $template->id,
            'attempt_ids' => [(int) $victim['attempt']->id],
            'filename' => 'victim.pdf',
        ])
        ->assertForbidden();
});

it('escapes raw svg inside applicant name in ReportHtmlContextFiller', function (): void {
    $filler = new ReportHtmlContextFiller;
    $template = '<p>{{applicant_name}}</p><div>{{qr_code_svg}}</div>';
    $context = [
        'applicant_name' => '<svg onload="alert(1)">Test</svg>',
        'qr_code_svg' => '<svg viewBox="0 0 100 100"><path d="M0 0"/></svg>',
    ];

    $filled = $filler->fill($template, $context);

    // applicant_name must be escaped
    expect($filled)->toContain('&lt;svg onload=&quot;alert(1)&quot;&gt;Test&lt;/svg&gt;')
        ->not->toContain('<svg onload="alert(1)">')
        // trusted qr_code_svg remains raw unescaped SVG
        ->and($filled)->toContain('<svg viewBox="0 0 100 100"><path d="M0 0"/></svg>');
});
