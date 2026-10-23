<?php

declare(strict_types=1);

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\TblWidth;
use PhpOffice\PhpWord\Style\Language;

$autoload = dirname(__DIR__, 3).'/vendor/autoload.php';
if (! is_file($autoload)) {
    fwrite(STDERR, "PhpWord autoloader not found at {$autoload}. Run this script from the system application with dependencies installed.\n");
    exit(1);
}
require $autoload;

const INSTITUTION = 'ILOCOS SUR POLYTECHNIC STATE COLLEGE';
const CAMPUS = 'Tagudin Campus · Tagudin, Ilocos Sur';
const FORM = 'ISPSC Internal Communication Letterhead — Form Code ISPSC-QAD-F032a · Rev. 5 (07-07-2025)';
const CYCLE = 'Academic Year 2026-2027 · First Semester · SYN-CYCLE-2026';
const GREEN = '17633F';
const LIGHT_GREEN = 'E8F0EA';
const GRID = 'B8C6BD';
const MUTED = '52645A';

function newDocument(string $title, string $orientation = 'landscape'): array
{
    $word = new PhpWord;
    $word->setDefaultFontName('Arial');
    $word->setDefaultFontSize(9);
    $word->getSettings()->setThemeFontLang(new Language('en-US'));
    $word->getDocInfo()->setCreator('Ilocos Sur Polytechnic State College · Tagudin Campus');
    $word->getDocInfo()->setTitle($title);
    $word->getDocInfo()->setSubject(CYCLE);

    $section = $word->addSection([
        'paperSize' => 'A4',
        'orientation' => $orientation,
        'marginTop' => Converter::cmToTwip(1.3),
        'marginBottom' => Converter::cmToTwip(1.4),
        'marginLeft' => Converter::cmToTwip(1.3),
        'marginRight' => Converter::cmToTwip(1.3),
    ]);
    $header = $section->addHeader();
    $header->addText(INSTITUTION, ['bold' => true, 'size' => 11, 'color' => GREEN], ['alignment' => 'center', 'spaceAfter' => 0]);
    $header->addText(CAMPUS, ['size' => 9, 'color' => MUTED], ['alignment' => 'center', 'spaceAfter' => 1]);
    $header->addText(FORM, ['size' => 7, 'color' => MUTED], ['alignment' => 'center', 'borderBottomSize' => 12, 'borderBottomColor' => GREEN, 'spaceAfter' => 5]);
    $footer = $section->addFooter();
    $footer->addText('Controlled internal record · Handle applicant information under institutional privacy requirements.', ['size' => 7, 'color' => MUTED], ['alignment' => 'center']);

    return [$word, $section];
}

function titleBlock($section, string $eyebrow, string $title, string $subtitle): void
{
    $section->addText($eyebrow, ['bold' => true, 'size' => 8, 'color' => GREEN], ['spaceAfter' => 2]);
    $section->addText($title, ['bold' => true, 'size' => 17, 'color' => '182820'], ['spaceAfter' => 2]);
    $section->addText($subtitle, ['size' => 9, 'color' => MUTED], ['spaceAfter' => 8]);
}

function addMetaTable($section, array $entries): void
{
    $table = $section->addTable(['borderSize' => 5, 'borderColor' => GRID, 'cellMargin' => 90, 'width' => 5000, 'unit' => TblWidth::PERCENT]);
    foreach (array_chunk($entries, 2) as $pair) {
        $row = $table->addRow();
        foreach ($pair as [$label, $value]) {
            $cell = $row->addCell(2500, ['valign' => 'center']);
            $cell->addText($label, ['size' => 7, 'color' => MUTED]);
            $cell->addText($value, ['bold' => true, 'size' => 9]);
        }
        if (count($pair) === 1) {
            $row->addCell(2500)->addText('');
        }
    }
    $section->addTextBreak(1);
}

function addDataTable($section, array $headers, array $rows, array $widths = []): void
{
    $widths = $widths !== [] ? $widths : array_fill(0, count($headers), (int) floor(5000 / count($headers)));
    $table = $section->addTable(['borderSize' => 5, 'borderColor' => GRID, 'cellMargin' => 75, 'width' => 5000, 'unit' => TblWidth::PERCENT]);
    $headerRow = $table->addRow(null, ['tblHeader' => true]);
    foreach ($headers as $index => $label) {
        $headerRow->addCell($widths[$index], ['bgColor' => LIGHT_GREEN, 'valign' => 'center'])->addText($label, ['bold' => true, 'size' => 7, 'color' => '233D2D']);
    }
    foreach ($rows as $row) {
        $dataRow = $table->addRow();
        foreach (array_values($row) as $index => $value) {
            $dataRow->addCell($widths[$index] ?? null)->addText((string) $value, ['size' => 7]);
        }
    }
    $section->addTextBreak(1);
}

function addSignatures($section, array $signatories): void
{
    $table = $section->addTable(['borderSize' => 0, 'cellMargin' => 120, 'width' => 5000, 'unit' => TblWidth::PERCENT]);
    $row = $table->addRow();
    $width = (int) floor(5000 / count($signatories));
    foreach ($signatories as [$name, $title]) {
        $cell = $row->addCell($width, ['borderTopSize' => 6, 'borderTopColor' => '66776C']);
        $cell->addText($name, ['bold' => true, 'size' => 9]);
        $cell->addText($title, ['size' => 7, 'color' => MUTED]);
    }
}

function addSummary(string $path): void
{
    [$word, $section] = newDocument('College Admission Executive Brief');
    titleBlock($section, 'ACADEMIC LEADERSHIP · COLLEGE-LEVEL ADMISSIONS SNAPSHOT', 'College Admission Executive Brief', 'ISPSC College Admission Test (ICAT) · Decision support for College Deans');
    addMetaTable($section, [
        ['College / Dean', '{{college_name}} · {{college_dean}}'], ['Academic Year / Semester', CYCLE],
        ['Cycle reference', 'SYN-CYCLE-2026'], ['Report date / data cutoff', '{{report_date}} · {{data_cutoff}}'],
    ]);
    $section->addText('Executive metrics', ['bold' => true, 'color' => GREEN, 'size' => 10]);
    addDataTable($section, ['Total applicants', '1st-choice in college', '2nd-choice in college', 'ICAT qualifiers'], [['{{total_applicants}}', '{{first_choice_count}}', '{{backup_choice_count}}', '{{qualified_count}}']]);
    $section->addText('Reading rule: Applicant influx, choice preference, qualification, acceptance, and committed seat counts are distinct measures. A testing qualifier is not by itself an admission or seat commitment.', ['size' => 8, 'color' => MUTED], ['spaceAfter' => 7]);
    addDataTable($section, ['College / department', 'Program', 'Applicants', '1st choice', '2nd choice', 'Qualifiers', 'Accepted', 'Committed seats', 'Planned seats', 'Occupancy'], [['{{college_or_department}}', '{{program_code}}', '{{applicant_count}}', '{{first_choice_count}}', '{{second_choice_count}}', '{{qualified_count}}', '{{accepted_count}}', '{{committed_seats}}', '{{planned_quota}}', '{{occupancy_percent}}']]);
    addDataTable($section, ['Executive interpretation / action', 'Entry'], [['Primary demand / influx', '{{demand_observation}}'], ['Qualifier / acceptance observation', '{{qualification_observation}}'], ['Seat occupancy / quota risk', '{{occupancy_observation}}'], ['Recommended decision / follow-up', '{{executive_action}}']]);
    addSignatures($section, [['{{college_dean}}', 'College Dean · Reviewed / date'], ['{{campus_executive_director}}', 'Campus Executive Director · Noted / date']]);
    saveDocument($word, $path);
}

function addQuotaMatrix(string $path): void
{
    [$word, $section] = newDocument('Program Quota Variance Matrix');
    titleBlock($section, 'ACADEMIC LEADERSHIP · CAPACITY AND COMMITMENT VIEW', 'Program Quota Variance Matrix', 'ISPSC College Admission Test (ICAT) · Planned quota compared with accepted candidates and committed reservations');
    addMetaTable($section, [
        ['College / Dean', '{{college_name}} · {{college_dean}}'], ['Academic Year / Semester', CYCLE],
        ['Cycle reference', 'SYN-CYCLE-2026'], ['Projection cutoff', '{{data_cutoff}}'],
    ]);
    $section->addText('Remaining slots must follow the documented system capacity basis; acceptance and reservation are distinct. Over-quota is a variance for review, not authority to raise capacity.', ['size' => 8, 'color' => MUTED], ['spaceAfter' => 7]);
    addDataTable($section, ['College / department', 'Program', 'Planned quota', 'Accepted candidates', 'Committed reservations', 'Remaining slots', 'Variance (committed − quota)', 'Quota status', 'Review note / source'], [['{{college_or_department}}', '{{program_code}}', '{{planned_quota}}', '{{accepted_candidates}}', '{{committed_reservations}}', '{{remaining_slots}}', '{{quota_variance}}', '{{over_quota_indicator}}', '{{review_note}}']]);
    addSignatures($section, [['{{college_dean}}', 'College Dean · Reviewed / date'], ['{{department_chairperson}}', 'Department Chairperson · Reconciled / date'], ['{{campus_executive_director}}', 'Campus Executive Director · Noted / date']]);
    saveDocument($word, $path);
}

function addInterviewForm(string $path): void
{
    [$word, $section] = newDocument('Department Interview Scoring Form', 'portrait');
    titleBlock($section, 'ACADEMIC LEADERSHIP · CONFIDENTIAL CANDIDATE ASSESSMENT', 'Department Interview Scoring Form', 'ICAT · For program screening only where an approved plan requires interview, audition, or aptitude evaluation');
    addMetaTable($section, [
        ['Candidate / NOA Number', '{{candidate_name}} · {{noa_number}}'], ['Program / Department', '{{program_code}} · {{department_name}}'],
        ['Screening type', '{{screening_type}}'], ['Date / venue', '{{interview_date}} · {{venue}}'],
        ['Academic Year / Semester', CYCLE], ['Panel / chairperson', '{{panel_names}} · {{department_chairperson}}'],
    ]);
    $section->addText('Before use, transcribe the approved criteria, weights, scale, pass threshold, and scoring anchors. No criteria, weight, or passing score is prescribed by this blank template. Confirm weights total 100%.', ['size' => 8, 'color' => MUTED], ['spaceAfter' => 7]);
    addDataTable($section, ['Criterion / observable evidence', 'Approved scoring anchor / notes', 'Weight (%)', 'Panelist 1 score', 'Panelist 2 score'], [['{{criterion}} / {{observable_evidence}}', '{{approved_anchor}}', '{{weight_percent}}', '{{panelist_1_score}}', '{{panelist_2_score}}'], ['{{criterion}} / {{observable_evidence}}', '{{approved_anchor}}', '{{weight_percent}}', '{{panelist_1_score}}', '{{panelist_2_score}}'], ['TOTAL WEIGHT / WEIGHTED SCORE', '', '{{weight_total}}', '{{panelist_1_weighted_total}}', '{{panelist_2_weighted_total}}']]);
    addDataTable($section, ['Panel record', 'Entry'], [['Evidence-based observations', '{{panel_observations}}'], ['Scoring method / scale', '{{scoring_method_and_scale}}'], ['Approved threshold', '{{approved_threshold}}'], ['Panel recommendation', '{{panel_recommendation}}'], ['Accommodation / process note', '{{process_note}}']]);
    addSignatures($section, [['{{panelist_1}}', 'Panelist · Signature / date'], ['{{panelist_2}}', 'Panelist · Signature / date'], ['{{department_chairperson}}', 'Department Chairperson · Endorsement / date']]);
    saveDocument($word, $path);
}

function addPreferenceReport(string $path): void
{
    [$word, $section] = newDocument('Student Preference Demand Share');
    titleBlock($section, 'ACADEMIC LEADERSHIP · APPLICANT PREFERENCE DISTRIBUTION', 'Student Preference Demand Share', 'ICAT · First-choice demand and second-choice fallback volume by college and department');
    addMetaTable($section, [
        ['Academic Year / Semester', CYCLE], ['Cycle reference', 'SYN-CYCLE-2026'],
        ['Projection cutoff', '{{data_cutoff}}'], ['Scope / preparer', '{{scope}} · {{prepared_by}}'],
    ]);
    $section->addText('Demand shares use separate rank denominators. Second-choice preference is fallback volume, not proof of displacement, acceptance, or commitment. Combined volume may count one candidate at both ranks.', ['size' => 8, 'color' => MUTED], ['spaceAfter' => 7]);
    addDataTable($section, ['College', 'Department', 'Program', '1st-choice volume', '1st-choice share', '2nd-choice fallback volume', '2nd-choice share', 'Combined preference volume', 'Planning note'], [['{{college_name}}', '{{department_name}}', '{{program_code}}', '{{first_choice_volume}}', '{{first_choice_share}}', '{{second_choice_volume}}', '{{second_choice_share}}', '{{combined_preference_volume}}', '{{demand_profile_note}}']]);
    addSignatures($section, [['{{department_chairperson}}', 'Department Chairperson · Prepared / date'], ['{{college_dean}}', 'College Dean · Reviewed / date'], ['{{campus_executive_director}}', 'Campus Executive Director · Noted / date']]);
    saveDocument($word, $path);
}

function saveDocument(PhpWord $word, string $path): void
{
    IOFactory::createWriter($word, 'Word2007')->save($path);
    fwrite(STDOUT, "Generated {$path}\n");
}

$directory = __DIR__;
addSummary($directory.'/college-admission-executive-brief.docx');
addQuotaMatrix($directory.'/program-quota-variance-matrix.docx');
addInterviewForm($directory.'/department-interview-scoring-form.docx');
addPreferenceReport($directory.'/student-preference-demand-share.docx');
