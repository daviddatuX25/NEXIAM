<?php

namespace App\Application\Capabilities\Schema;

/**
 * Minimal JSON Schema subset for capability inputs (object + additionalProperties:false).
 */
/**
 * Strict JSON Schema Draft 2020-12 subset for tool DTOs.
 *
 * Validation is deliberately non-coercive: JSON values must already have the
 * declared PHP type after JSON decoding.
 */
final class CapabilityInputValidator
{
    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $input
     */
    public function validate(array $schema, array $input): ?string
    {
        $schemaUri = $schema['$schema'] ?? 'https://json-schema.org/draft/2020-12/schema';
        if ($schemaUri !== 'https://json-schema.org/draft/2020-12/schema') {
            return 'unsupported_schema_version';
        }

        return $this->validateValue($schema, $input, '$');
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function validateValue(array $schema, mixed $value, string $path): ?string
    {
        if (isset($schema['const']) && $value !== $schema['const']) {
            return $path.':const_mismatch';
        }

        if (isset($schema['enum']) && (! is_array($schema['enum']) || ! in_array($value, $schema['enum'], true))) {
            return $path.':enum_mismatch';
        }

        $type = $schema['type'] ?? null;
        $types = is_string($type) ? [$type] : $type;
        if (! is_array($types) || $types === [] || array_filter($types, 'is_string') !== $types) {
            return $path.':unsupported_schema_type';
        }

        $matchedType = null;
        foreach ($types as $candidateType) {
            if ($this->matchesType($candidateType, $value)) {
                $matchedType = $candidateType;
                break;
            }
        }
        if ($matchedType === null) {
            return $path.':type_mismatch';
        }
        $type = $matchedType;

        if ($type === 'object') {
            return $this->validateObject($schema, $value, $path);
        }

        if ($type === 'array') {
            return $this->validateArray($schema, $value, $path);
        }

        if ($type === 'string') {
            return $this->validateString($schema, $value, $path);
        }

        if (in_array($type, ['integer', 'number'], true)) {
            return $this->validateNumber($schema, $value, $path);
        }

        return null;
    }

    private function matchesType(string $type, mixed $value): bool
    {
        return match ($type) {
            'object' => is_array($value),
            'array' => is_array($value),
            'string' => is_string($value),
            'integer' => is_int($value) && ! is_bool($value),
            'number' => (is_int($value) || is_float($value)) && ! is_bool($value),
            'boolean' => is_bool($value),
            'null' => $value === null,
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $value
     */
    private function validateObject(array $schema, array $value, string $path): ?string
    {
        $properties = $schema['properties'] ?? [];
        if (! is_array($properties)) {
            return $path.':invalid_schema_properties';
        }

        $additionalProperties = $schema['additionalProperties'] ?? false;
        if ($additionalProperties !== false && $additionalProperties !== true) {
            return $path.':invalid_additional_properties';
        }

        if ($additionalProperties === false) {
            foreach (array_keys($value) as $key) {
                if (! is_string($key) || ! array_key_exists($key, $properties)) {
                    return $path.'.'.(string) $key.':unknown_field';
                }
            }
        }

        $required = $schema['required'] ?? [];
        if (! is_array($required)) {
            return $path.':invalid_schema_required';
        }

        foreach ($required as $key) {
            if (! is_string($key)) {
                return $path.':invalid_schema_required';
            }

            if (! array_key_exists($key, $value)) {
                return $path.'.'.$key.':missing_field';
            }
        }

        foreach ($value as $key => $item) {
            if (! is_string($key) || ! array_key_exists($key, $properties)) {
                continue;
            }

            $propertySchema = $properties[$key];
            if (! is_array($propertySchema)) {
                return $path.'.'.$key.':invalid_schema_property';
            }

            $error = $this->validateValue($propertySchema, $item, $path.'.'.$key);
            if ($error !== null) {
                return $error;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<mixed>  $value
     */
    private function validateArray(array $schema, array $value, string $path): ?string
    {
        if (isset($schema['minItems']) && (! is_int($schema['minItems']) || count($value) < $schema['minItems'])) {
            return $path.':min_items';
        }

        if (isset($schema['maxItems']) && (! is_int($schema['maxItems']) || count($value) > $schema['maxItems'])) {
            return $path.':max_items';
        }

        $items = $schema['items'] ?? null;
        if ($items === null) {
            return null;
        }

        if (! is_array($items)) {
            return $path.':invalid_schema_items';
        }

        foreach ($value as $index => $item) {
            $error = $this->validateValue($items, $item, $path.'.'.$index);
            if ($error !== null) {
                return $error;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function validateString(array $schema, string $value, string $path): ?string
    {
        if (isset($schema['minLength']) && (! is_int($schema['minLength']) || mb_strlen($value) < $schema['minLength'])) {
            return $path.':min_length';
        }

        if (isset($schema['maxLength']) && (! is_int($schema['maxLength']) || mb_strlen($value) > $schema['maxLength'])) {
            return $path.':max_length';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function validateNumber(array $schema, int|float $value, string $path): ?string
    {
        if (isset($schema['minimum']) && (! is_int($schema['minimum']) && ! is_float($schema['minimum']) || $value < $schema['minimum'])) {
            return $path.':minimum';
        }

        if (isset($schema['maximum']) && (! is_int($schema['maximum']) && ! is_float($schema['maximum']) || $value > $schema['maximum'])) {
            return $path.':maximum';
        }

        return null;
    }
}
