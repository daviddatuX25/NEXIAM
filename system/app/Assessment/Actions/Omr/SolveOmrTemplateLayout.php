<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Omr\Layout\LayoutDiagnostic;
use App\Assessment\Omr\Layout\OmrTemplateLayoutException;
use App\Assessment\Omr\Layout\OmrTemplateLayoutSolver;
use App\Assessment\Omr\Layout\SolvedOmrTemplateLayout;
use App\Models\Foundation\AptitudeArea;
use Illuminate\Support\Collection;

use function array_values;

/**
 * Application action over the pure layout solver: validates blueprint area
 * codes against the aptitude catalog, then delegates placement. Preview and
 * save both call this action so the solved template is single-sourced.
 */
final class SolveOmrTemplateLayout
{
    public function __construct(private readonly OmrTemplateLayoutSolver $solver) {}

    /**
     * @param  array<string, mixed>  $blueprint
     *
     * @throws OmrTemplateLayoutException when the blueprint cannot be solved
     */
    public function handle(array $blueprint): SolvedOmrTemplateLayout
    {
        $diagnostics = $this->solver->validate($blueprint);
        if ($diagnostics !== []) {
            throw new OmrTemplateLayoutException($diagnostics);
        }

        $areaCodes = $this->knownAreaCodes();
        foreach (array_values($blueprint['sections'] ?? []) as $index => $section) {
            $code = (string) ($section['area_code'] ?? '');
            if (! $areaCodes->contains($code)) {
                throw new OmrTemplateLayoutException([
                    new LayoutDiagnostic(
                        'unknown_area_code',
                        sprintf('Section %d references unknown aptitude area "%s".', $index + 1, $code),
                        ['section' => $index + 1, 'area_code' => $code],
                    ),
                ]);
            }
        }

        return $this->solver->solve($blueprint);
    }

    /** @return Collection<int, string> */
    private function knownAreaCodes(): Collection
    {
        return AptitudeArea::query()->pluck('code');
    }
}
