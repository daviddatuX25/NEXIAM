const ACTION_PRESENTATION: Record<
    string,
    { title: string; successMessage: string }
> = {
    'exams.session.create@1': {
        title: 'Create exam session',
        successMessage: 'Exam session created.',
    },
    'exams.session.assign@1': {
        title: 'Assign applicants to session',
        successMessage: 'Applicants assigned to the session.',
    },
    'exams.session.publish@1': {
        title: 'Publish exam session',
        successMessage: 'Exam session published.',
    },
    'exams.session.reservation.open@1': {
        title: 'Open exam reservations',
        successMessage: 'Exam reservations opened.',
    },
    'consultation.session.create@1': {
        title: 'Create consultation session',
        successMessage: 'Consultation session created.',
    },
    'consultation.session.reservation.open@1': {
        title: 'Open consultation reservations',
        successMessage: 'Consultation reservations opened.',
    },
    'setup.rooms.create_draft@1': {
        title: 'Create room',
        successMessage: 'Room created.',
    },
    'setup.rooms.update@1': {
        title: 'Update room capacity',
        successMessage: 'Room capacity updated.',
    },
    'setup.cycles.create_draft@1': {
        title: 'Create academic cycle',
        successMessage: 'Academic cycle created.',
    },
    'setup.cycles.activate@1': {
        title: 'Activate academic cycle',
        successMessage: 'Academic cycle activated.',
    },
    'setup.quotas.upsert@1': {
        title: 'Update program quota',
        successMessage: 'Program quota updated.',
    },
    'exams.slot.reserve@1': {
        title: 'Reserve exam slot',
        successMessage: 'Exam slot reserved.',
    },
    'consultation.decision.record@2': {
        title: 'Record consultation decision',
        successMessage: 'Consultation decision recorded.',
    },
    'consultation.session.cancel@1': {
        title: 'Cancel consultation session',
        successMessage: 'Consultation session cancelled.',
    },
    'exams.session.start@1': {
        title: 'Start exam session',
        successMessage: 'Exam session started.',
    },
    'exams.session.close@1': {
        title: 'Close exam session',
        successMessage: 'Exam session closed.',
    },
    'setup.notifications.update_admission@1': {
        title: 'Update admission notifications',
        successMessage: 'Admission notifications updated.',
    },
    'setup.notifications.update_exam@1': {
        title: 'Update exam notifications',
        successMessage: 'Exam notifications updated.',
    },
    'setup.windows.store@1': {
        title: 'Update application window',
        successMessage: 'Application window updated.',
    },
    'admissions.attempt.accept@1': {
        title: 'Accept applicant',
        successMessage: 'Applicant accepted.',
    },
    'admissions.attempt.bulk_accept@1': {
        title: 'Accept applicants',
        successMessage: 'Applicants accepted.',
    },
    'assessment.grading.finalize@1': {
        title: 'Finalize grades',
        successMessage: 'Grades finalized.',
    },
    'admission.office_inquiries.open@1': {
        title: 'Send office inquiry',
        successMessage: 'Office inquiry sent.',
    },
    'admission.office_inquiries.reply@1': {
        title: 'Reply to office inquiry',
        successMessage: 'Office inquiry reply sent.',
    },
    'exams.attendance.record@1': {
        title: 'Record exam attendance',
        successMessage: 'Exam attendance recorded.',
    },
    'admissions.attempt.request_correction@1': {
        title: 'Request applicant correction',
        successMessage: 'Correction requested.',
    },
    'assessment.scores.record_manual@1': {
        title: 'Record manual scores',
        successMessage: 'Scores recorded.',
    },
    'release.result.publish@2': {
        title: 'Release examination result',
        successMessage: 'Examination result released.',
    },
    'registrar.enrollment.stamp_enrolling@1': {
        title: 'Enrollment stamp (retired)',
        successMessage: 'This action is retired; no new changes can be made.',
    },
    'registrar.enrollment.stamp_enrolled@1': {
        title: 'Enrollment stamp (retired)',
        successMessage: 'This action is retired; no new changes can be made.',
    },
    'registrar.enrollment.reverse_stamp@1': {
        title: 'Reverse enrollment stamp (retired)',
        successMessage: 'This action is retired; no new changes can be made.',
    },
};

function isHumanReadable(value: string): boolean {
    return (
        !/\b(?:uuid|capability|tier|digest|sha-?256)\b/i.test(value) &&
        !/\b[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\b/i.test(
            value,
        ) &&
        !/\b[a-z][\w.-]*@[0-9]+\b/i.test(value)
    );
}

export function getXiamActionPresentation(
    capability?: string | null,
    title?: string | null,
) {
    const known =
        capability && Object.hasOwn(ACTION_PRESENTATION, capability)
            ? ACTION_PRESENTATION[capability]
            : undefined;
    if (known) return known;
    if (
        title &&
        !title.includes('@') &&
        title !== 'Allowlisted action proposal' &&
        isHumanReadable(title)
    ) {
        return { title, successMessage: 'Changes saved.' };
    }
    return { title: 'Review changes', successMessage: 'Changes saved.' };
}
