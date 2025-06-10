<?php
/**
 * Archivo: pages/clientes/eliminar.php
 * Función: Elimina un cliente tras validar CSRF y permisos.
 * Seguridad: Sesión activa, roles, CSRF token, validación id, PDO consultas preparadas.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../config/database.php';

require_role(['administrador']); // Solo admin puede eliminar clientes

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit('Método no permitido');
}

// Validar ID cliente
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    die('ID inválido');
}

// Validar CSRF token
$token = $_GET['csrf_token'] ?? '';
if (!validate_csrf_token($token)) {
    die('Token CSRF inválido');
}

$pdo = getPDO();

$stmt = $pdo->prepare("DELETE FROM clientes WHERE id = :id");
$stmt->bindValue(':id', $id, PDO::PARAM_INT);

try {
    $stmt->execute();
    // Redirigir con mensaje (puedes mejorar para mostrar mensaje flash)
    header('Location: index.php?msg=cliente_eliminado');
    exit;
} catch (PDOException $e) {
    die('Error al eliminar cliente: ' . $e->getMessage());
}
