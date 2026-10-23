<?php

namespace Database\Seeders;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Assessment\GradingSession;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Release\ResultDocument;
use Illuminate\Database\Seeder;

class CohortDistributionDemoSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::query()->where('code', 'SYN-INST-001')->first()
            ?? Institution::query()->create(['code' => 'SYN-INST-001', 'name' => (string) config('public-site.institution.name', 'Ilocos Sur Polytechnic State College')]);

        $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->first()
            ?? AcademicCycle::query()->create([
                'institution_id' => $institution->id,
                'code' => 'SYN-CYCLE-2026',
                'name' => '2025-2026 1st Semester',
                'school_year' => '2025-2026',
                'semester' => '1',
                'opens_on' => '2026-08-01',
                'closes_on' => '2026-12-31',
                'is_active' => true,
            ]);

        $window = ApplicationWindow::query()->where('academic_cycle_id', $cycle->id)->first();
        $windowId = $window?->id;

        $room101 = Room::query()->where('institution_id', $institution->id)->where('code', 'RM-101')->first()
            ?? Room::query()->create([
                'institution_id' => $institution->id,
                'code' => 'RM-101',
                'name' => 'Lecture Hall 101',
                'capacity' => 40,
                'is_active' => true,
            ]);

        $room204 = Room::query()->where('institution_id', $institution->id)->where('code', 'RM-204')->first()
            ?? Room::query()->create([
                'institution_id' => $institution->id,
                'code' => 'RM-204',
                'name' => 'Computer Lab 204',
                'capacity' => 40,
                'is_active' => true,
            ]);

        // 1. Seed Exam Sessions
        ExamSession::query()->updateOrCreate(
            ['academic_cycle_id' => $cycle->id, 'code' => 'EX-DIST-001'],
            [
                'room_id' => $room101->id,
                'status' => ExamSession::STATUS_DRAFT,
                'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
                'auto_assign_enabled' => true,
                'starts_at' => now()->addDays(10)->setTime(9, 0),
                'effective_end_at' => now()->addDays(10)->setTime(11, 0),
                'version' => 1,
            ]
        );

        ExamSession::query()->updateOrCreate(
            ['academic_cycle_id' => $cycle->id, 'code' => 'EX-DIST-002'],
            [
                'room_id' => $room204->id,
                'status' => ExamSession::STATUS_DRAFT,
                'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
                'auto_assign_enabled' => false,
                'starts_at' => now()->addDays(10)->setTime(13, 0),
                'effective_end_at' => now()->addDays(10)->setTime(15, 0),
                'version' => 1,
            ]
        );

        ExamSession::query()->updateOrCreate(
            ['academic_cycle_id' => $cycle->id, 'code' => 'EX-DIST-003'],
            [
                'room_id' => $room101->id,
                'status' => ExamSession::STATUS_DRAFT,
                'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
                'auto_assign_enabled' => false,
                'starts_at' => now()->addDays(11)->setTime(9, 0),
                'effective_end_at' => now()->addDays(11)->setTime(11, 0),
                'version' => 1,
            ]
        );

        ExamSession::query()->updateOrCreate(
            ['academic_cycle_id' => $cycle->id, 'code' => 'EX-DIST-004'],
            [
                'room_id' => $room204->id,
                'status' => ExamSession::STATUS_DRAFT,
                'booking_mode' => ExamSession::BOOKING_OPEN_FOR_RESERVATION,
                'auto_assign_enabled' => false,
                'starts_at' => now()->addDays(12)->setTime(9, 0),
                'effective_end_at' => now()->addDays(12)->setTime(11, 0),
                'version' => 1,
            ]
        );

        // 2. Seed Consultation Sessions
        ConsultationSession::query()->updateOrCreate(
            ['academic_cycle_id' => $cycle->id, 'code' => 'CS-DIST-001'],
            [
                'room_id' => $room101->id,
                'status' => ConsultationSession::STATUS_DRAFT,
                'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
                'auto_assign_enabled' => true,
                'scheduled_at' => now()->addDays(14)->setTime(9, 0),
                'version' => 1,
            ]
        );

        ConsultationSession::query()->updateOrCreate(
            ['academic_cycle_id' => $cycle->id, 'code' => 'CS-DIST-002'],
            [
                'room_id' => $room204->id,
                'status' => ConsultationSession::STATUS_DRAFT,
                'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
                'auto_assign_enabled' => false,
                'scheduled_at' => now()->addDays(14)->setTime(13, 0),
                'version' => 1,
            ]
        );

        ConsultationSession::query()->updateOrCreate(
            ['academic_cycle_id' => $cycle->id, 'code' => 'CS-DIST-003'],
            [
                'room_id' => $room101->id,
                'status' => ConsultationSession::STATUS_PUBLISHED,
                'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
                'auto_assign_enabled' => false,
                'scheduled_at' => now()->addDays(15)->setTime(9, 0),
                'version' => 1,
            ]
        );

        // 3. Seed 10 unassigned accepted exam candidates
        $examCandidates = [
            ['ref' => 'APP-DIST-EX01', 'last' => 'AQUINO', 'first' => 'BENIGNO', 'program' => 'BSCS', 'strand' => 'STEM'],
            ['ref' => 'APP-DIST-EX02', 'last' => 'BONIFACIO', 'first' => 'ANDRES', 'program' => 'BSIT', 'strand' => 'STEM'],
            ['ref' => 'APP-DIST-EX03', 'last' => 'CORONEL', 'first' => 'CLARA', 'program' => 'BSCS', 'strand' => 'STEM'],
            ['ref' => 'APP-DIST-EX04', 'last' => 'DELA PAZ', 'first' => 'DIEGO', 'program' => 'BSEE', 'strand' => 'TVL'],
            ['ref' => 'APP-DIST-EX05', 'last' => 'ESTRADA', 'first' => 'JOSEPH', 'program' => 'BA-Psych', 'strand' => 'STEM'],
            ['ref' => 'APP-DIST-EX06', 'last' => 'FLORES', 'first' => 'MARIA', 'program' => 'BSCS', 'strand' => 'STEM'],
            ['ref' => 'APP-DIST-EX07', 'last' => 'GARCIA', 'first' => 'CARLOS', 'program' => 'BSEE', 'strand' => 'TVL'],
            ['ref' => 'APP-DIST-EX08', 'last' => 'HERRERA', 'first' => 'LORENA', 'program' => 'BSIT', 'strand' => 'STEM'],
            ['ref' => 'APP-DIST-EX09', 'last' => 'ILAGAN', 'first' => 'MIGUEL', 'program' => 'BSCS', 'strand' => 'STEM'],
            ['ref' => 'APP-DIST-EX10', 'last' => 'JACINTO', 'first' => 'EMILIO', 'program' => 'BA-Psych', 'strand' => 'STEM'],
        ];

        foreach ($examCandidates as $index => $c) {
            $identity = ApplicantIdentity::query()->updateOrCreate(
                ['institution_id' => $institution->id, 'normalized_key' => 'ID-'.$c['ref']],
                [
                    'first_name' => $c['first'],
                    'last_name' => $c['last'],
                    'birthdate' => '2005-05-15',
                    'sex' => ($index % 2 === 0) ? 'male' : 'female',
                    'email' => strtolower($c['ref']).'@nexiam.test',
                ]
            );

            AdmissionAttempt::query()->updateOrCreate(
                ['reference_code' => $c['ref']],
                [
                    'applicant_identity_id' => $identity->id,
                    'institution_id' => $institution->id,
                    'academic_cycle_id' => $cycle->id,
                    'application_window_id' => $windowId,
                    'status' => AdmissionAttempt::STATUS_ACCEPTED,
                    'preferred_program_code' => $c['program'],
                    'strand' => $c['strand'],
                    'accepted_at' => now()->subDays(10 - $index),
                    'version' => 1,
                ]
            );
        }

        // 4. Seed completed exam session & grading for consultation candidates
        $completedExamSession = ExamSession::query()->updateOrCreate(
            ['academic_cycle_id' => $cycle->id, 'code' => 'EX-DIST-COMPLETED'],
            [
                'room_id' => $room101->id,
                'status' => ExamSession::STATUS_COMPLETED,
                'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
                'starts_at' => now()->subDays(5)->setTime(9, 0),
                'effective_end_at' => now()->subDays(5)->setTime(11, 0),
                'closed_at' => now()->subDays(5)->setTime(11, 30),
                'version' => 1,
            ]
        );

        $gradingSession = GradingSession::query()->updateOrCreate(
            ['exam_session_id' => $completedExamSession->id],
            [
                'status' => GradingSession::STATUS_FINALIZED,
                'opened_at' => now()->subDays(5)->setTime(12, 0),
                'finalized_at' => now()->subDays(4)->setTime(14, 0),
                'version' => 1,
            ]
        );

        // 5. Seed 5 result-ready consultation candidates
        $consultationCandidates = [
            ['ref' => 'APP-DIST-CS01', 'last' => 'KALAW', 'first' => 'TEODORO', 'program' => 'BSIT'],
            ['ref' => 'APP-DIST-CS02', 'last' => 'LOPEZ', 'first' => 'GRAZIANO', 'program' => 'BSCS'],
            ['ref' => 'APP-DIST-CS03', 'last' => 'MABINI', 'first' => 'APOLINARIO', 'program' => 'BA-Psych'],
            ['ref' => 'APP-DIST-CS04', 'last' => 'NATIVIDAD', 'first' => 'MAMERTO', 'program' => 'BSEE'],
            ['ref' => 'APP-DIST-CS05', 'last' => 'OSMENA', 'first' => 'SERGIO', 'program' => 'BSCS'],
        ];

        foreach ($consultationCandidates as $index => $c) {
            $identity = ApplicantIdentity::query()->updateOrCreate(
                ['institution_id' => $institution->id, 'normalized_key' => 'ID-'.$c['ref']],
                [
                    'first_name' => $c['first'],
                    'last_name' => $c['last'],
                    'birthdate' => '2005-08-20',
                    'sex' => ($index % 2 === 0) ? 'male' : 'female',
                    'email' => strtolower($c['ref']).'@nexiam.test',
                ]
            );

            $attempt = AdmissionAttempt::query()->updateOrCreate(
                ['reference_code' => $c['ref']],
                [
                    'applicant_identity_id' => $identity->id,
                    'institution_id' => $institution->id,
                    'academic_cycle_id' => $cycle->id,
                    'application_window_id' => $windowId,
                    'status' => AdmissionAttempt::STATUS_ACCEPTED,
                    'preferred_program_code' => $c['program'],
                    'strand' => 'STEM',
                    'accepted_at' => now()->subDays(15),
                    'version' => 1,
                ]
            );

            // Active exam assignment in the completed session
            ExamAssignment::query()->updateOrCreate(
                ['exam_session_id' => $completedExamSession->id, 'admission_attempt_id' => $attempt->id],
                [
                    'status' => ExamAssignment::STATUS_ASSIGNED,
                    'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
                    'origin' => ExamAssignment::ORIGIN_MANUAL,
                    'version' => 1,
                ]
            );

            // Ready result document
            ResultDocument::query()->updateOrCreate(
                ['admission_attempt_id' => $attempt->id],
                [
                    'template_version' => 'e2-v1',
                    'checksum' => hash('sha256', "result-doc:{$attempt->id}"),
                    'status' => ResultDocument::STATUS_READY,
                    'storage_path' => "results/{$attempt->id}/preview.html",
                    'preview_path' => "results/{$attempt->id}/preview.html",
                    'created_at' => now()->subDays(3)->setTime(10 + $index, 0),
                    'updated_at' => now()->subDays(3)->setTime(10 + $index, 0),
                ]
            );
        }
    }
}
