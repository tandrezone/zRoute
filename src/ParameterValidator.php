<?php

declare(strict_types=1);

namespace zRoute;

use InvalidArgumentException;
use zRoute\Exceptions\ValidationException;

/**
 * Validates and type-casts incoming request parameters against a route's
 * parameter definition block.
 *
 * Each entry in the definition array may contain:
 *
 *   - type      (string)  Scalar type name: 'integer'|'float'|'boolean'|'string'|'array'
 *   - required  (bool)    Whether the parameter must be present (default: true)
 *   - default   (mixed)   Fallback value when the parameter is absent
 *   - source    (string)  Hint about where the value comes from: 'path'|'query'|'body'|'auto'
 *   - regex     (string)  Additional regex that the raw string value must satisfy (path params)
 *   - structure (array)   For 'array' types: maps nested keys to their expected scalar types
 *
 * Usage:
 *
 *   $validator = new ParameterValidator();
 *   $validated = $validator->validate($routeParameters, $allInputValues);
 */
class ParameterValidator
{
    /**
     * Validate and cast $input against the parameter $definition.
     *
     * @param array<string,array<string,mixed>> $definition Route parameter definitions.
     * @param array<string,mixed>               $input      Merged request input values.
     *
     * @return array<string,mixed> The validated (and type-cast) parameter values.
     *
     * @throws ValidationException When one or more parameters fail validation.
     */
    public function validate(array $definition, array $input): array
    {
        $errors    = [];
        $validated = [];

        foreach ($definition as $name => $rules) {
            $type     = (string) ($rules['type']     ?? 'string');
            $required = (bool)   ($rules['required'] ?? true);
            $default  = $rules['default'] ?? null;
            $source   = (string) ($rules['source']   ?? 'auto');

            $value = $input[$name] ?? null;

            if ($value === null) {
                if ($default !== null) {
                    $value = $default;
                } elseif (!$required) {
                    // Optional with no default — omit from result.
                    continue;
                } else {
                    $errors[$name] = "Parameter '{$name}' is required.";
                    continue;
                }
            }

            // Path-parameter regex constraint.
            if ($source === 'path' && isset($rules['regex'])) {
                $pattern = '/^(?:' . $rules['regex'] . ')$/';
                if (!preg_match($pattern, (string) $value)) {
                    $errors[$name] = "Parameter '{$name}' does not match the required pattern.";
                    continue;
                }
            }

            // Type-cast and structural validation.
            try {
                $structure          = is_array($rules['structure'] ?? null) ? $rules['structure'] : null;
                $validated[$name]   = $this->castValue($value, $type, $name, $structure);
            } catch (InvalidArgumentException $e) {
                $errors[$name] = $e->getMessage();
            }
        }

        if ($errors !== []) {
            throw new ValidationException('Request validation failed.', $errors);
        }

        return $validated;
    }

    // -----------------------------------------------------------------------
    // Internal helpers
    // -----------------------------------------------------------------------

    private function castValue(mixed $value, string $type, string $name, ?array $structure): mixed
    {
        return match ($type) {
            'int', 'integer'     => $this->castInt($value, $name),
            'float', 'double'    => $this->castFloat($value, $name),
            'bool', 'boolean'    => $this->castBool($value, $name),
            'string'             => (string) $value,
            'array'              => $this->castArray($value, $name, $structure),
            default              => $value,
        };
    }

    private function castInt(mixed $value, string $name): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?[0-9]+$/', $value)) {
            return (int) $value;
        }
        throw new InvalidArgumentException("Parameter '{$name}' must be an integer.");
    }

    private function castFloat(mixed $value, string $name): float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }
        throw new InvalidArgumentException("Parameter '{$name}' must be a float.");
    }

    private function castBool(mixed $value, string $name): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (in_array($value, ['1', 'true', 'yes', 1, true], true)) {
            return true;
        }
        if (in_array($value, ['0', 'false', 'no', 0, false], true)) {
            return false;
        }
        throw new InvalidArgumentException("Parameter '{$name}' must be a boolean.");
    }

    private function castArray(mixed $value, string $name, ?array $structure): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException("Parameter '{$name}' must be an array.");
        }

        if ($structure !== null) {
            foreach ($structure as $key => $subType) {
                if (array_key_exists($key, $value)) {
                    $value[$key] = $this->castValue($value[$key], (string) $subType, "{$name}.{$key}", null);
                }
            }
        }

        return $value;
    }
}
