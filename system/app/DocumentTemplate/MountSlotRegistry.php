<?php

namespace App\DocumentTemplate;

use Illuminate\Validation\ValidationException;

final class MountSlotRegistry
{
    public const GRAIN_STUDENT = 'student';

    public const GRAIN_APPLICANT = self::GRAIN_STUDENT;

    public const GRAIN_SESSION = 'session';

    public const GRAIN_CYCLE_PROGRAM = 'cycle_program';

    public const GRAIN_STAFF = 'staff';

    /**
     * @return array<string, array{
     *   slot_key: string,
     *   label: string,
     *   grain: string,
     *   office: string,
     *   mode: string
     * }>
     */
    public static function all(): array
    {
        return [
            'admissions_registry_bulk' => [
                'slot_key' => 'admissions_registry_bulk',
                'label' => 'Applications Registry (/admissions bulk bar & row)',
                'grain' => self::GRAIN_STUDENT,
                'office' => 'registrar',
                'mode' => 'batch',
            ],
            'admissions_applicant_show' => [
                'slot_key' => 'admissions_applicant_show',
                'label' => 'Applicant Profile (/admissions/{id})',
                'grain' => self::GRAIN_STUDENT,
                'office' => 'registrar',
                'mode' => 'single',
            ],
            'applicant_qr_scan_modal' => [
                'slot_key' => 'applicant_qr_scan_modal',
                'label' => 'Applicant QR Scan / Action Hub',
                'grain' => self::GRAIN_STUDENT,
                'office' => 'registrar',
                'mode' => 'single',
            ],
            'registrar_enrollment_clearance' => [
                'slot_key' => 'registrar_enrollment_clearance',
                'label' => 'Admission Clearance (/registrar/enrollment-eligibility)',
                'grain' => self::GRAIN_STUDENT,
                'office' => 'registrar',
                'mode' => 'batch',
            ],
            'exam_session_show' => [
                'slot_key' => 'exam_session_show',
                'label' => 'Exam Session Workspace (/exams/sessions/{id})',
                'grain' => self::GRAIN_SESSION,
                'office' => 'guidance',
                'mode' => 'single',
            ],
            'guidance_applicant_support' => [
                'slot_key' => 'guidance_applicant_support',
                'label' => 'Guidance Applicant Case & Consultation (/consultation)',
                'grain' => self::GRAIN_STUDENT,
                'office' => 'guidance',
                'mode' => 'single',
            ],
            'consultation_session_show' => [
                'slot_key' => 'consultation_session_show',
                'label' => 'Consultation Session Workspace (/consultation/sessions/{id})',
                'grain' => self::GRAIN_SESSION,
                'office' => 'guidance',
                'mode' => 'single',
            ],
            'analytics_reports_hub' => [
                'slot_key' => 'analytics_reports_hub',
                'label' => 'Analytics & Reports Hub (/reports)',
                'grain' => self::GRAIN_CYCLE_PROGRAM,
                'office' => 'any',
                'mode' => 'report',
            ],
            'program_workspace_show' => [
                'slot_key' => 'program_workspace_show',
                'label' => 'Program Workspace (/staff/programs/{code})',
                'grain' => self::GRAIN_CYCLE_PROGRAM,
                'office' => 'program',
                'mode' => 'report',
            ],
            'personnel_voucher_modal' => [
                'slot_key' => 'personnel_voucher_modal',
                'label' => 'Personnel Management (/staff/personnel voucher slip)',
                'grain' => self::GRAIN_STAFF,
                'office' => 'admin',
                'mode' => 'single',
            ],
        ];
    }

    /**
     * @return array{slot_key: string, label: string, grain: string, office: string, mode: string}|null
     */
    public static function find(string $slotKey): ?array
    {
        return self::all()[$slotKey] ?? null;
    }

    /**
     * @return list<array{
     *   slot_key: string,
     *   label: string,
     *   grain: string,
     *   office: string,
     *   mode: string
     * }>
     */
    public static function forGrain(string $grain, ?string $office = null): array
    {
        return array_values(array_filter(
            self::all(),
            static function (array $slot) use ($grain, $office): bool {
                if ($slot['grain'] !== $grain) {
                    return false;
                }

                if ($office === null || $office === '') {
                    return true;
                }

                return $slot['office'] === 'any' || $slot['office'] === $office;
            },
        ));
    }

    /**
     * @return list<array{
     *   slot_key: string,
     *   label: string,
     *   grain: string,
     *   office: string,
     *   mode: string
     * }>
     */
    public static function forOffice(string $office): array
    {
        return array_values(array_filter(
            self::all(),
            static fn (array $slot): bool => $slot['office'] === $office || $slot['slot_key'] === 'analytics_reports_hub',
        ));
    }

    /**
     * @param  array<int|string, mixed>  $requestedSlots
     * @return list<string>
     */
    public static function sanitizeSlots(array $requestedSlots, string $grain, ?string $office = null): array
    {
        $allowedKeys = array_column(self::forGrain($grain, $office), 'slot_key');
        $allowedLookup = array_fill_keys($allowedKeys, true);
        $sanitized = [];

        foreach ($requestedSlots as $item) {
            $slotKey = is_string($item)
                ? trim($item)
                : (is_array($item) && is_string($item['slot_key'] ?? null) ? trim($item['slot_key']) : null);

            if ($slotKey === null || $slotKey === '' || ! isset($allowedLookup[$slotKey])) {
                throw ValidationException::withMessages([
                    'mounted_slots' => ['Mount slots must be registered and compatible with this document surface.'],
                ]);
            }

            if (isset($sanitized[$slotKey])) {
                throw ValidationException::withMessages([
                    'mounted_slots' => ['Mount slots must not contain duplicates.'],
                ]);
            }

            $sanitized[$slotKey] = $slotKey;
        }

        return array_values($sanitized);
    }

    public static function grainLabel(string $grain): string
    {
        return match ($grain) {
            self::GRAIN_STUDENT => 'Student Grain — 1 slip per applicant (N-page batch PDF)',
            self::GRAIN_SESSION => 'Session Grain — 1 roster per exam session',
            self::GRAIN_STAFF => 'Staff Grain — 1 voucher slip per personnel account',
            default => 'Cycle / Program Grain — Aggregate & roster report',
        };
    }
}
