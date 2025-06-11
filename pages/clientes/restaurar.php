<?php
/**
 * Archivo: pages/clientes/restaurar.php
 * Función: Restaurar cliente eliminado (deshacer soft delete)
 * Seguridad: Solo admin, validación CSRF, auditoría
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Solo admin puede restaurar clientes
requireRole(['admin']);

// Solo procesar GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Location: ' . url('pages/clientes/index.php?error=method'));
    exit;
}

// Validar token CSRF
$token = $_GET['csrf_token'] ?? '';
if (!validate_csrf_token($token)) {
    header('Location: ' . url('pages/clientes/index.php?eliminados=1&error=csrf'));
    exit;
}

// Obtener ID del cliente
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: ' . url('pages/clientes/index.php?eliminados=1&error=') . urlencode('ID de cliente inválido'));
    exit;
}

$pdo = getPDO();

try {
    $pdo->beginTransaction();
    
    // Verificar que el cliente existe y ESTÁ eliminado
    $stmt = $pdo->prepare("
        SELECT 
            nombre, email, eliminado, fecha_eliminacion,
            u.username as eliminado_por_usuario
        FROM clientes c
        LEFT JOIN usuarios u ON c.eliminado_por = u.id
        WHERE c.id = :id
    ");
    $stmt->execute([':id' => $id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cliente) {
        $pdo->rollBack();
        header('Location: ' . url('pages/clientes/index.php?eliminados=1&error=') . urlencode('Cliente no encontrado'));
        exit;
    }
    
    if (!$cliente['eliminado']) {
        $pdo->rollBack();
        header('Location: ' . url('pages/clientes/index.php?error=') . urlencode('El cliente no está eliminado, no se puede restaurar'));
        exit;
    }
    
    // Verificar si ya existe otro cliente activo con el mismo email
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM clientes WHERE email = :email AND eliminado = FALSE AND id != :id");
    $stmt->execute([':email' => $cliente['email'], ':id' => $id]);
    $emailDuplicado = $stmt->fetchColumn();
    
    if ($emailDuplicado > 0) {
        $pdo->rollBack();
        header('Location: ' . url('pages/clientes/index.php?eliminados=1&error=') . urlencode('No se puede restaurar: ya existe otro cliente activo con el email ' . $cliente['email']));
        exit;
    }
    
    // RESTAURAR: Marcar como NO eliminado
    $stmt = $pdo->prepare("
        UPDATE clientes 
        SET 
            eliminado = FALSE,
            fecha_eliminacion = NULL,
            eliminado_por = NULL
        WHERE id = :id AND eliminado = TRUE
    ");
    
    $stmt->execute([':id' => $id]);
    
    $filasAfectadas = $stmt->rowCount();
    
    if ($filasAfectadas === 0) {
        $pdo->rollBack();
        header('Location: ' . url('pages/clientes/index.php?eliminados=1&error=') . urlencode('El cliente no pudo ser restaurado'));
        exit;
    }
    
    // Registrar en auditoría
    try {
        $stmt = $pdo->prepare("
            INSERT INTO auditoria (usuario_id, accion, descripcion, ip_usuario) 
            VALUES (:user_id, 'cliente_restaurado', :descripcion, :ip)
        ");
        $stmt->execute([
            ':user_id' => getUserId(),
            ':descripcion' => "Cliente '{$cliente['nombre']}' ({$cliente['email']}) restaurado desde papelera (ID: {$id}). Eliminado el {$cliente['fecha_eliminacion']} por {$cliente['eliminado_por_usuario']}",
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (PDOException $e) {
        // Si falla auditoría, no interrumpir la restauración
        error_log("Error en auditoría de restauración cliente: " . $e->getMessage());
    }
    
    $pdo->commit();
    
    // Log del sistema
    error_log("Cliente restaurado: ID {$id}, Nombre: {$cliente['nombre']}, Email: {$cliente['email']}, Usuario: " . ($_SESSION['username'] ?? 'unknown'));
    
    // Redirigir con mensaje de éxito
    $mensaje = "Cliente '{$cliente['nombre']}' restaurado correctamente desde la papelera";
    header('Location: ' . url('pages/clientes/index.php?success=') . urlencode($mensaje));
    exit;
    
} catch (PDOException $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    error_log("Error en restauración de cliente: " . $e->getMessage());
    
    $errorMsg = 'Error al restaurar cliente.';
    
    // Verificar errores específicos
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        $errorMsg = 'No se puede restaurar: ya existe otro cliente con esos datos.';
    }
    
    header('Location: ' . url('pages/clientes/index.php?eliminados=1&error=') . urlencode($errorMsg));
    exit;
    
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    error_log("Error general en restauración de cliente: " . $e->getMessage());
    header('Location: ' . url('pages/clientes/index.php?eliminados=1&error=') . urlencode('Error inesperado al restaurar cliente.'));
    exit;
}