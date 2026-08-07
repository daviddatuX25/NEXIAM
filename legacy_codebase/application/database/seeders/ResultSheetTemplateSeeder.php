<?php

namespace Database\Seeders;

use App\Models\ResultSheetTemplate;
use Illuminate\Database\Seeder;

class ResultSheetTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $content = <<<'HTML'
<div style="font-family: 'Inter', sans-serif; font-size: 0.8rem; background: #fff; border-radius: 10px; overflow: hidden; border: 1px solid #d1fae5;">

  <!-- Header Band -->
  <div class="bg-primary px-4 py-3 flex justify-between items-center">
    <div>
      <div class="text-white font-bold" style="font-size:1rem; letter-spacing:0.05em;">SECURECAT</div>
      <div style="color:rgba(255,255,255,0.75); font-size:0.65rem; letter-spacing:0.08em; text-transform:uppercase;">Aptitude Examination System</div>
    </div>
    <div class="text-right">
      <div style="color:rgba(255,255,255,0.9); font-size:0.7rem; font-weight:600;">RESULT RELEASE SHEET</div>
      <div style="color:rgba(255,255,255,0.6); font-size:0.6rem;">Confidential Document</div>
    </div>
  </div>

  <!-- Applicant Info Band -->
  <div style="background:#f0fdf4; border-bottom:1px solid #bbf7d0;" class="px-4 py-2 grid grid-cols-2 gap-2">
    <div>
      <div style="color:#6b7280; font-size:0.6rem; text-transform:uppercase; letter-spacing:0.06em; font-weight:600;">Applicant Name</div>
      <div class="font-semibold" style="color:#14532d; font-size:0.8rem;">{{applicant_name}}</div>
    </div>
    <div>
      <div style="color:#6b7280; font-size:0.6rem; text-transform:uppercase; letter-spacing:0.06em; font-weight:600;">Applicant No.</div>
      <div class="font-semibold" style="color:#14532d; font-size:0.8rem;">{{applicant_number}}</div>
    </div>
    <div>
      <div style="color:#6b7280; font-size:0.6rem; text-transform:uppercase; letter-spacing:0.06em; font-weight:600;">Exam Date</div>
      <div style="color:#374151; font-size:0.75rem;">{{exam_date}}</div>
    </div>
    <div>
      <div style="color:#6b7280; font-size:0.6rem; text-transform:uppercase; letter-spacing:0.06em; font-weight:600;">Examination Room</div>
      <div style="color:#374151; font-size:0.75rem;">{{room_name}}</div>
    </div>
  </div>

  <!-- Score Table -->
  <div class="px-4 pt-3 pb-1">
    <table class="w-full border-collapse" style="font-size:0.75rem;">
      <thead>
        <tr style="border-bottom:2px solid #bbf7d0;">
          <th class="text-left py-1.5" style="color:#166534; font-weight:700; font-size:0.65rem; text-transform:uppercase; letter-spacing:0.06em;">Aptitude Pillar</th>
          <th class="text-right py-1.5" style="color:#166534; font-weight:700; font-size:0.65rem; text-transform:uppercase; letter-spacing:0.06em;">Score</th>
          <th class="text-right py-1.5" style="color:#166534; font-weight:700; font-size:0.65rem; text-transform:uppercase; letter-spacing:0.06em;">Rating</th>
        </tr>
      </thead>
      <tbody>
        <tr class="scores-rows-placeholder"><td colspan="3" style="padding:2px 0;"></td></tr>
      </tbody>
      <tfoot>
        <tr style="border-top:2px solid #bbf7d0; background:#f0fdf4;">
          <td class="py-1.5 font-bold" style="color:#14532d; font-size:0.75rem;">Overall Performance</td>
          <td class="text-right py-1.5" style="color:#14532d;">—</td>
          <td class="text-right py-1.5">
            <span style="background:#166534; color:#fff; border-radius:999px; padding:1px 8px; font-size:0.65rem; font-weight:700;">{{overall_pct}}%</span>
          </td>
        </tr>
      </tfoot>
    </table>
  </div>

  <!-- Signature Block -->
  <div class="px-4 pt-3 pb-2 flex justify-between items-end" style="border-top:1px dashed #bbf7d0; margin-top:8px;">
    <div class="text-center">
      <div class="border-b" style="width:90px; border-color:#9ca3af; margin-bottom:2px;">&nbsp;</div>
      <div style="color:#6b7280; font-size:0.6rem; text-transform:uppercase; letter-spacing:0.05em;">Guidance Counselor</div>
    </div>
    <div style="color:#d1fae5; font-size:1.5rem; font-weight:900; letter-spacing:-0.05em;">✦</div>
    <div class="text-center">
      <div class="border-b" style="width:90px; border-color:#9ca3af; margin-bottom:2px;">&nbsp;</div>
      <div style="color:#6b7280; font-size:0.6rem; text-transform:uppercase; letter-spacing:0.05em;">School Principal</div>
    </div>
  </div>

  <!-- Footer -->
  <div style="background:#f0fdf4; border-top:1px solid #bbf7d0; padding:4px 16px; display:flex; justify-content:space-between; align-items:center;">
    <div style="color:#9ca3af; font-size:0.55rem;">This document is system-generated and does not require a physical signature unless noted.</div>
    <div style="color:#bbf7d0; font-size:0.6rem; font-weight:700;">SECURECAT</div>
  </div>

</div>
HTML;

        ResultSheetTemplate::updateOrCreate(
            ['name' => 'Default'],
            [
                'mode' => 'html',
                'paper_size' => 'a4',
                'orientation' => 'portrait',
                'logical_unit' => 'half_a4',
                'content' => $content,
                'document_path' => null,
                'is_active' => true,
            ]
        );
    }
}
