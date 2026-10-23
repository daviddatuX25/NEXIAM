<?php

use App\Models\Communications\PublicPolicy;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use Database\Seeders\PublicIntakeConsentDemoSeeder;

it('provides confirmation print URL and serves official admission slip document template', function (): void {
    seedE2Foundation();

    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $window = ApplicationWindow::query()->where('code', 'SYN-WINDOW-2026-A')->firstOrFail();
    (new PublicIntakeConsentDemoSeeder)->run();
    $institution = Institution::current();
    $policy = PublicPolicy::designatedIntake((int) $institution->getKey())
        ?? throw new RuntimeException('A designated public intake policy is required.');
    $revision = $policy->publishedRevision
        ?? throw new RuntimeException('The designated policy must have a published revision.');

    $submitResponse = $this->post(route('apply.submit'), [
        'academic_cycle_id' => $cycle->id,
        'application_window_id' => $window->id,
        'public_policy_id' => $policy->id,
        'public_policy_revision_id' => $revision->id,
        'public_policy_revision_digest' => $revision->contentDigest(),
        'first_name' => 'Geraldine',
        'last_name' => 'Tos',
        'email' => 'geraldine.tos@example.test',
        'birthdate' => '2001-01-01',
        'sex' => 'female',
        'applicant_type' => 'new',
        'program_preference_1' => 'BSIT',
        'terms_accepted' => true,
        'last_school_enrolled' => 'Sample SHS',
        'strand' => 'STEM',
        'grades' => ['GWA' => '1.50', 'ENG' => '88', 'FIL' => '90', 'MATH' => '85', 'SCI' => '87'],
        'city' => 'Tagudin',
    ]);

    $submitResponse->assertRedirect(route('apply.confirmation'));
    $referenceCode = (string) session('reference_code');
    expect($referenceCode)->not->toBeEmpty();

    // Confirmation page has print_url prop
    $confirmResponse = $this->get(route('apply.confirmation'));
    $confirmResponse->assertOk()->assertInertia(fn ($page) => $page
        ->component('Admission/Confirmation')
        ->where('reference_code', $referenceCode)
        ->where('print_url', fn (string $url): bool => str_contains($url, '/apply/confirmation/print') && str_contains($url, $referenceCode))
    );

    // Reloading in the same session preserves print_url
    $reloadResponse = $this->get(route('apply.confirmation'));
    $reloadResponse->assertOk()->assertInertia(fn ($page) => $page
        ->component('Admission/Confirmation')
        ->where('reference_code', $referenceCode)
        ->where('print_url', fn (string $url): bool => str_contains($url, '/apply/confirmation/print'))
    );

    // Standalone print route renders official document template
    $hash = hash_hmac('sha256', $referenceCode, (string) config('app.key'));
    $printResponse = $this->get(route('apply.confirmation.print', [
        'reference' => $referenceCode,
        'hash' => $hash,
    ]));

    $printResponse->assertOk()->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    $html = $printResponse->getContent();
    expect($html)
        ->toContain('Applicant QR Pass')
        ->toContain('APPLICANT COPY')
        ->toContain('OFFICE / BACKUP COPY')
        ->toContain('Geraldine Tos')
        ->toContain($referenceCode)
        ->toContain('BSIT')
        ->toContain('data-applicant-qr-print="true"')
        ->toContain('CUT HERE')
        ->toContain('window.print()');

    // Embedded print route (for iframe preview and silent printing)
    $embeddedResponse = $this->get(route('apply.confirmation.print', [
        'reference' => $referenceCode,
        'hash' => $hash,
        'embedded' => '1',
    ]));

    $embeddedResponse->assertOk();
    $embeddedHtml = $embeddedResponse->getContent();
    expect($embeddedHtml)
        ->toContain('data-applicant-qr-print="true"')
        ->toContain('Applicant copy QR code')
        ->toContain('Office backup copy QR code')
        ->not->toContain('<nav class="print-actions"')
        ->not->toContain('window.print()');

    // PDF download route
    $pdfResponse = $this->get(route('apply.confirmation.print', [
        'reference' => $referenceCode,
        'hash' => $hash,
        'format' => 'pdf',
    ]));

    $pdfResponse->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($pdfResponse->getContent())->toStartWith('%PDF-');

    // Tampered reference or invalid hash without session rejects with 404
    $this->flushSession();
    $this->get(route('apply.confirmation.print', [
        'reference' => $referenceCode,
        'hash' => 'invalid-hash-token',
    ]))->assertNotFound();

    $this->get(route('apply.confirmation.print', [
        'reference' => 'APP-NONEXISTENT',
        'hash' => hash_hmac('sha256', 'APP-NONEXISTENT', (string) config('app.key')),
    ]))->assertNotFound();
});
