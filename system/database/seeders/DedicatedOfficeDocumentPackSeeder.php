<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Reports\ReportTemplate;
use Illuminate\Database\Seeder;

class DedicatedOfficeDocumentPackSeeder extends Seeder
{
    public function run(): void
    {
        // Clean out any legacy raw docx templates without builder blueprints
        ReportTemplate::query()
            ->where('mode', 'docx')
            ->where('source_kind', 'docx')
            ->whereNull('builder_json')
            ->delete();

        // Seed all official Document Template Builder system templates
        $this->call(ReportTemplateSeeder::class);

        $this->command?->info('Dedicated office document packs verified compliant with Document Template Builder.');
    }
}
