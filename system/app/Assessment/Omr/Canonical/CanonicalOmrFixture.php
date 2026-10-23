<?php

namespace App\Assessment\Omr\Canonical;

use JsonException;
use RuntimeException;

final class CanonicalOmrFixture
{
    public const STABLE_CODE = 'CANONICAL-HTML';

    public function __construct(
        private readonly ?string $root = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function manifest(): array
    {
        return $this->readJson($this->path('manifest.json'));
    }

    /**
     * @return array<string, mixed>
     */
    public function authoring(): array
    {
        return $this->readJson($this->path('authoring.json'));
    }

    /**
     * @return array<string, mixed>
     */
    public function verification(): array
    {
        $manifest = $this->manifest();

        return $this->readJson($this->path((string) ($manifest['verification_file'] ?? '')));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function assets(): array
    {
        $assets = $this->manifest()['assets'] ?? null;
        if (! is_array($assets) || $assets === []) {
            throw new RuntimeException('canonical_fixture_assets_missing');
        }

        return array_values($assets);
    }

    /**
     * @param  array<string, mixed>  $asset
     */
    public function assetContents(array $asset): string
    {
        $path = $this->path('assets/'.(string) ($asset['file'] ?? ''));
        $contents = is_file($path) ? file_get_contents($path) : false;
        if (! is_string($contents)) {
            throw new RuntimeException('canonical_fixture_asset_unreadable:'.$path);
        }

        return $contents;
    }

    /**
     * @return array<string, string>
     */
    public function identities(): array
    {
        $identities = $this->manifest()['identities'] ?? null;
        if (! is_array($identities)) {
            throw new RuntimeException('canonical_fixture_identities_missing');
        }

        return array_map(static fn (mixed $value): string => (string) $value, $identities);
    }

    public function assertIntegrity(): void
    {
        $manifest = $this->manifest();
        $authoringPath = $this->path((string) ($manifest['authoring_file'] ?? ''));
        $authoring = $this->readJson($authoringPath);

        if (($manifest['schema'] ?? null) !== 'nexiam.omr.canonical-fixture.v1'
            || ($manifest['stable_code'] ?? null) !== self::STABLE_CODE) {
            throw new RuntimeException('canonical_fixture_manifest_invalid');
        }

        $this->assertHash(
            (string) ($manifest['authoring_file_sha256'] ?? ''),
            hash_file('sha256', $authoringPath) ?: '',
            'canonical_fixture_authoring_hash_mismatch',
        );

        $verificationPath = $this->path((string) ($manifest['verification_file'] ?? ''));
        $verification = $this->readJson($verificationPath);
        $this->assertHash(
            (string) ($manifest['verification_file_sha256'] ?? ''),
            hash_file('sha256', $verificationPath) ?: '',
            'canonical_fixture_verification_hash_mismatch',
        );

        $identities = $this->identities();
        $artifactSet = data_get($authoring, 'canonical.artifact_set');
        if (! is_array($artifactSet)) {
            throw new RuntimeException('canonical_fixture_artifact_set_missing');
        }

        $identityPaths = [
            'definition_hash' => 'definition_hash',
            'artifact_hash' => 'artifact_hash',
            'geometry_identity' => 'geometry_identity',
            'native_bundle_hash' => 'review_manifest.bundle_hash',
            'review_manifest_hash' => 'review_manifest_hash',
            'config_hash' => 'config_hash',
            'template_hash' => 'template_hash',
            'reference_hash' => 'reference_hash',
        ];

        foreach ($identityPaths as $identity => $path) {
            $this->assertHash(
                $identities[$identity] ?? '',
                (string) data_get($artifactSet, $path, ''),
                'canonical_fixture_'.$identity.'_mismatch',
            );
        }

        $run = $verification['run'] ?? null;
        $captures = $verification['captures'] ?? null;
        if (! is_array($run) || ! is_array($captures) || count($captures) !== count(OmrNoGoMatrixRunner::VARIANTS)) {
            throw new RuntimeException('canonical_fixture_verification_shape_invalid');
        }

        foreach ([
            'artifact_hash' => 'artifact_hash',
            'native_bundle_hash' => 'native_bundle_hash',
            'review_manifest_hash' => 'manifest_hash',
            'config_hash' => 'config_hash',
            'geometry_identity' => 'mapping_hash',
        ] as $identity => $runField) {
            $this->assertHash(
                $identities[$identity] ?? '',
                (string) ($run[$runField] ?? ''),
                'canonical_fixture_verification_'.$runField.'_mismatch',
            );
        }

        $variants = array_column($captures, 'variant');
        sort($variants);
        $expectedVariants = OmrNoGoMatrixRunner::VARIANTS;
        sort($expectedVariants);
        if ($variants !== $expectedVariants) {
            throw new RuntimeException('canonical_fixture_verification_variants_mismatch');
        }

        $assets = $this->assets();

        foreach ($assets as $asset) {
            if (! is_array($asset)) {
                throw new RuntimeException('canonical_fixture_asset_invalid');
            }

            $file = (string) ($asset['file'] ?? '');
            $sha256 = (string) ($asset['sha256'] ?? '');
            $slot = (string) ($asset['slot'] ?? '');
            $sourceId = (int) ($asset['source_id'] ?? 0);
            $assetPath = $this->path('assets/'.$file);

            if ($file === '' || $slot === '' || $sourceId < 1 || ! is_file($assetPath)) {
                throw new RuntimeException('canonical_fixture_asset_missing');
            }

            $this->assertHash(
                $sha256,
                hash_file('sha256', $assetPath) ?: '',
                'canonical_fixture_asset_hash_mismatch:'.$file,
            );

            if ((int) data_get($authoring, $slot) !== $sourceId) {
                throw new RuntimeException('canonical_fixture_asset_slot_mismatch:'.$slot);
            }
        }
    }

    private function path(string $path): string
    {
        $root = $this->root ?? database_path('fixtures/omr/canonical-html');

        return rtrim($root, '/').'/'.ltrim($path, '/');
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('canonical_fixture_file_missing:'.$path);
        }

        $contents = file_get_contents($path);
        if (! is_string($contents)) {
            throw new RuntimeException('canonical_fixture_file_unreadable:'.$path);
        }

        try {
            $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('canonical_fixture_json_invalid:'.$path, 0, $exception);
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('canonical_fixture_json_shape_invalid:'.$path);
        }

        return $decoded;
    }

    private function assertHash(string $expected, string $actual, string $error): void
    {
        if (preg_match('/\A[a-f0-9]{64}\z/', $expected) !== 1
            || preg_match('/\A[a-f0-9]{64}\z/', $actual) !== 1
            || ! hash_equals($expected, $actual)) {
            throw new RuntimeException($error);
        }
    }
}
