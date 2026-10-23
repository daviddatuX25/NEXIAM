<?php

namespace Database\Seeders;

use App\DocumentTemplate\Packaging\DirectoryBundleReader;
use App\Models\ResultSheet\ResultSheetTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Seeds the result-sheet template authority: the verified institutional ICAT
 * Word template is the active default when its seed asset is available. The
 * HTML template remains available as an inactive comparison/fallback option.
 * Re-seeding never duplicates rows and always leaves exactly one active row.
 */
class ResultSheetTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $html = ResultSheetTemplate::query()->updateOrCreate(
            ['name' => 'ICAT Results (HTML)'],
            [
                'source_kind' => ResultSheetTemplate::SOURCE_LEGACY_HTML,
                'mode' => ResultSheetTemplate::MODE_HTML,
                'paper_size' => 'a4',
                'orientation' => 'portrait',
                'logical_unit' => ResultSheetTemplate::LOGICAL_FULL,
                'content' => <<<'HTML'
                <div class="print-template">
                  <h2 style="text-align:center">{{institution_name}} — Guidance and Counseling Services</h2>
                  <h3 style="text-align:center">{{institution_exam_name}} ({{institution_exam_acronym}}) Results</h3>
                  <p style="text-align:center"><em>Date of Examination: {{exam_date}} ({{exam_time}}) · Room: {{room_name}}</em></p>
                  <table>
                    <tbody>
                      <tr><td><strong>Applicant No.</strong></td><td>{{applicant_reference}}</td></tr>
                      <tr><td><strong>Name</strong></td><td>{{family_name}}, {{first_name}} {{middle_name}} {{suffix}}</td></tr>
                      <tr><td><strong>Sex / GWA</strong></td><td>{{sex}} / {{gwa}}</td></tr>
                      <tr><td><strong>Course Applied / Strand</strong></td><td>{{course_applied_code}} / {{strand}}</td></tr>
                    </tbody>
                  </table>
                  <table>
                    <thead><tr><th>Aptitude Factor</th><th>Raw Score</th><th>Descriptive Rating</th></tr></thead>
                    <tbody class="scores-rows-placeholder"></tbody>
                  </table>
                  <p><strong>Overall Score:</strong> {{overall_pct}}</p>
                  <p><strong>Recommended Program:</strong> {{recommended_course}}</p>
                  <p><strong>Counselor:</strong> {{counselor_name}}</p>
                </div>
                HTML,
                'is_active' => false,
                'created_by_user_id' => null,
            ],
        );

        $catalogDir = database_path('seed-assets/templates/result_sheet/template_main_icat_test1');
        $official = null;

        if (is_dir($catalogDir)) {
            $bundle = (new DirectoryBundleReader)->read($catalogDir);
            $templateData = $bundle['manifest']['template'];
            $fileMeta = $bundle['manifest']['files']['template.docx'];
            $docxBytes = $bundle['files']['template.docx'];

            $official = ResultSheetTemplate::query()->updateOrCreate(
                ['name' => $templateData['name'] ?? 'ICAT Results (Word)'],
                [
                    'source_kind' => ResultSheetTemplate::SOURCE_DOCX,
                    'mode' => ResultSheetTemplate::MODE_DOCX,
                    'paper_size' => $templateData['paper_size'] ?? 'legal',
                    'orientation' => $templateData['orientation'] ?? ResultSheetTemplate::ORIENTATION_PORTRAIT,
                    'logical_unit' => $templateData['logical_unit'] ?? ResultSheetTemplate::LOGICAL_FULL,
                    'document_path' => 'result-sheet-templates/icat-seed.docx',
                    'document_checksum' => $fileMeta['sha256'],
                    'document_extension' => 'docx',
                    'is_active' => true,
                ],
            );

            $target = Storage::disk('local')->path('result-sheet-templates/icat-seed.docx');
            if (! is_file($target) || hash_file('sha256', $target) !== $fileMeta['sha256']) {
                @mkdir(dirname($target), 0755, true);
                file_put_contents($target, $docxBytes);
            }
        } else {
            $asset = database_path('seed-assets/result-sheets/template_main_icat_test1.docx');
            if (is_file($asset)) {
                $official = ResultSheetTemplate::query()->updateOrCreate(
                    ['name' => 'ICAT Results (Word)'],
                    [
                        'source_kind' => ResultSheetTemplate::SOURCE_DOCX,
                        'mode' => ResultSheetTemplate::MODE_DOCX,
                        'paper_size' => 'legal',
                        'orientation' => ResultSheetTemplate::ORIENTATION_PORTRAIT,
                        'logical_unit' => ResultSheetTemplate::LOGICAL_FULL,
                        'document_path' => 'result-sheet-templates/icat-seed.docx',
                        'document_checksum' => hash_file('sha256', $asset),
                        'document_extension' => 'docx',
                        'is_active' => true,
                    ],
                );

                $target = Storage::disk('local')->path('result-sheet-templates/icat-seed.docx');
                if (! is_file($target) || hash_file('sha256', $target) !== hash_file('sha256', $asset)) {
                    @mkdir(dirname($target), 0755, true);
                    copy($asset, $target);
                }
            }
        }

        $activeId = $official?->id ?? $html->id;

        ResultSheetTemplate::query()
            ->where('id', '!=', $activeId)
            ->update(['is_active' => false]);

        ResultSheetTemplate::query()
            ->whereKey($activeId)
            ->update(['is_active' => true]);
    }
}
