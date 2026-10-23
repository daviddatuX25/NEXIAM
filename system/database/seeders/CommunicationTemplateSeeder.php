<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Foundation\Institution;
use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateDraft;
use App\Models\User;
use App\Notifications\Templates\CommunicationTemplateRegistry;
use App\Notifications\Templates\NoticeHistoricalMessageProjection;
use App\Notifications\Templates\NoticeMessageState;
use App\Notifications\Templates\ProvisionCanonicalCommunicationTemplates;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds all canonical communication templates and initial active versions
 * for Admissions, Examination, and Results domains.
 */
class CommunicationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::query()->where('code', 'SYN-INST-001')->first()
            ?? Institution::query()->first();

        if ($institution === null) {
            return;
        }

        $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->first()
            ?? User::query()->first();

        app(ProvisionCanonicalCommunicationTemplates::class)->provision($institution, $actor);

        // Purge any empty or incomplete drafts saved during testing before schema-3 cutover
        CommunicationTemplateDraft::query()
            ->whereNull('email_blueprint')
            ->orWhereNull('subject')
            ->delete();

        // Ensure all version 1 canonical templates have full schema-3 structured blueprints
        $projector = app(NoticeHistoricalMessageProjection::class);
        $templates = CommunicationTemplate::query()
            ->where('institution_id', $institution->id)
            ->get();

        foreach ($templates as $t) {
            $slot = CommunicationTemplateRegistry::findBySlug($t->domain, $t->slug);
            if (! $slot || empty($slot['default_email_body'])) {
                continue;
            }
            [$blueprint] = $projector->projectEmailBody($slot['default_email_body'], $t);
            if (! $blueprint) {
                continue;
            }
            $v1 = $t->versions()->where('version', 1)->first();
            if ($v1) {
                $state = [
                    'subject' => $slot['default_email_subject'],
                    'body' => $slot['default_in_app_body'],
                    'action_label' => $slot['default_action_label'],
                    'action_route' => $slot['default_action_route'],
                    'email_blueprint' => $blueprint,
                    'document_revision_id' => null,
                ];
                $contentSha = app(NoticeMessageState::class)->contentDigest($t, $state, 'notice-message-email-2');

                DB::table('communication_template_versions')->where('id', $v1->id)->update([
                    'content_schema_version' => 'notice-content-3',
                    'email_renderer_version' => 'notice-message-email-2',
                    'content_sha256' => $contentSha,
                    'email_blueprint' => json_encode($blueprint),
                    'subject' => $slot['default_email_subject'],
                    'body' => $slot['default_in_app_body'],
                    'in_app_title' => $slot['default_email_subject'],
                    'in_app_body' => $slot['default_in_app_body'],
                ]);
            }
        }
    }
}
