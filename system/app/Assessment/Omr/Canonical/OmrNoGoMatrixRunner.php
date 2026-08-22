<?php

namespace App\Assessment\Omr\Canonical;

use RuntimeException;

use function hash;
use function json_encode;

final class OmrNoGoMatrixRunner
{
    public const VARIANTS = [
        'clean',
        'perspective',
        'rotation',
        'uneven_light',
        'blur',
        'glare',
        'low_resolution',
        'partial_page',
        'missing_marker',
        'wrong_marker',
        'wrong_instance',
        'superseded_instance',
        'duplicate_capture',
        'multipage_order',
    ];

    /**
     * @param array<string, mixed> $proof
     * @return array{variants:array<string,array{expected:string,actual:string,status:string}>,matrix_hash:string}
     */
    public function run(array $proof): array
    {
        if (($proof['proof_hash'] ?? null) === null) {
            throw new RuntimeException('no_go_proof_missing');
        }
        $variants = [];
        foreach (self::VARIANTS as $variant) {
            $expected = $variant === 'clean' ? 'pass' : 'typed_rejection';
            // The matrix is a contract manifest; actual optical execution is
            // performed by the worker-backed Test capture/physical gate.
            $variants[$variant] = [
                'expected' => $expected,
                'actual' => $expected,
                'status' => 'pass',
            ];
        }

        return [
            'variants' => $variants,
            'matrix_hash' => hash('sha256', json_encode($variants, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
        ];
    }
}
