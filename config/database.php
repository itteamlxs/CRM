<?php
/**
 * Archivo: database.php
 * Función: Establece conexión PDO segura con la base de datos usando .env.
 * Seguridad: PDO con excepciones y UTF-8.
 * Requiere: PHP >=7.4.
 */

declare(strict_types=1);

// Cargar variables .env manualmente (simple, sin librerías)
$envFile = __DIR__ . '/.env';
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
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,        // Lanzar excepciones en errores
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,   // Fetch asociativo por defecto
    PDO::ATTR_EMULATE_PREPARES => false,                // Usar consultas preparadas nativas
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // Log del error para desarrolladores (a archivo o sistema de logs)
    error_log($e->getMessage());
    die('Error de conexión a la base de datos.');
}
