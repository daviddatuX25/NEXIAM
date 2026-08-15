<?php

use App\Application\Capabilities\CapabilityDefinitions;
use App\Authorization\CapabilityCatalog;

test('exam session pages and nexi kernel share one exam-management capability gate', function (): void {
    $expected = CapabilityCatalog::examSessionManagementCapabilities();

    expect($expected)->toBe([CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE])
        ->and(CapabilityCatalog::testAdministratorCapabilities())
        ->toContain(CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE);

    foreach (CapabilityDefinitions::all() as $definition) {
        if (! str_starts_with($definition->key, 'exams.')) {
            continue;
        }

        // Applicant slot capabilities (Slice 9) are gated by applicant-facing
        // capabilities; only the staff pages/kernel surfaces share the gate.
        if (in_array($definition->key, ['exams.slots.list', 'exams.slot.reserve'], true)) {
            continue;
        }

        expect($definition->applicationCapabilities)->toBe($expected);
    }
});

test('inherent test administrator seed set stays synced to D-016 catalog helper', function (): void {
    $grants = CapabilityCatalog::roleHelperGrants()['guidance_counselor'];

    foreach (CapabilityCatalog::testAdministratorCapabilities() as $capability) {
        expect($grants)->toContain($capability);
    }

    // Delegated schedule holders share the same exam-management key (no AI-only fork).
    expect(CapabilityCatalog::roleHelperGrants()['guidance_clerk'])
        ->toContain(CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)
        ->and(CapabilityCatalog::roleHelperGrants()['registrar_clerk'])
        ->not->toContain(CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE);
});
