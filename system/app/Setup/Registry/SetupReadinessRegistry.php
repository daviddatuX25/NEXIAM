<?php

namespace App\Setup\Registry;

use App\Models\Foundation\Institution;
use App\Setup\Contracts\SetupDomainDefinition;
use App\Setup\Definitions\AcademicCyclesDefinition;
use App\Setup\Definitions\AdmissionSupportDefinition;
use App\Setup\Definitions\AiCenterDefinition;
use App\Setup\Definitions\AnswerSheetsDefinition;
use App\Setup\Definitions\ApplicationSuggestionsDefinition;
use App\Setup\Definitions\AptitudeDomainsDefinition;
use App\Setup\Definitions\ConsultationPolicyDefinition;
use App\Setup\Definitions\DepartmentsDefinition;
use App\Setup\Definitions\ExamMaterialsDefinition;
use App\Setup\Definitions\IntakeFieldsDefinition;
use App\Setup\Definitions\IntakeRequirementsDefinition;
use App\Setup\Definitions\NormPackagesDefinition;
use App\Setup\Definitions\NotificationsDefinition;
use App\Setup\Definitions\ProgramPublicProfilesDefinition;
use App\Setup\Definitions\ProgramQuotasDefinition;
use App\Setup\Definitions\ProgramsDefinition;
use App\Setup\Definitions\PublicIntakePrivacyPolicyDefinition;
use App\Setup\Definitions\QuestionBookletsDefinition;
use App\Setup\Definitions\ResultTemplatesDefinition;
use App\Setup\Definitions\RoomsDefinition;
use App\Setup\Definitions\ScannerDiagnosticsDefinition;
use App\Setup\Definitions\SeatingArrangementsDefinition;
use App\Setup\Definitions\StaffAccountsDefinition;
use App\Setup\Definitions\TestInstrumentsDefinition;
use App\Setup\Queries\SetupSeatingApplicabilityQuery;

final class SetupReadinessRegistry
{
    /** @var list<SetupDomainDefinition> */
    private array $definitions;

    public function __construct()
    {
        $this->definitions = [
            new AcademicCyclesDefinition,
            new ProgramsDefinition,
            new DepartmentsDefinition,
            new ProgramPublicProfilesDefinition,
            new NotificationsDefinition,
            new IntakeRequirementsDefinition,
            new IntakeFieldsDefinition,
            new ApplicationSuggestionsDefinition,
            new PublicIntakePrivacyPolicyDefinition,
            new RoomsDefinition,
            new SeatingArrangementsDefinition,
            new ExamMaterialsDefinition,
            new AnswerSheetsDefinition,
            new QuestionBookletsDefinition,
            new ScannerDiagnosticsDefinition,
            new TestInstrumentsDefinition,
            new AptitudeDomainsDefinition,
            new NormPackagesDefinition,
            new ResultTemplatesDefinition,
            new AdmissionSupportDefinition,
            new ProgramQuotasDefinition,
            new ConsultationPolicyDefinition,
            new AiCenterDefinition,
            new StaffAccountsDefinition,
        ];
    }

    /**
     * @return list<SetupDomainDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    /**
     * @return list<SetupDomainDefinition>
     */
    public function forInstitution(Institution $institution): array
    {
        return array_values(array_filter(
            $this->definitions,
            static fn (SetupDomainDefinition $definition): bool => $definition->key() !== 'seating_arrangements'
                || app(SetupSeatingApplicabilityQuery::class)->isApplicable($institution),
        ));
    }

    /**
     * @return array<int, array{stage: int, title: string, domains: list<SetupDomainDefinition>}>
     */
    public function byStage(): array
    {
        $stages = [];

        foreach ($this->definitions as $definition) {
            $stage = $definition->stage();
            $stages[$stage] ??= [
                'stage' => $stage,
                'title' => $definition->stageTitle(),
                'domains' => [],
            ];
            $stages[$stage]['domains'][] = $definition;
        }

        ksort($stages);

        return $stages;
    }

    /**
     * @return list<SetupDomainDefinition>
     */
    public function byDesk(string $desk): array
    {
        return array_values(array_filter(
            $this->definitions,
            static fn (SetupDomainDefinition $definition): bool => $definition->desk() === $desk,
        ));
    }

    public function get(string $key): ?SetupDomainDefinition
    {
        foreach ($this->definitions as $definition) {
            if ($definition->key() === $key) {
                return $definition;
            }
        }

        return null;
    }

    public function isVisibleInNavigation(string $key): bool
    {
        return ! in_array($key, [
            'omr_test',
            'test_instruments',
            'aptitude',
            'norm_packages',
        ], true);
    }
}
