<?php

namespace App\Foundation;

final class DemoPersonaCatalog
{
    /**
     * Canonical development and testing staff accounts.
     *
     * @var list<array{
     *     key: string,
     *     category: string,
     *     role_label: string,
     *     name: string,
     *     email: string,
     *     password: string,
     *     office: string,
     *     rank: int,
     *     description: string,
     *     login_url: string
     * }>
     */
    private const STAFF_PERSONAS = [
        [
            'key' => 'registrar-clerk',
            'category' => 'Admissions & Registrar',
            'role_label' => 'Registrar Clerk',
            'name' => 'Synthetic Registrar Clerk',
            'email' => 'registrar.clerk@nexiam.test',
            'password' => 'password',
            'office' => 'Registrar',
            'rank' => 2,
            'description' => 'Review applications, check Form 138/TOR requirements, record admission decisions.',
            'login_url' => '/login',
        ],
        [
            'key' => 'registrar-admin',
            'category' => 'Admissions & Registrar',
            'role_label' => 'Registrar Admin (Office Lead)',
            'name' => 'Synthetic Registrar Admin',
            'email' => 'registrar.admin@nexiam.test',
            'password' => 'password',
            'office' => 'Registrar',
            'rank' => 3,
            'description' => 'Academic cycle configuration, admissions window management, demo operator.',
            'login_url' => '/login',
        ],
        [
            'key' => 'guidance-counselor',
            'category' => 'Guidance & Assessment',
            'role_label' => 'Guidance Counselor (Office Lead)',
            'name' => 'Synthetic Guidance Counselor',
            'email' => 'guidance.counselor@nexiam.test',
            'password' => 'password',
            'office' => 'Guidance',
            'rank' => 3,
            'description' => 'Exam scheduling, result releases, consultation decisions, demo operator.',
            'login_url' => '/login',
        ],
        [
            'key' => 'guidance-clerk',
            'category' => 'Guidance & Assessment',
            'role_label' => 'Guidance Clerk',
            'name' => 'Synthetic Guidance Clerk',
            'email' => 'guidance.clerk@nexiam.test',
            'password' => 'password',
            'office' => 'Guidance',
            'rank' => 2,
            'description' => 'Daily consultation queue, intake assistance, notice templates.',
            'login_url' => '/login',
        ],
        [
            'key' => 'guidance-support',
            'category' => 'Guidance & Assessment',
            'role_label' => 'Guidance Support',
            'name' => 'Synthetic Guidance Support',
            'email' => 'guidance.support@nexiam.test',
            'password' => 'password',
            'office' => 'Guidance',
            'rank' => 1,
            'description' => 'Guidance office support work.',
            'login_url' => '/login',
        ],
        [
            'key' => 'program-head-bsit',
            'category' => 'Academic Leadership',
            'role_label' => 'Program Head — BSIT',
            'name' => 'Program Head — BS Information Technology (BSIT)',
            'email' => 'bsit.head@nexiam.test',
            'password' => 'password',
            'office' => 'Academic Programs',
            'rank' => 3,
            'description' => 'Referred consultation clearance, program pool quota decisions, public program profile.',
            'login_url' => '/login',
        ],
        [
            'key' => 'cas-dean',
            'category' => 'Academic Leadership',
            'role_label' => 'Dean of Arts & Sciences (CAS)',
            'name' => 'Dean of Arts & Sciences (CAS)',
            'email' => 'cas.dean@nexiam.test',
            'password' => 'password',
            'office' => 'Academic Programs',
            'rank' => 3,
            'description' => 'CAS collegiate oversight (BSIT, BSMath, BA-EL, BA-Psych, BASS).',
            'login_url' => '/login',
        ],
        [
            'key' => 'cbme-dean',
            'category' => 'Academic Leadership',
            'role_label' => 'Dean of Business Management & Entrepreneurship (CBME)',
            'name' => 'Dean of Business Management & Entrepreneurship (CBME)',
            'email' => 'cbme.dean@nexiam.test',
            'password' => 'password',
            'office' => 'Academic Programs',
            'rank' => 3,
            'description' => 'CBME collegiate oversight (BSBA, BSEntrep, BPA).',
            'login_url' => '/login',
        ],
        [
            'key' => 'cte-dean',
            'category' => 'Academic Leadership',
            'role_label' => 'Dean of Teacher Education (CTE)',
            'name' => 'Dean of Teacher Education (CTE)',
            'email' => 'cte.dean@nexiam.test',
            'password' => 'password',
            'office' => 'Academic Programs',
            'rank' => 3,
            'description' => 'CTE collegiate oversight (BSEd, BSEd-Eng, BSEd-Math, BEEd, BPEd).',
            'login_url' => '/login',
        ],
        [
            'key' => 'admin-lead',
            'category' => 'Institutional Administration',
            'role_label' => 'Institutional Administrator (Lead)',
            'name' => 'Synthetic Institutional Administrator',
            'email' => 'admin.lead@nexiam.test',
            'password' => 'password',
            'office' => 'Admin Office',
            'rank' => 4,
            'description' => 'Master calendar, campus rooms, curricular offerings, setup delegation, AI umbrella governance, and executive oversight.',
            'login_url' => '/login',
        ],
        [
            'key' => 'hr-admin',
            'category' => 'Institutional Administration',
            'role_label' => 'HR Administrator',
            'name' => 'Synthetic HR Administrator',
            'email' => 'hr.admin@nexiam.test',
            'password' => 'password',
            'office' => 'Admin Office',
            'rank' => 4,
            'description' => 'Staff personnel directory, account activation vouchers, password resets, role grants.',
            'login_url' => '/login',
        ],
    ];

    /**
     * Canonical development and testing applicant accounts.
     *
     * @var list<array{
     *     key: string,
     *     category: string,
     *     role_label: string,
     *     name: string,
     *     access_key: string,
     *     birthdate: string,
     *     stage_state: string,
     *     description: string,
     *     login_url: string
     * }>
     */
    private const APPLICANT_PERSONAS = [
        [
            'key' => 'applicant-accepted',
            'category' => 'Applicants',
            'role_label' => 'Accepted Applicant',
            'name' => 'Demo Accepted',
            'access_key' => 'DEMO-ACPT',
            'birthdate' => '2003-09-25',
            'stage_state' => 'Application Accepted',
            'description' => 'Verified by Registrar, ready to book exam session reservation.',
            'login_url' => '/applicant/login',
        ],
        [
            'key' => 'applicant-pending',
            'category' => 'Applicants',
            'role_label' => 'Pending Applicant',
            'name' => 'Demo Pending',
            'access_key' => 'DEMO-PEND',
            'birthdate' => '2003-09-25',
            'stage_state' => 'Submitted / Under Review',
            'description' => 'Awaiting intake document and grade verification by Registrar.',
            'login_url' => '/applicant/login',
        ],
        [
            'key' => 'applicant-online-consultation',
            'category' => 'Applicants',
            'role_label' => 'Online Consultation Candidate',
            'name' => 'Online Portal',
            'access_key' => 'PORT-ONLN',
            'birthdate' => '2003-09-25',
            'stage_state' => 'Results Released (Online)',
            'description' => 'Exam scored; viewing scores and attending online consultation room.',
            'login_url' => '/applicant/login',
        ],
        [
            'key' => 'applicant-f2f-consultation',
            'category' => 'Applicants',
            'role_label' => 'Face-to-Face Consultation Candidate',
            'name' => 'F2F Portal',
            'access_key' => 'PORT-F2F2',
            'birthdate' => '2003-09-25',
            'stage_state' => 'Results Released (F2F)',
            'description' => 'Physical document printed; attending in-person consultation queue.',
            'login_url' => '/applicant/login',
        ],
    ];

    /**
     * Complete directory of all personas (for development directory modal).
     *
     * @return array{staff: list<array<string, mixed>>, applicants: list<array<string, mixed>>}
     */
    public function directory(): array
    {
        return [
            'staff' => self::STAFF_PERSONAS,
            'applicants' => self::APPLICANT_PERSONAS,
        ];
    }

    /**
     * Map a step's actor string to its primary testing persona.
     *
     * @return array<string, mixed>|null
     */
    public function resolveActorPersona(string $actor): ?array
    {
        $normalized = strtolower(trim($actor));

        if (str_contains($normalized, 'applicant') || str_contains($normalized, 'student')) {
            if (str_contains($normalized, 'accepted')) {
                return $this->findApplicant('applicant-accepted');
            }
            if (str_contains($normalized, 'portal') || str_contains($normalized, 'outcome')) {
                return $this->findApplicant('applicant-online-consultation');
            }

            return $this->findApplicant('applicant-pending');
        }

        if (str_contains($normalized, 'registrar')) {
            if (str_contains($normalized, 'admin') || str_contains($normalized, 'lead')) {
                return $this->findStaff('registrar-admin');
            }

            return $this->findStaff('registrar-clerk');
        }

        if (str_contains($normalized, 'proctor') || str_contains($normalized, 'attendance')) {
            return $this->findStaff('guidance-counselor');
        }

        if (str_contains($normalized, 'guidance') || str_contains($normalized, 'counselor') || str_contains($normalized, 'scoring') || str_contains($normalized, 'release')) {
            if (str_contains($normalized, 'clerk')) {
                return $this->findStaff('guidance-clerk');
            }

            return $this->findStaff('guidance-counselor');
        }

        if (str_contains($normalized, 'dean') || str_contains($normalized, 'program head') || str_contains($normalized, 'academic')) {
            return $this->findStaff('program-head-bsn');
        }

        if (str_contains($normalized, 'hr') || str_contains($normalized, 'delegation') || str_contains($normalized, 'account')) {
            return $this->findStaff('hr-admin');
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findStaff(string $key): ?array
    {
        foreach (self::STAFF_PERSONAS as $persona) {
            if ($persona['key'] === $key) {
                return $persona + ['type' => 'staff'];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findApplicant(string $key): ?array
    {
        foreach (self::APPLICANT_PERSONAS as $persona) {
            if ($persona['key'] === $key) {
                return $persona + ['type' => 'applicant'];
            }
        }

        return null;
    }
}
