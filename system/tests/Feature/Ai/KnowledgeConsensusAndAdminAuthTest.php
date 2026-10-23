<?php

declare(strict_types=1);

use App\Ai\Knowledge\Actions\ApproveKnowledgeSourceVersion;
use App\Ai\Knowledge\Actions\CreateKnowledgeDraft;
use App\Ai\Knowledge\Actions\SignKnowledgeSourceConsensus;
use App\Ai\Knowledge\Actions\StartKnowledgeExtraction;
use App\Ai\Knowledge\PublicationDigest;
use App\Models\Ai\KnowledgeSourceConsensusSignature;
use App\Models\Ai\KnowledgeSourceVersion;
use App\Models\User;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    seedE2Foundation();
});

/**
 * @return array{admin: User, registrar: User, guidance: User, clerk: User}
 */
function knowledgeConsensusActors(): array
{
    return [
        'admin' => User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail(),
        'registrar' => User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail(),
        'guidance' => User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail(),
        'clerk' => User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail(),
    ];
}

function createKnowledgeConsensusDraft(User $creator, string $title): KnowledgeSourceVersion
{
    return app(CreateKnowledgeDraft::class)->handle($creator, [
        'title' => $title,
        'body_text' => 'A policy draft used to verify office-scoped knowledge governance.',
    ]);
}

function markKnowledgeConsensusDraftReadyForReview(KnowledgeSourceVersion $version): KnowledgeSourceVersion
{
    $version->forceFill(['status' => KnowledgeSourceVersion::STATUS_REVIEW_REQUIRED])->save();

    return $version->fresh();
}

test('Admin can create a draft owned by the Admin office', function (): void {
    $actors = knowledgeConsensusActors();

    $version = createKnowledgeConsensusDraft($actors['admin'], 'Admin office policy draft');

    expect($version->source->owning_office)->toBe('admin')
        ->and($version->source->owner_user_id)->toBe((string) $actors['admin']->id)
        ->and($version->status)->toBe(KnowledgeSourceVersion::STATUS_DRAFT);
});

test('Admin cannot manage a Registrar-owned draft', function (): void {
    $actors = knowledgeConsensusActors();
    $version = createKnowledgeConsensusDraft($actors['clerk'], 'Registrar-owned draft');

    expect(fn () => app(StartKnowledgeExtraction::class)->handle(
        $actors['admin'],
        $version->source,
        $version,
    ))->toThrow(HttpException::class);
});

test('Registrar cannot manage an Admin-owned draft', function (): void {
    $actors = knowledgeConsensusActors();
    $version = createKnowledgeConsensusDraft($actors['admin'], 'Admin-owned draft');

    expect(fn () => app(StartKnowledgeExtraction::class)->handle(
        $actors['registrar'],
        $version->source,
        $version,
    ))->toThrow(HttpException::class);
});

test('a version creator cannot approve their own version', function (): void {
    $actors = knowledgeConsensusActors();
    $version = markKnowledgeConsensusDraftReadyForReview(
        createKnowledgeConsensusDraft($actors['registrar'], 'Registrar self-approval draft'),
    );

    expect(fn () => app(ApproveKnowledgeSourceVersion::class)->handle(
        $actors['registrar'],
        $version->source,
        $version,
    ))->toThrow(RuntimeException::class, 'creator_cannot_approve');
});

test('participating Admin office head can sign a version and the signature binds to its digest', function (): void {
    $actors = knowledgeConsensusActors();
    $version = markKnowledgeConsensusDraftReadyForReview(
        createKnowledgeConsensusDraft($actors['registrar'], 'Multi-office consensus draft'),
    );

    $signature = app(SignKnowledgeSourceConsensus::class)->handle(
        $actors['admin'],
        $version->source,
        $version,
        ['admin', 'registrar'],
    );

    expect($signature)->toBeInstanceOf(KnowledgeSourceConsensusSignature::class)
        ->and($signature->office)->toBe('admin')
        ->and($signature->signer_id)->toBe($actors['admin']->id)
        ->and($signature->knowledge_source_version_id)->toBe($version->id)
        ->and($signature->signed_digest)->toBe(app(PublicationDigest::class)->forVersion($version));
});

test('a version creator cannot sign their own consensus version', function (): void {
    $actors = knowledgeConsensusActors();
    $version = markKnowledgeConsensusDraftReadyForReview(
        createKnowledgeConsensusDraft($actors['registrar'], 'Self-signature prevention draft'),
    );

    expect(fn () => app(SignKnowledgeSourceConsensus::class)->handle(
        $actors['registrar'],
        $version->source,
        $version,
        ['admin', 'registrar'],
    ))->toThrow(RuntimeException::class, 'creator_cannot_sign');
});

test('a content edit makes a previous consensus signature stale for the publication digest', function (): void {
    $actors = knowledgeConsensusActors();
    $version = markKnowledgeConsensusDraftReadyForReview(
        createKnowledgeConsensusDraft($actors['registrar'], 'Digest-bound consensus draft'),
    );
    $digest = app(PublicationDigest::class);

    $signature = app(SignKnowledgeSourceConsensus::class)->handle(
        $actors['admin'],
        $version->source,
        $version,
        ['admin', 'registrar'],
    );
    $digestAtSigning = $digest->forVersion($version);

    $editedBody = 'The policy content has changed since the consensus signature was recorded.';
    $version->forceFill([
        'body_text' => $editedBody,
        'content_checksum' => hash('sha256', $editedBody),
    ])->save();
    $editedDigest = $digest->forVersion($version->fresh());

    expect($signature->fresh()->signed_digest)->toBe($digestAtSigning)
        ->and($editedDigest)->not->toBe($digestAtSigning)
        ->and($signature->fresh()->signed_digest)->not->toBe($editedDigest);
});
