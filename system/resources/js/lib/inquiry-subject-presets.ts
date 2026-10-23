export type InquirySubjectPreset = {
    key: string;
    label: string;
    subject: string;
    office: 'registrar' | 'guidance' | null;
    office_label: string;
};

export const INQUIRY_SUBJECT_PRESETS: readonly InquirySubjectPreset[] = [
    {
        key: 'application_details',
        label: 'Application Form & Details',
        subject: 'Application Form & Personal Details',
        office: 'registrar',
        office_label: 'Registrar',
    },
    {
        key: 'document_requirements',
        label: 'Documents (Form 138 / PSA / TOR)',
        subject: 'Document Requirements (Form 138, PSA, TOR)',
        office: 'registrar',
        office_label: 'Registrar',
    },
    {
        key: 'transferee_academic_history',
        label: 'Transferee / School Not Listed',
        subject: 'Transferee, Second-Courser, or School Not Listed',
        office: 'registrar',
        office_label: 'Registrar',
    },
    {
        key: 'exam_schedule_slot',
        label: 'Entrance Exam Schedule & Slot',
        subject: 'Entrance Exam Schedule & Slot Reservation',
        office: 'guidance',
        office_label: 'Guidance',
    },
    {
        key: 'test_day_venue',
        label: 'Test Day & Venue Requirements',
        subject: 'Test Day Requirements & Venue',
        office: 'guidance',
        office_label: 'Guidance',
    },
    {
        key: 'results_release_advising',
        label: 'Results, Release Day & Advising',
        subject: 'Release Day Appointment, Results & Advising',
        office: 'guidance',
        office_label: 'Guidance',
    },
    {
        key: 'clearance_quota_enrollment',
        label: 'Clearance, Quota & Enrollment',
        subject: 'Admission Clearance, Program Quota & Enrollment',
        office: 'registrar',
        office_label: 'Registrar',
    },
    {
        key: 'custom',
        label: 'Other / Custom Topic',
        subject: '',
        office: null,
        office_label: 'Choose office manually',
    },
] as const;

export function findInquirySubjectPreset(key: string | null | undefined): InquirySubjectPreset | null {
    if (!key) return null;
    return INQUIRY_SUBJECT_PRESETS.find((preset) => preset.key === key) ?? null;
}
