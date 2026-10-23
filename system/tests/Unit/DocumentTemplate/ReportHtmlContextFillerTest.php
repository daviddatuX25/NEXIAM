<?php

use App\DocumentTemplate\Rendering\ReportHtmlContextFiller;

test('it escapes dynamic context values containing markup', function (): void {
    $html = '<p>{{ applicant_name }}</p><p>{{ program_title }}</p><p>{{ notes }}</p>';

    $result = (new ReportHtmlContextFiller)->fill($html, [
        'applicant_name' => '<svg onload=alert(1)>',
        'program_title' => '<script>alert(1)</script>',
        'notes' => '<svg><script>run()</script></svg>',
    ]);

    expect($result)
        ->toContain('&lt;svg onload=alert(1)&gt;')
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->toContain('&lt;svg&gt;&lt;script&gt;run()&lt;/script&gt;&lt;/svg&gt;')
        ->not->toContain('<svg')
        ->not->toContain('<script>');
});

test('it renders svg markup raw for approved qr context tokens', function (): void {
    $svg = '<svg viewBox="0 0 100 100"><rect /></svg>';
    $html = '{{ qr_code_svg }}|{{ applicant_locator_qr }}|{{ verification_qr }}';

    $result = (new ReportHtmlContextFiller)->fill($html, [
        'qr_code_svg' => $svg,
        'applicant_locator_qr' => $svg,
        'verification_qr' => $svg,
    ]);

    expect($result)->toBe("$svg|$svg|$svg");
});

test('it strips an xml declaration before rendering approved qr svg markup', function (): void {
    $svg = '<svg viewBox="0 0 100 100"><rect /></svg>';

    $result = (new ReportHtmlContextFiller)->fill('{{ qr_code_svg }}', [
        'qr_code_svg' => '<?xml version="1.0"?>'.$svg,
    ]);

    expect($result)->toBe($svg)
        ->not->toContain('<?xml');
});

test('it escapes non svg values passed to the qr code token', function (): void {
    $value = '<script>alert(1)</script>';

    $result = (new ReportHtmlContextFiller)->fill('{{ qr_code_svg }}', [
        'qr_code_svg' => $value,
    ]);

    expect($result)->toBe(e($value));
});

test('it replaces pagination aliases and removes unmatched tokens', function (): void {
    $result = (new ReportHtmlContextFiller)->fill(
        'Page {{ page_i }} of {{ page_all }}; also {{ pg_i }} / {{ pg_all }}; {{ non_existent }}',
        [],
    );

    expect($result)
        ->toContain('data-nexiam-page-footer="page_i"')
        ->toContain('data-nexiam-page-footer="page_all"')
        ->not->toContain('{{ non_existent }}')
        ->not->toContain('non_existent');
});
