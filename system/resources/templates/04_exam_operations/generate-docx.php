<?php

declare(strict_types=1);

use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;
use PhpOffice\PhpWord\Style\Table;
use PhpOffice\PhpWord\Writer\Word2007;

$systemRoot = dirname(__DIR__, 3);
$autoload = $systemRoot.'/vendor/autoload.php';
if (! is_file($autoload)) {
    fwrite(STDERR, "Composer autoload not found at {$autoload}. Run this script from the system installation.\n");
    exit(1);
}
require $autoload;

if (! class_exists(PhpWord::class)) {
    fwrite(STDERR, "PhpOffice\\PhpWord is not installed. Install the application's locked Composer dependencies first.\n");
    exit(1);
}

Settings::setDefaultFontName('Arial');
Settings::setDefaultFontSize(9);

$green = '174B35';
$border = '7F8D83';
$headerFill = 'E8EEE9';
$muted = '53645A';

/** Add the standard ISPSC campus letterhead and controlled form reference. */
function addLetterhead(Section $section, string $title, bool $landscape = false): void
{
    $section->addText('ILOCOS SUR POLYTECHNIC STATE COLLEGE', ['bold' => true, 'size' => 14, 'color' => '174B35'], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
    $section->addText('Tagudin Campus · Tagudin, Ilocos Sur', ['size' => 9, 'color' => '44534B'], ['alignment' => Jc::CENTER, 'spaceAfter' => 4]);
    $section->addText('ISPSC Internal Communication Letterhead     |     Form Code: ISPSC-QAD-F032a     |     Rev. 5 (07-07-2025)', ['size' => 7, 'color' => '53645A'], ['alignment' => Jc::CENTER, 'spaceAfter' => 8]);
    $section->addText($title, ['bold' => true, 'size' => 15, 'color' => '17231D'], ['alignment' => Jc::CENTER, 'spaceAfter' => 3]);
    $section->addText('Academic Year 2026-2027 · First Semester · ISPSC College Admission Test (ICAT)', ['size' => 9], ['alignment' => Jc::CENTER, 'spaceAfter' => 10]);
    $header = $section->addHeader();
    $header->addText('ISPSC · TAGUDIN CAMPUS', ['bold' => true, 'size' => 8, 'color' => '174B35'], ['alignment' => Jc::RIGHT]);
    $footer = $section->addFooter();
    $footer->addText('Controlled exam record · Handle securely under institutional privacy procedures', ['size' => 7, 'color' => '53645A'], ['alignment' => Jc::CENTER]);
}

/** Add bordered, fixed-width table with a shaded header and writable blank rows. */
function addGrid(Section $section, array $headers, int $blankRows, int $rowHeight = 420): void
{
    global $border, $headerFill;
    $table = $section->addTable([
        'borderSize' => 6,
        'borderColor' => $border,
        'cellMargin' => 70,
        'width' => 100 * 50,
        'unit' => TblWidth::PERCENT,
        'layout' => Table::LAYOUT_FIXED,
    ]);
    $table->addRow(420, ['tblHeader' => true, 'cantSplit' => true]);
    foreach ($headers as $header) {
        $cell = $table->addCell(null, ['bgColor' => $headerFill, 'valign' => 'center']);
        $cell->addText($header, ['bold' => true, 'size' => 7], ['alignment' => Jc::CENTER]);
    }
    for ($row = 0; $row < $blankRows; $row++) {
        $table->addRow($rowHeight, ['cantSplit' => true]);
        foreach ($headers as $_header) {
            $table->addCell(null, ['valign' => 'center'])->addText(' ', ['size' => 8]);
        }
    }
}
/** Add a numbered seating roster with Set A / Set B alternating by seat. */
function addSeatingGrid(Section $section, int $blankRows): void
{
    global $border, $headerFill;
    $headers = ['Seat No.', 'NOA Number', 'Booklet Set', 'Examinee Name', 'Arrival Time', 'Examinee Signature', 'Attendance'];
    $table = $section->addTable([
        'borderSize' => 6,
        'borderColor' => $border,
        'cellMargin' => 70,
        'width' => 100 * 50,
        'unit' => TblWidth::PERCENT,
        'layout' => Table::LAYOUT_FIXED,
    ]);
    $table->addRow(420, ['tblHeader' => true, 'cantSplit' => true]);
    foreach ($headers as $header) {
        $cell = $table->addCell(null, ['bgColor' => $headerFill, 'valign' => 'center']);
        $cell->addText($header, ['bold' => true, 'size' => 7], ['alignment' => Jc::CENTER]);
    }
    for ($seat = 1; $seat <= $blankRows; $seat++) {
        $table->addRow(480, ['cantSplit' => true]);
        $values = [(string) $seat, '', $seat % 2 === 1 ? 'Set A' : 'Set B', '', '', '', ''];
        foreach ($values as $value) {
            $table->addCell(null, ['valign' => 'center'])->addText($value === '' ? ' ' : $value, ['size' => 8]);
        }
    }
}

/** Add field labels with a writable rule beneath each one. */
function addFields(Section $section, array $fields): void
{
    foreach ($fields as $label) {
        $section->addText($label.':  ______________________________________________________________', ['size' => 8], ['spaceAfter' => 5]);
    }
}

/** Add simple sign-off lines without supplying fictional signatory names. */
function addSignatures(Section $section, array $roles): void
{
    $section->addTextBreak(1);
    foreach ($roles as $role) {
        $section->addText('________________________________________     '.$role.'     Date: ______________', ['size' => 8], ['spaceAfter' => 12]);
    }
}

/** Create a Word document section with the appropriate paper orientation. */
function makeDocument(string $title, bool $landscape = false): array
{
    $word = new PhpWord;
    $word->getDocInfo()->setCreator('Ilocos Sur Polytechnic State College · Tagudin Campus');
    $word->getDocInfo()->setTitle($title);
    $word->getDocInfo()->setSubject('ISPSC College Admission Test (ICAT) · Academic Year 2026-2027 · First Semester');
    $section = $word->addSection([
        'paperSize' => 'A4',
        'orientation' => $landscape ? 'landscape' : 'portrait',
        'marginLeft' => 650,
        'marginRight' => 650,
        'marginTop' => 650,
        'marginBottom' => 650,
        'footerHeight' => 300,
        'headerHeight' => 250,
    ]);
    addLetterhead($section, $title, $landscape);
    addFields($section, ['Exam date', 'Session / start time', 'Testing venue / room']);

    return [$word, $section];
}

/** Write a generated Word document alongside this script. */
function saveDocument(PhpWord $word, string $filename): void
{
    $path = __DIR__.'/'.$filename;
    (new Word2007($word))->save($path);
    fwrite(STDOUT, "Created {$path}\n");
}

// Room seating and attendance manifest.
[$word, $section] = makeDocument('ICAT Room Seating & Attendance Sheet', true);
$section->addText('Chief Room Proctor: ____________________________________     Session: __________________', ['size' => 8], ['spaceAfter' => 5]);
$section->addText('Assign alternating Set A / Set B booklets in seat order. Verify the NOA Number and obtain the examinee signature at arrival.', ['size' => 8, 'color' => $muted], ['spaceAfter' => 7]);
addSeatingGrid($section, 20);
addSignatures($section, ['Chief Room Proctor', 'Room Proctor', 'Testing Center Head · Review']);
saveDocument($word, 'room-seating-and-attendance-sheet.docx');

// Proctor assignment, check-in, and material custody log.
[$word, $section] = makeDocument('ICAT Proctor Assignment & Custody Log', true);
addFields($section, ['Testing Center Head', 'Deployment prepared by']);
$section->addText('Record each proctor, room, assigned session/duty, check-in time, and material pack or seal identifier.', ['size' => 8, 'color' => $muted], ['spaceAfter' => 7]);
addGrid($section, ['No.', 'Proctor Name', 'Title / Office', 'Venue / Room', 'Assigned Session / Duty', 'Check-in Time', 'Material Pack / Seal ID', 'Initials'], 12, 500);
$section->addTextBreak(1);
$section->addText('Testing Center Head Attestation', ['bold' => true, 'size' => 9]);
$section->addText('I attest that the deployment recorded above reflects the assignment for this ICAT session and that custody issues observed at check-in are recorded.', ['size' => 8], ['spaceAfter' => 8]);
addSignatures($section, ['Testing Center Head', 'Chief Room Proctor']);
saveDocument($word, 'proctor-assignment-and-custody-log.docx');

// Materials distribution and return reconciliation.
[$word, $section] = makeDocument('ICAT Exam Material Distribution Manifest');
addFields($section, ['Issuing custodian', 'Receiving Chief Room Proctor']);
$section->addText('A. Test booklet bundles', ['bold' => true, 'size' => 9, 'color' => $green], ['spaceBefore' => 5, 'spaceAfter' => 4]);
addGrid($section, ['Set', 'Bundle / Seal ID', 'Issued Count', 'Returned Count', 'Used', 'Unused'], 5, 430);
$section->addText('B. Unused answer sheets and other controlled materials', ['bold' => true, 'size' => 9, 'color' => $green], ['spaceBefore' => 9, 'spaceAfter' => 4]);
addGrid($section, ['Material / Form', 'Batch / Seal ID', 'Issued', 'Returned', 'Variance / Notes'], 6, 430);
$section->addText('Reconciliation notes / discrepancies: __________________________________________________________', ['size' => 8], ['spaceBefore' => 8, 'spaceAfter' => 12]);
addSignatures($section, ['Issuing Custodian · Released by', 'Chief Room Proctor · Received by', 'Chief Room Proctor · Returned by', 'Receiving Custodian · Count verified']);
saveDocument($word, 'exam-material-distribution-manifest.docx');

// Incident documentation form for irregularities, disturbances, and accommodations.
[$word, $section] = makeDocument('ICAT Examination Incident Report');
addFields($section, ['Incident reference number', 'Report date / time', 'Reporting person / title', 'Incident time / venue location']);
$section->addText('Incident category (select all that apply):   [ ] Testing irregularity   [ ] Disturbance / disruption   [ ] Special accommodation   [ ] Safety / facility   [ ] Material discrepancy   [ ] Other: __________', ['size' => 8], ['spaceAfter' => 9]);
$sections = [
    'Persons involved (NOA Number only where relevant)',
    'Objective description and sequence of events',
    'Immediate action taken / exam-time impact',
    'Witnesses and supporting record references',
    'Follow-up or disposition',
];
foreach ($sections as $label) {
    $section->addText($label, ['bold' => true, 'size' => 8, 'color' => $green], ['spaceBefore' => 7, 'spaceAfter' => 3]);
    $section->addText(str_repeat('_', 98)."\n".str_repeat('_', 98)."\n".str_repeat('_', 98), ['size' => 8], ['spaceAfter' => 5]);
}
addSignatures($section, ['Reporting Proctor / Staff', 'Chief Room Proctor · Review', 'Licensed Guidance Counselor (RGC) · if referred']);
saveDocument($word, 'examination-incident-report.docx');
