<?php

declare(strict_types=1);

$autoload = __DIR__.'/../../vendor/autoload.php';
if (! is_file($autoload)) {
    fwrite(STDERR, "Composer autoload not found at {$autoload}\n");
    exit(1);
}
require $autoload;

$baselinePath = __DIR__.'/../../database/seeders/fixtures/baselines/institutional-baseline-reference.docx';
if (! is_file($baselinePath)) {
    fwrite(STDERR, "Baseline docx not found at {$baselinePath}\n");
    exit(1);
}

// 22 Template Definitions
$templates = [
    // 01 Registrar Office
    [
        'dir' => '01_registrar', 'file' => 'enrollment-clearance-roster.docx',
        'title' => 'ENROLLMENT CLEARANCE ROSTER',
        'subtitle' => 'ISPSC College Admission Test (ICAT) · Tagudin Campus · AY 2026-2027',
        'orientation' => 'portrait', 'table_width' => 8300, 'table_indent' => 900,
        'cols' => [['#', 450], ['NOA No.', 1200], ['Applicant Name', 2200], ['Program', 900], ['GWA', 650], ['Clearance Status', 1000], ['Receipt No.', 1000], ['Form 138 Status', 900]],
        'rows' => ['{{row_no}}', '{{reference_code}}', '{{applicant_name}}', '{{program_code}}', '{{gwa}}', '{{clearance_status}}', '{{clearance_receipt_no}}', '{{form_138_status}}'],
        'signatures' => [['Registrar Service Window 1', 'Verified and Cleared by:'], ['University Registrar', 'Attested and Approved by:']],
    ],
    [
        'dir' => '01_registrar', 'file' => 'applications-census.docx',
        'title' => 'APPLICATIONS SUMMARY CENSUS', 'subtitle' => 'Official Registered Applicants · Tagudin Campus · AY 2026-2027',
        'orientation' => 'portrait', 'table_width' => 8300, 'table_indent' => 900,
        'cols' => [['#', 450], ['NOA No.', 1200], ['Applicant Name', 2200], ['Strand', 900], ['GWA', 650], ['1st Choice', 1000], ['2nd Choice', 1000], ['Admission Status', 900]],
        'rows' => ['{{row_no}}', '{{reference_code}}', '{{applicant_name}}', '{{strand}}', '{{gwa}}', '{{first_choice}}', '{{alternate_choice}}', '{{admission_status}}'],
        'signatures' => [['Admissions Evaluation Clerk', 'Prepared by:'], ['University Registrar', 'Noted by:']],
    ],
    [
        'dir' => '01_registrar', 'file' => 'course-quota-qualifiers.docx',
        'title' => 'COURSE QUOTA QUALIFIERS AND ADMISSION STATUS', 'subtitle' => 'Official Program Qualifiers vs Target Quota · Tagudin Campus · AY 2026-2027',
        'orientation' => 'portrait', 'table_width' => 8300, 'table_indent' => 900,
        'cols' => [['#', 450], ['NOA No.', 1200], ['Applicant Name', 2200], ['Program', 900], ['ICAT Score', 650], ['Stanine', 1000], ['Quota Status', 1000], ['Remarks', 900]],
        'rows' => ['{{row_no}}', '{{reference_code}}', '{{applicant_name}}', '{{program_code}}', '{{composite_score}}', '{{stanine}}', '{{admission_status}}', '{{remarks}}'],
        'signatures' => [['Admissions & Records Officer', 'Certified by:'], ['University Registrar', 'Approved by:']],
    ],
    [
        'dir' => '01_registrar', 'file' => 'feeder-school-and-origin-census.docx',
        'title' => 'FEEDER HIGH SCHOOL & ORIGIN CENSUS', 'subtitle' => 'Geographic and Senior High School Distribution · Tagudin Campus · AY 2026-2027',
        'orientation' => 'portrait', 'table_width' => 8300, 'table_indent' => 900,
        'cols' => [['#', 800], ['Feeder High School / Municipality', 4300], ['Applicants', 1600], ['Share %', 1600]],
        'rows' => ['{{row_no}}', '{{origin}}', '{{applicant_count}}', '{{population_share}}'],
        'signatures' => [['Registrar Records Clerk', 'Prepared by:'], ['University Registrar', 'Approved by:']],
    ],
    [
        'dir' => '01_registrar', 'file' => 'discrepancy-and-ingestion-audit.docx',
        'title' => 'INGESTION & DISCREPANCY AUDIT REPORT', 'subtitle' => 'Verified Form 138 vs Portal Intake Corrections · Tagudin Campus · AY 2026-2027',
        'orientation' => 'portrait', 'table_width' => 8300, 'table_indent' => 900,
        'cols' => [['#', 500], ['NOA No.', 1300], ['Applicant Name', 2400], ['Discrepancy Category', 1100], ['Encoded Value', 1000], ['Verified Value', 1000], ['Action Taken', 1000]],
        'rows' => ['{{row_no}}', '{{reference_code}}', '{{applicant_name}}', '{{issue_category}}', '{{encoded_value}}', '{{verified_value}}', '{{action_taken}}'],
        'signatures' => [['Intake Verification Staff', 'Audited by:'], ['University Registrar', 'Verified by:']],
    ],
    // 02 Guidance and Testing
    [
        'dir' => '02_guidance_and_testing', 'file' => 'career-advising-and-referral-slip.docx',
        'title' => 'CAREER ADVISING & COURSE REFERRAL SLIP', 'subtitle' => 'Guidance and Counseling Services · Tagudin Campus · AY 2026-2027',
        'orientation' => 'portrait', 'table_width' => 8300, 'table_indent' => 900,
        'cols' => [['Aptitude Dimension / Subtest', 4300], ['Raw Score', 1600], ['Stanine', 1200], ['Rating', 1200]],
        'rows' => ['{{subtest_name}}', '{{raw_score}}', '{{stanine}}', '{{rating}}'],
        'signatures' => [['Licensed Guidance Counselor (RGC)', 'Counselor:'], ['Applicant Signature', 'Conforme:']],
    ],
    [
        'dir' => '02_guidance_and_testing', 'file' => 'passing-candidates-census.docx',
        'title' => 'PASSING CANDIDATES CENSUS', 'subtitle' => 'Official ICAT Entrance Examination Passers · Tagudin Campus · AY 2026-2027',
        'orientation' => 'portrait', 'table_width' => 8300, 'table_indent' => 900,
        'cols' => [['#', 500], ['NOA No.', 1300], ['Applicant Name', 2400], ['Strand', 1100], ['ICAT Score', 1000], ['Stanine', 1000], ['Qualified Program', 1000]],
        'rows' => ['{{row_no}}', '{{reference_code}}', '{{applicant_name}}', '{{strand}}', '{{composite_score}}', '{{stanine}}', '{{program_name}}'],
        'signatures' => [['Psychometrician / Test Specialist', 'Prepared by:'], ['Head, Guidance & Testing Center', 'Attested by:']],
    ],
    [
        'dir' => '02_guidance_and_testing', 'file' => 'score-and-stanine-distribution.docx',
        'title' => 'FINAL APTITUDE SCORE DISTRIBUTION', 'subtitle' => 'Statistical Competency Summary per Subtest · Tagudin Campus · AY 2026-2027',
        'orientation' => 'portrait', 'table_width' => 8300, 'table_indent' => 900,
        'cols' => [['Aptitude Area / Subtest', 4300], ['Examinees', 1600], ['Mean Score', 1200], ['Std Dev', 1200]],
        'rows' => ['{{aptitude_area}}', '{{candidate_count}}', '{{mean}}', '{{median}}'],
        'signatures' => [['Testing Coordinator', 'Prepared by:'], ['Head, Guidance & Testing Center', 'Noted by:']],
    ],
    [
        'dir' => '02_guidance_and_testing', 'file' => 'day-of-release-throughput-tally.docx',
        'title' => 'DAY OF RELEASE HOURLY THROUGHPUT TALLY', 'subtitle' => 'Operational Advising Throughput by Room · Tagudin Campus · AY 2026-2027',
        'orientation' => 'portrait', 'table_width' => 8300, 'table_indent' => 900,
        'cols' => [['Time Window', 1800], ['Advising Room', 1800], ['Advised', 1600], ['Referred', 1600], ['Walk-ins', 1500]],
        'rows' => ['{{time_window}}', '{{room_name}}', '{{advised_count}}', '{{referred_count}}', '{{walkin_count}}'],
        'signatures' => [['Day of Release Operations Officer', 'Recorded by:'], ['Head, Guidance & Testing Center', 'Approved by:']],
    ],
    [
        'dir' => '02_guidance_and_testing', 'file' => 'exam-absenteeism-report.docx',
        'title' => 'EXAM ATTENDANCE & NO-SHOW REPORT', 'subtitle' => 'Unattended Scheduled ICAT Testing Sessions · Tagudin Campus · AY 2026-2027',
        'orientation' => 'portrait', 'table_width' => 8300, 'table_indent' => 900,
        'cols' => [['#', 800], ['NOA No.', 1800], ['Examinee Full Name', 3500], ['Session', 1100], ['Room', 1100]],
        'rows' => ['{{row_no}}', '{{reference_code}}', '{{applicant_name}}', '{{session_code}}', '{{room_name}}'],
        'signatures' => [['Chief Examination Proctor', 'Reported by:'], ['Head, Guidance & Testing Center', 'Noted by:']],
    ],
    // 03 Academic Leadership
    [
        'dir' => '03_academic_leadership', 'file' => 'college-admission-executive-brief.docx',
        'title' => 'COLLEGE ADMISSION EXECUTIVE BRIEF', 'subtitle' => 'Department Admissions & Seat Occupancy · Tagudin Campus · AY 2026-2027',
        'orientation' => 'portrait', 'table_width' => 8300, 'table_indent' => 900,
        'cols' => [['Constituent Program', 4300], ['Quota', 1600], ['Advised', 1200], ['Occupancy %', 1200]],
        'rows' => ['{{program_name}}', '{{target_quota}}', '{{committed_count}}', '{{occupancy_rate}}'],
        'signatures' => [['College Admissions Coordinator', 'Prepared by:'], ['Dean of College', 'Submitted by:']],
    ],
    [
        'dir' => '03_academic_leadership', 'file' => 'program-quota-variance-matrix.docx',
        'title' => 'PROGRAM QUOTA & ADVISORY VARIANCE MATRIX', 'subtitle' => 'Target Capacity vs Committed Placements · Tagudin Campus · AY 2026-2027',
        'orientation' => 'portrait', 'table_width' => 8300, 'table_indent' => 900,
        'cols' => [['Program Code', 500], ['Program Name', 1300], ['Planned Quota', 2400], ['Accepted', 1100], ['Committed', 1000], ['Remaining', 1000], ['Variance', 1000]],
        'rows' => ['{{program_code}}', '{{program_name}}', '{{target_quota}}', '{{accepted_count}}', '{{committed_count}}', '{{remaining_slots}}', '{{variance_status}}'],
        'signatures' => [['Department Chairperson', 'Prepared by:'], ['Dean of College', 'Approved by:']],
    ],
    [
        'dir' => '03_academic_leadership', 'file' => 'department-interview-scoring-form.docx',
        'title' => 'DEPARTMENT INTERVIEW & AUDITION SCORING RUBRIC', 'subtitle' => 'Secondary Program Evaluation · Tagudin Campus · AY 2026-2027',
        'orientation' => 'portrait', 'table_width' => 8300, 'table_indent' => 900,
        'cols' => [['Assessment Dimension / Rubric', 4300], ['Weight', 1600], ['Score (1-100)', 1200], ['Remarks', 1200]],
        'rows' => ['{{dimension_name}}', '{{weight_percent}}', '{{raw_score}}', '{{remarks}}'],
        'signatures' => [['Department Interviewer / Panelist', 'Evaluator:'], ['Department Chairperson', 'Endorsed by:']],
    ],
    [
        'dir' => '03_academic_leadership', 'file' => 'student-preference-demand-share.docx',
        'title' => 'STUDENT PREFERENCE & PROGRAM DEMAND SHARE', 'subtitle' => 'Primary vs Alternate Course Selection · Tagudin Campus · AY 2026-2027',
        'orientation' => 'portrait', 'table_width' => 8300, 'table_indent' => 900,
        'cols' => [['Offered Program', 4300], ['1st Choice', 1600], ['2nd/3rd Choice', 1200], ['Total Pool', 1200]],
        'rows' => ['{{program_name}}', '{{first_choice_count}}', '{{alternate_choice_count}}', '{{distinct_applicant_count}}'],
        'signatures' => [['Academic Affairs Analyst', 'Prepared by:'], ['Dean of College', 'Noted by:']],
    ],
    // 04 Exam Operations
    [
        'dir' => '04_exam_operations', 'file' => 'room-seating-and-attendance-sheet.docx',
        'title' => 'ROOM SEATING ARRANGEMENT & ATTENDANCE SHEET', 'subtitle' => 'Official Examination Manifest · Tagudin Campus · AY 2026-2027',
        'orientation' => 'portrait', 'table_width' => 8300, 'table_indent' => 900,
        'cols' => [['Seat', 450], ['Row', 450], ['NOA No.', 1200], ['Examinee Full Name', 2200], ['Strand', 900], ['Booklet', 1000], ['Signature', 1200], ['Status', 900]],
        'rows' => ['{{seat_number}}', '{{row_number}}', '{{reference_code}}', '{{applicant_name}}', '{{strand}}', '{{booklet_code}}', '{{signature_blank}}', '{{attendance_status}}'],
        'signatures' => [['Chief Room Proctor', 'Room Proctor:'], ['Head of Testing Operations', 'Attested by:']],
    ],
    [
        'dir' => '04_exam_operations', 'file' => 'proctor-assignment-and-custody-log.docx',
        'title' => 'PROCTOR ASSIGNMENT & TEST KIT CUSTODY LOG', 'subtitle' => 'Testing Operations Deployment · Tagudin Campus · AY 2026-2027',
        'orientation' => 'portrait', 'table_width' => 8300, 'table_indent' => 900,
        'cols' => [['Room / Venue', 500], ['Session', 1300], ['Chief Proctor', 2400], ['Kits Issued', 1100], ['Kits Returned', 1000], ['Check-in', 1000], ['Attestation', 1000]],
        'rows' => ['{{room_name}}', '{{session_code}}', '{{proctor_name}}', '{{kits_issued}}', '{{kits_returned}}', '{{check_in_time}}', '{{custody_status}}'],
        'signatures' => [['Test Kit Custodian', 'Released by:'], ['Head of Testing Operations', 'Supervised by:']],
    ],
    [
        'dir' => '04_exam_operations', 'file' => 'exam-material-distribution-manifest.docx',
        'title' => 'EXAM MATERIAL DISTRIBUTION MANIFEST', 'subtitle' => 'Booklet and OMR Sheet Dispatch Ledger · Tagudin Campus · AY 2026-2027',
        'orientation' => 'portrait', 'table_width' => 8300, 'table_indent' => 900,
        'cols' => [['Bundle #', 500], ['Target Room', 1300], ['Booklet Series', 2400], ['Sheets Dispatched', 1100], ['Sheets Used', 1000], ['Unused Returned', 1000], ['Custodian', 1000]],
        'rows' => ['{{bundle_code}}', '{{room_name}}', '{{booklet_series}}', '{{sheets_dispatched}}', '{{sheets_used}}', '{{sheets_returned}}', '{{custodian_name}}'],
        'signatures' => [['Materials Logistics Officer', 'Prepared by:'], ['Head of Testing Operations', 'Approved by:']],
    ],
    [
        'dir' => '04_exam_operations', 'file' => 'examination-incident-report.docx',
        'title' => 'EXAMINATION INCIDENT & IRREGULARITY REPORT', 'subtitle' => 'Official Testing Incident Documentation · Tagudin Campus · AY 2026-2027',
        'orientation' => 'portrait', 'table_width' => 8300, 'table_indent' => 900,
        'cols' => [['Incident Field', 4300], ['Recorded Detail', 4000]],
        'rows' => ['{{incident_field}}', '{{incident_value}}'],
        'signatures' => [['Reporting Room Proctor', 'Reported by:'], ['Head of Testing Operations', 'Action Approved by:']],
    ],
    // 05 Institutional Admin and HR
    [
        'dir' => '05_institutional_admin_and_hr', 'file' => 'universal-staff-accomplishment-report.docx',
        'title' => 'UNIVERSAL STAFF ACCOMPLISHMENT AUDIT REPORT', 'subtitle' => 'Verified Milestone Actions & IPCR Supporting Ledger · Tagudin Campus · AY 2026-2027',
        'orientation' => 'portrait', 'table_width' => 8300, 'table_indent' => 900,
        'cols' => [['Major Final Output (MFO) Category', 4300], ['Target', 1600], ['Actual Verified', 1200], ['Variance', 1200]],
        'rows' => ['{{mfo_category}}', '{{target_count}}', '{{verified_count}}', '{{variance_rating}}'],
        'signatures' => [['Staff Member / Employee', 'Prepared by:'], ['Campus Executive Director', 'Approved by:']],
    ],
    [
        'dir' => '05_institutional_admin_and_hr', 'file' => 'institutional-admissions-funnel.docx',
        'title' => 'INSTITUTIONAL ADMISSIONS FUNNEL VELOCITY', 'subtitle' => 'Stage-by-Stage Conversion Summary · Tagudin Campus · AY 2026-2027',
        'orientation' => 'portrait', 'table_width' => 8300, 'table_indent' => 900,
        'cols' => [['Admissions Pipeline Stage', 4300], ['Candidates', 1600], ['Retention %', 1200], ['Conversion', 1200]],
        'rows' => ['{{stage_name}}', '{{candidate_count}}', '{{retention_percent}}', '{{conversion_rate}}'],
        'signatures' => [['Institutional Planning Officer', 'Compiled by:'], ['Campus Executive Director', 'Noted by:']],
    ],
    [
        'dir' => '05_institutional_admin_and_hr', 'file' => 'campus-venue-capacity-audit.docx',
        'title' => 'CAMPUS VENUE CAPACITY & UTILIZATION AUDIT', 'subtitle' => 'Testing Hall and Classroom Usage · Tagudin Campus · AY 2026-2027',
        'orientation' => 'portrait', 'table_width' => 8300, 'table_indent' => 900,
        'cols' => [['Campus Building', 500], ['Room Code', 1300], ['Capacity', 2400], ['Sessions', 1100], ['Tested', 1000], ['Occupancy %', 1000], ['Status', 1000]],
        'rows' => ['{{building_name}}', '{{room_code}}', '{{capacity}}', '{{session_count}}', '{{tested_count}}', '{{occupancy_rate}}', '{{venue_status}}'],
        'signatures' => [['Facilities & Space Administrator', 'Audited by:'], ['Campus Executive Director', 'Approved by:']],
    ],
    [
        'dir' => '05_institutional_admin_and_hr', 'file' => 'board-of-regents-admissions-packet.docx',
        'title' => 'EXECUTIVE ADMISSIONS & QUOTA SUMMARY TO THE BOARD', 'subtitle' => 'Institutional Yield and Demographic Equality · Tagudin Campus · AY 2026-2027',
        'orientation' => 'portrait', 'table_width' => 8300, 'table_indent' => 900,
        'cols' => [['College / Department', 600], ['Quota', 1800], ['Male', 2900], ['Female', 1500], ['Total Cleared', 1500]],
        'rows' => ['{{department_name}}', '{{target_quota}}', '{{male_cleared}}, {{female_cleared}}, {{total_cleared}}'],
        'signatures' => [['University Registrar', 'Presented by:'], ['Campus Executive Director', 'Attested by:']],
    ],
];

echo "Starting generation of 22 baseline-cloned DOCX templates...\n";
foreach ($templates as $idx => $def) {
    $targetDir = __DIR__.'/'.$def['dir'];
    if (! is_dir($targetDir)) {
        mkdir($targetDir, 0775, true);
    }
    $targetPath = $targetDir.'/'.$def['file'];
    copy($baselinePath, $targetPath);

    $zip = new ZipArchive;
    if ($zip->open($targetPath) !== true) {
        fwrite(STDERR, "Failed to open cloned zip at {$targetPath}\n");

        continue;
    }
    $docXml = $zip->getFromName('word/document.xml');
    $twipWidth = 8300;
    $twipIndent = 900;
    $body = '';
    $body .= '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:before="240" w:after="80"/></w:pPr><w:r><w:rPr><w:b/><w:sz w:val="26"/><w:color w:val="174B35"/></w:rPr><w:t>'.htmlspecialchars($def['title'], ENT_XML1).'</w:t></w:r></w:p>';
    $body .= '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:after="280"/></w:pPr><w:r><w:rPr><w:sz w:val="18"/><w:color w:val="44534B"/></w:rPr><w:t>'.htmlspecialchars($def['subtitle'], ENT_XML1).'</w:t></w:r></w:p>';
    $body .= '<w:tbl><w:tblPr><w:tblW w:w="8300" w:type="dxa"/><w:tblInd w:w="900" w:type="dxa"/><w:tblBorders><w:top w:val="single" w:sz="4" w:space="0" w:color="7F8D83"/><w:left w:val="single" w:sz="4" w:space="0" w:color="7F8D83"/><w:bottom w:val="single" w:sz="4" w:space="0" w:color="7F8D83"/><w:right w:val="single" w:sz="4" w:space="0" w:color="7F8D83"/><w:insideH w:val="single" w:sz="4" w:space="0" w:color="D1D5DB"/><w:insideV w:val="single" w:sz="4" w:space="0" w:color="D1D5DB"/></w:tblBorders></w:tblPr><w:tr><w:trPr><w:tblHeader/></w:trPr>';
    foreach ($def['cols'] as [$colName, $colWidth]) {
        $body .= '<w:tc><w:tcPr><w:tcW w:w="'.$colWidth.'" w:type="dxa"/><w:shd w:val="clear" w:color="auto" w:fill="E8EEE9"/></w:tcPr><w:p><w:pPr><w:spacing w:before="60" w:after="60"/><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:b/><w:sz w:val="16"/><w:color w:val="174B35"/></w:rPr><w:t>'.htmlspecialchars($colName, ENT_XML1).'</w:t></w:r></w:p></w:tc>';
    }
    $body .= '</w:tr>';
    for ($r = 1; $r <= 3; $r++) {
        $body .= '<w:tr>';
        foreach ($def['cols'] as $cIdx => [$colName, $colWidth]) {
            $cellTag = $def['rows'][$cIdx] ?? '{{data}}';
            $body .= '<w:tc><w:tcPr><w:tcW w:w="'.$colWidth.'" w:type="dxa"/></w:tcPr><w:p><w:pPr><w:spacing w:before="60" w:after="60"/></w:pPr><w:r><w:rPr><w:sz w:val="16"/><w:color w:val="233D2D"/></w:rPr><w:t>'.htmlspecialchars($cellTag, ENT_XML1).'</w:t></w:r></w:p></w:tc>';
        }
        $body .= '</w:tr>';
    }
    $body .= '</w:tbl><w:p><w:pPr><w:spacing w:before="360" w:after="80"/></w:pPr></w:p>';
    $sigCount = count($def['signatures']);
    $sigColWidth = (int) floor(($twipWidth - 400) / $sigCount);
    $body .= '<w:tbl><w:tblPr><w:tblW w:w="8300" w:type="dxa"/><w:tblInd w:w="900" w:type="dxa"/><w:tblBorders><w:top w:val="none"/><w:left w:val="none"/><w:bottom w:val="none"/><w:right w:val="none"/><w:insideH w:val="none"/><w:insideV w:val="none"/></w:tblBorders></w:tblPr><w:tr>';
    foreach ($def['signatures'] as $sIdx => [$signatoryName, $signatoryRole]) {
        if ($sIdx > 0) {
            $body .= '<w:tc><w:tcPr><w:tcW w:w="400" w:type="dxa"/></w:tcPr><w:p/></w:tc>';
        }
        $body .= '<w:tc><w:tcPr><w:tcW w:w="'.$sigColWidth.'" w:type="dxa"/></w:tcPr><w:p><w:pPr><w:spacing w:after="40"/></w:pPr><w:r><w:rPr><w:sz w:val="15"/><w:color w:val="66776C"/></w:rPr><w:t>'.htmlspecialchars($signatoryRole, ENT_XML1).'</w:t></w:r></w:p><w:p><w:pPr><w:spacing w:before="260" w:after="30"/></w:pPr><w:r><w:rPr><w:b/><w:sz w:val="17"/></w:rPr><w:t>_____________________________________</w:t></w:r></w:p><w:p><w:r><w:rPr><w:b/><w:sz w:val="16"/><w:color w:val="17231D"/></w:rPr><w:t>'.htmlspecialchars($signatoryName, ENT_XML1).'</w:t></w:r></w:p></w:tc>';
    }
    $body .= '</w:tr></w:tbl>';
    $docXml = str_replace('<w:sectPr', $body.'<w:sectPr', $docXml);
    $zip->addFromString('word/document.xml', $docXml);
    $zip->close();
    echo sprintf("[%02d/22] Successfully generated %s (portrait, %d dxa)\n", $idx + 1, $def['file'], $twipWidth);
}
echo "All 22 baseline-cloned DOCX templates successfully built!\n";
