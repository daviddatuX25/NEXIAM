<?php

namespace App\Assessment\Omr\Diagnostics;

use App\Assessment\Omr\Canonical\OmrSourcePolicy;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use RuntimeException;

use function array_key_exists;
use function array_keys;
use function array_values;
use function hash;
use function in_array;
use function is_int;
use function is_string;
use function json_encode;
use function ksort;
use function str_starts_with;
use function strtoupper;

/**
 * Immutable deterministic proof plan (OpenSpec 35.13, AD-3).
 *
 * Binds one target's hashes, purpose, and plan version into a canonical plan
 * hash so digital proof, quick rehearsal, and full qualification share one
 * expected vector and cannot drift. `expectedVector` maps expanded native
 * field labels to their declared option value; `calibrationDigits` carries
 * expected applicant-number digits ONLY when the target maps an identity
 * grid. No applicant identity is fabricated by this object.
 */
final readonly class OmrProofPlan
{
    /**
     * @param  array<string, string>  $expectedVector  native label => declared option value
     * @param  array<string, int>  $calibrationDigits  APPLICANT_NUMBER_<position> => digit (empty when no identity grid)
     * @param  list<array{page_no: int, labels: list<string>, instruction: string}>  $pageInstructions
     * @param  array<string, string>  $targetHashes  artifact|native_bundle|manifest|config|mapping hashes the plan pins
     */
    public function __construct(
        public string $planVersion,
        public ProofPurpose $purpose,
        public string $seed,
        public string $challengeCode,
        public array $expectedVector,
        public array $calibrationDigits,
        public array $pageInstructions,
        public array $targetHashes,
        public OmrRegistrationMode $registrationMode,
        public OmrQrMode $qrMode,
        /** @var list<array{page_no:int}> */
        public array $compiledPages,
        /** @var list<array<string,mixed>> */
        public array $diagnosticQrReservation,
        public ?string $method = null,
        /** @var list<array{page_no:int,label:string,area:string|null,position:int}> */
        public array $sampleManifest = [],
        /** @var list<string> */
        public array $allowedMedia = [],
        ?int $requiredPageCount = null,
        ?string $expectedVectorHash = null,
        public string $sourceKind = OmrSourcePolicy::SYSTEM_DESIGNED,
    ) {
        $pageCount = max(0, $requiredPageCount ?? 0);
        foreach ($this->compiledPages as $page) {
            $pageCount = max($pageCount, (int) ($page['page_no'] ?? 0));
        }
        $this->requiredPageCount = $pageCount;
        $this->expectedVectorHash = $expectedVectorHash ?? hash(
            'sha256',
            json_encode($this->expectedVector, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        );
        $this->validateShape();
    }

    public string $expectedVectorHash;

    public int $requiredPageCount;

    public const METHOD_PRE_SHADED = 'pre_shaded';

    public const METHOD_HAND_SHADED = 'hand_shaded';

    public const MEDIUM_HB_2_PENCIL = 'hb_2_pencil';

    public const MEDIUM_DARK_BLACK_PEN = 'dark_black_pen';

    public const HAND_SHADED_MEDIA = [
        self::MEDIUM_HB_2_PENCIL,
        self::MEDIUM_DARK_BLACK_PEN,
    ];

    public const METHODS = [
        self::METHOD_PRE_SHADED,
        self::METHOD_HAND_SHADED,
    ];

    /** True when this is a method-bound physical plan rather than a digital plan. */
    public function isMethodBound(): bool
    {
        return $this->method !== null;
    }

    /** Canonical plan hash over every plan field (assets excluded). */
    public function planHash(): string
    {
        return hash('sha256', json_encode($this->canonicalPayload(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Rebuild a plan from its canonical payload (the inverse of
     * `canonicalPayload()`), so queue payloads can carry the exact plan
     * without losing determinism.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $expected = (array) ($payload['expected_vector'] ?? []);
        $instructions = (array) ($payload['page_instructions'] ?? []);
        $method = isset($payload['method']) ? (string) $payload['method'] : null;
        $sampleManifest = (array) ($payload['sample_manifest'] ?? []);
        $registrationMode = OmrRegistrationMode::tryFrom((string) ($payload['registration_mode'] ?? ''));
        $qrMode = OmrQrMode::tryFrom((string) ($payload['qr_mode'] ?? ''));
        if ($registrationMode === null || $qrMode === null) {
            throw new RuntimeException('plan_policy_missing: explicit registration and QR modes are required.');
        }
        if ($sampleManifest === []) {
            $position = 1;
            foreach ($instructions as $instruction) {
                foreach ((array) ($instruction['labels'] ?? []) as $label) {
                    if (! is_string($label) || ! array_key_exists($label, $expected)) {
                        continue;
                    }
                    $area = null;
                    if (preg_match('/^([A-Z0-9]+(?:_[A-Z0-9]+)*)_Q_\\d+$/', $label, $matches) === 1) {
                        $area = $matches[1];
                    }
                    $sampleManifest[] = [
                        'page_no' => (int) ($instruction['page_no'] ?? 0),
                        'label' => $label,
                        'area' => $area,
                        'position' => $position++,
                    ];
                }
            }
        }
        $allowedMedia = array_values(array_map('strval', (array) ($payload['allowed_media'] ?? [])));
        if ($allowedMedia === [] && $method === self::METHOD_HAND_SHADED) {
            $allowedMedia = self::HAND_SHADED_MEDIA;
        }

        return new self(
            planVersion: (string) ($payload['plan_version'] ?? ''),
            purpose: ProofPurpose::from((string) ($payload['purpose'] ?? '')),
            seed: (string) ($payload['seed'] ?? ''),
            challengeCode: (string) ($payload['challenge_code'] ?? ''),
            expectedVector: $expected,
            calibrationDigits: array_map('intval', (array) ($payload['calibration_digits'] ?? [])),
            pageInstructions: $instructions,
            targetHashes: (array) ($payload['target_hashes'] ?? []),
            registrationMode: $registrationMode,
            qrMode: $qrMode,
            compiledPages: (array) ($payload['compiled_pages'] ?? []),
            diagnosticQrReservation: (array) ($payload['diagnostic_qr_reservation'] ?? []),
            method: $method,
            sampleManifest: $sampleManifest,
            allowedMedia: $allowedMedia,
            requiredPageCount: isset($payload['required_page_count']) ? (int) $payload['required_page_count'] : null,
            expectedVectorHash: isset($payload['expected_vector_hash']) ? (string) $payload['expected_vector_hash'] : null,
            sourceKind: (string) ($payload['source_kind'] ?? OmrSourcePolicy::SYSTEM_DESIGNED),
        );
    }

    /**
     * Expected vector restricted to the labels declared for one page.
     *
     * @return array<string, string>
     */
    public function expectedVectorForPage(int $pageNo): array
    {
        $labels = [];
        foreach ($this->pageInstructions as $instruction) {
            if ((int) ($instruction['page_no'] ?? 0) === $pageNo) {
                foreach ((array) ($instruction['labels'] ?? []) as $label) {
                    if (is_string($label)) {
                        $labels[] = $label;
                    }
                }
            }
        }

        return array_filter($this->expectedVector, static fn (string $label): bool => in_array($label, $labels, true), ARRAY_FILTER_USE_KEY);
    }

    /**
     * True when every pinned target hash equals the given target hashes —
     * the binding check before a run may consume this plan.
     *
     * @param  array<string, string>  $targetHashes
     */
    public function bindsTo(array $targetHashes): bool
    {
        foreach ($this->targetHashes as $name => $hash) {
            if (! array_key_exists($name, $targetHashes) || $targetHashes[$name] !== $hash) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function canonicalPayload(): array
    {
        $instructions = array_map(
            static fn (array $instruction): array => [
                'page_no' => (int) ($instruction['page_no'] ?? 0),
                'labels' => array_values(array_map('strval', (array) ($instruction['labels'] ?? []))),
                'instruction' => (string) ($instruction['instruction'] ?? ''),
            ],
            $this->pageInstructions,
        );
        $expected = $this->expectedVector;
        ksort($expected);
        $digits = $this->calibrationDigits;
        ksort($digits);
        $targetHashes = $this->targetHashes;
        ksort($targetHashes);

        return [
            'plan_version' => $this->planVersion,
            'purpose' => $this->purpose->value,
            'seed' => $this->seed,
            'challenge_code' => $this->challengeCode,
            'expected_vector' => $expected,
            'calibration_digits' => $digits,
            'page_instructions' => $instructions,
            'registration_mode' => $this->registrationMode->value,
            'qr_mode' => $this->qrMode->value,
            'compiled_pages' => array_values(array_map(
                static fn (array $page): array => ['page_no' => (int) ($page['page_no'] ?? 0)],
                $this->compiledPages,
            )),
            'diagnostic_qr_reservation' => array_values($this->diagnosticQrReservation),
            'method' => $this->method,
            'sample_manifest' => array_values(array_map(
                static fn (array $entry): array => [
                    'page_no' => (int) ($entry['page_no'] ?? 0),
                    'label' => (string) ($entry['label'] ?? ''),
                    'area' => isset($entry['area']) ? (string) $entry['area'] : null,
                    'position' => (int) ($entry['position'] ?? 0),
                ],
                $this->sampleManifest,
            )),
            'allowed_media' => array_values($this->allowedMedia),
            'required_page_count' => $this->requiredPageCount,
            'expected_vector_hash' => $this->expectedVectorHash,
            'target_hashes' => $targetHashes,
            ...($this->sourceKind === OmrSourcePolicy::UPLOADED_ORIGINAL ? ['source_kind' => $this->sourceKind] : []),
        ];
    }

    private function validateShape(): void
    {
        if ($this->method !== null && ! in_array($this->method, self::METHODS, true)) {
            throw new RuntimeException('plan_method_invalid: physical plans must bind pre_shaded or hand_shaded.');
        }
        if ($this->purpose !== ProofPurpose::Digital && $this->method === null) {
            throw new RuntimeException('plan_method_required: physical plans must bind a method.');
        }
        $computedVectorHash = hash(
            'sha256',
            json_encode($this->expectedVector, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        );
        if ($this->expectedVectorHash !== $computedVectorHash) {
            throw new RuntimeException('plan_expected_vector_hash_invalid: expected-vector hash must be server-derived.');
        }
        if ($this->requiredPageCount < 1) {
            throw new RuntimeException('plan_required_pages_invalid: at least one required page is expected.');
        }
        if (! array_is_list($this->compiledPages) || count($this->compiledPages) !== $this->requiredPageCount) {
            throw new RuntimeException('plan_compiled_pages_invalid: ordered compiled pages must exactly match the required page count.');
        }
        $expectedPages = range(1, $this->requiredPageCount);
        $compiledPageNumbers = [];
        foreach ($this->compiledPages as $index => $page) {
            if (! is_array($page) || ! is_int($page['page_no'] ?? null) || $page['page_no'] !== $index + 1) {
                throw new RuntimeException('plan_compiled_pages_invalid: ordered compiled pages must be contiguous from Page 1.');
            }
            $compiledPageNumbers[] = $page['page_no'];
        }
        $isUploaded = $this->sourceKind === OmrSourcePolicy::UPLOADED_ORIGINAL;
        if ($isUploaded) {
            if ($this->diagnosticQrReservation !== []) {
                throw new RuntimeException('plan_uploaded_qr_forbidden: uploaded originals only allow QR-free proof plans.');
            }
        } else {
            if ($compiledPageNumbers !== $expectedPages
                || ! array_is_list($this->diagnosticQrReservation)
                || count($this->diagnosticQrReservation) !== $this->requiredPageCount) {
                throw new RuntimeException('plan_diagnostic_qr_reservation_invalid: every compiled page requires one proof-only QR reservation.');
            }
            foreach ($this->diagnosticQrReservation as $index => $reservation) {
                if (! is_array($reservation)) {
                    throw new RuntimeException('plan_diagnostic_qr_reservation_invalid: reservation geometry must be numeric and page-bound.');
                }
                foreach (['page_no', 'x_mm', 'y_mm', 'width_mm', 'height_mm', 'qr_x_mm', 'qr_y_mm', 'qr_size_mm', 'display_code_x_mm', 'display_code_y_mm', 'paper_width_mm', 'paper_height_mm'] as $key) {
                    if (! is_numeric($reservation[$key] ?? null)) {
                        throw new RuntimeException('plan_diagnostic_qr_reservation_invalid: reservation geometry must be numeric and page-bound.');
                    }
                }
                if ((int) $reservation['page_no'] !== $index + 1
                    || (float) $reservation['x_mm'] < 0
                    || (float) $reservation['y_mm'] < 0
                    || (float) $reservation['width_mm'] <= 0
                    || (float) $reservation['height_mm'] <= 0
                    || (float) $reservation['paper_width_mm'] <= 0
                    || (float) $reservation['paper_height_mm'] <= 0
                    || (float) $reservation['x_mm'] + (float) $reservation['width_mm'] > (float) $reservation['paper_width_mm']
                    || (float) $reservation['y_mm'] + (float) $reservation['height_mm'] > (float) $reservation['paper_height_mm']
                    || (float) $reservation['qr_x_mm'] < (float) $reservation['x_mm']
                    || (float) $reservation['qr_y_mm'] < (float) $reservation['y_mm']
                    || (float) $reservation['qr_size_mm'] <= 0
                    || (float) $reservation['qr_x_mm'] + (float) $reservation['qr_size_mm'] > (float) $reservation['x_mm'] + (float) $reservation['width_mm']
                    || (float) $reservation['qr_y_mm'] + (float) $reservation['qr_size_mm'] > (float) $reservation['y_mm'] + (float) $reservation['height_mm']) {
                    throw new RuntimeException('plan_diagnostic_qr_reservation_invalid: QR matrix must fit the reserved box inside physical paper bounds.');
                }
            }
        }
        if ($this->method === self::METHOD_HAND_SHADED
            && $this->allowedMedia !== []
            && $this->allowedMedia !== self::HAND_SHADED_MEDIA) {
            throw new RuntimeException('plan_medium_invalid: hand-shaded plans allow HB/#2 pencil or dark black pen only.');
        }
        foreach ($this->sampleManifest as $entry) {
            if (! is_array($entry)
                || (int) ($entry['page_no'] ?? 0) < 1
                || ! is_string($entry['label'] ?? null)
                || $entry['label'] === ''
                || ! array_key_exists($entry['label'], $this->expectedVector)
                || (int) ($entry['position'] ?? 0) < 1) {
                throw new RuntimeException('plan_sample_manifest_invalid: samples must bind to expected-vector labels and pages.');
            }
        }
        foreach ($this->expectedVector as $label => $value) {
            if (! is_string($label) || ! is_string($value) || $label === '' || $value === '') {
                throw new RuntimeException('plan_expected_vector_invalid: expected vector must map labels to option values.');
            }
        }
        foreach ($this->calibrationDigits as $label => $digit) {
            if (! is_string($label) || ! is_int($digit) || $digit < 0 || $digit > 9) {
                throw new RuntimeException('plan_calibration_digits_invalid: calibration digits must be 0-9 per label.');
            }
            if (strtoupper($label) !== $label || ! str_starts_with($label, 'APPLICANT_NUMBER_')) {
                throw new RuntimeException('plan_calibration_digits_invalid: calibration digits apply only to applicant-number labels.');
            }
        }
        if ($this->pageInstructions === []) {
            throw new RuntimeException('plan_instructions_missing: at least one page instruction is required.');
        }
        $pages = [];
        $covered = [];
        foreach ($this->pageInstructions as $index => $instruction) {
            $pageNo = (int) ($instruction['page_no'] ?? 0);
            if (! is_int($instruction['page_no'] ?? null) || $pageNo !== $index + 1) {
                throw new RuntimeException('plan_instructions_invalid: every compiled page must have one ordered page instruction.');
            }
            $pages[$pageNo] = true;
            $labels = (array) ($instruction['labels'] ?? []);
            if ($labels === []) {
                throw new RuntimeException('plan_instructions_invalid: every page instruction must declare its labels.');
            }
            foreach ($labels as $label) {
                if (! is_string($label) || $label === '') {
                    throw new RuntimeException('plan_instructions_invalid: instruction labels must be non-empty strings.');
                }
                if ($this->expectedVector !== [] && ! array_key_exists($label, $this->expectedVector)) {
                    throw new RuntimeException('plan_instructions_invalid: instruction label is not in the expected vector.');
                }
                $covered[] = $label;
            }
        }
        if (array_keys($pages) !== range(1, $this->requiredPageCount)) {
            throw new RuntimeException('plan_instructions_invalid: every compiled page must have one ordered page instruction.');
        }
        foreach (array_keys($this->expectedVector) as $label) {
            if (! in_array($label, $covered, true)) {
                throw new RuntimeException('plan_instructions_invalid: expected label is not covered by any page instruction.');
            }
        }
        foreach (['artifact', 'native_bundle', 'manifest', 'config', 'mapping'] as $name) {
            if (! is_string($this->targetHashes[$name] ?? null) || $this->targetHashes[$name] === '') {
                throw new RuntimeException('plan_target_hashes_invalid: artifact/native_bundle/manifest/config/mapping hashes are required.');
            }
        }
    }
}
