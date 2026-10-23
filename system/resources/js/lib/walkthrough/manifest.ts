import type { TourDomain, TourPersona } from './types';

export interface TourManifestEntry {
    readonly key: string;
    readonly title: string;
    readonly persona: TourPersona;
    readonly domain: TourDomain;
    readonly route: string;
    readonly description: string;
    readonly samplePrompts: readonly string[];
    readonly allowAskAi?: boolean;
}

export const TOUR_MANIFEST: readonly TourManifestEntry[] = [
    {
        key: 'applicant-exam-schedule',
        title: 'Exam Scheduling Guide',
        persona: 'applicant-mentor',
        domain: 'applicant',
        route: '/applicant/schedule',
        description:
            'Guides applicants through self-reservation exam slots, room capacities, and published appointments.',
        samplePrompts: [
            'How do I choose my exam date?',
            'Where is my exam appointment?',
            'Show me the schedule guide.',
        ],
    },
    {
        key: 'assessment-omr-camera-scanner',
        title: 'OMR Camera Scanner Guide',
        persona: 'exam-administrator-guide',
        domain: 'examinations',
        route: '/scoring/omr',
        description:
            'Guides Test Administrators through 4-corner fiducial alignment, darkness thresholding, and unreadable QR recovery.',
        samplePrompts: [
            'How do I scan an answer sheet?',
            'Why is the QR code not reading?',
            'Guide me through the OMR scanner.',
        ],
    },
    {
        key: 'assessment-grading-dashboard',
        title: 'Scoring Dashboard Guide',
        persona: 'staff-copilot',
        domain: 'examinations',
        route: '/grading',
        description:
            'Guides testing staff through room-scoped scoring rosters, spreadsheet score imports, and batch finalization.',
        samplePrompts: [
            'How do I score an exam session?',
            'How do I import scores?',
            'Show me around the scoring hub.',
        ],
    },
    {
        key: 'release-batch-releasing',
        title: 'Result Documents & Printing Guide',
        persona: 'staff-copilot',
        domain: 'admissions',
        route: '/release',
        description:
            'Guides staff through active result sheet template verification, release counts, and batch printing workspaces.',
        samplePrompts: [
            'How do I print result slips?',
            'Why is printing blocked?',
            'Show me result releasing.',
        ],
    },
    {
        key: 'applicant-program-choice',
        title: 'Program Selection Guide',
        persona: 'applicant-mentor',
        domain: 'applicant',
        route: '/applicant/program-choice',
        description:
            'Guides applicants through acknowledging advised programs, reviewing alternative course pools, and enrollment locking.',
        samplePrompts: [
            'How do I confirm my course?',
            'Can I choose another college degree?',
            'Guide me through program selection.',
        ],
    },
    {
        key: 'exam-monitoring',
        title: 'Exam Day Monitoring Guide',
        persona: 'exam-administrator-guide',
        domain: 'examinations',
        route: '/exams/monitoring',
        description:
            'Guides test administrators through real-time attendance telemetry, active testing rooms, and absence alerts.',
        samplePrompts: [
            'How do I monitor live exams?',
            'What does the absence alert mean?',
            'Tour exam monitoring.',
        ],
    },
    {
        key: 'data-ingestion',
        title: 'Data Ingestion Hub Guide',
        persona: 'staff-copilot',
        domain: 'setup',
        route: '/imports',
        description:
            'Guides staff through template headers, exact scoring context, workbook analysis and staged review.',
        samplePrompts: [
            'How do I import student spreadsheets?',
            'How are invalid rows handled?',
            'Tour data ingestion.',
        ],
    },
    {
        key: 'enrollment-eligibility',
        title: 'Admission Clearance Counter Guide',
        persona: 'staff-copilot',
        domain: 'admissions',
        route: '/registrar/enrollment-eligibility',
        description:
            'Guides registrars through the 4 computed clearance milestones (accepted, graded, released, advised).',
        samplePrompts: [
            'How do I check admission clearance readiness?',
            'Can I manually override missing requirements?',
            'Tour admission clearance.',
        ],
    },
    {
        key: 'admission-support',
        title: 'Admission Decision Support Guide',
        persona: 'staff-copilot',
        domain: 'setup',
        route: '/setup/decision-support',
        description:
            'Guides staff through TabFM calibration, benchmark provenance hashes, and counselor autonomy principles.',
        samplePrompts: [
            'How does TabFM match students?',
            'What is the ethical framework for AI?',
            'Tour decision support.',
        ],
    },
    {
        key: 'setup-readiness-hub',
        title: 'System Setup Readiness Guide',
        persona: 'staff-copilot',
        domain: 'setup',
        route: '/setup',
        description:
            'Guides administrators through the 4-phase readiness audit and resolving critical configuration blockers.',
        samplePrompts: [
            'Why is setup readiness incomplete?',
            'What are critical blockers?',
            'Tour system setup.',
        ],
    },
    {
        key: 'exam-sessions',
        title: 'Exam Scheduling Planning Guide',
        persona: 'staff-copilot',
        domain: 'examinations',
        route: '/exams/sessions',
        description:
            'Guides testing coordinators through session planning, cohort auto-distribution, and bulk publishing.',
        samplePrompts: [
            'How do I auto-distribute examinees?',
            'How do I publish exam sessions?',
            'Tour exam scheduling.',
        ],
    },
    {
        key: 'consultation-scheduling',
        title: 'Release Day Scheduling Guide',
        persona: 'staff-copilot',
        domain: 'consultation',
        route: '/consultation/sessions',
        description:
            'Guides staff through Release Day session capacity, reservation modes, and applicant assignment.',
        samplePrompts: [
            'How do I schedule a Release Day session?',
            'How do I manage Release Days?',
            'Tour Release Day Scheduling.',
        ],
    },
    {
        key: 'consultation-hub',
        title: 'Release Day Guide',
        persona: 'staff-copilot',
        domain: 'consultation',
        route: '/consultation',
        description:
            'Guides staff through the Release Day queue, re-advising queue, and walk-in applicant lookups.',
        samplePrompts: [
            'How do I look up a walk-in applicant?',
            'What is the re-advising queue?',
            'Tour Release Day.',
        ],
    },
    {
        key: 'exam-materials',
        title: 'Test Kits Guide',
        persona: 'staff-copilot',
        domain: 'setup',
        route: '/setup/exam-materials/test-kits',
        description:
            'Guides staff through Test Kits and opening a kit to review its setup context.',
        samplePrompts: [
            'How do I find a Test Kit?',
            'How do I open a Test Kit?',
            'Tour Test Kits.',
        ],
    },
    {
        key: 'program-head-overview',
        title: 'Program Head Workspace Guide',
        persona: 'staff-copilot',
        domain: 'admissions',
        route: '/staff/programs',
        description:
            'Guides academic department heads through assigned degree programs, quota limits, and candidate rosters.',
        samplePrompts: [
            'How do I view candidates for my department?',
            'How do quotas work?',
            'Tour program head workspace.',
        ],
    },
    {
        key: 'xiam-knowledge',
        title: 'FAQs Guide',
        persona: 'staff-copilot',
        domain: 'communications',
        route: '/knowledge',
        description:
            'Guides staff through FAQ review, authoring, and publication across central offices.',
        samplePrompts: [
            'How do I add a new FAQ?',
            'Who approves FAQs?',
            'Tour FAQs hub.',
        ],
    },
    {
        key: 'applicant-apply-guide',
        title: 'Admission Application Guide',
        persona: 'applicant-mentor',
        domain: 'applicant',
        route: '/apply',
        description:
            'Walks prospective applicants through personal details, academic background, degree preferences, and passwordless access key setup.',
        allowAskAi: false,
        samplePrompts: [
            'How do I fill out the application form?',
            'What happens after submitting my application?',
            'Tour the application form.',
        ],
    },
    {
        key: 'applicant-consultation',
        title: 'Your Release Day Appointment',
        persona: 'applicant-mentor',
        domain: 'applicant',
        route: '/applicant/release-schedule',
        description:
            'Guides applicants through their Release Day appointment status and preparation for the encounter.',
        samplePrompts: [
            'How do I review my Release Day appointment?',
            'What should I bring to my appointment?',
            'Tour my Release Day appointment.',
        ],
    },
    {
        key: 'applicant-access-key-safety',
        title: 'Access Key Safety Guide',
        persona: 'applicant-mentor',
        domain: 'applicant',
        route: '/admission/confirmation',
        description:
            'Guides applicants on keeping their 16-character access key safe on shared computers and internet cafes.',
        allowAskAi: false,
        samplePrompts: [
            'How do I keep my key safe?',
            'Can I save my key on a public computer?',
            'Tour access key safety.',
        ],
    },
    {
        key: 'applicant-my-application',
        title: 'My Application Record Guide',
        persona: 'applicant-mentor',
        domain: 'applicant',
        route: '/applicant/applications',
        description:
            'Guides applicants through their submitted demographic and academic background, program choices, and correction requests.',
        samplePrompts: [
            'How do I fix a correction on my application?',
            'How are my documents verified?',
            'Tour my application.',
        ],
    },
    {
        key: 'applicant-portal',
        title: 'Applicant Portal Status Guide',
        persona: 'applicant-mentor',
        domain: 'applicant',
        route: '/applicant/portal',
        description:
            'Guides applicants through their multi-stage status spine, active milestone actions, and digital entry pass.',
        samplePrompts: [
            'What step am I currently on?',
            'Where is my exam day pass?',
            'Tour the applicant portal.',
        ],
    },
    {
        key: 'applicant-qr',
        title: 'Permanent Applicant QR Pass Guide',
        persona: 'applicant-mentor',
        domain: 'applicant',
        route: '/applicant/qr',
        description:
            'Guides applicants on presenting their permanent QR code at the campus gate, testing rooms, and registrar.',
        samplePrompts: [
            'How do I use my QR code?',
            'Does my QR pass expire?',
            'Tour my QR pass.',
        ],
    },
    {
        key: 'staff-operations-dashboard',
        title: 'Staff Operations Dashboard Guide',
        persona: 'staff-copilot',
        domain: 'admissions',
        route: '/staff',
        description:
            'Guides university staff through role-tailored executive telemetry: admissions inflow, exam triage, and quotas.',
        samplePrompts: [
            'Show me around the operations dashboard.',
            'What do these telemetry cards mean?',
            'Tour staff dashboard.',
        ],
    },
];

export function getTourForRoute(pathname: string): TourManifestEntry | null {
    const cleanPath = pathname.split('?')[0].replace(/\/$/, '') || '/';
    return TOUR_MANIFEST.find((entry) => entry.route === cleanPath) ?? null;
}
