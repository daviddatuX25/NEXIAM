<?php

namespace Database\Seeders;

use App\Models\AdmissionSlipTemplate;
use Illuminate\Database\Seeder;

class AdmissionSlipTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $content = <<<'HTML'
<div class="border border-foreground/20 rounded p-4 space-y-3" style="font-size: 0.875rem;">
  <div class="text-center">
    <h1 class="text-base font-bold">SecureCAT — Computerized Admission &amp; Testing</h1>
    <p class="text-xs text-muted-foreground mt-0.5">ADMISSION SLIP</p>
  </div>
  <table class="w-full text-sm border-collapse">
    <tr>
      <td class="text-muted-foreground w-32">Applicant Number</td>
      <td class="font-mono font-bold">{{applicant_number}}</td>
      <td rowspan="4" class="text-right align-top">{{photo_placeholder}}</td>
    </tr>
    <tr>
      <td class="text-muted-foreground">Name</td>
      <td>{{full_name}}</td>
    </tr>
    <tr>
      <td class="text-muted-foreground">Birthdate</td>
      <td>{{birthdate}}</td>
    </tr>
    <tr>
      <td class="text-muted-foreground">Sex</td>
      <td>{{sex}}</td>
    </tr>
  </table>
  <div class="space-y-1">
    <h2 class="text-sm font-semibold">Course Preferences</h2>
    <ol class="list-decimal list-inside text-sm space-y-0.5">
      <li>{{course_1}}</li>
      <li>{{course_2}}</li>
      <li>{{course_3}}</li>
    </ol>
  </div>
  <p class="text-xs text-muted-foreground">Exam schedule and room assignment will be provided after publication.</p>
  <div class="pt-2">{{qr_placeholder}}</div>
</div>
HTML;

        AdmissionSlipTemplate::updateOrCreate(
            ['name' => 'Default'],
            [
                'mode' => 'html',
                'paper_size' => 'a4',
                'orientation' => 'portrait',
                'logical_unit' => 'full',
                'content' => $content,
                'docx_path' => null,
                'is_active' => true,
            ]
        );
    }
}
