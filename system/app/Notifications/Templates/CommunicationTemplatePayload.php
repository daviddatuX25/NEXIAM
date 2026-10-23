<?php

declare(strict_types=1);

namespace App\Notifications\Templates;

final class CommunicationTemplatePayload
{
    public static function json(mixed $value): string
    {
        return json_encode(self::canonical($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function hash(mixed $value): string
    {
        return hash('sha256', self::json($value));
    }

    private static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as $key => $item) {
            $value[$key] = self::canonical($item);
        }

        return $value;
    }
}
