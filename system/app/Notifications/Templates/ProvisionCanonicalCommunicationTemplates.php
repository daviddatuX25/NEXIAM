<?php

namespace App\Notifications\Templates;

use App\Models\Foundation\Institution;
use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProvisionCanonicalCommunicationTemplates
{
    /** @param list<string>|null $onlySlugs */
    public function provision(Institution|int $institution, ?User $actor = null, ?string $onlyDomain = null, ?array $onlySlugs = null): void
    {
        $institutionId = $institution instanceof Institution ? $institution->id : $institution;
        $domains = $onlyDomain === null ? ['admissions', 'examination', 'results'] : [$onlyDomain];

        DB::transaction(function () use ($institutionId, $actor, $domains, $onlySlugs): void {
            foreach ($domains as $domain) {
                foreach (CommunicationTemplateRegistry::slotsFor($domain) as $slot) {
                    if ($onlySlugs !== null && ! in_array($slot['slug'], $onlySlugs, true)) {
                        continue;
                    }
                    $template = CommunicationTemplate::firstOrCreate(
                        [
                            'institution_id' => $institutionId,
                            'event_key' => $slot['event_key'],
                        ],
                        [
                            'domain' => $domain,
                            'slug' => $slot['slug'],
                            'name' => $slot['name'],
                            'description' => $slot['description'],
                            'active_version' => 1,
                        ],
                    );

                    $template = CommunicationTemplate::query()
                        ->whereKey($template->getKey())
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($template->versions()->exists()) {
                        continue;
                    }
                    $blueprint = null;
                    if (! empty($slot['default_email_body'])) {
                        [$projectedBlueprint] = app(NoticeHistoricalMessageProjection::class)->projectEmailBody(
                            $slot['default_email_body'],
                            $template,
                        );
                        $blueprint = $projectedBlueprint;
                    }
                    $blueprint ??= [
                        'schema_version' => 'notice-email-1',
                        'layout' => 'flow',
                        'blocks' => [
                            ['type' => 'paragraph', 'text' => strip_tags((string) ($slot['default_email_body'] ?? ''))],
                        ],
                    ];
                    $state = [
                        'subject' => $slot['default_email_subject'],
                        'body' => $slot['default_in_app_body'],
                        'action_label' => $slot['default_action_label'],
                        'action_route' => $slot['default_action_route'],
                        'email_blueprint' => $blueprint,
                        'document_revision_id' => null,
                    ];
                    $contentSha = app(NoticeMessageState::class)->contentDigest($template, $state, 'notice-message-email-2');

                    CommunicationTemplateVersion::create([
                        'communication_template_id' => $template->id,
                        'version' => 1,
                        'content_schema_version' => 'notice-content-3',
                        'email_renderer_version' => 'notice-message-email-2',
                        'content_sha256' => $contentSha,
                        'subject' => $slot['default_email_subject'],
                        'body' => $slot['default_in_app_body'],
                        'email_subject' => $slot['default_email_subject'],
                        'email_body' => $slot['default_email_body'],
                        'in_app_title' => $slot['default_email_subject'],
                        'in_app_body' => $slot['default_in_app_body'],
                        'email_blueprint' => $blueprint,
                        'action_label' => $slot['default_action_label'],
                        'action_route' => $slot['default_action_route'],
                        'document_mode' => 'none',
                        'created_by_user_id' => $actor?->id,
                    ]);
                }
            }
        });
    }
}
