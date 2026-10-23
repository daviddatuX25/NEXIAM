<?php

namespace App\Assessment\Omr;

use InvalidArgumentException;

use function abs;
use function array_is_list;
use function array_keys;
use function array_map;
use function count;
use function filter_var;
use function hash_equals;
use function is_array;
use function is_finite;
use function is_numeric;
use function preg_match;
use function sort;

/** A shape-checked, source-bound advisory; it is never an authoritative transform. */
final readonly class OmrClientQuad
{
    /** @param list<array{x:float,y:float}> $points */
    public function __construct(
        public array $points,
        public int $width,
        public int $height,
        public int $orientation,
        public string $inputHash,
        public int $pageNo,
    ) {
        if (count($points) !== 4 || $width < 1 || $height < 1 || $orientation < 1 || $orientation > 8 || $pageNo < 1 || $pageNo > 4
            || preg_match('/^[a-f0-9]{64}$/', $inputHash) !== 1 || ! self::isCanonicalConvexQuad($points)) {
            throw new InvalidArgumentException('The client quad is malformed.');
        }
    }

    /** @param array<string, mixed> $value */
    public static function fromArray(array $value): self
    {
        self::assertExactKeys($value, ['points', 'width', 'height', 'orientation', 'input_hash', 'page_no']);
        $rawPoints = $value['points'] ?? null;
        if (! is_array($rawPoints) || ! array_is_list($rawPoints) || count($rawPoints) !== 4) {
            throw new InvalidArgumentException('The client quad must contain four ordered points.');
        }

        $points = [];
        foreach ($rawPoints as $point) {
            if (! is_array($point) || ! array_is_list($point) || count($point) !== 2) {
                throw new InvalidArgumentException('A client quad point must be a normalized [x, y] pair.');
            }
            foreach ([0, 1] as $axis) {
                if (! is_numeric($point[$axis] ?? null)) {
                    throw new InvalidArgumentException('A client quad point is not finite and normalized.');
                }
                $coordinate = (float) $point[$axis];
                if (! is_finite($coordinate) || $coordinate < 0.0 || $coordinate > 1.0) {
                    throw new InvalidArgumentException('A client quad point is not finite and normalized.');
                }
                $point[$axis] = $coordinate;
            }
            $points[] = ['x' => $point[0], 'y' => $point[1]];
        }

        foreach (['width', 'height', 'orientation', 'page_no'] as $field) {
            if (filter_var($value[$field] ?? null, FILTER_VALIDATE_INT) === false) {
                throw new InvalidArgumentException('The client quad source identity is malformed.');
            }
        }
        $inputHash = $value['input_hash'] ?? null;
        if (! is_string($inputHash)) {
            throw new InvalidArgumentException('The client quad source hash is malformed.');
        }

        return new self(
            points: $points,
            width: (int) $value['width'],
            height: (int) $value['height'],
            orientation: (int) $value['orientation'],
            inputHash: $inputHash,
            pageNo: (int) $value['page_no'],
        );
    }

    public function isBoundTo(OmrSourceImage $sourceImage, int $expectedPageNo): bool
    {
        return $this->width === $sourceImage->width
            && $this->height === $sourceImage->height
            && $this->orientation === $sourceImage->orientation
            && $this->pageNo === $expectedPageNo
            && hash_equals($sourceImage->inputHash, $this->inputHash);
    }

    /** @return array{points:list<array{0:float,1:float}>,width:int,height:int,orientation:int,input_hash:string,page_no:int} */
    public function toArray(): array
    {
        return [
            'points' => array_map(static fn (array $point): array => [$point['x'], $point['y']], $this->points),
            'width' => $this->width,
            'height' => $this->height,
            'orientation' => $this->orientation,
            'input_hash' => $this->inputHash,
            'page_no' => $this->pageNo,
        ];
    }

    /** @param array<string, mixed> $value @param list<string> $keys */
    private static function assertExactKeys(array $value, array $keys): void
    {
        $actual = array_keys($value);
        sort($actual);
        sort($keys);
        if ($actual !== $keys) {
            throw new InvalidArgumentException('The client quad has unknown or missing fields.');
        }
    }

    /** @param list<array{x:float,y:float}> $points */
    private static function isCanonicalConvexQuad(array $points): bool
    {
        $sign = 0;
        $twiceArea = 0.0;
        for ($index = 0; $index < 4; $index++) {
            $current = $points[$index];
            $next = $points[($index + 1) % 4];
            $after = $points[($index + 2) % 4];
            $cross = ($next['x'] - $current['x']) * ($after['y'] - $next['y'])
                - ($next['y'] - $current['y']) * ($after['x'] - $next['x']);
            if (! is_finite($cross) || abs($cross) <= 1.0e-8) {
                return false;
            }
            $currentSign = $cross > 0.0 ? 1 : -1;
            if ($sign !== 0 && $currentSign !== $sign) {
                return false;
            }
            $sign = $currentSign;
            $twiceArea += $current['x'] * $next['y'] - $next['x'] * $current['y'];
        }

        return $sign > 0 && $twiceArea > 1.0e-6;
    }
}
