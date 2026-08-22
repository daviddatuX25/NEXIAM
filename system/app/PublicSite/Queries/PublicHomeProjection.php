<?php

namespace App\PublicSite\Queries;

use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;

class PublicHomeProjection
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $cycle = AcademicCycle::active();
        $configuredPrograms = collect(config('public-site.programs', []))->keyBy('code');
        $catalogPrograms = Program::query()
            ->active()
            ->orderBy('name')
            ->get(['code', 'name'])
            ->map(function (Program $program) use ($configuredPrograms): array {
                $metadata = $configuredPrograms->get($program->code, []);

                return [
                    ...$metadata,
                    'code' => $program->code,
                    'name' => $program->name,
                    'isSimulated' => false,
                ];
            });

        $programs = $catalogPrograms->isNotEmpty()
            ? $catalogPrograms->values()->all()
            : $configuredPrograms
                ->map(fn (array $program): array => [...$program, 'isSimulated' => true])
                ->values()
                ->all();

        return [
            'institution' => config('public-site.institution'),
            'hero' => config('public-site.hero'),
            'stories' => config('public-site.stories', []),
            'announcements' => config('public-site.announcements', []),
            'preview' => config('public-site.preview'),
            'programs' => $programs,
            'activeSeason' => $cycle === null ? null : [
                'name' => $cycle->staffLabel(),
                'window' => $cycle->applicationWindowLabel(),
            ],
        ];
    }
}
