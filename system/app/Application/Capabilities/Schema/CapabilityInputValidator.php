<?php

namespace App\Application\Capabilities\Schema;

/**
 * Minimal JSON Schema subset for capability inputs (object + additionalProperties:false).
 */
final class CapabilityInputValidator
{
    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $input
     */
    public function validate(array $schema, array $input): ?string
    {
        $type = $schema['type'] ?? null;
        if ($type !== 'object') {
            return 'unsupported_schema_type';
        }

        $properties = $schema['properties'] ?? [];
        if (! is_array($properties)) {
            return 'invalid_schema_properties';
        }

        $additional = $schema['additionalProperties'] ?? true;
        if ($additional === false) {
            foreach (array_keys($input) as $key) {
                if (! array_key_exists($key, $properties)) {
                    return "unknown_field:{$key}";
                }
            }
        }

        $required = $schema['required'] ?? [];
        if (is_array($required)) {
            foreach ($required as $field) {
                if (! array_key_exists($field, $input)) {
                    return "missing_field:{$field}";
                }
            }
        }

        return null;
    }
}
