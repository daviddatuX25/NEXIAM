<?php

use App\Assessment\Omr\Canonical\OmrBlankPrintContract;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrTemplateAsset;

function uploadedCanonicalPolicyVersion(array $policyOverrides = []): OmrAnswerSheetTemplateVersion
{
    $pages = [['page_no' => 1], ['page_no' => 2]];
    $policy = ['registration_mode' => 'document_bound', 'qr_mode' => 'qr_disabled', 'compiled_pages' => $pages];
    $version = new OmrAnswerSheetTemplateVersion;
    $version->forceFill([
        'id' => 42,
        'registration_mode' => OmrRegistrationMode::DocumentBound,
        'qr_mode' => OmrQrMode::Disabled,
        'page_count' => 2,
        'native_bundle_hash' => str_repeat('a', 64),
        'native_bundle_json' => ['manifest' => $policy, 'template' => ['pages' => $pages]],
        'authoring_manifest' => array_replace_recursive([
            'source_kind' => 'uploaded_original',
            'compiled_pages' => $pages,
            'artifact_set' => [...$policy, 'schema' => 'nexiam.omr.canonical-artifact-set.v1', 'artifact_hash' => str_repeat('b', 64), 'pdf_hash' => null],
        ], $policyOverrides),
    ]);

    return $version;
}

it('resolves canonical artifact-set policy for uploaded references without granting reproduction', function (): void {
    $contract = OmrBlankPrintContract::fromVersion(uploadedCanonicalPolicyVersion());
    expect($contract->compiledPages)->toBe([['page_no' => 1], ['page_no' => 2]])
        ->and($contract->artifactHash)->toBe(str_repeat('b', 64));
    expect(fn () => $contract->assertPrintAsset(new OmrTemplateAsset))
        ->toThrow(RuntimeException::class, 'print_uploaded_source_forbidden');
});

it('rejects conflicting top-level or artifact-set policy rather than trusting another matching source', function (array $overrides): void {
    expect(fn () => OmrBlankPrintContract::fromVersion(uploadedCanonicalPolicyVersion($overrides)))
        ->toThrow(RuntimeException::class, 'print_policy_manifest_mismatch');
})->with([
    'top-level conflict' => [['qr_mode' => 'qr_signed']],
    'nested conflict' => [['registration_mode' => 'document_bound', 'qr_mode' => 'qr_disabled', 'artifact_set' => ['qr_mode' => 'qr_signed']]],
    'unrecognized nested schema' => [['artifact_set' => ['schema' => 'unknown']]],
]);
