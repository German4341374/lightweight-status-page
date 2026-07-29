<?php

declare(strict_types=1);

namespace App\Support;

final class InputValidator
{
    public static function text(mixed $value, string $field, int $maxLength, bool $required = true): string
    {
        $text = trim(\is_string($value) ? $value : '');
        if ($required && '' === $text) {
            throw new ValidationException(\sprintf('%s is required.', $field));
        }
        if (mb_strlen($text) > $maxLength) {
            throw new ValidationException(\sprintf('%s must contain at most %d characters.', $field, $maxLength));
        }

        return $text;
    }

    public static function integer(mixed $value, string $field, int $minimum, int $maximum): int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT);
        if (false === $parsed || $parsed < $minimum || $parsed > $maximum) {
            throw new ValidationException(\sprintf('%s must be between %d and %d.', $field, $minimum, $maximum));
        }

        return $parsed;
    }
}
