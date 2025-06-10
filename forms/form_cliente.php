<?php
/**
 * Archivo: forms/procesar_cliente.php
 * Función: Procesa creación o edición de clientes.
 * Seguridad: CSRF, validación, sanitización, PDO preparado, sesión y roles.
 * Requiere: Sesión activa, rol administrador o vendedor.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['administrador', 'vendedor']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
}

// Validar token CSRF
$token = $_POST['csrf_token'] ?? '';
if (!validate_csrf_token($token)) {
    die('Token CSRF inválido');
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
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
    $errors[] = 'Email inválido o vacío.';
}
if ($telefono !== '' && mb_strlen($telefono) > 20) {
    $errors[] = 'Teléfono debe tener máximo 20 caracteres.';
}
if (mb_strlen($direccion) > 255) {
    $errors[] = 'Dirección debe tener máximo 255 caracteres.';
}
if (!in_array($estado, ['activo', 'inactivo'], true)) {
    $errors[] = 'Estado inválido.';
}

if ($errors) {
    // Mostrar errores y detener proceso
    foreach ($errors as $err) {
        echo htmlspecialchars($err) . '<br>';
    }
    exit;
}

$pdo = getPDO();

try {
    if ($id) {
        // Actualizar cliente
        $stmt = $pdo->prepare("UPDATE clientes SET nombre = :nombre, email = :email, telefono = :telefono, direccion = :direccion, estado = :estado WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    } else {
        // Insertar cliente nuevo
        $stmt = $pdo->prepare("INSERT INTO clientes (nombre, email, telefono, direccion, estado) VALUES (:nombre, :email, :telefono, :direccion, :estado)");
    }

    $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->bindValue(':telefono', $telefono, PDO::PARAM_STR);
    $stmt->bindValue(':direccion', $direccion, PDO::PARAM_STR);
    $stmt->bindValue(':estado', $estado, PDO::PARAM_STR);

    $stmt->execute();

    // Redirigir a listado con mensaje de éxito
    header('Location: ../pages/clientes/index.php?msg=cliente_guardado');
    exit;

} catch (PDOException $e) {
    die('Error al guardar cliente: ' . htmlspecialchars($e->getMessage()));
}
