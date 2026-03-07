<?php
/**
 * Input validation utilities.
 */

class Validator
{
    public static function required($value): bool
    {
        if (is_string($value)) {
            return trim($value) !== '';
        }
        return $value !== null && $value !== '';
    }

    public static function minLength(string $value, int $min): bool
    {
        return mb_strlen(trim($value), 'UTF-8') >= $min;
    }

    public static function maxLength(string $value, int $max): bool
    {
        return mb_strlen(trim($value), 'UTF-8') <= $max;
    }

    /**
     * Username: 4-20 chars, alphanumeric only.
     */
    public static function username(string $value): bool
    {
        return (bool)preg_match('/^[a-zA-Z0-9]{4,20}$/', $value);
    }

    /**
     * Password: minimum 8 characters.
     */
    public static function password(string $value): bool
    {
        return mb_strlen($value, 'UTF-8') >= 8;
    }

    /**
     * Phone number: Korean format (digits, hyphens allowed).
     */
    public static function phone(string $value): bool
    {
        $cleaned = preg_replace('/[\s\-]/', '', $value);
        return (bool)preg_match('/^(0[0-9]{1,2}[0-9]{3,4}[0-9]{4}|01[016789][0-9]{7,8})$/', $cleaned);
    }

    public static function email(string $value): bool
    {
        return (bool)filter_var(trim($value), FILTER_VALIDATE_EMAIL);
    }

    public static function numeric($value): bool
    {
        return is_numeric($value);
    }

    /**
     * Date format: YYYY-MM-DD.
     */
    public static function date(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }
        $parts = explode('-', $value);
        return checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0]);
    }
}
