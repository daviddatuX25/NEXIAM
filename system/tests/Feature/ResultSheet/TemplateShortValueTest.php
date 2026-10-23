<?php

use App\ResultSheet\Support\ResultSheetPlaceholderService;
use App\ResultSheet\Support\TemplateShortValue;

it('uses explicit academic-history mappings before deterministic derivation', function (): void {
    expect(TemplateShortValue::resolve('Humanities and Social Sciences'))->toBe(['value' => 'HUMSS', 'basis' => 'mapped'])
        ->and(TemplateShortValue::resolve('Accountancy, Business and Management'))->toBe(['value' => 'ABM', 'basis' => 'mapped'])
        ->and(TemplateShortValue::resolve('Science, Technology, Engineering and Mathematics'))->toBe(['value' => 'STEM', 'basis' => 'mapped'])
        ->and(TemplateShortValue::resolve('Bachelor of Science in Computer Engineering'))->toBe(['value' => 'BSCpE', 'basis' => 'mapped'])
        ->and(TemplateShortValue::resolve('Ilocos Sur College', 'school'))->toBe(['value' => 'ISC', 'basis' => 'derived']);
});

it('recognizes compact acronyms and derives Unicode initials conservatively', function (): void {
    expect(TemplateShortValue::resolve('ispsc', 'school'))->toBe(['value' => 'ISPSC', 'basis' => 'mapped'])
        ->and(TemplateShortValue::resolve('i.s.p.s.c.', 'school'))->toBe(['value' => 'ISPSC', 'basis' => 'preserved'])
        ->and(TemplateShortValue::resolve('I S P S C', 'school'))->toBe(['value' => 'ISPSC', 'basis' => 'preserved'])
        ->and(TemplateShortValue::resolve('ILOCOS SUR COLLEGE', 'school'))->toBe(['value' => 'ISC', 'basis' => 'derived'])
        ->and(TemplateShortValue::resolve('all-capital phrase'))->toBe(['value' => 'ACP', 'basis' => 'derived'])
        ->and(TemplateShortValue::resolve('École supérieure de l’éducation'))->toBe(['value' => 'ÉSDLÉ', 'basis' => 'derived'])
        ->and(TemplateShortValue::resolve('  '))->toBe(['value' => '', 'basis' => 'empty'])
        ->and(TemplateShortValue::resolve('...'))->toBe(['value' => '', 'basis' => 'empty']);
});

it('keeps connective-only and qualification tokens meaningful', function (): void {
    expect(TemplateShortValue::resolve('of the and'))->toBe(['value' => 'OTA', 'basis' => 'derived'])
        ->and(TemplateShortValue::resolve('Caregiving NC II'))->toBe(['value' => 'CNCII', 'basis' => 'derived'])
        ->and(TemplateShortValue::resolve('Caregiving NC III'))->toBe(['value' => 'CNCIII', 'basis' => 'derived']);
});

it('does not select the first conflicting normalized mapping', function (): void {
    expect(TemplateShortValue::resolveUsing('same name', [
        ['key' => 'Same Name', 'value' => 'FIRST'],
        ['key' => ' same   name ', 'value' => 'SECOND'],
    ]))->toBe(['value' => 'SN', 'basis' => 'derived']);

});

it('maps and renders native school and history beside their distinct abbreviations', function (): void {
    $values = app(ResultSheetPlaceholderService::class)->buildReplacements([[
        'name' => 'Test Applicant',
        'reference' => 'AY26-00001',
        'last_school_enrolled' => 'Ilocos Sur College',
        'strand' => 'Humanities and Social Sciences',
        'applicant_type' => 'new',
    ]]);
    $html = app(ResultSheetPlaceholderService::class)->renderRaw(
        '<p>{{last_school_enrolled}} / {{last_school_short}} / {{school_short}}</p><p>{{strand}} / {{strand_or_prev_course_short}} / {{history_short}}</p><p>{{course_applied}} / {{course_applied_code}}</p>',
        $values,
    );

    expect($html)->toContain('Ilocos Sur College / ISC / ISC')
        ->and($html)->toContain('Humanities and Social Sciences / HUMSS / HUMSS');
});

it('renders a transferee previous course separately from the applied program without rewriting native text', function (): void {
    $service = app(ResultSheetPlaceholderService::class);
    $values = $service->buildReplacements([[
        'reference' => 'AY26-00002',
        'last_school_enrolled' => 'School & College',
        'strand' => 'Bachelor of Science in Computer Engineering',
        'strand_or_prev_course' => 'Bachelor of Science in Computer Engineering',
        'applicant_type' => 'transferee',
        'course_applied' => 'Bachelor of Science in Information Technology',
        'course_applied_code' => 'BSIT',
    ]]);
    $html = $service->renderRaw(
        '<p>{{last_school_enrolled}} / {{school_short}}</p><p>{{strand_or_prev_course}} / {{history_short}}</p><p>{{course_applied}} / {{cc}}</p>',
        $values,
    );

    expect($html)->toContain('School & College / SC')
        ->and($html)->toContain('Bachelor of Science in Computer Engineering / BSCpE')
        ->and($html)->toContain('Bachelor of Science in Information Technology / BSIT');
});
