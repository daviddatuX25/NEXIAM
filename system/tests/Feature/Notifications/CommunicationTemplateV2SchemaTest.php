<?php

use App\Models\Foundation\Institution;
use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateDocument;
use App\Models\Notifications\CommunicationTemplateDraft;
use App\Models\Notifications\CommunicationTemplateReceipt;
use App\Models\Notifications\CommunicationTemplateVersion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => seedE2Foundation());

test('communication template supports family_revision with integer cast', function (): void {
    $institution = Institution::firstOrFail();

    $template = CommunicationTemplate::create([
        'institution_id' => $institution->id,
        'domain' => 'examination',
        'event_key' => 'exam.reminder.v1',
        'slug' => 'reminder',
        'name' => 'Examination Reminder',
        'description' => 'Reminder notice for scheduled exam sessions.',
        'active_version' => 1,
        'family_revision' => 2,
    ]);

    expect($template->family_revision)->toBe(2);

    $refetched = CommunicationTemplate::findOrFail($template->id);
    expect($refetched->family_revision)->toBe(2);
});

test('communication template draft stores v2 fields and casts arrays properly', function (): void {
    $institution = Institution::firstOrFail();
    $user = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $template = CommunicationTemplate::create([
        'institution_id' => $institution->id,
        'domain' => 'examination',
        'event_key' => 'exam.session_published.v1',
        'slug' => 'session-published',
        'name' => 'Session Published',
        'active_version' => 1,
        'family_revision' => 1,
    ]);

    $blueprint = [
        'schema_version' => 'notice-email-1',
        'layout' => 'flow',
        'blocks' => [
            ['type' => 'paragraph', 'text' => 'Hello {{applicant_name}}'],
        ],
    ];

    $provenance = [
        'source_asset_id' => '01923456-789a-7bcd-ef01-23456789abcd',
        'source_sha256' => hash('sha256', 'mock-source'),
        'converter_version' => 'notice-docx-1',
        'report_id' => '01923456-789a-7bcd-ef01-23456789abce',
        'report_sha256' => hash('sha256', 'mock-report'),
        'mapped_blocks' => 1,
        'findings' => [],
    ];

    $draft = CommunicationTemplateDraft::create([
        'communication_template_id' => $template->id,
        'base_version' => 1,
        'lock_version' => 3,
        'stage' => 2,
        'content_schema_version' => 'notice-content-2',
        'subject' => 'Notice of Examination Session',
        'body' => 'Your examination session is scheduled and available in portal.',
        'email_subject' => 'Notice of Examination Session',
        'email_body' => 'Your examination session is scheduled.',
        'in_app_title' => 'Session Scheduled',
        'in_app_body' => 'Check your assigned schedule.',
        'document_mode' => 'none',
        'email_blueprint' => $blueprint,
        'email_renderer_version' => 'notice-email-1',
        'import_provenance' => $provenance,
        'content_sha256' => hash('sha256', 'mock-content'),
        'updated_by_user_id' => $user->id,
    ]);

    expect($draft->lock_version)->toBe(3)
        ->and($draft->stage)->toBe(2)
        ->and($draft->content_schema_version)->toBe('notice-content-2')
        ->and($draft->subject)->toBe('Notice of Examination Session')
        ->and($draft->body)->toBe('Your examination session is scheduled and available in portal.')
        ->and($draft->email_blueprint)->toBeArray()
        ->and($draft->email_blueprint['schema_version'])->toBe('notice-email-1')
        ->and($draft->import_provenance)->toBeArray()
        ->and($draft->import_provenance['converter_version'])->toBe('notice-docx-1');
});

test('communication template version stores v2 fields and casts arrays properly', function (): void {
    $institution = Institution::firstOrFail();
    $user = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $template = CommunicationTemplate::create([
        'institution_id' => $institution->id,
        'domain' => 'results',
        'event_key' => 'result.released.online.v1',
        'slug' => 'released-online',
        'name' => 'Results Released Online',
        'active_version' => 1,
        'family_revision' => 1,
    ]);

    $version = CommunicationTemplateVersion::create([
        'communication_template_id' => $template->id,
        'version' => 1,
        'content_schema_version' => 'notice-content-2',
        'subject' => 'Assessment Results Available',
        'body' => 'Your exam result is ready for review.',
        'email_subject' => 'Assessment Results Available',
        'email_body' => 'Your exam result is ready.',
        'in_app_title' => 'Results Available',
        'in_app_body' => 'View your exam results online.',
        'document_mode' => 'none',
        'email_blueprint' => [
            'schema_version' => 'notice-email-1',
            'layout' => 'flow',
            'blocks' => [
                ['type' => 'paragraph', 'text' => 'Results are published.'],
            ],
        ],
        'email_renderer_version' => 'notice-email-1',
        'content_sha256' => hash('sha256', 'version-1-content'),
        'created_by_user_id' => $user->id,
    ]);

    expect($version->content_schema_version)->toBe('notice-content-2')
        ->and($version->subject)->toBe('Assessment Results Available')
        ->and($version->email_blueprint)->toBeArray()
        ->and($version->email_renderer_version)->toBe('notice-email-1');
});

test('communication template receipt enforces idempotency unique constraint', function (): void {
    $institution = Institution::firstOrFail();
    $user = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $receipt = CommunicationTemplateReceipt::create([
        'institution_id' => $institution->id,
        'domain' => 'examination',
        'canonical_slug' => 'reminder',
        'operation' => 'save_draft',
        'idempotency_key' => 'idemp-key-12345',
        'payload_sha256' => hash('sha256', 'payload-content'),
        'response_payload' => ['status' => 'saved', 'lock_version' => 2],
        'created_by_user_id' => $user->id,
    ]);

    expect($receipt->id)->not->toBeEmpty()
        ->and($receipt->institution->id)->toBe($institution->id)
        ->and($receipt->creator->id)->toBe($user->id)
        ->and($receipt->response_payload)->toBe(['status' => 'saved', 'lock_version' => 2]);

    // Second receipt with same composite unique key must fail
    expect(function () use ($institution, $user): void {
        CommunicationTemplateReceipt::create([
            'institution_id' => $institution->id,
            'domain' => 'examination',
            'canonical_slug' => 'reminder',
            'operation' => 'save_draft',
            'idempotency_key' => 'idemp-key-12345',
            'payload_sha256' => hash('sha256', 'different-payload-content'),
            'response_payload' => ['status' => 'replayed'],
            'created_by_user_id' => $user->id,
        ]);
    })->toThrow(QueryException::class);
});

test('communication template has receipts relation', function (): void {
    $institution = Institution::firstOrFail();

    $template = CommunicationTemplate::create([
        'institution_id' => $institution->id,
        'domain' => 'examination',
        'event_key' => 'exam.reminder.custom.v1',
        'slug' => 'reminder-custom',
        'name' => 'Custom Reminder',
        'active_version' => 1,
        'family_revision' => 1,
    ]);

    CommunicationTemplateReceipt::create([
        'institution_id' => $institution->id,
        'domain' => 'examination',
        'canonical_slug' => 'reminder-custom',
        'operation' => 'publish_version',
        'idempotency_key' => 'idemp-key-99999',
        'payload_sha256' => hash('sha256', 'payload-custom'),
        'response_payload' => ['version' => 2],
    ]);

    expect($template->receipts()->count())->toBe(1);
    expect($template->receipts->first()->operation)->toBe('publish_version');
});

test('forward repair truthfully labels blueprintless historical content without changing historical channel bytes', function (): void {
    $template = CommunicationTemplate::query()->create(['institution_id' => Institution::firstOrFail()->id,
        'domain' => 'examination', 'event_key' => 'exam.reminder.v1', 'slug' => 'historical-preservation',
        'name' => 'Historical Notice', 'active_version' => 1]);
    $version = CommunicationTemplateVersion::query()->create(['communication_template_id' => $template->id, 'version' => 1,
        'content_schema_version' => 'notice-content-2', 'email_renderer_version' => 'notice-email-1',
        'email_subject' => 'Stored email subject', 'email_body' => '<p>Original <strong>HTML</strong></p>',
        'in_app_title' => 'Different portal title', 'in_app_body' => 'Different portal body', 'document_mode' => 'none']);
    $before = $version->fresh()->getAttributes();
    $migration = require database_path('migrations/2026_10_05_110000_repair_notice_content_discriminators.php');
    $migration->up();
    $after = $version->fresh()->getAttributes();
    foreach (['email_subject', 'email_body', 'in_app_title', 'in_app_body', 'document_mode', 'created_at', 'id', 'version'] as $field) {
        expect($after[$field] ?? null)->toBe($before[$field] ?? null);
    }
    expect($after['content_schema_version'])->toBe('notice-content-1')->and($after['email_renderer_version'])->toBe('legacy')
        ->and($version->fresh()->email_blueprint)->toBeNull()->and($template->fresh()->active_version)->toBe(1);
});

test('blueprintless seed rows default to historical discriminators and draft uniqueness is enforced', function (): void {
    $template = CommunicationTemplate::query()->create(['institution_id' => Institution::firstOrFail()->id,
        'domain' => 'examination', 'event_key' => 'exam.reminder.v1', 'slug' => 'sole-draft',
        'name' => 'Sole Draft Notice', 'active_version' => 1]);
    $fields = ['communication_template_id' => $template->id, 'base_version' => 1,
        'email_subject' => '', 'email_body' => '', 'in_app_title' => '', 'in_app_body' => '', 'document_mode' => 'none'];
    $draft = CommunicationTemplateDraft::query()->create($fields);
    expect($draft->content_schema_version)->toBe('notice-content-1')->and($draft->email_renderer_version)->toBe('legacy');
    expect(fn () => CommunicationTemplateDraft::query()->create($fields))->toThrow(QueryException::class);
});

test('canonical DOCX media type round trips without truncation under the forward document MIME column bound', function (): void {
    $template = CommunicationTemplate::query()->create(['institution_id' => Institution::firstOrFail()->id,
        'domain' => 'examination', 'event_key' => 'exam.reminder.v1', 'slug' => 'mime-preservation',
        'name' => 'Canonical MIME Notice', 'active_version' => 1]);
    $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    $document = CommunicationTemplateDocument::query()->create([
        'communication_template_id' => $template->id, 'institution_id' => $template->institution_id,
        'original_name' => 'canonical.docx', 'storage_path' => 'communication-templates/'.$template->id.'/canonical.docx',
        'checksum' => hash('sha256', 'schema-only MIME fixture'), 'mime_type' => $mime, 'file_size_bytes' => 0,
    ]);
    expect($document->fresh()->mime_type)->toBe($mime);
    if (DB::getDriverName() === 'pgsql') {
        $column = DB::selectOne(
            "SELECT character_maximum_length FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'communication_template_documents' AND column_name = 'mime_type'"
        );
        expect((int) $column->character_maximum_length)->toBeGreaterThanOrEqual(strlen($mime));
    }
});
