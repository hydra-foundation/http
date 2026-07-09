<?php

declare(strict_types=1);

namespace Hydra\Http;

use Hydra\Http\Exceptions\BadRequestException;

/**
 * Shared typed accessors over one request value bag.
 *
 * {@see Input} (parsed body) and {@see Query} (query string) are thin
 * subclasses that differ only in which PSR-7 bag they read; every accessor
 * lives here so the two stay identical by construction. This class is an
 * implementation detail — type-hint the concrete sibling, not the base.
 *
 * Accessors are falsy-safe: "0" is a present string and 0 is a present int;
 * only genuinely absent or wrong-shaped values fall back to the default.
 * The exceptions are {@see bool()} and {@see array()}, where a present but
 * wrong-shaped value is a malformed request and fails loud as a 400.
 */
abstract class FieldReader
{
    /** @param array<string, mixed> $values */
    protected function __construct(private readonly array $values)
    {
    }

    /** True if the field was submitted at all (even as an empty string). */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    /**
     * The field as a string. A missing field, or one submitted as an array
     * (e.g. `name[]`), yields the default — never a TypeError. Not trimmed:
     * trimming is the caller's choice (a password's spaces may matter).
     */
    public function string(string $key, string $default = ''): string
    {
        $value = $this->values[$key] ?? null;

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * The field as an int, or the default when it is absent or non-numeric.
     * "0" reads as 0 (numeric), "" and "abc" read as the default.
     */
    public function int(string $key, ?int $default = null): ?int
    {
        $value = $this->values[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * The field as a float, or the default when it is absent or non-numeric.
     * "0" reads as 0.0 (numeric), "" and "abc" read as the default.
     */
    public function float(string $key, ?float $default = null): ?float
    {
        $value = $this->values[$key] ?? null;

        return is_numeric($value) ? (float) $value : $default;
    }

    /**
     * The field as a bool. Only explicit forms are accepted — true/1/yes/on
     * and false/0/no/off, case-insensitive (the true-forms match
     * `Environment::bool()`), plus real booleans from a parsed JSON body.
     * A missing field yields the default; anything else present is a
     * malformed request, not a false — it raises a 400 rather than guessing.
     */
    public function bool(string $key, ?bool $default = null): ?bool
    {
        $value = $this->values[$key] ?? null;

        if ($value === null) {
            return $default;
        }
        if (is_bool($value)) {
            return $value;
        }

        return match (is_scalar($value) ? strtolower((string) $value) : null) {
            'true', '1', 'yes', 'on' => true,
            'false', '0', 'no', 'off' => false,
            default => throw new BadRequestException(
                sprintf('Field "%s" must be a boolean (true/false/1/0/yes/no/on/off).', $key)
            ),
        };
    }

    /**
     * The field as an array (e.g. `tags[]`), or the default when absent.
     * A scalar where an array was expected is a malformed request — it raises
     * a 400 instead of being wrapped in a one-element array silently.
     *
     * @param array<array-key, mixed> $default
     * @return array<array-key, mixed>
     */
    public function array(string $key, array $default = []): array
    {
        $value = $this->values[$key] ?? null;

        if ($value === null) {
            return $default;
        }
        if (is_array($value)) {
            return $value;
        }

        throw new BadRequestException(sprintf('Field "%s" must be an array.', $key));
    }
}
