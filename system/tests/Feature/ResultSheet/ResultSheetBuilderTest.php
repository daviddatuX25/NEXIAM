<?php

use App\Models\ResultSheet\ResultSheetTemplate;
use App\Models\User;
use App\ResultSheet\Actions\SaveResultSheetTemplate;
use App\ResultSheet\Builder\ResultSheetBuilderCompiler;
use App\ResultSheet\Builder\ResultSheetBuilderSchema;
use RuntimeException;

it('compiles the default result-sheet builder document with complete bindings', function (): void {
    $document = ResultSheetBuilderSchema::defaultDocument();
    $compiler = app(ResultSheetBuilderCompiler::class);
    $result = $compiler->compile($document);

    expect($result['diagnostics'])->toBeEmpty()
        ->and($result['html'])->toContain('scores-rows-placeholder')
        ->and($compiler->usedBindings($document))->toContain('applicant_reference')
        ->and($compiler->usedBindings($document))->toContain('scores_rows')
        ->and($compiler->usedBindings($document))->toContain('counselor_name');
});

it('persists a versioned builder template with compiled html', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $document = ResultSheetBuilderSchema::defaultDocument();

    $response = $this->actingAs($guidance)
        ->post(route('setup.result-templates.store'), [
            'name' => 'Builder Contract Test',
            'mode' => 'html',
            'source_kind' => ResultSheetTemplate::SOURCE_BUILDER,
            'builder_json' => json_encode($document, JSON_THROW_ON_ERROR),
            'content' => '',
            'paper_size' => 'a4',
            'orientation' => 'portrait',
            'logical_unit' => 'full',
            'is_active' => false,
        ]);
    $response->assertRedirect();

    $template = ResultSheetTemplate::query()->where('name', 'Builder Contract Test')->firstOrFail();

    expect($template->source_kind)->toBe(ResultSheetTemplate::SOURCE_BUILDER)
        ->and($template->builder_schema_version)->toBe(ResultSheetBuilderSchema::VERSION)
        ->and($template->compiler_version)->toBe(ResultSheetBuilderSchema::COMPILER_VERSION)
        ->and($template->compiled_html)->toContain('result-sheet-builder')
        ->and($template->content)->toBe($template->compiled_html)
        ->and($template->printChecksum())->toHaveLength(64);
});

it('rejects stale builder edits using lock version', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $template = ResultSheetTemplate::query()->where('name', 'ICAT Results (HTML)')->firstOrFail();

    expect(fn () => app(SaveResultSheetTemplate::class)->update($template, [
        'mode' => 'html',
        'builder_json' => ResultSheetBuilderSchema::defaultDocument(),
        'lock_version' => 0,
    ], $guidance))->toThrow(RuntimeException::class, 'template_conflict');
});

it('normalizes editable typography and appearance deterministically', function (): void {
    $document = ResultSheetBuilderSchema::defaultDocument();
    $document['components'][0]['props']['typography']['font_size_pt'] = 26;
    $document['components'][0]['props']['typography']['text_color'] = '#123456';
    $document['components'][0]['props']['text_styles']['title']['font_size_pt'] = 28;
    $document['components'][0]['props']['text_styles']['title']['text_color'] = '#654321';
    $document['components'][0]['props']['appearance']['border_width_px'] = 2;
    $document['components'][0]['props']['appearance']['border_color'] = '#abcdef';
    $document['components'][0]['props']['spacing']['padding_mm'] = 4;

    $compiler = app(ResultSheetBuilderCompiler::class);
    $first = $compiler->compile($document);
    $second = $compiler->compile($document);

    expect($first['diagnostics'])->toBeEmpty()
        ->and($first['document']['components'][0]['props']['typography']['font_size_pt'])->toBe(26.0)
        ->and($first['document']['components'][0]['props']['appearance']['border_width_px'])->toBe(2.0)
        ->and($first['html'])->toContain('font-size:26pt')
        ->and($first['html'])->toContain('font-size:28pt')
        ->and($first['html'])->toContain('#654321')
        ->and($first['html'])->toContain('#abcdef')
        ->and($first['html'])->toBe($second['html']);
});

it('compiles a validated component template override', function (): void {
    $document = ResultSheetBuilderSchema::defaultDocument();
    $document['component_templates'] = [
        'header' => [
            'revision' => 2,
            'html' => '<section><h1>{{slot:title}}</h1><p>{{slot:subtitle}}</p></section>',
        ],
    ];

    $compiler = app(ResultSheetBuilderCompiler::class);
    $result = $compiler->compile($document);

    expect($result['diagnostics'])->toBeEmpty()
        ->and($result['html'])->toContain('<h1>')
        ->and($result['html'])->toContain('{{institution_name}}')
        ->and($result['document']['component_templates']['header']['revision'])->toBe(2);

    $document['component_templates']['header']['html'] = '<script>alert(1)</script>';
    expect($compiler->compile($document)['diagnostics'])->not->toBeEmpty();

    $document['component_templates']['header']['html'] = '<section>{{slot:title}} {{unknown}}</section>';
    expect(array_column($compiler->compile($document)['diagnostics'], 'code'))->toContain('binding_unknown');
});

it('blocks malformed builder macros, bindings, geometry, and dynamic score overflow', function (): void {
    $document = ResultSheetBuilderSchema::defaultDocument();
    $document['components'][0]['x'] = 'not-a-number';
    $document['components'][1]['props']['text'] = '{{unclosed';
    $document['components'][3]['h'] = 59;
    $document['components'][5]['props']['binding'] = 'not_allowed';

    $codes = array_column(ResultSheetBuilderSchema::diagnostics($document, 8), 'code');

    expect($codes)->toContain('geometry')
        ->and($codes)->toContain('unresolved_macro')
        ->and($codes)->toContain('dynamic_overflow')
        ->and($codes)->toContain('binding_unknown');
});

it('publishes complete generic component capability traits', function (): void {
    foreach (ResultSheetBuilderSchema::componentCapabilities() as $capability) {
        expect($capability)->toHaveKeys(['aspect_ratio', 'resize_axes', 'min_dimensions', 'max_dimensions']);
    }
});
