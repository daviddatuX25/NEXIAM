<?php

use App\DocumentTemplate\Rendering\DocxTemplateFiller;
use App\DocumentTemplate\Rendering\LibreOfficeConverter;
use App\Models\Admission\ApplicantIdentity;
use App\Notifications\NotificationDelivery;
use App\Notifications\Services\NoticeDocumentAttachmentCompiler;
use App\Printing\PrintRenderer;
use Illuminate\Support\Str;

test('generic PDF tuples escape recipient text and every other message remains PDF free', function (): void {
    seedE2Foundation();
    $applicant = submitSyntheticApplicant('pdf-boundary@example.test', 'Boundary');
    $blueprint = ['schema_version' => 1, 'layout_paradigm' => 'flow', 'page' => [
        'paper_size' => 'a4', 'orientation' => 'portrait', 'width_mm' => 210, 'height_mm' => 297, 'margin_mm' => 12],
        'components' => [['id' => 'recipient', 'type' => 'field', 'x' => 0, 'y' => 0, 'w' => 180, 'h' => 15,
            'props' => ['label' => 'Applicant', 'binding' => 'applicant_name']]]];
    $print = ['mode' => 'builder', 'blueprint' => $blueprint, 'settings' => ['paper_size' => 'a4', 'orientation' => 'portrait']];
    $renderer = Mockery::mock(PrintRenderer::class);
    $renderer->shouldReceive('render')->twice()->withArgs(function (string $html): bool {
        expect($html)->toContain('&lt;script&gt;Own &amp; name&lt;/script&gt;')->not->toContain('<script>');

        return true;
    })->andReturn('%PDF-1.4 boundary');
    $compiler = new NoticeDocumentAttachmentCompiler(app(DocxTemplateFiller::class), app(LibreOfficeConverter::class), null, $renderer);
    foreach ([
        ['admissions', 'accepted', 'admission.accepted.v1', 'admission.accepted', true],
        ['examination', 'session-published', 'exam.session_published.v1', 'exam.session_published', true],
        ['examination', 'reminder', 'exam.reminder.v1', 'exam.reminder', false],
        ['results', 'released-f2f', 'result.released.v1', 'result.released.f2f', false],
        ['results', 'consultation-reserved', 'consultation.slot_reserved.v1', 'consultation.slot_reserved', false],
    ] as [$domain, $slug, $event, $key, $eligible]) {
        $delivery = NotificationDelivery::query()->create(['event_id' => hash('sha256', Str::uuid()),
            'event_type' => $event, 'event_version' => 1, 'recipient_type' => ApplicantIdentity::class,
            'recipient_id' => $applicant['attempt']->applicant_identity_id, 'channel' => 'mail',
            'template_key' => $key, 'template_version' => 1, 'status' => NotificationDelivery::STATUS_PENDING,
            'payload' => ['snapshot_schema_version' => 'notice-delivery-2', 'domain' => $domain, 'canonical_slug' => $slug,
                'recipient' => ['type' => ApplicantIdentity::class, 'id' => $applicant['attempt']->applicant_identity_id],
                'context' => ['values' => ['applicant_name' => '<script>Own & name</script>']], 'print' => $print]]);
        if (! $eligible) {
            expect(fn () => $compiler->compileFrozenPdf($print, $delivery))->toThrow(RuntimeException::class, 'notice_print_event_ineligible');

            continue;
        }
        $path = $compiler->compileFrozenPdf($print, $delivery);
        try {
            expect(hash_file('sha256', $path))->toBe(hash('sha256', '%PDF-1.4 boundary'));
        } finally {
            @unlink($path);
            @rmdir(dirname($path));
        }
        expect(is_file($path))->toBeFalse()->and(is_dir(dirname($path)))->toBeFalse();
    }
});
