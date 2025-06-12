<?php
/**
 * Archivo: pages/clientes/restaurar.php - CORREGIDO
 * Función: Restaurar cliente eliminado (cambiar eliminado de TRUE a FALSE)
 * Seguridad: Solo admin puede restaurar, validación CSRF, auditoría
 * CORREGIDO: Simplificado - solo cambia eliminado=FALSE
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
    header('Location: ' . url('pages/clientes/index.php?error=method_not_allowed'));
    exit;
}

// Validar token CSRF
$token = $_GET['csrf_token'] ?? '';
if (!validate_csrf_token($token)) {
    header('Location: ' . url('pages/clientes/index.php?eliminados=1&error=token_invalido'));
    exit;
}

// Obtener y validar ID del cliente
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id <= 0) {
    header('Location: ' . url('pages/clientes/index.php?eliminados=1&error=') . urlencode('ID de cliente inválido'));
    exit;
}

$pdo = getPDO();

try {
    $pdo->beginTransaction();
    
    // 1. Verificar que el cliente existe y ESTÁ eliminado
    $stmt = $pdo->prepare("
        SELECT 
            id, nombre, email, eliminado, fecha_eliminacion,
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
    
    // 2. Verificar si ya existe otro cliente activo con el mismo email
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM clientes 
        WHERE email = :email AND eliminado = FALSE AND id != :id
    ");
    $stmt->execute([':email' => $cliente['email'], ':id' => $id]);
    $emailDuplicado = $stmt->fetchColumn();
    
    if ($emailDuplicado > 0) {
        $pdo->rollBack();
        header('Location: ' . url('pages/clientes/index.php?eliminados=1&error=') . urlencode('No se puede restaurar: ya existe otro cliente activo con el email ' . $cliente['email']));
        exit;
    }
    
    // 3. RESTAURAR: Cambiar eliminado de TRUE a FALSE
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
        header('Location: ' . url('pages/clientes/index.php?eliminados=1&error=') . urlencode('El cliente no pudo ser restaurado o ya fue restaurado'));
        exit;
    }
    
    // 4. Registrar en auditoría
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
    
    // 5. Log del sistema
    error_log("Cliente restaurado exitosamente: ID {$id}, Nombre: {$cliente['nombre']}, Email: {$cliente['email']}, Usuario: " . ($_SESSION['username'] ?? 'unknown'));
    
    // 6. Redirigir con mensaje de éxito
    $mensaje = "✅ Cliente '{$cliente['nombre']}' restaurado correctamente";
    header('Location: ' . url('pages/clientes/index.php?success=') . urlencode($mensaje));
    exit;
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error en restauración de cliente: " . $e->getMessage());
    
    $errorMsg = 'Error al restaurar cliente desde la base de datos.';
    
    // Verificar errores específicos
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        $errorMsg = 'No se puede restaurar: ya existe otro cliente con esos datos.';
    }
    
    header('Location: ' . url('pages/clientes/index.php?eliminados=1&error=') . urlencode($errorMsg));
    exit;
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error general en restauración de cliente: " . $e->getMessage());
    header('Location: ' . url('pages/clientes/index.php?eliminados=1&error=') . urlencode('Error inesperado al restaurar cliente.'));
    exit;
}