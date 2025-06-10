<?php
/**
 * Archivo: forms/procesar_cliente.php
 * Función: Inserta o actualiza clientes en la base de datos.
 * Seguridad: CSRF, PDO con consultas preparadas, validación y sanitización.
 * Requiere: Sesión activa, rol administrador o vendedor.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['administrador', 'vendedor']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
}

// Validar token CSRF
$token = $_POST['csrf_token'] ?? '';
if (!validate_csrf_token($token)) {
    exit('Token CSRF inválido');
}

// Sanear y validar inputs
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$nombre = trim($_POST['nombre'] ?? '');
$email = trim($_POST['email'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$direccion = trim($_POST['direccion'] ?? '');
$estado = $_POST['estado'] ?? 'activo';

$errores = [];

if ($nombre === '' || mb_strlen($nombre) > 100) {
    $errores[] = 'El nombre es obligatorio y debe tener menos de 100 caracteres.';
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
    $errores[] = 'El email es obligatorio y debe ser válido con menos de 100 caracteres.';
}

if ($telefono !== '' && mb_strlen($telefono) > 20) {
    $errores[] = 'El teléfono debe tener menos de 20 caracteres.';
}

if (mb_strlen($direccion) > 255) {
    $errores[] = 'La dirección debe tener menos de 255 caracteres.';
}

if (!in_array($estado, ['activo', 'inactivo'], true)) {
    $estado = 'activo';
}

if ($errores) {
    // Podrías manejar errores guardándolos en sesión y redirigiendo, aquí simplificamos:
    foreach ($errores as $error) {
        echo htmlspecialchars($error) . "<br>";
    }
    echo '<a href="javascript:history.back()">Volver</a>';
    exit;
}

$pdo = getPDO();

try {
    if ($id) {
        // Actualizar cliente existente
        $sql = "UPDATE clientes SET nombre = :nombre, email = :email, telefono = :telefono, direccion = :direccion, estado = :estado WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nombre' => $nombre,
            ':email' => $email,
            ':telefono' => $telefono ?: null,
            ':direccion' => $direccion ?: null,
            ':estado' => $estado,
            ':id' => $id,
        ]);
    } else {
        // Insertar nuevo cliente
        $sql = "INSERT INTO clientes (nombre, email, telefono, direccion, estado) VALUES (:nombre, :email, :telefono, :direccion, :estado)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nombre' => $nombre,
            ':email' => $email,
            ':telefono' => $telefono ?: null,
            ':direccion' => $direccion ?: null,
            ':estado' => $estado,
        ]);
    }

    // Redirigir al listado de clientes después de éxito
    header('Location: ../pages/clientes/index.php?msg=success');
    exit;

} catch (PDOException $e) {
    // Manejar errores, mostrar de forma segura
    echo "Error en base de datos: " . htmlspecialchars($e->getMessage());
    echo '<br><a href="javascript:history.back()">Volver</a>';
    exit;
}
