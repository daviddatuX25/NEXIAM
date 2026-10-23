<?php

namespace App\Foundation;

use Illuminate\Support\Facades\File;

final class DemoGuideAssets
{
    private const ROOT = 'views/capstone/demo-guide';

    /**
     * @var array<string, array{kind: string, name: string}>
     */
    private const APPROVED = [
        'AD-ADM-01' => ['kind' => 'diagram', 'name' => 'AD-ADM-01.svg'],
        'AD-EXM-01' => ['kind' => 'diagram', 'name' => 'AD-EXM-01.svg'],
        'AD-EXM-03' => ['kind' => 'diagram', 'name' => 'AD-EXM-03.svg'],
        'AD-GRD-01' => ['kind' => 'diagram', 'name' => 'AD-GRD-01.svg'],
        'AD-DOC-02' => ['kind' => 'diagram', 'name' => 'AD-DOC-02.svg'],
        'AD-CON-02' => ['kind' => 'diagram', 'name' => 'AD-CON-02.svg'],
        'AD-COM-02' => ['kind' => 'diagram', 'name' => 'AD-COM-02.svg'],
        'AD-ENR-01' => ['kind' => 'diagram', 'name' => 'AD-ENR-01.svg'],
        'AD-IDN-02' => ['kind' => 'diagram', 'name' => 'AD-IDN-02.svg'],
        'AD-IDN-01' => ['kind' => 'diagram', 'name' => 'AD-IDN-01.svg'],
        'protocol' => ['kind' => 'material', 'name' => 'appendix-n-facilitator-protocol.md'],
        'questionnaire' => ['kind' => 'material', 'name' => 'appendix-j-tam-questionnaire.md'],
        'activity-appendix' => ['kind' => 'material', 'name' => 'appendix-o-activity-views.md'],
    ];

    /** @var array<string, array<string, mixed>> */
    private readonly array $manifestAssets;

    /** @var array<string, array<string, mixed>> */
    private readonly array $sourceAssets;

    public function __construct()
    {
        $this->manifestAssets = $this->readManifest('published-manifest.json');
        $this->sourceAssets = $this->readManifest('asset-sources.json');
    }

    /**
     * @param  list<string>  $ids
     * @return list<array{id: string, title: string, url: string, note: string}>
     */
    public function diagrams(array $ids): array
    {
        return array_values(array_filter(array_map(
            fn (string $id): ?array => $this->metadata($id, 'diagram'),
            $ids,
        )));
    }

    /**
     * @return list<array{id: string, title: string, url: string, note: string}>
     */
    public function materials(): array
    {
        $ids = [];
        foreach ($this->sourceAssets as $asset) {
            if (($asset['kind'] ?? null) === 'material' && is_string($asset['id'] ?? null)) {
                $ids[] = $asset['id'];
            }
        }

        return array_values(array_filter(array_map(
            fn (string $id): ?array => $this->metadata($id, 'material'),
            $ids,
        )));
    }

    /**
     * @return array{content: string, name: string}|null
     */
    public function resolve(string $id, string $kind): ?array
    {
        $asset = $this->publishedAsset($id, $kind);
        if ($asset === null) {
            return null;
        }

        $content = File::get($asset['path']);
        if (! hash_equals($asset['sha256'], hash('sha256', $content))) {
            return null;
        }

        return ['content' => $content, 'name' => $asset['name']];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function readManifest(string $filename): array
    {
        $manifestPath = resource_path(self::ROOT.'/'.$filename);
        $manifest = File::exists($manifestPath) ? json_decode(File::get($manifestPath), true) : null;

        return is_array($manifest) && ($manifest['schema'] ?? null) === 1 && is_array($manifest['assets'] ?? null)
            ? array_column($manifest['assets'], null, 'id')
            : [];
    }

    /**
     * @return array{id: string, title: string, url: string, note: string}|null
     */
    private function metadata(string $id, string $kind): ?array
    {
        $asset = $this->publishedAsset($id, $kind);
        $source = $this->sourceAssets[$id] ?? null;

        if ($asset === null
            || ! is_array($source)
            || ($source['kind'] ?? null) !== $kind
            || ($source['name'] ?? null) !== $asset['name']
            || ! is_string($source['title'] ?? null)
            || ! is_string($source['note'] ?? null)
        ) {
            return null;
        }

        return [
            'id' => $id,
            'title' => $source['title'],
            'url' => '/demo/'.($kind === 'diagram' ? 'diagrams' : 'materials').'/'.$id,
            'note' => $source['note'],
        ];
    }

    /**
     * @return array{path: string, name: string, sha256: string}|null
     */
    private function publishedAsset(string $id, string $kind): ?array
    {
        $approved = self::APPROVED[$id] ?? null;
        $published = $this->manifestAssets[$id] ?? null;
        $source = $this->sourceAssets[$id] ?? null;

        if ($approved === null
            || $approved['kind'] !== $kind
            || ! is_array($published)
            || ($published['kind'] ?? null) !== $kind
            || ($published['name'] ?? null) !== $approved['name']
            || ! is_array($source)
            || ($source['kind'] ?? null) !== $kind
            || ($source['name'] ?? null) !== $approved['name']
            || ! preg_match('/^[a-f0-9]{64}$/', (string) ($published['sha256'] ?? ''))
        ) {
            return null;
        }

        $directory = realpath(resource_path(self::ROOT.'/assets'));
        $path = realpath(resource_path(self::ROOT.'/assets/'.$approved['name']));

        if ($directory === false || $path === false || ! str_starts_with($path, $directory.DIRECTORY_SEPARATOR) || ! is_file($path)) {
            return null;
        }

        return ['path' => $path, 'name' => $approved['name'], 'sha256' => $published['sha256']];
    }
}
