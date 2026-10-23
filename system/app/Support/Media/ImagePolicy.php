<?php

declare(strict_types=1);

namespace App\Support\Media;

final class ImagePolicy
{
    public int $maxBytes = 5 * 1024 * 1024;

    public int $maxInputDimension = 4096;

    public int $maxInputPixels = 16_000_000;

    /** @var list<string> */
    public array $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

    public string $mode = 'fit';

    public ?int $targetWidth = null;

    public ?int $targetHeight = null;

    public string $outputFormat = 'match_input';

    public int $quality = 85;

    public static function staffAvatar(): self
    {
        $policy = new self;
        $policy->maxBytes = 2 * 1024 * 1024;
        $policy->mode = 'square_crop';
        $policy->targetWidth = 256;
        $policy->targetHeight = 256;
        $policy->outputFormat = 'png';
        $policy->quality = 6;

        return $policy;
    }

    public static function roomPhoto(): self
    {
        $policy = new self;
        $policy->maxBytes = 2 * 1024 * 1024;
        $policy->targetWidth = 1200;
        $policy->targetHeight = 1600;
        $policy->outputFormat = function_exists('imagewebp') ? 'webp' : 'png';

        return $policy;
    }

    public static function homepageImage(): self
    {
        $policy = new self;
        $policy->maxInputDimension = 8000;
        $policy->maxInputPixels = 40_000_000;
        $policy->outputFormat = 'match_input';
        $policy->quality = 88;

        return $policy;
    }

    public static function homepageMedia(): self
    {
        return self::homepageImage();
    }

    public static function programPhoto(): self
    {
        $policy = new self;
        $policy->outputFormat = 'match_input';
        $policy->quality = 88;

        return $policy;
    }

    public static function institutionLogo(): self
    {
        $policy = new self;
        $policy->maxBytes = 2 * 1024 * 1024;
        $policy->maxInputDimension = 4096;
        $policy->targetWidth = 512;
        $policy->targetHeight = 512;
        $policy->mode = 'fit';
        $policy->outputFormat = function_exists('imagewebp') ? 'webp' : 'png';
        $policy->quality = 90;

        return $policy;
    }
}
