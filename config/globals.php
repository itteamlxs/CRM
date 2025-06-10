<?php
/**
 * Archivo: globals.php
 * Función: Constantes globales, configuración de seguridad HTTP headers y zona horaria.
 * Requiere: Carga después de la conexión a BD.
 */

declare(strict_types=1);

// Incluir archivo .env para configurar zona horaria e idioma
$envFile = __DIR__ . '/.env';
$env = parse_ini_file($envFile, false, INI_SCANNER_TYPED);

define('DEFAULT_LANG', $env['DEFAULT_LANG'] ?? 'es');
define('DEFAULT_TIMEZONE', $env['DEFAULT_TIMEZONE'] ?? 'UTC');

date_default_timezone_set(DEFAULT_TIMEZONE);

// Encabezados HTTP de seguridad (se pueden agregar más según sea necesario)
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');

// Definir roles para el sistema
define('ROLE_ADMIN', 'administrador');
define('ROLE_SELLER', 'vendedor');

// Definir expiración de sesión en segundos (ejemplo: 30 minutos)
define('SESSION_EXPIRATION_TIME', 1800);
