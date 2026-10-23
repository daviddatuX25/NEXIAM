<?php

declare(strict_types=1);

use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;
use PhpOffice\PhpWord\Style\Language;
use PhpOffice\PhpWord\Style\Table as TableStyle;

$systemRoot = dirname(__DIR__, 3);
$autoload = $systemRoot.'/vendor/autoload.php';
if (! is_file($autoload)) {
    fwrite(STDERR, "PhpWord autoloader not found at {$autoload}. Run this script in the configured system/ runtime.\n");
    exit(1);
}
require_once $autoload;

const INSTITUTION = 'ILOCOS SUR POLYTECHNIC STATE COLLEGE';
const CAMPUS = 'Tagudin Campus · Tagudin, Ilocos Sur';
const FORM = 'ISPSC Internal Communication Letterhead — ISPSC-QAD-F032a · Rev. 5 (07-07-2025)';
const CYCLE = 'Academic Year 2026–2027 · First Semester · SYN-CYCLE-2026';
const GREEN = '17633F';
const LIGHT_GREEN = 'E8F0EA';
const GRID = '82948A';
const MUTED = '52645A';

/** @param array<string, mixed> $document */
function createDocument(array $document): void
{
    $word = new PhpWord;
    $word->setDefaultFontName('Arial');
    $word->setDefaultFontSize(9);
    $word->getSettings()->setThemeFontLang(new Language('en-US'));
    $word->getDocInfo()->setCreator('Ilocos Sur Polytechnic State College · Tagudin Campus');
    $word->getDocInfo()->setTitle($document['title']);
    $word->getDocInfo()->setSubject(CYCLE);

    $orientation = $document['orientation'];
    $section = $word->addSection([
        'paperSize' => 'A4',
        'orientation' => $orientation,
        'marginTop' => Converter::cmToTwip(1.5),
        'marginBottom' => Converter::cmToTwip(1.5),
        'marginLeft' => Converter::cmToTwip($orientation === 'landscape' ? 1.2 : 1.5),
        'marginRight' => Converter::cmToTwip($orientation === 'landscape' ? 1.2 : 1.5),
        'headerHeight' => Converter::cmToTwip(0.6),
        'footerHeight' => Converter::cmToTwip(0.6),
    ]);

    $header = $section->addHeader();
    $header->addText(INSTITUTION, ['bold' => true, 'size' => 11, 'color' => GREEN], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
    $header->addText(CAMPUS, ['size' => 8, 'color' => MUTED], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
    $header->addText(FORM, ['size' => 7, 'color' => MUTED], ['alignment' => Jc::CENTER, 'borderBottomSize' => 12, 'borderBottomColor' => GREEN, 'spaceAfter' => 3]);
    $footer = $section->addFooter();
    $footer->addText('Controlled institutional record · Handle applicant information in accordance with institutional privacy requirements.', ['size' => 7, 'color' => MUTED], ['alignment' => Jc::CENTER]);

    $section->addText($document['title'], ['bold' => true, 'size' => 15, 'color' => '152B25'], ['alignment' => Jc::CENTER, 'spaceBefore' => 100, 'spaceAfter' => 3]);
    $section->addText($document['subtitle'], ['size' => 8, 'color' => MUTED], ['alignment' => Jc::CENTER, 'spaceAfter' => 5]);
    addMetaTable($section, $document['metadata']);
    $section->addText($document['notice'], ['size' => 7.5, 'color' => '263E32'], ['spaceBefore' => 5, 'spaceAfter' => 5]);

    foreach ($document['sections'] as $content) {
        $section->addText($content['title'], ['bold' => true, 'size' => 9, 'color' => GREEN], ['spaceBefore' => 5, 'spaceAfter' => 2]);
        if (isset($content['columns'])) {
            addGrid($section, $content['columns'], $content['rows'], $orientation === 'landscape' ? 15400 : 10450);
        } else {
            $section->addText($content['text'], ['size' => 8], ['borderSize' => 4, 'borderColor' => GRID, 'spaceAfter' => 4]);
        }
    }

    addSignatures($section, $document['signatures']);
    $path = __DIR__.'/'.$document['filename'].'.docx';
    IOFactory::createWriter($word, 'Word2007')->save($path);
    fwrite(STDOUT, "Generated {$path}\n");
}

/** @param list<array{0:string,1:string}> $items */
function addMetaTable(Section $section, array $items): void
{
    $table = $section->addTable(['borderSize' => 4, 'borderColor' => 'A8B5AE', 'cellMargin' => 75, 'width' => 100 * 50, 'unit' => TblWidth::PERCENT]);
    foreach (array_chunk($items, 2) as $pair) {
        $table->addRow(null, ['cantSplit' => true]);
        foreach ($pair as [$label, $value]) {
            $cell = $table->addCell(7700, ['valign' => 'center']);
            $cell->addText($label, ['bold' => true, 'size' => 7, 'color' => MUTED]);
            $cell->addText($value, ['size' => 8]);
        }
        if (count($pair) === 1) {
            $table->addCell(7700)->addText(' ', ['size' => 8]);
        }
    }
}

/** @param list<string> $columns @param list<list<string>> $rows */
function addGrid(Section $section, array $columns, array $rows, int $tableWidth): void
{
    $columnWidth = intdiv($tableWidth, count($columns));
    $table = $section->addTable([
        'borderSize' => 5,
        'borderColor' => GRID,
        'cellMargin' => 65,
        'layout' => TableStyle::LAYOUT_FIXED,
        'width' => $tableWidth,
        'unit' => TblWidth::TWIP,
    ]);
    $table->addRow(null, ['tblHeader' => true, 'cantSplit' => true]);
    foreach ($columns as $column) {
        $table->addCell($columnWidth, ['bgColor' => LIGHT_GREEN, 'valign' => 'center'])->addText($column, ['bold' => true, 'size' => 7, 'color' => '173E2B']);
    }
    foreach ($rows as $row) {
        $table->addRow(500, ['cantSplit' => true]);
        foreach ($columns as $index => $_column) {
            $table->addCell($columnWidth, ['valign' => 'top'])->addText($row[$index] ?? ' ', ['size' => 7]);
        }
    }
}

/** @param list<string> $titles */
function addSignatures(Section $section, array $titles): void
{
    $section->addTextBreak(1);
    $table = $section->addTable(['borderSize' => 0, 'cellMargin' => 100, 'width' => 100 * 50, 'unit' => TblWidth::PERCENT]);
    foreach (array_chunk($titles, 2) as $pair) {
        $table->addRow(900, ['cantSplit' => true]);
        foreach ($pair as $title) {
            $table->addCell(7700)->addText("____________________________\n".$title, ['size' => 7], ['alignment' => Jc::CENTER]);
        }
        if (count($pair) === 1) {
            $table->addCell(7700)->addText(' ', ['size' => 7]);
        }
    }
}

$documents = [
    [
        'filename' => 'career-advising-and-referral-slip',
        'title' => 'Career Advising and Referral Slip',
        'subtitle' => 'ISPSC College Admission Test (ICAT) · '.CYCLE,
        'orientation' => 'portrait',
        'metadata' => [
            ['NOA number / Applicant', '{{noa_number}} · {{applicant_name}}'],
            ['Advising date / time', '{{advising_datetime}}'],
            ['ICAT composite score', '{{icat_composite_score}} · {{assessment_reference}}'],
            ['Current course choice', '{{current_course_choice}}'],
            ['Referral destination / reference', '{{referral_destination}} · {{referral_reference}}'],
        ],
        'notice' => 'Transcribe approved, source-verified results and recorded course selection only. This slip does not calculate eligibility or guarantee admission.',
        'sections' => [
            ['title' => 'ICAT aptitude-area results', 'columns' => ['Aptitude area', 'Final score (official record)', 'Record / source reference'], 'rows' => [
                ['General Ability', '{{general_ability_score}}', '{{general_ability_source}}'], ['Verbal', '{{verbal_score}}', '{{verbal_source}}'], ['Numerical', '{{numerical_score}}', '{{numerical_source}}'], ['Spatial', '{{spatial_score}}', '{{spatial_source}}'], ['Perceptual', '{{perceptual_score}}', '{{perceptual_source}}'], ['Manual Dexterity', '{{manual_dexterity_score}}', '{{manual_dexterity_source}}'],
            ]],
            ['title' => 'Course advising outcome', 'columns' => ['Applicant stated selection', 'Course / program endorsed after advising'], 'rows' => [['{{applicant_course_selection}}', '{{endorsed_course_selection}}']]],
            ['title' => 'Counselor notes and next steps', 'text' => '{{counselor_notes}}'],
            ['title' => 'Referral / follow-up', 'text' => '{{referral_or_follow_up}}'],
        ],
        'signatures' => ['Licensed Guidance Counselor (RGC) · Signature / date', 'Head of Guidance & Counseling Services · Review / date', 'Applicant acknowledgment (not an admission decision) · Signature / date', 'University Registrar · Record acknowledgment, if required / date'],
    ],
    [
        'filename' => 'passing-candidates-census',
        'title' => 'Passing Candidates Census',
        'subtitle' => 'Examinees passing the ICAT entrance battery · '.CYCLE,
        'orientation' => 'landscape',
        'metadata' => [['Campus / scope', 'Tagudin Campus · {{reporting_scope}}'], ['Result cutoff / preparer', '{{data_cutoff}} · {{prepared_by}}'], ['Source / result batch', '{{source_reference}} · {{result_batch_reference}}']],
        'notice' => 'Include only examinees with a finalized official result and a passing determination under the applicable approved criterion. This census does not infer a threshold, qualification, or admission offer.',
        'sections' => [
            ['title' => 'Passing candidates', 'columns' => ['NOA number', 'Applicant name', 'Senior High School strand', 'ICAT composite score', 'Qualified program', 'Result reference'], 'rows' => [['{{noa_number}}', '{{applicant_name}}', '{{strand}}', '{{icat_composite_score}}', '{{qualified_program}}', '{{result_reference}}']]],
        ],
        'signatures' => ['Prepared by · Guidance & Counseling Services / date', 'Licensed Guidance Counselor (RGC) · Review / date', 'University Registrar · Approval / date'],
    ],
    [
        'filename' => 'score-and-stanine-distribution',
        'title' => 'Score and Score-Band Distribution Report',
        'subtitle' => 'ICAT aptitude-area descriptive statistics · '.CYCLE,
        'orientation' => 'landscape',
        'metadata' => [['Instrument / version', '{{instrument_name}} · {{instrument_version}}'], ['Population / inclusion rule', '{{population_definition}}'], ['Data cutoff / date', '{{data_cutoff}} · {{report_date}}'], ['Source / prepared by', '{{source_reference}} · {{prepared_by}}'], ['Approved band specification', '{{score_band_specification}}']],
        'notice' => 'Use a compatible approved final-score metric per aptitude area. State exclusions and method. Score-band labels and boundaries must be approved; do not infer cutoffs, calculate a cross-area composite, or report stanines. One final score per aptitude area is the current result contract.',
        'sections' => [
            ['title' => 'Descriptive statistics by aptitude area', 'columns' => ['Aptitude area', 'Count (n)', 'Mean', 'Median', 'Standard deviation', 'Metric / method note'], 'rows' => [['{{aptitude_area}}', '{{score_count}}', '{{mean}}', '{{median}}', '{{standard_deviation}}', '{{metric_and_method_note}}']]],
            ['title' => 'Score-band counts by aptitude area', 'columns' => ['Aptitude area', 'Approved band label', 'Inclusive boundaries', 'Count', 'Share', 'Specification reference'], 'rows' => [['{{aptitude_area}}', '{{band_label}}', '{{inclusive_boundaries}}', '{{band_count}}', '{{band_share}}', '{{band_specification_reference}}']]],
            ['title' => 'Exclusions / limitations', 'text' => '{{exclusions_and_limitations}}'],
        ],
        'signatures' => ['Prepared by · Guidance & Counseling Services / date', 'Reviewed by · Licensed Guidance Counselor (RGC) / date', 'Approved by · University Registrar / date'],
    ],
    [
        'filename' => 'day-of-release-throughput-tally',
        'title' => 'Day of Release Advising Throughput Tally',
        'subtitle' => 'ICAT result release · '.CYCLE,
        'orientation' => 'portrait',
        'metadata' => [['Release date / reporting period', '{{release_date}} · {{reporting_period}}'], ['Venue / session', '{{venue}} · {{release_session}}'], ['Source / tally reference', '{{source_reference}} · {{tally_reference}}'], ['Prepared by / cutoff', '{{prepared_by}} · {{data_cutoff}}']],
        'notice' => 'Count completed advising encounters once. Classify recorded outcomes only from authorized records. Outcome categories are mutually exclusive per classified encounter; this tally is not an admission decision.',
        'sections' => [
            ['title' => 'Release-day counts', 'columns' => ['Measure', 'Count', 'Source / definition', 'Reconciliation note'], 'rows' => [
                ['Consultations conducted', '{{consultations_conducted}}', '{{consultation_count_source}}', '{{consultation_reconciliation_note}}'], ['Direct approvals', '{{direct_approvals}}', '{{direct_approval_source}}', '{{direct_approval_note}}'], ['Course modifications', '{{course_modifications}}', '{{course_modification_source}}', '{{course_modification_note}}'], ['Department referrals', '{{department_referrals}}', '{{department_referral_source}}', '{{department_referral_note}}'], ['Unclassified / pending', '{{unclassified_count}}', '{{unclassified_source}}', '{{unclassified_note}}'],
            ]],
            ['title' => 'Operational notes / exceptions', 'text' => '{{operational_notes}}'],
        ],
        'signatures' => ['Prepared by · Guidance & Counseling Services / date', 'Reviewed by · Head of Guidance & Counseling Services / date'],
    ],
    [
        'filename' => 'exam-absenteeism-report',
        'title' => 'ICAT Exam Absenteeism Report',
        'subtitle' => 'Absent or no-show examinees for rescheduling review · '.CYCLE,
        'orientation' => 'landscape',
        'metadata' => [['Exam date / session scope', '{{exam_date}} · {{session_scope}}'], ['Venue / room', '{{venue}} · {{room}}'], ['Roster / attendance source', '{{roster_reference}} · {{attendance_reference}}'], ['Cutoff / prepared by', '{{data_cutoff}} · {{prepared_by}}']],
        'notice' => 'List rostered examinees whose attendance is confirmed as absent or no-show. Rescheduling is subject to approved examination policy and authorized review; this report does not grant a new attempt or infer eligibility.',
        'sections' => [
            ['title' => 'Absent / no-show examinees', 'columns' => ['NOA number', 'Applicant name', 'Exam date / session', 'Attendance status', 'Evidence reference', 'Reported reason / note', 'Rescheduling review / disposition', 'Reviewer / date'], 'rows' => [['{{noa_number}}', '{{applicant_name}}', '{{exam_date}} · {{session}}', '{{attendance_status}}', '{{attendance_evidence_reference}}', '{{reported_reason}}', '{{rescheduling_review_disposition}}', '{{reviewer}} · {{review_date}}']]],
        ],
        'signatures' => ['Prepared by · Chief Room Proctor / testing staff / date', 'Reviewed by · Licensed Guidance Counselor (RGC) / authorized reviewer / date'],
    ],
];

foreach ($documents as $document) {
    createDocument($document);
}
