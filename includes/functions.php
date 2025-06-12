<?php
/**
 * CORRECCIÓN PARA includes/functions.php
 * BUSCAR Y REEMPLAZAR la función getProductoById
 */

// ============================================================================
// BUSCAR ESTA FUNCIÓN EN functions.php (aprox línea 150-160)
// ============================================================================

/*
REEMPLAZAR ESTA FUNCIÓN:

function getProductoById(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM vista_productos WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ?: null;
}

POR ESTA FUNCIÓN CORREGIDA:
*/

/**
 * Obtener producto por ID con información de categoría - CORREGIDA
 * @param PDO $pdo Conexión a base de datos  
 * @param int $id ID del producto
 * @return array|null Datos del producto o null si no existe
 */
function getProductoById(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            c.nombre as categoria_nombre,
            c.descripcion as categoria_descripcion,
            CASE 
                WHEN p.stock_actual <= 0 THEN 'sin_stock'
                WHEN p.stock_actual <= p.stock_minimo THEN 'stock_bajo'
                ELSE 'stock_normal'
            END as estado_stock
        FROM productos p
        LEFT JOIN categorias c ON p.categoria_id = c.id
        WHERE p.id = :id AND p.eliminado = FALSE
    ");
    $stmt->execute([':id' => $id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ?: null;
}

// ============================================================================
// TAMBIÉN BUSCAR Y CORREGIR esta función si existe:
// ============================================================================

/*
SI TIENES esta función, reemplázala:

function getCategorias(PDO $pdo): array
{
    $stmt = $pdo->prepare("SELECT id, nombre, descripcion FROM categorias WHERE activa = TRUE ORDER BY nombre ASC");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

POR ESTA VERSIÓN MÁS ROBUSTA:
*/

/**
 * Obtener todas las categorías activas - CORREGIDA
 * @param PDO $pdo Conexión a base de datos
 * @return array Lista de categorías
 */
function getCategorias(PDO $pdo): array
{
    try {
        $stmt = $pdo->prepare("SELECT id, nombre, descripcion FROM categorias WHERE activa = TRUE ORDER BY nombre ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error obteniendo categorías: " . $e->getMessage());
        return [];
    }
}

// ============================================================================
// Y ESTA FUNCIÓN PARA GENERAR SKU:
// ============================================================================

/**
 * Generar código SKU único para producto - MEJORADA
 * @param PDO $pdo Conexión a base de datos
 * @param string $nombre Nombre del producto
 * @param int $categoriaId ID de la categoría
 * @return string Código SKU único
 */
function generarSKU(PDO $pdo, string $nombre, int $categoriaId): string
{
    try {
        // Obtener código de categoría
        $categoria = getCategoriaById($pdo, $categoriaId);
        $prefijo = $categoria ? strtoupper(substr($categoria['nombre'], 0, 3)) : 'GEN';
        
        // Crear base del SKU
        $nombreLimpio = preg_replace('/[^a-zA-Z0-9]/', '', $nombre);
        $nombreCorto = strtoupper(substr($nombreLimpio, 0, 6));
        
        // Buscar número correlativo disponible
        $contador = 1;
        do {
            $sku = $prefijo . '-' . $nombreCorto . '-' . str_pad($contador, 3, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM productos WHERE codigo_sku = :sku AND eliminado = FALSE");
            $stmt->execute([':sku' => $sku]);
            $existe = $stmt->fetchColumn() > 0;
            $contador++;
        } while ($existe && $contador <= 999);
        
        return $sku;
        
    } catch (PDOException $e) {
        error_log("Error generando SKU: " . $e->getMessage());
        // Fallback a SKU simple
        return 'SKU-' . time() . '-' . rand(100, 999);
    }
}

// ============================================================================
// AGREGAR ESTA FUNCIÓN SI NO EXISTE:
// ============================================================================

/**
 * Validar que una categoría existe y está activa
 * @param PDO $pdo Conexión a base de datos
 * @param int $categoriaId ID de la categoría
 * @return bool True si la categoría existe y está activa
 */
function validarCategoriaExiste(PDO $pdo, int $categoriaId): bool
{
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM categorias WHERE id = :id AND activa = TRUE");
        $stmt->execute([':id' => $categoriaId]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Error validando categoría: " . $e->getMessage());
        return false;
    }
}

// ============================================================================
// VERIFICAR QUE ESTA FUNCIÓN FUNCIONE CORRECTAMENTE:
// ============================================================================

/**
 * Registrar movimiento de inventario - VERIFICAR QUE FUNCIONE
 * @param PDO $pdo Conexión a base de datos
 * @param int $productoId ID del producto
 * @param string $tipo Tipo de movimiento (entrada, salida, ajuste)
 * @param int $cantidad Cantidad del movimiento
 * @param string $motivo Motivo del movimiento
 * @param int $usuarioId ID del usuario que hace el movimiento
 * @return bool True si se registró correctamente
 */
function registrarMovimientoInventario(PDO $pdo, int $productoId, string $tipo, int $cantidad, string $motivo, int $usuarioId): bool
{
    try {
        $pdo->beginTransaction();
        
        // Obtener stock actual
        $stmt = $pdo->prepare("SELECT stock_actual FROM productos WHERE id = :id AND eliminado = FALSE");
        $stmt->execute([':id' => $productoId]);
        $stockActual = (int) $stmt->fetchColumn();
        
        // Calcular nuevo stock
        $nuevoStock = $stockActual;
        switch ($tipo) {
            case 'entrada':
                $nuevoStock += $cantidad;
                break;
            case 'salida':
                $nuevoStock -= $cantidad;
                break;
            case 'ajuste':
                $nuevoStock = $cantidad; // En ajuste, la cantidad ES el nuevo stock
                $cantidad = $nuevoStock - $stockActual; // Recalcular diferencia
                break;
        }
        
        // Validar que el stock no sea negativo
        if ($nuevoStock < 0) {
            throw new InvalidArgumentException("El stock no puede ser negativo");
        }
        
        // Registrar movimiento
        $stmt = $pdo->prepare("
            INSERT INTO inventario_movimientos 
            (producto_id, tipo_movimiento, cantidad, stock_anterior, stock_nuevo, motivo, usuario_id) 
            VALUES (:producto_id, :tipo, :cantidad, :stock_anterior, :stock_nuevo, :motivo, :usuario_id)
        ");
        $stmt->execute([
            ':producto_id' => $productoId,
            ':tipo' => $tipo,
            ':cantidad' => $cantidad,
            ':stock_anterior' => $stockActual,
            ':stock_nuevo' => $nuevoStock,
            ':motivo' => $motivo,
            ':usuario_id' => $usuarioId
        ]);
        
        // Actualizar stock en productos - SINCRONIZAR AMBOS CAMPOS
        $stmt = $pdo->prepare("
            UPDATE productos 
            SET stock_actual = :stock, stock = :stock 
            WHERE id = :id AND eliminado = FALSE
        ");
        $stmt->execute([':stock' => $nuevoStock, ':id' => $productoId]);
        
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error en movimiento de inventario: " . $e->getMessage());
        return false;
    }
}

?>