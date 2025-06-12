<?php
/**
 * Archivo: forms/procesar_producto.php - CORREGIDO COMPLETO
 * Función: Procesar formulario de creación/edición de productos
 * REPARADO: Campos BD corregidos, validaciones mejoradas, errores solucionados
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['admin', 'vendedor']);

// Solo procesar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Location: ' . url('pages/productos/index.php?error=method'));
    exit;
}

// Validar token CSRF
$token = $_POST['csrf_token'] ?? '';
if (!validate_csrf_token($token)) {
    header('Location: ' . url('pages/productos/index.php?error=csrf'));
    exit;
}

$pdo = getPDO();

// Obtener y sanitizar datos del formulario
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$nombre = trim($_POST['nombre'] ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');
$categoria_id = filter_input(INPUT_POST, 'categoria_id', FILTER_VALIDATE_INT);
$codigo_sku = trim($_POST['codigo_sku'] ?? '');
$precio_compra = filter_input(INPUT_POST, 'precio_compra', FILTER_VALIDATE_FLOAT);
$precio_venta = filter_input(INPUT_POST, 'precio_venta', FILTER_VALIDATE_FLOAT);
$stock_actual = filter_input(INPUT_POST, 'stock_actual', FILTER_VALIDATE_INT);
$stock_minimo = filter_input(INPUT_POST, 'stock_minimo', FILTER_VALIDATE_INT);
$stock_maximo = filter_input(INPUT_POST, 'stock_maximo', FILTER_VALIDATE_INT);
$unidad_medida = $_POST['unidad_medida'] ?? 'unidad';
$activo = filter_input(INPUT_POST, 'activo', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
$eliminar_imagen = isset($_POST['eliminar_imagen']) && $_POST['eliminar_imagen'] === '1';

$esEdicion = $id !== null && $id !== false;

// Validaciones básicas
$errores = [];

if (empty($nombre) || strlen($nombre) > 200) {
    $errores[] = 'El nombre del producto es obligatorio y debe tener máximo 200 caracteres';
}

if (!$categoria_id) {
    $errores[] = 'Debe seleccionar una categoría válida';
}

if (!$precio_venta || $precio_venta <= 0) {
    $errores[] = 'El precio de venta debe ser mayor a 0';
}

if ($precio_compra !== null && $precio_compra < 0) {
    $errores[] = 'El precio de compra debe ser mayor o igual a 0';
}

if ($stock_actual === null || $stock_actual < 0) {
    $errores[] = 'El stock actual debe ser mayor o igual a 0';
}

if ($stock_minimo === null || $stock_minimo < 0) {
    $errores[] = 'El stock mínimo debe ser mayor or igual a 0';
}

if ($stock_maximo !== null && $stock_maximo !== false && $stock_maximo < $stock_minimo) {
    $errores[] = 'El stock máximo no puede ser menor al stock mínimo';
}

$unidades_validas = ['unidad', 'kg', 'gramo', 'litro', 'metro', 'caja', 'paquete'];
if (!in_array($unidad_medida, $unidades_validas)) {
    $errores[] = 'Unidad de medida no válida';
}

// Si hay errores, redirigir con mensaje
if ($errores) {
    $errorMsg = implode(' ', $errores);
    $redirectUrl = url('pages/productos/form.php') . ($esEdicion ? "?id=$id" : '') . '&error=' . urlencode($errorMsg);
    header('Location: ' . $redirectUrl);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Variables para tracking
    $productoId = null;
    $stockAnterior = 0;
    $imagenAnterior = '';
    
    if ($esEdicion) {
        // Verificar que el producto existe
        $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = :id AND eliminado = FALSE");
        $stmt->execute([':id' => $id]);
        $producto_existente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$producto_existente) {
            throw new Exception('Producto no encontrado');
        }
        
        $productoId = $id;
        $stockAnterior = (int) $producto_existente['stock_actual'];
        $imagenAnterior = $producto_existente['imagen'] ?? '';
        
        // Verificar duplicado de código SKU (excluyendo el producto actual)
        if (!empty($codigo_sku)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM productos WHERE codigo_sku = :sku AND id != :id AND eliminado = FALSE");
            $stmt->execute([':sku' => $codigo_sku, ':id' => $id]);
            
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Ya existe un producto con ese código SKU.');
            }
        }
        
    } else {
        // Verificar duplicado de código SKU para nuevo producto
        if (!empty($codigo_sku)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM productos WHERE codigo_sku = :sku AND eliminado = FALSE");
            $stmt->execute([':sku' => $codigo_sku]);
            
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Ya existe un producto con ese código SKU.');
            }
        }
    }
    
    // Generar código SKU automáticamente si está vacío
    if (empty($codigo_sku)) {
        $codigo_sku = generarSKU($pdo, $nombre, $categoria_id);
    }
    
    // Manejar imagen
    $nombreImagen = $imagenAnterior;
    
    // Si se marca eliminar imagen
    if ($eliminar_imagen && $imagenAnterior) {
        eliminarImagenProducto($imagenAnterior);
        $nombreImagen = null;
    }
    
    // Si se sube nueva imagen
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        // Eliminar imagen anterior si existe
        if ($imagenAnterior) {
            eliminarImagenProducto($imagenAnterior);
        }
        
        $nuevaImagen = subirImagenProducto($_FILES['imagen'], $nombre);
        if ($nuevaImagen) {
            $nombreImagen = $nuevaImagen;
        } else {
            throw new Exception('Error al subir la imagen. Verifica que sea un archivo válido (PNG, JPG, GIF) menor a 5MB.');
        }
    }
    
    // Preparar datos para inserción/actualización
    $precio_compra = $precio_compra ?: 0.00;
    $stock_maximo = $stock_maximo ?: null;
    
    if ($esEdicion) {
        // Actualizar producto existente - CAMPOS CORREGIDOS
        $sql = "
            UPDATE productos SET 
                nombre = :nombre,
                descripcion = :descripcion,
                categoria_id = :categoria_id,
                codigo_sku = :codigo_sku,
                precio_compra = :precio_compra,
                precio_venta = :precio_venta,
                precio_base = :precio_venta,
                stock_actual = :stock_actual,
                stock = :stock_actual,
                stock_minimo = :stock_minimo,
                stock_maximo = :stock_maximo,
                unidad_medida = :unidad_medida,
                imagen = :imagen,
                activo = :activo
            WHERE id = :id AND eliminado = FALSE
        ";
        
        $params = [
            ':nombre' => $nombre,
            ':descripcion' => $descripcion ?: null,
            ':categoria_id' => $categoria_id,
            ':codigo_sku' => $codigo_sku,
            ':precio_compra' => $precio_compra,
            ':precio_venta' => $precio_venta,
            ':stock_actual' => $stock_actual,
            ':stock_minimo' => $stock_minimo,
            ':stock_maximo' => $stock_maximo,
            ':unidad_medida' => $unidad_medida,
            ':imagen' => $nombreImagen,
            ':activo' => $activo,
            ':id' => $id
        ];
        
        $accion = 'actualizar';
        $mensaje = 'Producto actualizado correctamente';
        
    } else {
        // Crear nuevo producto - CAMPOS CORREGIDOS
        $sql = "
            INSERT INTO productos (
                nombre, descripcion, categoria_id, codigo_sku, precio_compra, 
                precio_venta, precio_base, stock_actual, stock, stock_minimo, stock_maximo, 
                unidad_medida, imagen, activo, eliminado
            ) VALUES (
                :nombre, :descripcion, :categoria_id, :codigo_sku, :precio_compra,
                :precio_venta, :precio_venta, :stock_actual, :stock_actual, :stock_minimo, :stock_maximo,
                :unidad_medida, :imagen, :activo, FALSE
            )
        ";
        
        $params = [
            ':nombre' => $nombre,
            ':descripcion' => $descripcion ?: null,
            ':categoria_id' => $categoria_id,
            ':codigo_sku' => $codigo_sku,
            ':precio_compra' => $precio_compra,
            ':precio_venta' => $precio_venta,
            ':stock_actual' => $stock_actual,
            ':stock_minimo' => $stock_minimo,
            ':stock_maximo' => $stock_maximo,
            ':unidad_medida' => $unidad_medida,
            ':imagen' => $nombreImagen,
            ':activo' => $activo
        ];
        
        $accion = 'crear';
        $mensaje = 'Producto creado correctamente';
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    // Obtener ID del producto
    if (!$esEdicion) {
        $productoId = $pdo->lastInsertId();
    }
    
    // Registrar movimiento de inventario si cambió el stock
    if ($esEdicion && $stock_actual != $stockAnterior) {
        $diferencia = $stock_actual - $stockAnterior;
        $motivo = $diferencia > 0 
            ? "Ajuste de inventario: incremento de " . abs($diferencia) . " unidades" 
            : "Ajuste de inventario: reducción de " . abs($diferencia) . " unidades";
        
        registrarMovimientoInventario(
            $pdo, 
            $productoId, 
            'ajuste', 
            $stock_actual, 
            $motivo, 
            getUserId()
        );
    } elseif (!$esEdicion && $stock_actual > 0) {
        // Registrar stock inicial para producto nuevo
        registrarMovimientoInventario(
            $pdo, 
            $productoId, 
            'entrada', 
            $stock_actual, 
            'Stock inicial del producto', 
            getUserId()
        );
    }
    
    $pdo->commit();
    
    // Log de auditoría
    error_log("Producto {$accion}do: ID {$productoId}, Nombre: {$nombre}, SKU: {$codigo_sku}, Usuario: " . ($_SESSION['username'] ?? 'unknown'));
    
    // Redirigir con mensaje de éxito
    header('Location: ' . url('pages/productos/index.php?success=') . urlencode($mensaje));
    exit;
    
} catch (PDOException $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    error_log("Error de base de datos al guardar producto: " . $e->getMessage());
    
    $errorMsg = 'Error de base de datos al guardar producto.';
    
    // Verificar errores específicos
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        if (strpos($e->getMessage(), 'codigo_sku') !== false) {
            $errorMsg = 'Ya existe un producto con ese código SKU.';
        } else {
            $errorMsg = 'Ya existe un producto con esos datos.';
        }
    } elseif (strpos($e->getMessage(), 'foreign key constraint') !== false) {
        $errorMsg = 'La categoría seleccionada no es válida.';
    }
    
    $redirectUrl = url('pages/productos/form.php') . ($esEdicion ? "?id=$id" : '') . '&error=' . urlencode($errorMsg);
    header('Location: ' . $redirectUrl);
    exit;
    
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    error_log("Error general al guardar producto: " . $e->getMessage());
    
    $redirectUrl = url('pages/productos/form.php') . ($esEdicion ? "?id=$id" : '') . '&error=' . urlencode($e->getMessage());
    header('Location: ' . $redirectUrl);
    exit;
}