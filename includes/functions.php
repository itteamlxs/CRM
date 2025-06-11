<?php
/**
 * Archivo: functions.php
 * Funciones helper: Sanitización, validación, helpers generales, conexión PDO.
 */

declare(strict_types=1);

// Sanitizar texto simple para evitar XSS
function sanitizeText(string $text): string
{
    return htmlspecialchars(trim($text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Validar email
function validateEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Validar entero positivo
function validatePositiveInt($value): bool
{
    return filter_var($value, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]) !== false;
}

// Validar float positivo (precio, etc)
function validatePositiveFloat($value): bool
{
    return filter_var($value, FILTER_VALIDATE_FLOAT) !== false && floatval($value) >= 0;
}

// Validar texto con longitud máxima
function validateMaxLength(string $text, int $max): bool
{
    return mb_strlen($text) <= $max;
}

/**
 * Obtener conexión PDO global o crear una nueva
 * @return PDO Conexión a la base de datos
 */
function getPDO(): PDO
{
    static $pdo = null;
    
    if ($pdo === null) {
        // Cargar variables .env
        $envFile = __DIR__ . '/../config/.env';
        if (!file_exists($envFile)) {
            die('Archivo .env no encontrado.');
        }
        $env = parse_ini_file($envFile, false, INI_SCANNER_TYPED);
        if (!$env) {
            die('Error al leer archivo .env');
        }

        $host = $env['DB_HOST'] ?? 'localhost';
        $dbname = $env['DB_NAME'] ?? 'crm_db';
        $user = $env['DB_USER'] ?? 'root';
        $pass = $env['DB_PASS'] ?? '';

        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            die('Error de conexión a la base de datos.');
        }
    }
    
    return $pdo;
}

/**
 * Generar token CSRF si no existe en sesión
 * @return string Token CSRF
 */
function generate_csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validar token CSRF enviado en formulario
 * @param string|null $token Token a validar
 * @return bool True si es válido
 */
function validate_csrf_token(?string $token): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Obtener la URL base del proyecto automáticamente
 * Detecta si está en subdirectorio o raíz
 * @return string Base URL sin barra final
 */
function getBaseUrl(): string
{
    static $baseUrl = null;
    
    if ($baseUrl === null) {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        
        // Buscar la ruta del proyecto detectando where están los archivos
        $scriptDir = dirname($scriptName);
        
        // Si el script está en subdirectorios (como /forms/ o /pages/), subir hasta encontrar la raíz
        $pathParts = explode('/', trim($scriptDir, '/'));
        $baseParts = [];
        
        // Buscar hasta encontrar la raíz del proyecto (donde están las carpetas principales)
        foreach ($pathParts as $part) {
            if (in_array($part, ['forms', 'pages', 'includes', 'lang', 'config', 'assets'])) {
                break;
            }
            if ($part !== '') {
                $baseParts[] = $part;
            }
        }
        
        $baseUrl = '/' . implode('/', $baseParts);
        if ($baseUrl === '/') {
            $baseUrl = '';
        }
    }
    
    return $baseUrl;
}

/**
 * Generar URL completa para el proyecto
 * @param string $path Ruta relativa (ej: 'pages/dashboard.php')
 * @return string URL completa
 */
function url(string $path): string
{
    $base = getBaseUrl();
    $path = ltrim($path, '/');
    return $base . '/' . $path;
}

// ============================================================================
// FUNCIONES PARA MÓDULO DE PRODUCTOS
// ============================================================================

/**
 * Obtener todas las categorías activas
 * @param PDO $pdo Conexión a base de datos
 * @return array Lista de categorías
 */
function getCategorias(PDO $pdo): array
{
    $stmt = $pdo->prepare("SELECT id, nombre, descripcion FROM categorias WHERE activa = TRUE ORDER BY nombre ASC");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtener categoría por ID
 * @param PDO $pdo Conexión a base de datos
 * @param int $id ID de la categoría
 * @return array|null Datos de la categoría o null si no existe
 */
function getCategoriaById(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM categorias WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ?: null;
}

/**
 * Obtener producto por ID con información de categoría
 * @param PDO $pdo Conexión a base de datos  
 * @param int $id ID del producto
 * @return array|null Datos del producto o null si no existe
 */
function getProductoById(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM vista_productos WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ?: null;
}

/**
 * Generar código SKU único para producto
 * @param PDO $pdo Conexión a base de datos
 * @param string $nombre Nombre del producto
 * @param int $categoriaId ID de la categoría
 * @return string Código SKU único
 */
function generarSKU(PDO $pdo, string $nombre, int $categoriaId): string
{
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
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM productos WHERE codigo_sku = :sku");
        $stmt->execute([':sku' => $sku]);
        $existe = $stmt->fetchColumn() > 0;
        $contador++;
    } while ($existe && $contador <= 999);
    
    return $sku;
}

/**
 * Registrar movimiento de inventario
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
        $stmt = $pdo->prepare("SELECT stock_actual FROM productos WHERE id = :id");
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
        
        // Actualizar stock en productos
        $stmt = $pdo->prepare("UPDATE productos SET stock_actual = :stock WHERE id = :id");
        $stmt->execute([':stock' => $nuevoStock, ':id' => $productoId]);
        
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error en movimiento de inventario: " . $e->getMessage());
        return false;
    }
}

/**
 * Subir imagen de producto
 * @param array $archivo Archivo $_FILES
 * @param string $nombreProducto Nombre del producto para el archivo
 * @return string|null Nombre del archivo subido o null si hay error
 */
function subirImagenProducto(array $archivo, string $nombreProducto): ?string
{
    if (!isset($archivo['tmp_name']) || $archivo['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    // Validar tipo de archivo
    $tiposPermitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($archivo['type'], $tiposPermitidos)) {
        return null;
    }
    
    // Validar tamaño (máximo 5MB)
    if ($archivo['size'] > 5 * 1024 * 1024) {
        return null;
    }
    
    // Crear directorio si no existe
    $directorioDestino = __DIR__ . '/../uploads/productos/';
    if (!file_exists($directorioDestino)) {
        mkdir($directorioDestino, 0755, true);
    }
    
    // Generar nombre único para el archivo
    $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
    $nombreArchivo = 'producto_' . date('Y-m-d_H-i-s') . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $nombreProducto) . '.' . $extension;
    $rutaDestino = $directorioDestino . $nombreArchivo;
    
    // Mover archivo
    if (move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
        return $nombreArchivo;
    }
    
    return null;
}

/**
 * Eliminar imagen de producto
 * @param string $nombreArchivo Nombre del archivo a eliminar
 * @return bool True si se eliminó correctamente
 */
function eliminarImagenProducto(string $nombreArchivo): bool
{
    $rutaArchivo = __DIR__ . '/../uploads/productos/' . $nombreArchivo;
    if (file_exists($rutaArchivo)) {
        return unlink($rutaArchivo);
    }
    return true; // Si no existe, consideramos que ya está "eliminado"
}

/**
 * Obtener URL de imagen de producto
 * @param string|null $nombreArchivo Nombre del archivo de imagen
 * @return string URL de la imagen o imagen por defecto
 */
function getImagenProductoUrl(?string $nombreArchivo): string
{
    if (empty($nombreArchivo)) {
        return url('assets/images/producto-default.png');
    }
    
    $rutaArchivo = __DIR__ . '/../uploads/productos/' . $nombreArchivo;
    if (file_exists($rutaArchivo)) {
        return url('uploads/productos/' . $nombreArchivo);
    }
    
    return url('assets/images/producto-default.png');
}

/**
 * Validar datos de producto
 * @param array $datos Datos del producto a validar
 * @return array Array de errores (vacío si no hay errores)
 */
function validarDatosProducto(array $datos): array
{
    $errores = [];
    
    if (empty($datos['nombre'])) {
        $errores[] = 'El nombre del producto es obligatorio';
    } elseif (strlen($datos['nombre']) > 200) {
        $errores[] = 'El nombre no puede exceder 200 caracteres';
    }
    
    if (empty($datos['categoria_id']) || !is_numeric($datos['categoria_id'])) {
        $errores[] = 'Debe seleccionar una categoría válida';
    }
    
    if (empty($datos['precio_venta']) || !is_numeric($datos['precio_venta']) || $datos['precio_venta'] <= 0) {
        $errores[] = 'El precio de venta debe ser un número mayor a 0';
    }
    
    if (isset($datos['precio_compra']) && !empty($datos['precio_compra']) && (!is_numeric($datos['precio_compra']) || $datos['precio_compra'] < 0)) {
        $errores[] = 'El precio de compra debe ser un número mayor o igual a 0';
    }
    
    if (isset($datos['stock_actual']) && (!is_numeric($datos['stock_actual']) || $datos['stock_actual'] < 0)) {
        $errores[] = 'El stock actual debe ser un número mayor o igual a 0';
    }
    
    if (isset($datos['stock_minimo']) && (!is_numeric($datos['stock_minimo']) || $datos['stock_minimo'] < 0)) {
        $errores[] = 'El stock mínimo debe ser un número mayor o igual a 0';
    }
    
    return $errores;
}