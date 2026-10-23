<?php

use App\DocumentTemplate\Rendering\DocxTemplateFiller;
use App\DocumentTemplate\Support\DocxMacroRepairer;
use App\DocumentTemplate\Support\DocxTextExtractor;
use PhpOffice\PhpWord\PhpWord;

beforeEach(function (): void {
    $this->repairer = new DocxMacroRepairer;
    $this->extractor = new DocxTextExtractor;
    $this->filler = new DocxTemplateFiller($this->repairer);
});

test('it fills scalar placeholders in a docx document', function (): void {
    $word = new PhpWord;
    $section = $word->addSection();
    $section->addText('Institution: {{institution_name}}');
    $section->addText('Report: {{report_title}}');

    $tempSource = tempnam(sys_get_temp_dir(), 'test_scalar_src_').'.docx';
    $word->save($tempSource, 'Word2007');

    try {
        $filledPath = $this->filler->fill($tempSource, [
            'institution_name' => 'NEXIAM University',
            'report_title' => 'Passing Report',
        ]);

        expect(file_exists($filledPath))->toBeTrue();
        $text = $this->extractor->text($filledPath);
        expect($text)->toContain('Institution: NEXIAM University')
            ->and($text)->toContain('Report: Passing Report')
            ->and($text)->not->toContain('{{');

        @unlink($filledPath);
    } finally {
        @unlink($tempSource);
    }
});

test('it clones and fills repeating table rows in a docx document', function (): void {
    $word = new PhpWord;
    $section = $word->addSection();
    $section->addText('Header: {{title}}');

    $table = $section->addTable();
    $table->addRow();
    $table->addCell()->addText('NOA: {{passers.reference_code}}');
    $table->addCell()->addText('Name: {{passers.applicant_name}}');
    $table->addCell()->addText('Score: {{passers.composite_score}}');

    $tempSource = tempnam(sys_get_temp_dir(), 'test_repeat_src_').'.docx';
    $word->save($tempSource, 'Word2007');

    try {
        $filledPath = $this->filler->fill($tempSource, [
            'title' => 'Official Passers List',
            'passers' => [
                [
                    'reference_code' => 'NOA-2026-001',
                    'applicant_name' => 'Amara Santos',
                    'composite_score' => '94.25',
                ],
                [
                    'reference_code' => 'NOA-2026-002',
                    'applicant_name' => 'Gabriel Reyes',
                    'composite_score' => '88.50',
                ],
            ],
        ], 'passers');

        expect(file_exists($filledPath))->toBeTrue();
        $text = $this->extractor->text($filledPath);
        expect($text)->toContain('Header: Official Passers List')
            ->and($text)->toContain('NOA: NOA-2026-001')
            ->and($text)->toContain('Name: Amara Santos')
            ->and($text)->toContain('Score: 94.25')
            ->and($text)->toContain('NOA: NOA-2026-002')
            ->and($text)->toContain('Name: Gabriel Reyes')
            ->and($text)->toContain('Score: 88.50')
            ->and($text)->not->toContain('{{');

        @unlink($filledPath);
    } finally {
        @unlink($tempSource);
    }
});

test('it clones and fills multiple distinct repeating table regions in a single docx document', function (): void {
    $word = new PhpWord;
    $section = $word->addSection();

    $programSummary = $section->addTable();
    $programSummary->addRow();
    $programSummary->addCell()->addText('Program: {{program_summary.program_name}}');
    $programSummary->addCell()->addText('Count: {{program_summary.applicant_count}}');

    $passers = $section->addTable();
    $passers->addRow();
    $passers->addCell()->addText('NOA: {{passers.reference_code}}');
    $passers->addCell()->addText('Name: {{passers.applicant_name}}');

    $tempSource = tempnam(sys_get_temp_dir(), 'test_multi_repeat_src_').'.docx';
    $word->save($tempSource, 'Word2007');

    try {
        $filledPath = $this->filler->fill($tempSource, [
            'program_summary' => [],
            'passers' => [
                [
                    'reference_code' => 'NOA-2026-001',
                    'applicant_name' => 'Amara Santos',
                ],
                [
                    'reference_code' => 'NOA-2026-002',
                    'applicant_name' => 'Gabriel Reyes',
                ],
            ],
        ], ['program_summary', 'passers']);

        expect(file_exists($filledPath))->toBeTrue();
        $text = $this->extractor->text($filledPath);
        expect($text)->toContain('Program: -')
            ->and($text)->toContain('Count: -')
            ->and($text)->toContain('NOA: NOA-2026-001')
            ->and($text)->toContain('Name: Amara Santos')
            ->and($text)->toContain('NOA: NOA-2026-002')
            ->and($text)->toContain('Name: Gabriel Reyes')
            ->and($text)->not->toContain('{{');

        @unlink($filledPath);
    } finally {
        @unlink($tempSource);
    }
});

test('it handles empty repeating rows by replacing with dashes', function (): void {
    $word = new PhpWord;
    $section = $word->addSection();
    $section->addText('Header: {{title}}');

    $table = $section->addTable();
    $table->addRow();
    $table->addCell()->addText('NOA: {{passers.reference_code}}');
    $table->addCell()->addText('Name: {{passers.applicant_name}}');

    $tempSource = tempnam(sys_get_temp_dir(), 'test_empty_src_').'.docx';
    $word->save($tempSource, 'Word2007');

    try {
        $filledPath = $this->filler->fill($tempSource, [
            'title' => 'Empty Results',
            'passers' => [],
        ], 'passers');

        expect(file_exists($filledPath))->toBeTrue();
        $text = $this->extractor->text($filledPath);
        expect($text)->toContain('Header: Empty Results')
            ->and($text)->not->toContain('{{');

        @unlink($filledPath);
    } finally {
        @unlink($tempSource);
    }
});

test('it discovers repeating row anchor from present table columns when first data key is absent', function (): void {
    $word = new PhpWord;
    $section = $word->addSection();
    $section->addText('Header: {{title}}');

    $table = $section->addTable();
    $table->addRow();
    // Notice: row_no is the first key in data, but the table only contains applicant_name and composite_score.
    $table->addCell()->addText('Name: {{passers.applicant_name}}');
    $table->addCell()->addText('Score: {{passers.composite_score}}');

    $tempSource = tempnam(sys_get_temp_dir(), 'test_anchor_src_').'.docx';
    $word->save($tempSource, 'Word2007');

    try {
        $filledPath = $this->filler->fill($tempSource, [
            'title' => 'Custom Passers',
            'passers' => [
                [
                    'row_no' => 1,
                    'reference_code' => 'NOA-2026-001',
                    'applicant_name' => 'Maria Clara',
                    'composite_score' => '95.50',
                ],
            ],
        ], 'passers');

        expect(file_exists($filledPath))->toBeTrue();
        $text = $this->extractor->text($filledPath);
        expect($text)->toContain('Name: Maria Clara')
            ->and($text)->toContain('Score: 95.50')
            ->and($text)->not->toContain('{{');

        @unlink($filledPath);
    } finally {
        @unlink($tempSource);
    }
});
