<?php

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\StaffProfile;

require_once __DIR__.'/../../Support/CaseMessagingHelpers.php';

test('applicant audit history maps persisted program office to Program Head in filter display and search', function (): void {
    [$attempt, $applicant, $staff] = buildMessagingReadyAttempt($this, 'audit-ph-owner@example.test');
    StaffProfile::query()->where('user_id', $staff->id)->update(['office' => 'program']);
    app(RecordAuditEvent::class)->handle('program.head_record_viewed', ['attempt_id' => (string) $attempt->id], (string) $staff->id);

    $this->actingAs($applicant)->get(route('applicant.access-history', ['actor_office' => 'program_head']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.actor_office', 'program_head')
            ->where('events.total', 1)
            ->where('events.data.0.actor_display', 'Program Head'));

    $this->actingAs($applicant)->get(route('applicant.access-history', ['search' => 'Program Head']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('events.total', 1)->where('events.data.0.actor_display', 'Program Head'));
});

test('applicant audit history includes own direct assessment cohort only and never returns raw payload', function (bool $numericTarget): void {
    [$attempt, $applicant, $staff] = buildMessagingReadyAttempt($this, 'audit-cohort-owner@example.test');
    [$outsider] = buildMessagingReadyAttempt($this, 'audit-cohort-outsider@example.test');
    $record = app(RecordAuditEvent::class);
    $cohort = $record->handle('exam.direct_assessment_assigned', [
        'attempt_ids' => [$numericTarget ? (int) $attempt->id : (string) $attempt->id, (string) $outsider->id],
        'private_note' => 'cohort-private-sentinel',
    ], (string) $staff->id);
    $foreign = $record->handle('exam.direct_assessment_assigned', ['attempt_ids' => [(int) $outsider->id]], (string) $staff->id);
    $privateMessage = $record->handle('thread.message_sent', [
        'attempt_id' => $attempt->id,
        'body' => 'private-staff-discussion-sentinel',
    ], (string) $staff->id);

    $response = $this->actingAs($applicant)->get(route('applicant.access-history'));
    $response->assertOk();
    expect($response->getContent())->not->toContain('cohort-private-sentinel', 'private-staff-discussion-sentinel');
    $response->assertInertia(function ($page) use ($cohort, $foreign, $privateMessage): void {
        $events = collect($page->toArray()['props']['events']['data']);
        expect($events->pluck('id')->all())->toContain((string) $cohort->id)
            ->not->toContain((string) $foreign->id, (string) $privateMessage->id);
        expect($events->every(fn (array $event): bool => ! array_key_exists('payload', $event)))->toBeTrue();
    });
})->with([true, false]);
