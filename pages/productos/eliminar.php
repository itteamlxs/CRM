<?php
/**
 * Archivo: pages/productos/eliminar.php
 * Función: Eliminar producto del sistema
 * Seguridad: Solo admin puede eliminar productos, validación CSRF, verificar dependencias
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Solo admin puede eliminar productos
requireRole(['admin']);

// Solo procesar GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Location: ' . url('pages/productos/index.php?error=method'));
    exit;
}

// Validar token CSRF
$token = $_GET['csrf_token'] ?? '';
if (!validate_csrf_token($token)) {
    header('Location: ' . url('pages/productos/index.php?error=csrf'));
    exit;
}

// Obtener ID del producto
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: ' . url('pages/productos/index.php?error=') . urlencode('ID de producto inválido'));
    exit;
}

$pdo = getPDO();

try {
    // Verificar que el producto existe
    $producto = getProductoById($pdo, $id);
    if (!$producto) {
        header('Location: ' . url('pages/productos/index.php?error=') . urlencode('Producto no encontrado'));
        exit;
    }
    
    // Verificar que no tenga dependencias (cotizaciones, ventas, etc.)
    $dependencias = [];
    
    // Verificar tabla cotizacion_productos si existe
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM cotizacion_productos WHERE producto_id = :id");
        $stmt->execute([':id' => $id]);
        $totalCotizaciones = $stmt->fetchColumn();
        
        if ($totalCotizaciones > 0) {
            $dependencias[] = "{$totalCotizaciones} cotización(es)";
        }
    } catch (PDOException $e) {
        // La tabla no existe, continuar
    }
    
    // Verificar tabla venta_productos si existe
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM venta_productos WHERE producto_id = :id");
        $stmt->execute([':id' => $id]);
        $totalVentas = $stmt->fetchColumn();
        
        if ($totalVentas > 0) {
            $dependencias[] = "{$totalVentas} venta(s)";
        }
    } catch (PDOException $e) {
        // La tabla no existe, continuar
    }
    
    // Verificar movimientos de inventario
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM inventario_movimientos WHERE producto_id = :id");
    $stmt->execute([':id' => $id]);
    $totalMovimientos = $stmt->fetchColumn();
    
    if ($totalMovimientos > 0) {
        $dependencias[] = "{$totalMovimientos} movimiento(s) de inventario";
    }
    
    // Si tiene dependencias, no permitir eliminación
    if (!empty($dependencias)) {
        $dependenciasTexto = implode(', ', $dependencias);
        $errorMsg = "No se puede eliminar el producto '{$producto['nombre']}' porque tiene registros asociados: {$dependenciasTexto}. ";
        $errorMsg .= "Considera desactivar el producto en lugar de eliminarlo.";
        
        header('Location: ' . url('pages/productos/index.php?error=') . urlencode($errorMsg));
        exit;
    }
    
    $pdo->beginTransaction();
    
    // Eliminar imagen si existe
    if ($producto['imagen']) {
        eliminarImagenProducto($producto['imagen']);
    }
    
    // Eliminar el producto
    $stmt = $pdo->prepare("DELETE FROM productos WHERE id = :id");
    $stmt->execute([':id' => $id]);
    
    $pdo->commit();
    
    // Log de auditoría
    error_log("Producto eliminado: ID {$id}, Nombre: {$producto['nombre']}, SKU: {$producto['codigo_sku']}, Usuario: " . ($_SESSION['username'] ?? 'unknown'));
    
    // Redirigir con mensaje de éxito
    $mensaje = "Producto '{$producto['nombre']}' eliminado correctamente";
    header('Location: ' . url('pages/productos/index.php?success=') . urlencode($mensaje));
    exit;
    
} catch (PDOException $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    error_log("Error de base de datos al eliminar producto: " . $e->getMessage());
    
    $errorMsg = 'Error de base de datos al eliminar producto.';
    
    // Verificar errores específicos
    if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
        $errorMsg = 'No se puede eliminar el producto porque tiene registros asociados.';
    }
    
    header('Location: ' . url('pages/productos/index.php?error=') . urlencode($errorMsg));
    exit;
    
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    error_log("Error general al eliminar producto: " . $e->getMessage());
    header('Location: ' . url('pages/productos/index.php?error=') . urlencode('Error inesperado al eliminar producto.'));
    exit;
}