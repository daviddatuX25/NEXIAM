<?php

namespace Database\Seeders;

use App\Models\ResultSheet\ResultSheetTemplate;
use Illuminate\Database\Seeder;

/**
 * Seeds the result-sheet template authority: one active HTML template shaped
 * like the institutional ICAT result sheet (legacy placeholders) plus an
 * inactive DOCX template imported from the real SecureCAT seed asset. The
 * active template drives every result-sheet render; re-seeding never
 * duplicates or swaps the active row.
 */
class ResultSheetTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $html = ResultSheetTemplate::query()->updateOrCreate(
            ['name' => 'ICAT Results (HTML)'],
            [
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
                'is_active' => true,
                'created_by_user_id' => null,
            ],
        );

        // Ensure exactly one active template (the HTML one).
        ResultSheetTemplate::query()
            ->where('id', '!=', $html->id)
            ->update(['is_active' => false]);

        // Real institutional Word template as an inactive DOCX option.
        $asset = database_path('seed-assets/result-sheets/template_main_icat_test1.docx');

        if (is_file($asset)) {
            ResultSheetTemplate::query()->updateOrCreate(
                ['name' => 'ICAT Results (Word)'],
                [
                    'mode' => ResultSheetTemplate::MODE_DOCX,
                    'paper_size' => 'legal',
                    'orientation' => ResultSheetTemplate::ORIENTATION_PORTRAIT,
                    'logical_unit' => ResultSheetTemplate::LOGICAL_FULL,
                    'document_path' => 'result-sheet-templates/icat-seed.docx',
                    'document_checksum' => hash_file('sha256', $asset),
                    'document_extension' => 'docx',
                    'is_active' => false,
                ],
            );

            $target = storage_path('app/private/result-sheet-templates/icat-seed.docx');

            if (! is_file($target) || hash_file('sha256', $target) !== hash_file('sha256', $asset)) {
                @mkdir(dirname($target), 0755, true);
                copy($asset, $target);
            }
        }
    }
}
