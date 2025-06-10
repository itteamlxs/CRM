<?php
/**
 * Archivo: forms/procesar_cliente.php
 * Función: Procesa creación o edición de clientes.
 * Seguridad: CSRF, validación, sanitización, PDO preparado, sesión y roles.
 * Requiere: Sesión activa, rol administrador o vendedor.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireRole(['administrador', 'vendedor']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Location: /pages/clientes/index.php?error=method');
    exit;
}

// Validar token CSRF
$token = $_POST['csrf_token'] ?? '';
if (!validate_csrf_token($token)) {
    header('Location: /pages/clientes/index.php?error=csrf');
    exit;
}

// Sanitizar y validar entradas
$id = isset($_POST['id']) ? filter_var($_POST['id'], FILTER_VALIDATE_INT) : null;
$nombre = trim($_POST['nombre'] ?? '');
$email = trim($_POST['email'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$direccion = trim($_POST['direccion'] ?? '');
$estado = $_POST['estado'] ?? 'activo';

// Validaciones básicas
$errors = [];

if ($nombre === '' || mb_strlen($nombre) > 100) {
    $errors[] = 'Nombre es obligatorio y debe tener máximo 100 caracteres.';
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
    $errors[] = 'Email inválido o vacío, máximo 100 caracteres.';
}

if ($telefono !== '' && mb_strlen($telefono) > 30) {
    $errors[] = 'Teléfono debe tener máximo 30 caracteres.';
}

if (mb_strlen($direccion) > 255) {
    $errors[] = 'Dirección debe tener máximo 255 caracteres.';
}

if (!in_array($estado, ['activo', 'inactivo'], true)) {
    $errors[] = 'Estado inválido.';
}

// Si hay errores, redirigir con mensaje
if ($errors) {
    $errorMsg = implode(' ', $errors);
    header('Location: /forms/form_cliente.php' . ($id ? "?id=$id" : '') . '&error=' . urlencode($errorMsg));
    exit;
}

try {
    $pdo = getPDO();
    
    // Verificar email único (excepto si es el mismo cliente en edición)
    $emailCheckSql = "SELECT id FROM clientes WHERE email = :email" . ($id ? " AND id != :current_id" : "") . " LIMIT 1";
    $emailStmt = $pdo->prepare($emailCheckSql);
    $emailStmt->bindValue(':email', $email, PDO::PARAM_STR);
    if ($id) {
        $emailStmt->bindValue(':current_id', $id, PDO::PARAM_INT);
    }
    $emailStmt->execute();
    
    if ($emailStmt->fetch()) {
        header('Location: /forms/form_cliente.php' . ($id ? "?id=$id" : '') . '&error=' . urlencode('El email ya está registrado por otro cliente.'));
        exit;
    }
    
    $pdo->beginTransaction();
    
    if ($id) {
        // Actualizar cliente existente
        $stmt = $pdo->prepare("UPDATE clientes SET nombre = :nombre, email = :email, telefono = :telefono, direccion = :direccion, estado = :estado WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $accion = 'actualizar';
    } else {
        // Insertar nuevo cliente
        $stmt = $pdo->prepare("INSERT INTO clientes (nombre, email, telefono, direccion, estado) VALUES (:nombre, :email, :telefono, :direccion, :estado)");
        $accion = 'crear';
    }

    $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->bindValue(':telefono', $telefono ?: null, PDO::PARAM_STR);
    $stmt->bindValue(':direccion', $direccion ?: null, PDO::PARAM_STR);
    $stmt->bindValue(':estado', $estado, PDO::PARAM_STR);

    $stmt->execute();
    
    // Obtener ID del cliente (para inserción o el existente)
    $clienteId = $id ?: (int)$pdo->lastInsertId();
    
    // Registrar en auditoría
    try {
        $auditStmt = $pdo->prepare("INSERT INTO auditoria (usuario_id, accion, descripcion, ip_usuario) VALUES (:user_id, :accion, :descripcion, :ip)");
        $auditStmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':accion' => "cliente_$accion",
            ':descripcion' => "Cliente '$nombre' (ID: $clienteId) " . ($accion === 'crear' ? 'creado' : 'actualizado'),
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (PDOException $e) {
        // Si falla auditoría, no interrumpir operación principal
        error_log("Error en auditoría: " . $e->getMessage());
    }
    
    $pdo->commit();

    // Redirigir con mensaje de éxito
    $mensaje = $accion === 'crear' ? 'Cliente creado correctamente' : 'Cliente actualizado correctamente';
    header("Location: /pages/clientes/index.php?success=" . urlencode($mensaje));
    exit;

} catch (PDOException $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    error_log("Error al guardar cliente: " . $e->getMessage());
    
    // Determinar tipo de error para mostrar mensaje apropiado
    $errorMsg = 'Error al guardar cliente en la base de datos.';
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        $errorMsg = 'El email ya está registrado por otro cliente.';
    }
    
    header('Location: /forms/form_cliente.php' . ($id ? "?id=$id" : '') . '&error=' . urlencode($errorMsg));
    exit;
    
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    error_log("Error general al guardar cliente: " . $e->getMessage());
    header('Location: /forms/form_cliente.php' . ($id ? "?id=$id" : '') . '&error=' . urlencode('Error inesperado al guardar cliente.'));
    exit;
}