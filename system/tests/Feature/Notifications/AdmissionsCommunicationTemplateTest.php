<?php

declare(strict_types=1);

use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Institution;
use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateDraft;
use App\Models\Notifications\CommunicationTemplateVersion;
use App\Models\User;
use App\Notifications\Templates\ProvisionCanonicalCommunicationTemplates;

beforeEach(function (): void {
    seedE2Foundation();
    $this->actor = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $this->actingAs($this->actor);
    app(ProvisionCanonicalCommunicationTemplates::class)->provision(Institution::current(), $this->actor, 'admissions');
    $this->template = CommunicationTemplate::query()->where('domain', 'admissions')->where('slug', 'accepted')->firstOrFail();
    $this->parameters = ['domain' => 'admissions', 'slug' => 'accepted'];
});

test('admissions authorization keeps another office from reading the current message composer', function (): void {
    $this->actingAs(User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail())
        ->getJson(route('setup.notifications.templates.editor-state', $this->parameters))->assertForbidden();
});

test('canonical admissions family reads expose composer without provisioning or mutation', function (): void {
    $before = [$this->template->fresh()->getAttributes(), CommunicationTemplateDraft::query()->count(),
        CommunicationTemplateVersion::query()->count(), AuditEvent::query()->count()];

    $this->actingAs($this->actor)->getJson(route('setup.notifications.templates.editor-state', $this->parameters))
        ->assertOk()->assertJsonPath('state.document_revision_id', null)->assertJsonPath('draft', null);

    expect([$this->template->fresh()->getAttributes(), CommunicationTemplateDraft::query()->count(),
        CommunicationTemplateVersion::query()->count(), AuditEvent::query()->count()])->toBe($before);
});
