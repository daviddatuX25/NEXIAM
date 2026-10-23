<?php

namespace App\PublicSite\Queries;

use App\Admission\Queries\CurrentIntakeQuery;
use App\Communications\Queries\AnnouncementQuery;
use App\Communications\Queries\HomepageContentQuery;
use App\Models\Communications\ProgramPublicProfile;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;

class PublicHomeProjection
{
    public function __construct(
        private readonly CurrentIntakeQuery $currentIntake,
        private readonly AnnouncementQuery $announcements,
        private readonly HomepageContentQuery $homepageContent,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $institution = Institution::query()->where('code', 'SYN-INST-001')->first();
        $intake = $institution === null ? null : $this->currentIntake->forInstitution($institution);
        $cycle = $intake['cycle'] ?? ($institution === null ? null : AcademicCycle::query()
            ->where('institution_id', $institution->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->first());
        $academicYear = $cycle?->school_year ?: $cycle?->name;
        $content = $this->homepageContent->publicContent((int) ($institution?->id ?? 0));
        $configuredPrograms = collect(config('public-site.programs', []))->keyBy('code');
        $publishedProfiles = $institution === null
            ? collect()
            : ProgramPublicProfile::query()
                ->where('institution_id', $institution->id)
                ->whereNotNull('published_revision_id')
                ->with('publishedRevision:id,short_description')
                ->get(['id', 'program_id', 'published_revision_id'])
                ->keyBy('program_id');
        $programs = $institution === null
            ? []
            : Program::query()
                ->active()
                ->where('institution_id', $institution->id)
                ->orderBy('name')
                ->get(['id', 'code', 'name'])
                ->map(function (Program $program) use ($configuredPrograms, $publishedProfiles): array {
                    $configured = $configuredPrograms->get($program->code, []);
                    $publishedSummary = trim((string) ($publishedProfiles->get($program->id)?->publishedRevision?->short_description ?? ''));

                    return [
                        ...$configured,
                        ...($publishedSummary !== '' ? ['summary' => $publishedSummary] : []),
                        'id' => (int) $program->id,
                        'code' => $program->code,
                        'name' => $program->name,
                        'isSimulated' => false,
                        'href' => '/programs/'.rawurlencode($program->code),
                    ];
                })
                ->values()
                ->all();
        if (empty($programs)) {
            $programs = $configuredPrograms
                ->map(fn (array $program): array => [...$program, 'isSimulated' => true])
                ->values()
                ->all();
        }

        return [
            'institution' => $institution?->toPublicArray() ?? config('public-site.institution'),
            'hero' => $content['hero'],
            'stories' => $content['stories'],
            ...$this->announcements->publicHome(),
            'preview' => config('public-site.preview'),
            'programs' => $programs,
            'activeSeason' => $cycle === null ? null : [
                'academic_year' => (string) $academicYear,
                'is_open' => $intake !== null,
            ],
        ];
    }
}
