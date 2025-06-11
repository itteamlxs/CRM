<?php
/**
 * Archivo: pages/clientes/eliminar.php - SOFT DELETE
 * Función: Eliminación lógica de clientes (no DELETE físico)
 * Seguridad: Solo admin, validación CSRF, auditoría completa
 * Cambio: UPDATE eliminado=TRUE en lugar de DELETE FROM
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Solo admin puede eliminar clientes
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
    header('Location: ' . url('pages/clientes/index.php?error=csrf'));
    exit;
}

// Obtener ID del cliente
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: ' . url('pages/clientes/index.php?error=') . urlencode('ID de cliente inválido'));
    exit;
}

$pdo = getPDO();

try {
    $pdo->beginTransaction();
    
    // Verificar que el cliente existe y NO está eliminado
    $stmt = $pdo->prepare("SELECT nombre, email, eliminado FROM clientes WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cliente) {
        $pdo->rollBack();
        header('Location: ' . url('pages/clientes/index.php?error=') . urlencode('Cliente no encontrado'));
        exit;
    }
    
    if ($cliente['eliminado']) {
        $pdo->rollBack();
        header('Location: ' . url('pages/clientes/index.php?error=') . urlencode('El cliente ya fue eliminado anteriormente'));
        exit;
    }
    
    // Verificar dependencias activas (cotizaciones, ventas pendientes, etc.)
    $dependencias = [];
    
    try {
        // Verificar cotizaciones activas (si la tabla existe)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM cotizaciones WHERE cliente_id = :id AND estado != 'cerrada'");
        $stmt->execute([':id' => $id]);
        $cotizacionesActivas = $stmt->fetchColumn();
        
        if ($cotizacionesActivas > 0) {
            $dependencias[] = "{$cotizacionesActivas} cotización(es) activa(s)";
        }
    } catch (PDOException $e) {
        // Tabla cotizaciones no existe, continuar
    }
    
    try {
        // Verificar ventas recientes (últimos 30 días)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM ventas v 
            INNER JOIN cotizaciones c ON v.cotizacion_id = c.id 
            WHERE c.cliente_id = :id AND v.fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");
        $stmt->execute([':id' => $id]);
        $ventasRecientes = $stmt->fetchColumn();
        
        if ($ventasRecientes > 0) {
            $dependencias[] = "{$ventasRecientes} venta(s) en los últimos 30 días";
        }
    } catch (PDOException $e) {
        // Tablas de ventas no existen, continuar
    }
    
    // Si hay dependencias críticas, advertir pero permitir eliminación lógica
    $mensajeAdvertencia = '';
    if (!empty($dependencias)) {
        $mensajeAdvertencia = ' (tenía: ' . implode(', ', $dependencias) . ')';
    }
    
    // SOFT DELETE: Marcar como eliminado en lugar de DELETE físico
    $stmt = $pdo->prepare("
        UPDATE clientes 
        SET 
            eliminado = TRUE,
            fecha_eliminacion = CURRENT_TIMESTAMP,
            eliminado_por = :user_id
        WHERE id = :id AND eliminado = FALSE
    ");
    
    $stmt->execute([
        ':id' => $id,
        ':user_id' => getUserId()
    ]);
    
    $filasAfectadas = $stmt->rowCount();
    
    if ($filasAfectadas === 0) {
        $pdo->rollBack();
        header('Location: ' . url('pages/clientes/index.php?error=') . urlencode('El cliente no pudo ser eliminado o ya fue eliminado'));
        exit;
    }
    
    // Registrar en auditoría
    try {
        $stmt = $pdo->prepare("
            INSERT INTO auditoria (usuario_id, accion, descripcion, ip_usuario) 
            VALUES (:user_id, 'cliente_eliminado', :descripcion, :ip)
        ");
        $stmt->execute([
            ':user_id' => getUserId(),
            ':descripcion' => "Cliente '{$cliente['nombre']}' ({$cliente['email']}) eliminado lógicamente (ID: {$id}){$mensajeAdvertencia}",
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (PDOException $e) {
        // Si falla auditoría, no interrumpir la eliminación
        error_log("Error en auditoría de eliminación cliente: " . $e->getMessage());
    }
    
    $pdo->commit();
    
    // Log del sistema
    error_log("Cliente eliminado (soft delete): ID {$id}, Nombre: {$cliente['nombre']}, Email: {$cliente['email']}, Usuario: " . ($_SESSION['username'] ?? 'unknown'));
    
    // Redirigir con mensaje de éxito
    $mensaje = "Cliente '{$cliente['nombre']}' eliminado correctamente";
    if ($mensajeAdvertencia) {
        $mensaje .= ". Nota: El cliente tenía datos asociados que se mantienen para auditoría";
    }
    
    header('Location: ' . url('pages/clientes/index.php?success=') . urlencode($mensaje));
    exit;
    
} catch (PDOException $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    error_log("Error en soft delete de cliente: " . $e->getMessage());
    
    $errorMsg = 'Error al eliminar cliente.';
    
    // Verificar errores específicos
    if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
        $errorMsg = 'No se puede eliminar el cliente debido a restricciones de datos.';
    }
    
    header('Location: ' . url('pages/clientes/index.php?error=') . urlencode($errorMsg));
    exit;
    
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    error_log("Error general en eliminación lógica de cliente: " . $e->getMessage());
    header('Location: ' . url('pages/clientes/index.php?error=') . urlencode('Error inesperado al eliminar cliente.'));
    exit;
}