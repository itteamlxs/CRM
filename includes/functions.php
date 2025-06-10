<?php
/**
 * Archivo: functions.php
 * Funciones helper: Sanitización, validación, helpers generales.
 */

declare(strict_types=1);

// Sanitizar texto simple para evitar XSS
function sanitizeText(string $text): string
{
    return htmlspecialchars(trim($text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Validar email
function validateEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Validar entero positivo
function validatePositiveInt($value): bool
{
    return filter_var($value, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]) !== false;
}

// Validar float positivo (precio, etc)
function validatePositiveFloat($value): bool
{
    return filter_var($value, FILTER_VALIDATE_FLOAT) !== false && floatval($value) >= 0;
}

// Validar texto con longitud máxima
function validateMaxLength(string $text, int $max): bool
{
    return mb_strlen($text) <= $max;
}
