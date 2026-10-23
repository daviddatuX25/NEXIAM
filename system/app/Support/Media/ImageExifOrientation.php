<?php

namespace App\Support\Media;

use RuntimeException;

final class ImageExifOrientation
{
    /**
     * Read the JPEG display orientation without retaining the temporary source file.
     *
     * @throws RuntimeException When temporary storage is unavailable.
     */
    public function fromJpegBytes(string $imageBytes): int
    {
        if (! function_exists('exif_read_data')) {
            return 1;
        }

        $path = tempnam(sys_get_temp_dir(), 'nexiam-exif-');

        if ($path === false) {
            throw new RuntimeException('Unable to inspect image orientation.', 1001);
        }

        try {
            if (@file_put_contents($path, $imageBytes, LOCK_EX) !== strlen($imageBytes)) {
                throw new RuntimeException('Unable to inspect image orientation.', 1001);
            }

            $data = @exif_read_data($path, 'IFD0', true, false);
            $orientation = $data['IFD0']['Orientation'] ?? $data['Orientation'] ?? 1;

            return is_int($orientation) && $orientation >= 1 && $orientation <= 8
                ? $orientation
                : 1;
        } finally {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}
