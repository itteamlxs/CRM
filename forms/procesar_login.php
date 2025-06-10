<?php
/**
 * Archivo: forms/procesar_login.php
 * Función: Procesa autenticación de usuarios del sistema.
 * Seguridad: CSRF, validación, sanitización, password_verify, sesión segura.
 * No requiere: Sesión previa (es el login).
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Solo procesar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Location: /pages/login.php?error=method');
    exit;
}

// Validar token CSRF
$token = $_POST['csrf_token'] ?? '';
if (!validate_csrf_token($token)) {
    header('Location: /pages/login.php?error=csrf');
    exit;
}

// Sanitizar y validar entradas
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

$errors = [];

if ($username === '' || mb_strlen($username) > 50) {
    $errors[] = 'Usuario inválido';
}

if ($password === '' || mb_strlen($password) > 255) {
    $errors[] = 'Contraseña inválida';
}

if ($errors) {
    header('Location: /pages/login.php?error=validation');
    exit;
}

try {
    $pdo = getPDO();
    
    // Buscar usuario por username
    $stmt = $pdo->prepare("SELECT id, username, password_hash, email, role, nombre_completo, activo FROM usuarios WHERE username = :username LIMIT 1");
    $stmt->bindValue(':username', $username, PDO::PARAM_STR);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verificar si usuario existe, está activo y contraseña es correcta
    if (!$user || !$user['activo'] || !password_verify($password, $user['password_hash'])) {
        // Log intento fallido (opcional)
        error_log("Login fallido para usuario: $username desde IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        
        // Esperar un poco para prevenir ataques de fuerza bruta
        sleep(1);
        
        header('Location: /pages/login.php?error=credentials');
        exit;
    }
    
    // Login exitoso - establecer sesión segura
    loginUser(
        (int)$user['id'], 
        $user['username'], 
        $user['role']
    );
    
    // Log login exitoso (opcional)
    error_log("Login exitoso para usuario: {$user['username']} desde IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    
    // Registrar en auditoría (si la tabla existe)
    try {
        $auditStmt = $pdo->prepare("INSERT INTO auditoria (usuario_id, accion, descripcion, ip_usuario) VALUES (:user_id, 'login', 'Inicio de sesión exitoso', :ip)");
        $auditStmt->execute([
            ':user_id' => $user['id'],
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (PDOException $e) {
        // Si falla la auditoría, no interrumpir el login
        error_log("Error en auditoría de login: " . $e->getMessage());
    }
    
    // Redirigir al dashboard
    header('Location: /pages/dashboard.php');
    exit;
    
} catch (PDOException $e) {
    error_log("Error en base de datos durante login: " . $e->getMessage());
    header('Location: /pages/login.php?error=database');
    exit;
} catch (Exception $e) {
    error_log("Error general durante login: " . $e->getMessage());
    header('Location: /pages/login.php?error=general');
    exit;
}