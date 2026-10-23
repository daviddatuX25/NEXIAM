<?php

namespace App\Testing\Actions;

use App\Admission\Support\ApplicantAccessKey;
use App\Admission\Support\NormalizedIdentityKey;
use App\Admission\Support\ReferenceCodeGenerator;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantAccessChallenge;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class GenerateDemoPipelineApplicants
{
    private const FIRST_NAMES_FEMALE = [
        'Maria Angela', 'Mary Grace', 'Bea Bianca', 'Princess Joy', 'Kimberly Anne',
        'Janine Mae', 'Danielle Rose', 'Sarah Jane', 'Mary Joy', 'Anne Bernadette',
        'Maria Cristina', 'Lovely Joy', 'Kyla Marie', 'Angel Mae', 'Christine Joy',
        'Maria', 'Angela', 'Christine', 'Bea', 'Nicole', 'Kimberly', 'Camille',
        'Patricia', 'Angelica', 'Alyssa', 'Sophia', 'Jessica', 'Hazel', 'Katherine',
        'Diana', 'Samantha', 'Andrea', 'Janine', 'Stephanie', 'Rochelle', 'Maricar',
        'Rowena', 'Bernadette', 'Katrina', 'Donna', 'Joyce', 'Althea', 'Cielo',
        'Jasmine', 'Danica', 'Clarisse', 'Lorraine', 'Erika', 'Charisse', 'Giselle',
    ];

    private const FIRST_NAMES_MALE = [
        'John Paul', 'Mark Anthony', 'Juan Carlos', 'Angelo Christian', 'Christian Dave',
        'Mark Joseph', 'John Mark', 'John Lloyd', 'Gian Carlo', 'Marc Lester',
        'Paul Vincent', 'Ace Gabriel', 'Prince Jomar', 'Michael Angelo', 'Carl Justine',
        'Juan', 'Jose', 'Angelo', 'Joshua', 'Christian', 'Mark', 'Kenneth',
        'Daniel', 'Gabriel', 'Justine', 'Alden', 'Gerald', 'Enrique', 'James',
        'Carlo', 'Jericho', 'Piolo', 'Marvin', 'Kevin', 'Jerome', 'Ryan',
        'Anthony', 'Francis', 'Dominic', 'Patrick', 'Lester', 'Vincent', 'Arnel',
        'Michael', 'Ronald', 'Noel', 'Joel', 'Dennis', 'Bryan', 'Renato',
    ];

    private const MIDDLE_NAMES = [
        'Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Mendoza', 'Ramos',
        'Aquino', 'Flores', 'Morales', 'Navarro', 'Mercado', 'Castillo', 'Soriano',
        'Corpuz', 'Villanueva', 'Tolentino', 'Santiago', 'Salazar', 'Perez', 'Lopez',
        'Gonzales', 'Valdez', 'Alcantara', 'Castro', 'Rivera', 'Salvador', 'Pascual',
        'Aguilar', 'Del Rosario', 'Panganiban', 'Cariño', 'Aglibut', 'Buenafe', 'Lazo',
        'Directo', 'Leal', 'Somera', 'Batin', 'Dizon', 'Valenzuela', 'Macugay',
    ];

    private const LAST_NAMES = [
        'Santos', 'Reyes', 'Cruz', 'Bautista', 'Garcia', 'Mendoza', 'Ramos', 'Aquino',
        'Flores', 'Morales', 'Navarro', 'Mercado', 'Castillo', 'Soriano', 'Corpuz',
        'Villanueva', 'Tolentino', 'Santiago', 'Salazar', 'Perez', 'Lopez', 'Gonzales',
        'Valdez', 'Alcantara', 'Castro', 'Rivera', 'Salvador', 'Pascual', 'Aguilar',
        'Del Rosario', 'Dela Cruz', 'Fernandez', 'Cariño', 'Aglibut', 'Buenafe', 'Lazo',
        'Directo', 'Leal', 'Somera', 'Batin', 'Cabanban', 'Quiocho', 'Brillantes',
        'Macugay', 'Tabangcura', 'Gacusan', 'Madriaga', 'Pajarillo', 'Villados',
    ];

    private const SCHOOLS = [
        'Tagudin National High School',
        'Saint Augustine\'s School (Tagudin)',
        'Sevilla National High School',
        'Tagudin General Comprehensive High School',
        'Quirino National High School',
        'Candon National High School',
        'Santa Cruz National High School',
        'Narvacan National Central High School',
        'Ilocos Sur National High School',
        'San Esteban National High School',
        'Santiago National High School',
        'Cervantes National High School',
    ];

    private const STRANDS = ['STEM', 'ABM', 'HUMSS', 'TVL', 'GAS'];

    public function __construct(
        private readonly ReferenceCodeGenerator $referenceCodeGenerator,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /**
     * Autogenerate applicants for the admission pipeline with readable names and combinations.
     *
     * @return array{
     *     count: int,
     *     cycle: array{id: int, code: string},
     *     window: array{id: int, label: string},
     *     applicants: list<array{
     *         id: int,
     *         reference_code: string,
     *         full_name: string,
     *         first_name: string,
     *         middle_name: string,
     *         last_name: string,
     *         birthdate: string,
     *         sex: string,
     *         email: string,
     *         phone: string,
     *         school: string,
     *         strand: string,
     *         programs: list<string>,
     *         access_key: string,
     *         status: string,
     *         view_url: string
     *     }>
     * }
     */
    public function generate(int $count = 10, string $status = 'pending', ?int $academicCycleId = null): array
    {
        if ($count < 1) {
            throw new RuntimeException('Applicant count must be at least 1.');
        }

        $institution = Institution::query()->where('code', 'SYN-INST-001')->first()
            ?? Institution::query()->first();

        if ($institution === null) {
            $institution = Institution::query()->create([
                'code' => 'SYN-INST-001',
                'name' => 'Ilocos Sur Polytechnic State College - Tagudin Campus',
                'short_name' => 'ISPSC',
                'campus_name' => 'Tagudin Campus',
                'location' => 'Tagudin, Ilocos Sur',
            ]);
        }

        $cycle = $academicCycleId !== null
            ? AcademicCycle::query()->where('institution_id', $institution->id)->findOrFail($academicCycleId)
            : (AcademicCycle::query()->where('institution_id', $institution->id)->where('is_active', true)->first()
                ?? AcademicCycle::query()->where('institution_id', $institution->id)->first());

        if ($cycle === null) {
            $cycle = AcademicCycle::query()->create([
                'institution_id' => $institution->id,
                'code' => 'AY'.now()->format('y').'-'.(now()->year + 1),
                'name' => 'Academic Year '.now()->year.'–'.(now()->year + 1),
                'school_year' => now()->year.'-'.(now()->year + 1),
                'semester' => '1',
                'is_active' => true,
                'opens_on' => now()->subMonths(2)->toDateString(),
                'closes_on' => now()->addMonths(10)->toDateString(),
            ]);
        }

        $window = ApplicationWindow::query()
            ->where('academic_cycle_id', $cycle->id)
            ->where('status', 'published')
            ->first()
            ?? ApplicationWindow::query()->where('academic_cycle_id', $cycle->id)->first();

        if ($window === null) {
            $window = ApplicationWindow::query()->create([
                'academic_cycle_id' => $cycle->id,
                'code' => 'DEMO-WINDOW-'.now()->year,
                'name' => 'Demo Admission Window',
                'status' => 'published',
                'opens_at' => now()->subDays(30),
                'closes_at' => now()->addDays(90),
            ]);
        }

        $offeredPrograms = Program::query()
            ->where('institution_id', $institution->id)
            ->where('is_active', true)
            ->where('is_admission_offered', true)
            ->pluck('code')
            ->all();

        if ($offeredPrograms === []) {
            $offeredPrograms = ['BSIT', 'BSEd', 'BEEd', 'BSBA', 'BA-Psych'];
        }

        $generated = [];
        $usedSignatures = [];

        for ($i = 0; $i < $count; $i++) {
            $sex = ($i % 2 === 0) ? 'female' : 'male';
            $firstNamesPool = $sex === 'female' ? self::FIRST_NAMES_FEMALE : self::FIRST_NAMES_MALE;

            // Generate an unambiguous unique name signature within this batch
            $attempts = 0;
            do {
                $firstName = $firstNamesPool[array_rand($firstNamesPool)];
                $middleName = self::MIDDLE_NAMES[array_rand(self::MIDDLE_NAMES)];
                $lastName = self::LAST_NAMES[array_rand(self::LAST_NAMES)];
                $attempts++;
            } while (($lastName === $middleName || isset($usedSignatures["{$firstName} {$middleName} {$lastName}"])) && $attempts < 50);

            $usedSignatures["{$firstName} {$middleName} {$lastName}"] = true;

            // Authentic Philippine college freshman birthdate (17–19 years old)
            $birthYear = now()->year - 18;
            $birthMonth = str_pad((string) random_int(1, 12), 2, '0', STR_PAD_LEFT);
            $birthDay = str_pad((string) random_int(1, 28), 2, '0', STR_PAD_LEFT);
            $birthdate = "{$birthYear}-{$birthMonth}-{$birthDay}";

            $cleanFirstSlug = Str::slug($firstName);
            $cleanLastSlug = Str::slug($lastName);
            $email = "{$cleanFirstSlug}.{$cleanLastSlug}".random_int(10, 99).'@example.test';
            $phone = '09'.random_int(100000000, 999999999);

            $school = self::SCHOOLS[array_rand(self::SCHOOLS)];
            $strand = self::STRANDS[array_rand(self::STRANDS)];

            // Pick 1 to 3 distinct offered programs
            $shuffledPrograms = $offeredPrograms;
            shuffle($shuffledPrograms);
            $prefCount = min(count($shuffledPrograms), random_int(1, 3));
            $chosenPrograms = array_slice($shuffledPrograms, 0, $prefCount);

            $accessKey = ApplicantAccessKey::mint();
            $normalizedKey = NormalizedIdentityKey::make($firstName, $lastName, $birthdate, $sex, $middleName);
            $referenceCode = $this->referenceCodeGenerator->next($cycle);

            $result = DB::transaction(function () use (
                $institution,
                $cycle,
                $window,
                $normalizedKey,
                $firstName,
                $middleName,
                $lastName,
                $birthdate,
                $sex,
                $email,
                $phone,
                $referenceCode,
                $status,
                $chosenPrograms,
                $school,
                $strand,
                $accessKey
            ): array {
                $identity = ApplicantIdentity::query()->create([
                    'institution_id' => $institution->id,
                    'normalized_key' => $normalizedKey,
                    'first_name' => $firstName,
                    'middle_name' => $middleName,
                    'last_name' => $lastName,
                    'suffix' => null,
                    'birthdate' => $birthdate,
                    'sex' => $sex,
                    'email' => $email,
                    'phone' => $phone,
                ]);

                $attempt = AdmissionAttempt::query()->create([
                    'applicant_identity_id' => $identity->id,
                    'institution_id' => $institution->id,
                    'academic_cycle_id' => $cycle->id,
                    'application_window_id' => $window->id,
                    'reference_code' => $referenceCode,
                    'status' => $status === 'accepted' ? AdmissionAttempt::STATUS_ACCEPTED : AdmissionAttempt::STATUS_PENDING,
                    'preferred_program_code' => $chosenPrograms[0],
                    'program_preference_1_code' => $chosenPrograms[0] ?? null,
                    'program_preference_2_code' => $chosenPrograms[1] ?? null,
                    'program_preference_3_code' => $chosenPrograms[2] ?? null,
                    'applicant_type' => 'new',
                    'last_school_enrolled' => $school,
                    'strand' => $strand,
                    'gwa' => number_format(random_int(8500, 9350) / 100, 2),
                    'contact_email' => $email,
                    'contact_phone' => $phone,
                    'accepted_at' => $status === 'accepted' ? now() : null,
                    'version' => 1,
                    'payload' => [
                        'demo_generated' => true,
                        'source' => 'demo:seed-pipeline',
                        'generated_at' => now()->toIso8601String(),
                    ],
                ]);

                ApplicantAccessChallenge::query()->create([
                    'applicant_identity_id' => $identity->id,
                    'admission_attempt_id' => $attempt->id,
                    'purpose' => 'access_key',
                    'token_hash' => ApplicantAccessKey::hash($accessKey),
                    'request_key' => 'demo-pipeline-applicant-access-'.$attempt->id.'-'.Str::random(8),
                    'expires_at' => now()->addYears(2),
                ]);

                $this->recordAuditEvent->handle('admission.attempt_submitted', [
                    'attempt_id' => $attempt->id,
                    'reference_code' => $referenceCode,
                    'source' => 'demo_pipeline',
                ], 'demo:seed-pipeline');

                if ($status === 'accepted') {
                    $this->recordAuditEvent->handle('admission.attempt_accepted', [
                        'attempt_id' => $attempt->id,
                        'reference_code' => $referenceCode,
                    ], 'demo:seed-pipeline');
                }

                return [
                    'id' => (int) $attempt->id,
                    'reference_code' => (string) $referenceCode,
                    'full_name' => (string) $identity->fullName(),
                    'first_name' => (string) $firstName,
                    'middle_name' => (string) $middleName,
                    'last_name' => (string) $lastName,
                    'birthdate' => (string) $birthdate,
                    'sex' => (string) $sex,
                    'email' => (string) $email,
                    'phone' => (string) $phone,
                    'school' => (string) $school,
                    'strand' => (string) $strand,
                    'programs' => $chosenPrograms,
                    'access_key' => (string) $accessKey,
                    'status' => (string) $attempt->status,
                    'view_url' => "/admissions/{$attempt->id}",
                ];
            });

            $generated[] = $result;
        }

        return [
            'count' => count($generated),
            'cycle' => [
                'id' => (int) $cycle->id,
                'code' => (string) $cycle->code,
            ],
            'window' => [
                'id' => (int) $window->id,
                'label' => (string) ($window->label ?? $window->code),
            ],
            'applicants' => $generated,
        ];
    }
}
