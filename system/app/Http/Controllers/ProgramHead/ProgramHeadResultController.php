<?php

namespace App\Http\Controllers\ProgramHead;

use App\Http\Controllers\Controller;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Foundation\Program;
use App\Printing\PrintRenderer;
use App\ProgramHead\Queries\ProgramHeadApplicantQueries;
use App\ResultSheet\Rendering\ResultSheetCssService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class ProgramHeadResultController extends Controller
{
    public function __invoke(
        Request $request,
        Program $program,
        AdmissionAttempt $attempt,
        ProgramHeadApplicantQueries $query,
        ResultSheetCssService $css,
        PrintRenderer $renderer,
    ): Response {
        $record = $query->protectedRecord($attempt, $program, $request->user());
        abort_unless(($record['tier'] ?? null) === 'B', 403, 'The released academic record is not available.');

        $html = view('program-head.result-sheet', [
            'record' => $record,
            'program' => ['code' => $program->code, 'name' => $program->name],
        ])->render();
        $bytes = $renderer->render($css->wrapForPdf($html, 'a4', 'portrait'), [
            'format' => 'A4',
            'orientation' => 'portrait',
        ]);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="academic-review-'.$attempt->id.'-'.$program->code.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
