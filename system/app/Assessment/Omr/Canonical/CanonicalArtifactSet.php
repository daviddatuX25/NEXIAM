<?php

namespace App\Assessment\Omr\Canonical;

use RuntimeException;

/** Immutable in-memory candidate outputs before draft asset persistence. */
final readonly class CanonicalArtifactSet
{
    /**
     * @param array<string,mixed> $definition
     * @param array<string,mixed> $template
     * @param array<string,mixed> $config
     * @param array<string,mixed> $manifest
     * @param array<string,mixed> $reviewManifest
     * @param array<string,string> $files
     * @param list<array{page_no:int,bytes:string,mime:string,width:int,height:int,sha256:string,metadata:array<string,mixed>}> $pages
     */
    public function __construct(
        public array $definition,
        public array $template,
        public array $config,
        public array $manifest,
        public array $reviewManifest,
        public string $reviewManifestHash,
        public array $files,
        public array $pages,
        public string $pdfBytes,
        public string $artifactHash,
        public string $templateHash,
        public string $configHash,
        public string $pdfHash,
        public string $referenceHash,
        public string $markerHash,
    ) {
    }


    /** Drop large raster/PDF buffers when only native diagnostics are needed. */
    public function forDiagnostics(): self
    {
        return new self(
            definition: $this->definition,
            template: $this->template,
            config: $this->config,
            manifest: $this->manifest,
            reviewManifest: $this->reviewManifest,
            reviewManifestHash: $this->reviewManifestHash,
            files: $this->files,
            pages: [],
            pdfBytes: '',
            artifactHash: $this->artifactHash,
            templateHash: $this->templateHash,
            configHash: $this->configHash,
            pdfHash: $this->pdfHash,
            referenceHash: $this->referenceHash,
            markerHash: $this->markerHash,
        );
    }
    public function file(string $name): string
    {
        if (! array_key_exists($name, $this->files)) {
            throw new RuntimeException("Canonical artifact file is missing: {$name}.");
        }

        return $this->files[$name];
    }
}
