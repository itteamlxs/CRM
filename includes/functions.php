<?php
/**
 * Archivo: functions.php
 * Funciones helper: Sanitización, validación, helpers generales, conexión PDO.
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

/**
 * Obtener conexión PDO global o crear una nueva
 * @return PDO Conexión a la base de datos
 */
function getPDO(): PDO
{
    static $pdo = null;
    
    if ($pdo === null) {
        // Cargar variables .env
        $envFile = __DIR__ . '/../config/.env';
        if (!file_exists($envFile)) {
            die('Archivo .env no encontrado.');
        }
        $env = parse_ini_file($envFile, false, INI_SCANNER_TYPED);
        if (!$env) {
            die('Error al leer archivo .env');
        }

        $host = $env['DB_HOST'] ?? 'localhost';
        $dbname = $env['DB_NAME'] ?? 'crm_db';
        $user = $env['DB_USER'] ?? 'root';
        $pass = $env['DB_PASS'] ?? '';

        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            die('Error de conexión a la base de datos.');
        }
    }
    
    return $pdo;
}

/**
 * Generar token CSRF si no existe en sesión
 * @return string Token CSRF
 */
function generate_csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validar token CSRF enviado en formulario
 * @param string|null $token Token a validar
 * @return bool True si es válido
 */
function validate_csrf_token(?string $token): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}