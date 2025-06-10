<?php
/**
 * Archivo: auth.php
 * Función: Control y validación de sesión, roles, expiración, y funciones de seguridad para autenticación.
 * Seguridad: Uso estricto de sesión segura, validación de roles, manejo de expiración por inactividad.
 * 
 * Requiere: session_start() al inicio de cada script que lo incluya.
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuración de expiración de sesión (ejemplo: 30 minutos)
define('SESSION_TIMEOUT', 1800);

/**
 * Inicia sesión segura y establece variables de sesión.
 * 
 * @param int $userId ID del usuario
 * @param string $username Nombre de usuario
 * @param string $role Rol del usuario (ej: admin, vendedor)
 */
function loginUser(int $userId, string $username, string $role): void
{
    session_regenerate_id(true); // Previene session fixation
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['role'] = $role;
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
}

/**
 * Verifica si la sesión está activa y no ha expirado.
 * 
 * @return bool True si sesión válida, false si expiró o no existe.
 */
function isSessionActive(): bool
{
    if (!isset($_SESSION['user_id'], $_SESSION['last_activity'])) {
        return false;
    }
    if ((time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        logoutUser();
        return false;
    }
    // Actualizar último tiempo de actividad
    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Cierra la sesión de forma segura.
 */
function logoutUser(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

/**
 * Requiere sesión activa para continuar. Si no está activa, redirige a login.
 */
function requireLogin(): void
{
    if (!isSessionActive()) {
        header('Location: ' . url('pages/login.php?timeout=1'));
        exit;
    }
}

/**
 * Requiere que el usuario tenga un rol permitido para continuar.
 * 
 * @param array $allowedRoles Array con roles permitidos, ej: ['admin', 'vendedor']
 */
function requireRole(array $allowedRoles = []): void
{
    requireLogin();

    if (!empty($allowedRoles) && !in_array($_SESSION['role'], $allowedRoles, true)) {
        http_response_code(403);
        echo 'Acceso denegado. No tienes permiso para ver esta página.';
        exit;
    }
}

/**
 * Obtiene el ID del usuario logueado, o null si no hay sesión válida.
 * 
 * @return int|null
 */
function getUserId(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * Obtiene el nombre de usuario logueado, o null si no hay sesión válida.
 * 
 * @return string|null
 */
function getUsername(): ?string
{
    return $_SESSION['username'] ?? null;
}

/**
 * Obtiene el rol del usuario logueado, o null si no hay sesión válida.
 * 
 * @return string|null
 */
function getUserRole(): ?string
{
    return $_SESSION['role'] ?? null;
}