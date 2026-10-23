<?php

namespace App\DocumentTemplate;

use Illuminate\Support\Facades\File;
use PhpOffice\PhpWord\PhpWord;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class BaselineInventoryCatalog
{
    /**
     * @return list<array{
     *   key: string,
     *   title: string,
     *   form_code?: string,
     *   revision?: string,
     *   format: string,
     *   paper_size: string,
     *   orientation: string,
     *   description?: string,
     *   fixture_path?: string,
     *   reference_url?: string,
     *   download_url: string
     * }>
     */
    public static function all(): array
    {
        return [
            [
                'key' => 'ispsc_qad_f032a_rev5',
                'title' => 'ISPSC Internal Communication Letterhead — QAD-F032a, Rev. 5',
                'form_code' => 'ISPSC-QAD-F032a',
                'revision' => 'Rev. 5 – 07-07-2025',
                'format' => 'docx',
                'paper_size' => 'a4',
                'orientation' => 'portrait',
                'description' => 'Official ISPSC letterhead shell with Vision/Mission header, logos, and Page {{ pg_i }} of {{ pg_all }} multi-page numbering.',
                'fixture_path' => base_path('database/seeders/fixtures/baselines/institutional-baseline-reference.docx'),
                'reference_url' => 'https://docs.google.com/document/d/1tsfn73Aeemud8uE90sCh_ejQOnciHGNG/edit?usp=sharing&ouid=116619166815376449890&rtpof=true&sd=true',
                'download_url' => '/setup/report-templates/baselines/ispsc_qad_f032a_rev5/download',
            ],
            [
                'key' => 'standard_portrait_letterhead',
                'title' => 'Standard Portrait Report Shell',
                'format' => 'docx',
                'paper_size' => 'a4',
                'orientation' => 'portrait',
                'download_url' => '/setup/report-templates/baselines/standard_portrait_letterhead/download',
            ],
            [
                'key' => 'landscape_wide_register',
                'title' => 'Landscape Wide Census & Attendance Shell',
                'format' => 'docx',
                'paper_size' => 'a4',
                'orientation' => 'landscape',
                'download_url' => '/setup/report-templates/baselines/landscape_wide_register/download',
            ],
            [
                'key' => 'executive_summary_frame',
                'title' => 'Executive KPI & Summary Report Shell',
                'format' => 'docx',
                'paper_size' => 'a4',
                'orientation' => 'portrait',
                'download_url' => '/setup/report-templates/baselines/executive_summary_frame/download',
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public static function find(string $key): ?array
    {
        foreach (self::all() as $baseline) {
            if ($baseline['key'] === $key) {
                return $baseline;
            }
        }

        return null;
    }

    public static function streamDownload(string $key): StreamedResponse
    {
        $baseline = self::find($key);
        abort_if($baseline === null, 404);

        $fixture = $baseline['fixture_path'] ?? null;
        if (is_string($fixture) && File::isFile($fixture)) {
            return response()->streamDownload(
                static function () use ($fixture): void {
                    readfile($fixture);
                },
                "{$key}.docx",
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            );
        }

        abort_if($key === 'ispsc_qad_f032a_rev5', 404, 'The institutional baseline fixture is unavailable.');

        return response()->streamDownload(static function () use ($baseline): void {
            $word = new PhpWord;
            $section = $word->addSection([
                'paperSize' => strtoupper($baseline['paper_size']),
                'orientation' => $baseline['orientation'],
            ]);
            $section->addTitle($baseline['title'], 1);
            $section->addText('');

            $temporaryPath = tempnam(sys_get_temp_dir(), 'baseline-shell-');
            abort_if($temporaryPath === false, 500, 'Unable to allocate a baseline document.');
            $docxPath = $temporaryPath.'.docx';
            unlink($temporaryPath);

            try {
                $word->save($docxPath, 'Word2007');
                readfile($docxPath);
            } finally {
                if (is_file($docxPath)) {
                    unlink($docxPath);
                }
            }
        }, "{$key}.docx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }
}
