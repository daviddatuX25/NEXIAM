const choices = (entries) => entries.map(([code, label, text]) => ({ code, label, text }));

export const ADMISSION_DISMISS_SUGGESTIONS = choices([
  ['duplicate', 'Duplicate application', 'This application duplicates another submission in the current cycle.'],
  ['withdrawal', 'Applicant withdrew', 'The applicant requested withdrawal of this application.'],
  ['eligibility', 'Eligibility not met', 'The applicant did not meet the published admission eligibility requirements.'],
  ['unverifiable', 'Unverifiable record', 'The application record is incomplete or could not be verified.'],
]);
export const ADMISSION_REQUEST_CORRECTION_SUGGESTIONS = choices([
  ['identity', 'Identity mismatch', 'The name or birthdate does not match the PSA Birth Certificate.'],
  ['school', 'Verify school details', 'Please verify the school or academic track and strand.'],
  ['contact', 'Complete contact details', 'The contact email or mobile number is incomplete.'],
  ['profile', 'Complete profile details', 'Please review and complete the missing profile details.'],
]);
export const ADMISSION_REOPEN_SUGGESTIONS = choices([
  ['evidence', 'Evidence submitted', 'The applicant submitted missing verification evidence.'],
  ['review-error', 'Review correction', 'The application was dismissed in error during batch review.'],
  ['registrar-review', 'Registrar reevaluation', 'Authorized reopening for Registrar reevaluation.'],
]);
export const ADMISSION_RECONSIDER_SUGGESTIONS = choices([
  ['profile-correction', 'Profile correction', 'Return the application to pending for profile or classification correction.'],
  ['document-check', 'Document recheck', 'Acceptance is paused pending document reverification.'],
  ['administrative-review', 'Administrative review', 'Administrative review is required before exam scheduling.'],
]);
export const ADMISSION_REISSUE_KEY_SUGGESTIONS = choices([
  ['lost-slip', 'Lost access slip', 'The applicant lost the original access key slip.'],
  ['counter-reprint', 'Counter reprint', 'The applicant requested a walk-in counter credential reprint.'],
  ['email-unavailable', 'Email unavailable', 'Email delivery was unavailable; the key was reissued at the Registrar window.'],
]);
export const ADMISSION_AMENDMENT_SUGGESTIONS = choices([
  ['legal-name', 'Legal name correction', 'Corrected the legal name spelling against the PSA Birth Certificate.'],
  ['contact-update', 'Contact update', 'Updated the contact email or mobile number per applicant request.'],
  ['school-program', 'School or program', 'Corrected the school name or program preference from the verified form.'],
]);
export const ADMISSION_STAFF_CORRECTION_NOTE_SUGGESTIONS = choices([
  ['profile-checked', 'Profile checked', 'Profile details checked against the presented record.'],
  ['counter-update', 'Counter update', 'Contact details updated at the counter.'],
  ['program-update', 'Program updated', 'Program preference updated per applicant request.'],
]);
export const ADMISSION_APPLICANT_RESUBMISSION_SUGGESTIONS = choices([
  ['personal-info', 'Personal information', 'I corrected my personal information.'],
  ['contact-info', 'Contact details', 'I updated my contact details.'],
  ['school-program', 'School or program', 'I updated my school or program preference.'],
]);
export const DOCUMENT_INTERNAL_NOTE_SUGGESTIONS = choices([
  ['original-inspected', 'Original inspected', 'Physical original inspected at the counter.'],
  ['copy-received', 'Copy received', 'Photocopy received; awaiting the original.'],
  ['previous-school', 'School release pending', 'Pending release from the previous school.'],
  ['registrar-checked', 'Registrar checked', 'Checked by Registrar staff.'],
]);
export const DOCUMENT_OVERALL_REQUEST_NOTE_SUGGESTIONS = choices([
  ['comply', 'Complete checklist', 'Please comply with the items listed in the document checklist.'],
  ['submit-items', 'Submit outstanding items', 'Please submit the outstanding documents for Registrar review.'],
]);
export const REGISTRAR_CLEARANCE_REVOKE_SUGGESTIONS = choices([
  ['deficient-document', 'Document deficiency', 'A deficient document was discovered during the final check.'],
  ['program-adjustment', 'Program adjustment', 'An authorized program choice adjustment is required.'],
  ['checklist-update', 'Checklist update', 'Clearance reopened for a Registrar checklist update.'],
]);
export const APPLICANT_PROGRAM_CANCEL_SUGGESTIONS = choices([
  ['different-program', 'Choose another program', 'I would like to choose a different degree program.'],
  ['selected-by-mistake', 'Selected by mistake', 'I selected this program by mistake.'],
  ['guidance-advising', 'Request advising', 'I would like Guidance advising before finalizing.'],
]);
