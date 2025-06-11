<?php
/**
 * Archivo: forms/procesar_importacion.php
 * Función: Procesar importación de productos desde CSV
 * Seguridad: Solo admin, validación CSRF, procesamiento seguro de archivos
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

// Verificar que se subió un archivo
if (!isset($_FILES['archivo_csv']) || $_FILES['archivo_csv']['error'] !== UPLOAD_ERR_OK) {
    header('Location: ' . url('pages/productos/importar.php?error=') . urlencode('Error al subir el archivo CSV'));
    exit;
}

$pdo = getPDO();

// Obtener opciones de importación
$categoria_defecto = filter_input(INPUT_POST, 'categoria_defecto', FILTER_VALIDATE_INT);
$duplicados = $_POST['duplicados'] ?? 'omitir';
$estado_inicial = filter_input(INPUT_POST, 'estado_inicial', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
$vista_previa = isset($_POST['vista_previa']) && $_POST['vista_previa'] === '1';

// Validar opciones
$duplicados_validos = ['omitir', 'actualizar', 'crear'];
if (!in_array($duplicados, $duplicados_validos)) {
    $duplicados = 'omitir';
}

try {
    // Leer archivo CSV
    $archivo = $_FILES['archivo_csv']['tmp_name'];
    $nombreArchivo = $_FILES['archivo_csv']['name'];
    
    // Validar tipo de archivo
    $extension = strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION));
    if ($extension !== 'csv') {
        throw new Exception('El archivo debe tener extensión .csv');
    }
    
    // Validar tamaño (10MB máximo)
    if ($_FILES['archivo_csv']['size'] > 10 * 1024 * 1024) {
        throw new Exception('El archivo no puede ser mayor a 10MB');
    }
    
    // Leer contenido del CSV
    $csvData = [];
    $errores = [];
    $warnings = [];
    
    if (($handle = fopen($archivo, 'r')) !== FALSE) {
        // Detectar delimitador
        $firstLine = fgets($handle);
        rewind($handle);
        
        $delimiter = ',';
        if (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
            $delimiter = ';';
        }
        
        $lineNumber = 0;
        $headers = [];
        
        while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
            $lineNumber++;
            
            if ($lineNumber === 1) {
                // Procesar encabezados
                $headers = array_map('trim', array_map('strtolower', $data));
                
                // Validar encabezados obligatorios
                if (!in_array('nombre', $headers)) {
                    throw new Exception('El CSV debe tener una columna "nombre"');
                }
                if (!in_array('precio_venta', $headers)) {
                    throw new Exception('El CSV debe tener una columna "precio_venta"');
                }
                
                continue;
            }
            
            // Procesar fila de datos
            if (count($data) < count($headers)) {
                // Rellenar con valores vacíos si faltan columnas
                $data = array_pad($data, count($headers), '');
            }
            
            $row = array_combine($headers, array_map('trim', $data));
            
            // Validar fila
            $rowErrors = [];
            
            if (empty($row['nombre'])) {
                $rowErrors[] = 'Nombre es obligatorio';
            }
            
            if (empty($row['precio_venta']) || !is_numeric($row['precio_venta']) || floatval($row['precio_venta']) <= 0) {
                $rowErrors[] = 'Precio de venta debe ser un número mayor a 0';
            }
            
            if ($rowErrors) {
                $errores[] = "Línea $lineNumber: " . implode(', ', $rowErrors);
                continue;
            }
            
            // Agregar datos procesados
            $csvData[] = [
                'linea' => $lineNumber,
                'datos' => $row,
                'procesado' => false
            ];
        }
        
        fclose($handle);
    } else {
        throw new Exception('No se pudo leer el archivo CSV');
    }
    
    if (empty($csvData)) {
        throw new Exception('El archivo CSV está vacío o no contiene datos válidos');
    }
    
    // Si hay errores críticos, mostrarlos
    if (!empty($errores)) {
        $errorMsg = 'Errores en el archivo CSV: ' . implode('; ', array_slice($errores, 0, 5));
        if (count($errores) > 5) {
            $errorMsg .= ' y ' . (count($errores) - 5) . ' errores más';
        }
        header('Location: ' . url('pages/productos/importar.php?error=') . urlencode($errorMsg));
        exit;
    }
    
    // Obtener categorías para mapeo
    $stmt = $pdo->query("SELECT id, nombre FROM categorias WHERE activa = TRUE");
    $categorias = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $categorias_lower = array_change_key_case($categorias, CASE_LOWER);
    
    // Procesar datos y preparar para importación
    $productosParaImportar = [];
    $productosOmitidos = [];
    $productosActualizar = [];
    
    foreach ($csvData as &$item) {
        $row = $item['datos'];
        
        // Procesar categoría
        $categoria_id = null;
        if (!empty($row['categoria'])) {
            $categoria_nombre = strtolower(trim($row['categoria']));
            if (isset($categorias_lower[$categoria_nombre])) {
                $categoria_id = array_search($categoria_nombre, array_map('strtolower', $categorias));
            }
        }
        
        if (!$categoria_id && $categoria_defecto) {
            $categoria_id = $categoria_defecto;
        }
        
        if (!$categoria_id) {
            $warnings[] = "Línea {$item['linea']}: Categoría '{$row['categoria']}' no encontrada, se omite producto";
            continue;
        }
        
        // Verificar duplicados
        $stmt = $pdo->prepare("SELECT id, nombre FROM productos WHERE nombre = :nombre");
        $stmt->execute([':nombre' => $row['nombre']]);
        $productoExistente = $stmt->fetch();
        
        if ($productoExistente) {
            if ($duplicados === 'omitir') {
                $productosOmitidos[] = $row['nombre'];
                continue;
            } elseif ($duplicados === 'actualizar') {
                $productosActualizar[] = [
                    'id' => $productoExistente['id'],
                    'datos' => $row,
                    'categoria_id' => $categoria_id,
                    'linea' => $item['linea']
                ];
                continue;
            } elseif ($duplicados === 'crear') {
                // Modificar nombre para hacerlo único
                $contador = 1;
                $nombreOriginal = $row['nombre'];
                do {
                    $row['nombre'] = $nombreOriginal . " ($contador)";
                    $stmt->execute([':nombre' => $row['nombre']]);
                    $contador++;
                } while ($stmt->fetch());
            }
        }
        
        // Preparar datos para importación
        $producto = [
            'nombre' => $row['nombre'],
            'descripcion' => $row['descripcion'] ?? null,
            'categoria_id' => $categoria_id,
            'codigo_sku' => !empty($row['codigo_sku']) ? $row['codigo_sku'] : null,
            'precio_compra' => !empty($row['precio_compra']) ? floatval($row['precio_compra']) : 0.00,
            'precio_venta' => floatval($row['precio_venta']),
            'stock_actual' => !empty($row['stock_actual']) ? intval($row['stock_actual']) : 0,
            'stock_minimo' => !empty($row['stock_minimo']) ? intval($row['stock_minimo']) : 0,
            'stock_maximo' => !empty($row['stock_maximo']) ? intval($row['stock_maximo']) : null,
            'unidad_medida' => !empty($row['unidad_medida']) ? $row['unidad_medida'] : 'unidad',
            'activo' => $estado_inicial,
            'linea' => $item['linea']
        ];
        
        // Validar unidad de medida
        $unidades_validas = ['unidad', 'kg', 'gramo', 'litro', 'metro', 'caja', 'paquete'];
        if (!in_array($producto['unidad_medida'], $unidades_validas)) {
            $producto['unidad_medida'] = 'unidad';
            $warnings[] = "Línea {$item['linea']}: Unidad de medida no válida, se usará 'unidad'";
        }
        
        $productosParaImportar[] = $producto;
        $item['procesado'] = true;
    }
    
    // Si es vista previa, mostrar resultados y parar aquí
    if ($vista_previa) {
        session_start();
        $_SESSION['importacion_preview'] = [
            'productos_importar' => $productosParaImportar,
            'productos_actualizar' => $productosActualizar,
            'productos_omitidos' => $productosOmitidos,
            'warnings' => $warnings,
            'opciones' => [
                'categoria_defecto' => $categoria_defecto,
                'duplicados' => $duplicados,
                'estado_inicial' => $estado_inicial
            ]
        ];
        
        header('Location: ' . url('pages/productos/preview_importacion.php'));
        exit;
    }
    
    // Procesar importación real
    $pdo->beginTransaction();
    
    $importados = 0;
    $actualizados = 0;
    $erroresImportacion = [];
    
    // Importar productos nuevos
    foreach ($productosParaImportar as $producto) {
        try {
            // Generar SKU si no existe
            if (empty($producto['codigo_sku'])) {
                $producto['codigo_sku'] = generarSKU($pdo, $producto['nombre'], $producto['categoria_id']);
            }
            
            // Verificar SKU duplicado
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
            $erroresImportacion[] = "Línea {$producto['linea']}: " . $e->getMessage();
        }
    }
    
    // Actualizar productos existentes
    foreach ($productosActualizar as $item) {
        try {
            $producto = $item['datos'];
            $id = $item['id'];
            
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
                ':stock_actual' => !empty($producto['stock_actual']) ? intval($producto['stock_actual']) : 0,
                ':stock_minimo' => !empty($producto['stock_minimo']) ? intval($producto['stock_minimo']) : 0,
                ':stock_maximo' => !empty($producto['stock_maximo']) ? intval($producto['stock_maximo']) : null,
                ':unidad_medida' => !empty($producto['unidad_medida']) ? $producto['unidad_medida'] : 'unidad',
                ':activo' => $estado_inicial,
                ':id' => $id
            ]);
            
            $actualizados++;
            
        } catch (Exception $e) {
            $erroresImportacion[] = "Línea {$item['linea']}: " . $e->getMessage();
        }
    }
    
    $pdo->commit();
    
    // Preparar mensaje de resultado
    $mensaje = "Importación completada: $importados productos importados";
    if ($actualizados > 0) {
        $mensaje .= ", $actualizados productos actualizados";
    }
    if (!empty($productosOmitidos)) {
        $mensaje .= ", " . count($productosOmitidos) . " productos omitidos por duplicados";
    }
    if (!empty($erroresImportacion)) {
        $mensaje .= ". Errores: " . count($erroresImportacion);
    }
    
    // Log de auditoría
    error_log("Importación CSV completada: $importados importados, $actualizados actualizados, Usuario: " . ($_SESSION['username'] ?? 'unknown'));
    
    header('Location: ' . url('pages/productos/index.php?success=') . urlencode($mensaje));
    exit;
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error en importación CSV: " . $e->getMessage());
    header('Location: ' . url('pages/productos/importar.php?error=') . urlencode($e->getMessage()));
    exit;
}