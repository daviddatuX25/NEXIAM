<?php

namespace App\Testing;

use App\Testing\Seeders\Stages\SeedConductStage;
use App\Testing\Seeders\Stages\SeedConsultationStage;
use App\Testing\Seeders\Stages\SeedEnrollmentStage;
use App\Testing\Seeders\Stages\SeedGradingStage;
use App\Testing\Seeders\Stages\SeedProgramStage;
use App\Testing\Seeders\Stages\SeedReservationStage;
use App\Testing\Seeders\Stages\SeedReviewStage;
use App\Testing\Seeders\Stages\SeedSetupStage;

final class PracticeScenarioCatalog
{
    public const ACTIONS = ['prepare', 'replace', 'retire', 'time', 'reminder', 'pair'];

    public function scenarios(string $purpose): array
    {
        $labels = $purpose === 'research' ? [
            'student' => 'Student', 'registrar' => 'Registrar', 'guidance' => 'Guidance', 'academic-leader' => 'Academic leader',
        ] : [
            'application-review' => 'Application and review',
            'exam-scheduling-attendance' => 'Exam scheduling and attendance',
            'scoring-results' => 'Scoring and results',
            'consultation-referral' => 'Consultation and referral',
            'case-coordination' => 'Case coordination',
            'enrollment-verification' => 'Enrollment verification',
            'staff-accounts-delegation' => 'Staff accounts and delegation',
            'account-technical-support' => 'Account and technical support',
        ];

        return array_map(static fn (string $key, string $label): array => ['key' => $key, 'label' => $label], array_keys($labels), array_values($labels));
    }

    public function valid(string $purpose, string $scenario): bool
    {
        return in_array($purpose, ['demo', 'research'], true) && in_array($scenario, array_column($this->scenarios($purpose), 'key'), true);
    }

    public function builders(string $scenario): array
    {
        return match ($scenario) {
            'application-review' => [SeedReviewStage::class],
            'exam-scheduling-attendance' => [SeedReservationStage::class, SeedConductStage::class],
            'scoring-results' => [SeedGradingStage::class, SeedConsultationStage::class],
            'consultation-referral', 'case-coordination', 'academic-leader' => [SeedProgramStage::class],
            'enrollment-verification' => [SeedEnrollmentStage::class],
            'staff-accounts-delegation', 'account-technical-support' => [SeedSetupStage::class],
            'student' => [SeedReviewStage::class, SeedReservationStage::class, SeedProgramStage::class],
            'registrar' => [SeedReviewStage::class, SeedEnrollmentStage::class],
            'guidance' => [SeedReservationStage::class, SeedConductStage::class, SeedGradingStage::class, SeedConsultationStage::class, SeedProgramStage::class],
        };
    }

    public function needsExam(string $scenario): bool
    {
        return ! in_array($scenario, ['application-review', 'staff-accounts-delegation', 'account-technical-support'], true);
    }

    public function needsResults(string $scenario): bool
    {
        return ! in_array($scenario, ['application-review', 'exam-scheduling-attendance', 'staff-accounts-delegation', 'account-technical-support'], true);
    }

    public function preserved(): array
    {
        return ['Genuine and unclassified applicants and their history', 'Institutional programs, quotas, cycles and admission windows', 'Rooms, seating and authored exam materials', 'Staff credentials, grants and program assignments', 'Result templates, physical receipts and private artifacts', 'Prior practice outcomes and audit history'];
    }
}
