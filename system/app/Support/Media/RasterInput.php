<?php

declare(strict_types=1);

namespace App\Support\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

final readonly class RasterInput
{
    private function __construct(private string $imageBytes)
    {
        if ($imageBytes === '') {
            throw ValidationException::withMessages(['image' => 'Choose a non-empty image file.']);
        }
    }

    public static function fromUploadedFile(UploadedFile $file): self
    {
        $path = $file->getRealPath();
        $bytes = is_string($path) ? @file_get_contents($path) : false;

        if (! is_string($bytes) || $bytes === '') {
            throw ValidationException::withMessages(['image' => 'The uploaded image could not be read.']);
        }

        return new self($bytes);
    }

    public static function fromBytes(string $bytes, ?string $clientMime = null): self
    {
        return new self($bytes);
    }

    public static function fromPath(string $path): self
    {
        $bytes = @file_get_contents($path);

        if (! is_string($bytes) || $bytes === '') {
            throw ValidationException::withMessages(['image' => 'The image file could not be read.']);
        }

        return new self($bytes);
    }

    public function bytes(): string
    {
        return $this->imageBytes;
    }

    public function size(): int
    {
        return strlen($this->imageBytes);
    }
}
