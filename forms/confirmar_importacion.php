<?php
/**
 * Archivo: forms/confirmar_importacion.php
 * Función: Confirmar y ejecutar la importación después de la vista previa
 * Seguridad: Solo admin, validación CSRF, usar datos de sesión
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Solo admin puede importar productos
requireRole(['admin']);

// Solo procesar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Location: ' . url('pages/productos/importar.php?error=method'));
    exit;
}

// Validar token CSRF
$token = $_POST['csrf_token'] ?? '';
if (!validate_csrf_token($token)) {
    header('Location: ' . url('pages/productos/importar.php?error=csrf'));
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar que hay datos de preview en sesión
if (!isset($_SESSION['importacion_preview'])) {
    header('Location: ' . url('pages/productos/importar.php?error=') . urlencode('No hay datos de importación para procesar'));
    exit;
}

$preview = $_SESSION['importacion_preview'];
$productosImportar = $preview['productos_importar'] ?? [];
$productosActualizar = $preview['productos_actualizar'] ?? [];
$opciones = $preview['opciones'] ?? [];

// Limpiar datos de sesión
unset($_SESSION['importacion_preview']);

$pdo = getPDO();

try {
    $pdo->beginTransaction();
    
    $importados = 0;
    $actualizados = 0;
    $erroresImportacion = [];
    
    // Importar productos nuevos
    foreach ($productosImportar as $producto) {
        try {
            // Generar SKU si no existe
            if (empty($producto['codigo_sku'])) {
                $producto['codigo_sku'] = generarSKU($pdo, $producto['nombre'], $producto['categoria_id']);
            }
            
            // Verificar SKU duplicado (por si cambió desde la vista previa)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM productos WHERE codigo_sku = :sku");
            $stmt->execute([':sku' => $producto['codigo_sku']]);
            if ($stmt->fetchColumn() > 0) {
                $producto['codigo_sku'] = generarSKU($pdo, $producto['nombre'], $producto['categoria_id']);
            }
            
            $sql = "
                INSERT INTO productos (
                    nombre, descripcion, categoria_id, codigo_sku, precio_compra,
                    precio_venta, stock_actual, stock_minimo, stock_maximo,
                    unidad_medida, activo
                ) VALUES (
                    :nombre, :descripcion, :categoria_id, :codigo_sku, :precio_compra,
                    :precio_venta, :stock_actual, :stock_minimo, :stock_maximo,
                    :unidad_medida, :activo
                )
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nombre' => $producto['nombre'],
                ':descripcion' => $producto['descripcion'],
                ':categoria_id' => $producto['categoria_id'],
                ':codigo_sku' => $producto['codigo_sku'],
                ':precio_compra' => $producto['precio_compra'],
                ':precio_venta' => $producto['precio_venta'],
                ':stock_actual' => $producto['stock_actual'],
                ':stock_minimo' => $producto['stock_minimo'],
                ':stock_maximo' => $producto['stock_maximo'],
                ':unidad_medida' => $producto['unidad_medida'],
                ':activo' => $producto['activo']
            ]);
            
            $productoId = $pdo->lastInsertId();
            
            // Registrar stock inicial si es mayor a 0
            if ($producto['stock_actual'] > 0) {
                registrarMovimientoInventario(
                    $pdo,
                    $productoId,
                    'entrada',
                    $producto['stock_actual'],
                    'Stock inicial importado desde CSV',
                    getUserId()
                );
            }
            
            $importados++;
            
        } catch (Exception $e) {
            $erroresImportacion[] = "Producto '{$producto['nombre']}': " . $e->getMessage();
        }
    }
    
    // Actualizar productos existentes
    foreach ($productosActualizar as $item) {
        try {
            $producto = $item['datos'];
            $id = $item['id'];
            
            // Obtener stock actual para registrar movimiento si cambió
            $stmt = $pdo->prepare("SELECT stock_actual FROM productos WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $stockAnterior = (int) $stmt->fetchColumn();
            $stockNuevo = !empty($producto['stock_actual']) ? intval($producto['stock_actual']) : 0;
            
            $sql = "
                UPDATE productos SET
                    descripcion = :descripcion,
                    categoria_id = :categoria_id,
                    precio_compra = :precio_compra,
                    precio_venta = :precio_venta,
                    stock_actual = :stock_actual,
                    stock_minimo = :stock_minimo,
                    stock_maximo = :stock_maximo,
                    unidad_medida = :unidad_medida,
                    activo = :activo
                WHERE id = :id
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':descripcion' => $producto['descripcion'] ?? null,
                ':categoria_id' => $item['categoria_id'],
                ':precio_compra' => !empty($producto['precio_compra']) ? floatval($producto['precio_compra']) : 0.00,
                ':precio_venta' => floatval($producto['precio_venta']),
                ':stock_actual' => $stockNuevo,
                ':stock_minimo' => !empty($producto['stock_minimo']) ? intval($producto['stock_minimo']) : 0,
                ':stock_maximo' => !empty($producto['stock_maximo']) ? intval($producto['stock_maximo']) : null,
                ':unidad_medida' => !empty($producto['unidad_medida']) ? $producto['unidad_medida'] : 'unidad',
                ':activo' => $opciones['estado_inicial'],
                ':id' => $id
            ]);
            
            // Registrar movimiento de inventario si cambió el stock
            if ($stockNuevo != $stockAnterior) {
                registrarMovimientoInventario(
                    $pdo,
                    $id,
                    'ajuste',
                    $stockNuevo,
                    'Ajuste de stock por importación CSV',
                    getUserId()
                );
            }
            
            $actualizados++;
            
        } catch (Exception $e) {
            $erroresImportacion[] = "Producto '{$producto['nombre']}' (actualización): " . $e->getMessage();
        }
    }
    
    $pdo->commit();
    
    // Preparar mensaje de resultado
    $mensaje = "Importación completada exitosamente: $importados productos creados";
    if ($actualizados > 0) {
        $mensaje .= ", $actualizados productos actualizados";
    }
    
    if (!empty($erroresImportacion)) {
        $mensaje .= ". Se encontraron " . count($erroresImportacion) . " errores durante la importación";
        
        // Log de errores para revisión
        foreach ($erroresImportacion as $error) {
            error_log("Error importación CSV: $error");
        }
    }
    
    // Log de auditoría
    error_log("Importación CSV confirmada: $importados creados, $actualizados actualizados, " . count($erroresImportacion) . " errores, Usuario: " . ($_SESSION['username'] ?? 'unknown'));
    
    header('Location: ' . url('pages/productos/index.php?success=') . urlencode($mensaje));
    exit;
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error crítico en confirmación de importación CSV: " . $e->getMessage());
    header('Location: ' . url('pages/productos/importar.php?error=') . urlencode('Error crítico durante la importación: ' . $e->getMessage()));
    exit;
}