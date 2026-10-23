<?php

declare(strict_types=1);

use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;
use PhpOffice\PhpWord\Style\Language;
use PhpOffice\PhpWord\Style\Table as TableStyle;

$systemRoot = dirname(__DIR__, 3);
$autoload = $systemRoot.'/vendor/autoload.php';
if (! is_file($autoload)) {
    fwrite(STDERR, "Composer autoloader not found at {$autoload}. Run this script in the configured system/ runtime.\n");
    exit(1);
}
require_once $autoload;

$outputDirectory = isset($argv[1]) ? rtrim($argv[1], DIRECTORY_SEPARATOR) : __DIR__;
if (! is_dir($outputDirectory) && ! mkdir($outputDirectory, 0775, true) && ! is_dir($outputDirectory)) {
    fwrite(STDERR, "Unable to create output directory: {$outputDirectory}\n");
    exit(1);
}

const INSTITUTION = 'ILOCOS SUR POLYTECHNIC STATE COLLEGE';
const CAMPUS = 'Tagudin Campus · Tagudin, Ilocos Sur';
const FORM_CODE = 'ISPSC Internal Communication Letterhead — ISPSC-QAD-F032a · Rev. 5 (07-07-2025)';
const CYCLE = 'Academic Year 2026–2027 · First Semester · SYN-CYCLE-2026';

$programs = ['BSIT', 'BSMath', 'BA-EL', 'BA-Psych', 'BASS', 'BPA', 'BSEd (English)', 'BSEd (Math)', 'BEEd', 'BPEd', 'BSBA', 'BSEntrep'];

$documents = [
    'applications-census' => [
        'title' => 'APPLICATIONS CENSUS',
        'subtitle' => 'ISPSC College Admission Test (ICAT) · Registrar Office · Window 1 verification',
        'metadata' => ['Report date: ____________________', 'Prepared by: ____________________', 'Total applications: __________', 'Window 1 pending: __________'],
        'notice' => 'Verify applicant identity and academic credentials at Registrar Window 1. Record source-verified details only. Admission status must reflect the authoritative record.',
        'columns' => ['NOA No.', 'Applicant name', 'Senior High School', 'Strand', 'GWA', 'First choice', 'Status', 'Window 1 verification notes'],
        'widths' => [11, 14, 15, 8, 6, 12, 9, 25],
        'rows' => 8,
        'signatures' => ['University Registrar', 'Registrar Window 1 · Verified by', 'Campus Executive Director'],
    ],
    'course-quota-qualifiers' => [
        'title' => 'COURSE QUOTA & QUALIFIERS ROSTER',
        'subtitle' => 'ISPSC College Admission Test (ICAT) · Official program qualification and reservation summary',
        'metadata' => ['As of: ____________________', 'Target quota total: __________', 'Committed reservations: __________', 'Remaining slots: __________'],
        'notice' => 'Include only authoritative qualification and reservation decisions. A committed reservation is not an enrollment clearance. Record approval references for any authorized quota adjustment.',
        'columns' => ['Degree program', 'Target quota', 'Official qualifiers', 'Committed reservations', 'Remaining slots', 'Approval / variance notes'],
        'widths' => [16, 13, 15, 18, 14, 24],
        'rows' => 0,
        'programs' => $programs,
        'detailColumns' => ['NOA No.', 'Applicant name', 'Strand', 'GWA', 'Degree program', 'Qualification status', 'Reservation status'],
        'detailWidths' => [13, 19, 10, 8, 16, 17, 17],
        'detailRows' => 7,
        'signatures' => ['University Registrar', 'College Dean · College: ____________________', 'Campus Executive Director'],
    ],
    'enrollment-clearance-roster' => [
        'title' => 'ENROLLMENT CLEARANCE ROSTER',
        'subtitle' => 'ISPSC College Admission Test (ICAT) · Officially cleared examinees · Registrar Office',
        'metadata' => ['Roster date: ____________________', 'Authority reference: ____________________', 'Officially cleared: __________', 'Form 138 (SF9) handoff pending: __________'],
        'notice' => 'Use only after official clearance is recorded. Verify identity before enrollment stamping. Record the actual Form 138 (SF9) physical handoff and receipt; admission, examination or quota status alone does not establish clearance.',
        'columns' => ['NOA No.', 'Applicant name', 'Program', 'LRN (masked)', 'GWA', 'Clearance status / date', 'Enrollment stamp', 'Form 138 (SF9) handoff', 'Receiving office / person', 'Notes / receipt ref.'],
        'widths' => [10, 13, 8, 9, 6, 12, 10, 12, 10, 10],
        'rows' => 7,
        'signatures' => ['University Registrar', 'Registrar Window 1 · Records verified by', 'Campus Executive Director'],
    ],
    'feeder-school-and-origin-census' => [
        'title' => 'FEEDER-SCHOOL & APPLICANT-ORIGIN CENSUS',
        'subtitle' => 'ISPSC College Admission Test (ICAT) · Registrar Office · Geographic and school-origin summary',
        'metadata' => ['Reporting period: ____________________', 'Applications included: __________', 'Distinct feeder schools: __________', 'Source date: ____________________'],
        'notice' => 'Aggregate counts by source-verified school and municipality across Ilocos Sur and neighboring municipalities. Do not infer or create school or municipality names; disclose unresolved origins and exclusions.',
        'columns' => ['Origin area', 'Municipality / city (verified)', 'Senior High School (verified)', 'Applicants', 'Share of census', 'Verification notes'],
        'widths' => [16, 18, 25, 11, 13, 17],
        'rows' => 8,
        'signatures' => ['University Registrar', 'Compiled by · Registrar Office', 'Campus Executive Director'],
    ],
    'discrepancy-and-ingestion-audit' => [
        'title' => 'DISCREPANCY & INGESTION AUDIT LOG',
        'subtitle' => 'ISPSC College Admission Test (ICAT) · Registrar Office · Admission record ingestion',
        'metadata' => ['Audit reference: ____________________', 'Ingestion batch: ____________________', 'Total discrepancies: __________', 'Reviewer: ____________________'],
        'notice' => 'Retain provenance, reason, evidence reference, reviewer attribution, timestamp and disposition for every correction. Flag duplicate NOA attempts for resolution; do not merge records based on similarity. Mask LRN except where approved review requires full identification.',
        'columns' => ['Case ID', 'Issue type', 'NOA No. / record ref.', 'Applicant ref. (masked)', 'Field', 'Observed / submitted value', 'Verified / corrected value', 'Reason / evidence ref.', 'Disposition', 'Reviewer / timestamp'],
        'widths' => [7, 10, 11, 10, 7, 12, 12, 12, 9, 10],
        'rows' => 7,
        'signatures' => ['Reviewed by · Registrar Office', 'University Registrar', 'Campus Executive Director'],
    ],
];

function addLetterhead(PhpWord $word, Section $section): void
{
    $header = $section->addHeader();
    $table = $header->addTable(['borderBottomSize' => 12, 'borderBottomColor' => '17633F', 'cellMargin' => 80]);
    $table->addRow();
    $table->addCell(2600)->addText('ISPSC\nTagudin Campus', ['bold' => true, 'color' => '17633F', 'size' => 9]);
    $table->addCell(9200)->addText(INSTITUTION."\n".CAMPUS, ['bold' => true, 'size' => 11], ['alignment' => Jc::CENTER]);
    $table->addCell(3500)->addText(FORM_CODE, ['size' => 7, 'color' => '52645D'], ['alignment' => Jc::END]);

    $footer = $section->addFooter();
    $footer->addText('Controlled internal record · Handle applicant information in accordance with institutional privacy requirements.', ['size' => 7, 'color' => '52645D']);
    $footer->addPreserveText('Page {PAGE} of {NUMPAGES}', ['size' => 7, 'color' => '52645D'], ['alignment' => Jc::END]);
}

function addMetadata(Section $section, array $items): void
{
    $table = $section->addTable(['borderSize' => 4, 'borderColor' => 'A8B5AE', 'cellMargin' => 90]);
    foreach (array_chunk($items, 2) as $pair) {
        $table->addRow();
        foreach ($pair as $item) {
            $table->addCell(7700)->addText($item, ['size' => 8]);
        }
        if (count($pair) === 1) {
            $table->addCell(7700)->addText(' ', ['size' => 8]);
        }
    }
}

/** @param list<string> $columns @param list<int> $widths @param list<list<string>> $rows */
function addGrid(Section $section, array $columns, array $widths, array $rows): void
{
    $table = $section->addTable([
        'borderSize' => 5,
        'borderColor' => '82948A',
        'cellMargin' => 65,
        'layout' => TableStyle::LAYOUT_FIXED,
        'width' => 100 * 50,
        'unit' => TblWidth::PERCENT,
    ]);
    $table->addRow(null, ['tblHeader' => true, 'cantSplit' => true]);
    foreach ($columns as $index => $column) {
        $table->addCell((int) ($widths[$index] * 154), ['bgColor' => 'E8F0EA', 'valign' => 'center'])->addText($column, ['bold' => true, 'size' => 7, 'color' => '173E2B']);
    }
    foreach ($rows as $row) {
        $table->addRow(620, ['cantSplit' => true]);
        foreach ($columns as $index => $_column) {
            $table->addCell((int) ($widths[$index] * 154), ['valign' => 'top'])->addText($row[$index] ?? ' ', ['size' => 7]);
        }
    }
}

function addSignatures(Section $section, array $titles): void
{
    $section->addTextBreak(1);
    $table = $section->addTable(['borderSize' => 0, 'cellMargin' => 100]);
    $table->addRow(900);
    foreach ($titles as $title) {
        $table->addCell(5100)->addText("____________________________\n".$title, ['size' => 8], ['alignment' => Jc::CENTER]);
    }
}

$created = [];
foreach ($documents as $filename => $definition) {
    $word = new PhpWord;
    $word->setDefaultFontName('Arial');
    $word->setDefaultFontSize(9);
    $word->getSettings()->setThemeFontLang(new Language('en-US'));
    $section = $word->addSection([
        'paperSize' => 'A4',
        'orientation' => 'landscape',
        'marginTop' => 1000,
        'marginBottom' => 1000,
        'marginLeft' => 850,
        'marginRight' => 850,
        'headerHeight' => 550,
        'footerHeight' => 450,
    ]);
    addLetterhead($word, $section);
    $section->addText($definition['title'], ['bold' => true, 'size' => 16, 'color' => '152B25'], ['alignment' => Jc::CENTER, 'spaceBefore' => 300, 'spaceAfter' => 80]);
    $section->addText($definition['subtitle'], ['size' => 9, 'color' => '52645D'], ['alignment' => Jc::CENTER, 'spaceAfter' => 220]);
    $section->addText(CYCLE, ['bold' => true, 'size' => 9]);
    addMetadata($section, $definition['metadata']);
    $section->addText($definition['notice'], ['size' => 8, 'color' => '263E32'], ['spaceBefore' => 180, 'spaceAfter' => 180]);

    if (isset($definition['programs'])) {
        $programRows = array_map(static fn (string $program): array => [$program, '', '', '', '', ''], $definition['programs']);
        addGrid($section, $definition['columns'], $definition['widths'], $programRows);
        $section->addPageBreak();
        $section->addText('QUALIFIER DETAIL ROSTER', ['bold' => true, 'size' => 12, 'color' => '152B25'], ['spaceAfter' => 180]);
        addGrid($section, $definition['detailColumns'], $definition['detailWidths'], array_fill(0, $definition['detailRows'], []));
    } else {
        addGrid($section, $definition['columns'], $definition['widths'], array_fill(0, $definition['rows'], []));
    }

    addSignatures($section, $definition['signatures']);
    $path = $outputDirectory.'/'.$filename.'.docx';
    IOFactory::createWriter($word, 'Word2007')->save($path);
    $created[] = $path;
}

fwrite(STDOUT, 'Generated '.count($created)." Registrar Office DOCX templates:\n");
foreach ($created as $path) {
    fwrite(STDOUT, "- {$path}\n");
}
