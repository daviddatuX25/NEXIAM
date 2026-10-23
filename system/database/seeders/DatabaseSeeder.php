<?php

namespace Database\Seeders;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * @return list<class-string<Seeder>>
     */
    public static function testSeeders(): array
    {
        return [
            ReferenceCatalogSeeder::class,
            InstitutionProfileSeeder::class,
            RoomSeatingProfileSeeder::class,
            CapabilityCatalogSeeder::class,
            StaffIdentitySeeder::class,
            DepartmentSeeder::class,
            StaffPlatformOverhaulDemoSeeder::class,
            ResultSheetTemplateSeeder::class,
            ReportTemplateSeeder::class,
            IntakeRequirementSeeder::class,
            ApplicationSuggestionListSeeder::class,
            OmrCanonicalDemoSeeder::class,
            PublicHomepageSeeder::class,
            CommunicationTemplateSeeder::class,
        ];
    }

    /**
     * Clean, reset-ready ISPSC Tagudin campus baseline (venues, staff,
     * colleges/programs, public program profiles, official ICAT Word result
     * sheet, policies/FAQs, and active TK-ICAT-2026 Test Kit without sample
     * applicant sessions).
     *
     * @return list<class-string<Seeder>>
     */
    public static function productionSeeders(): array
    {
        return [
            ReferenceCatalogSeeder::class,
            InstitutionProfileSeeder::class,
            RoomSeatingProfileSeeder::class,
            CapabilityCatalogSeeder::class,
            StaffIdentitySeeder::class,
            DepartmentSeeder::class,
            DepartmentOfficeLocationSeeder::class,
            ProgramPublicProfileSeeder::class,
            ProgramPriorityStrandSeeder::class,
            PublicHomepageSeeder::class,
            PublicIntakeConsentDemoSeeder::class,
            PublicPoliciesDemoSeeder::class,
            IntakeRequirementSeeder::class,
            ApplicationSuggestionListSeeder::class,
            ResultSheetTemplateSeeder::class,
            ReportTemplateSeeder::class,
            CommunicationTemplateSeeder::class,
            KnowledgeGovernanceDemoSeeder::class,
            IcatRealMaterialsSeeder::class,
        ];
    }

    /**
     * @return list<class-string<Seeder>>
     */
    public static function demoSeeders(): array
    {
        return [
            ReferenceCatalogSeeder::class,
            InstitutionProfileSeeder::class,
            RoomSeatingProfileSeeder::class,
            CapabilityCatalogSeeder::class,
            StaffIdentitySeeder::class,
            DepartmentSeeder::class,
            DepartmentOfficeLocationSeeder::class,
            ProgramQuotaSeeder::class,
            ProgramPublicProfileSeeder::class,
            StaffPlatformOverhaulDemoSeeder::class,
            StaffProfileErgonomicsDemoSeeder::class,
            PublicIntakeConsentDemoSeeder::class,
            ReportTemplateSeeder::class,
            ResultSheetTemplateSeeder::class,
            IntakeRequirementSeeder::class,
            ApplicationSuggestionListSeeder::class,
            JourneyFixtureSeeder::class,
            OmrCanonicalDemoSeeder::class,
            OmrDemoSeeder::class,
            SecureBookletDemoSeeder::class,
            ExamMaterialRoutingDemoSeeder::class,
            OmrDiagnosticDemoSeeder::class,
            AdmissionSupportDemoSeeder::class,
            ConsultationDemoSeeder::class,
            MonitoringDemoSeeder::class,
            CohortDistributionDemoSeeder::class,
            ExamPublicationAuthorityDemoSeeder::class,
            KnowledgeGovernanceDemoSeeder::class,
            ApplicantOfficeInquiryDemoSeeder::class,
            PublicHomepageSeeder::class,
            CommunicationTemplateSeeder::class,
            IcatRealMaterialsSeeder::class,
        ];
    }

    public function run(?array $seeders = null): void
    {
        $institution = Institution::query()->updateOrCreate(
            ['code' => 'SYN-INST-001'],
            ['name' => (string) config('public-site.institution.name', 'Ilocos Sur Polytechnic State College')],
        );

        AcademicCycle::query()->updateOrCreate(
            ['code' => 'SYN-CYCLE-2026'],
            [
                'institution_id' => $institution->id,
                'name' => '2025-2026 1st Semester',
                'school_year' => '2025-2026',
                'semester' => '1',
                'opens_on' => '2026-08-01',
                'closes_on' => '2026-12-31',
                'is_active' => true,
            ],
        );

        $this->call($seeders ?? [
            ReferenceCatalogSeeder::class,
            RoomSeatingProfileSeeder::class,
            CapabilityCatalogSeeder::class,
            StaffIdentitySeeder::class,
            DepartmentSeeder::class,
            DepartmentOfficeLocationSeeder::class,
            ProgramQuotaSeeder::class,
            StaffProfileErgonomicsDemoSeeder::class,
            PublicIntakeConsentDemoSeeder::class,
            ReportTemplateSeeder::class,
            ResultSheetTemplateSeeder::class,
            IntakeRequirementSeeder::class,
            ApplicationSuggestionListSeeder::class,
            JourneyFixtureSeeder::class,
            OmrCanonicalDemoSeeder::class,
            OmrDemoSeeder::class,
            SecureBookletDemoSeeder::class,
            ExamMaterialRoutingDemoSeeder::class,
            OmrDiagnosticDemoSeeder::class,
            ApplicantOfficeInquiryDemoSeeder::class,
            CommunicationTemplateSeeder::class,
            IcatRealMaterialsSeeder::class,
        ]);

        app(RecordAuditEvent::class)->handle('foundation.seed_applied', [
            'institution_code' => 'SYN-INST-001',
            'cycle_code' => 'SYN-CYCLE-2026',
            'staff_seeded' => true,
            'reference_catalogs_seeded' => true,
            'journey_fixture_seeded' => true,
        ], 'seeder');
    }
}
