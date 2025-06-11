<?php
/**
 * Archivo: pages/categorias/eliminar.php
 * Función: Eliminar categoría del sistema
 * Seguridad: Solo admin, validación CSRF, verificar dependencias
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Solo admin puede eliminar categorías
requireRole(['admin']);

// Solo procesar GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Location: ' . url('pages/categorias/index.php?error=method'));
    exit;
}

// Validar token CSRF
$token = $_GET['csrf_token'] ?? '';
if (!validate_csrf_token($token)) {
    header('Location: ' . url('pages/categorias/index.php?error=csrf'));
    exit;
}

// Obtener ID de la categoría
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: ' . url('pages/categorias/index.php?error=') . urlencode('ID de categoría inválido'));
    exit;
}

$pdo = getPDO();

try {
    // Verificar que la categoría existe
    $categoria = getCategoriaById($pdo, $id);
    if (!$categoria) {
        header('Location: ' . url('pages/categorias/index.php?error=') . urlencode('Categoría no encontrada'));
        exit;
    }
    
    // Verificar que no tenga productos asociados
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM productos WHERE categoria_id = :id");
    $stmt->execute([':id' => $id]);
    $totalProductos = $stmt->fetchColumn();
    
    if ($totalProductos > 0) {
        header('Location: ' . url('pages/categorias/index.php?error=') . urlencode("No se puede eliminar la categoría '{$categoria['nombre']}' porque tiene {$totalProductos} producto(s) asociado(s)."));
        exit;
    }
    
    // Verificar que no sea la categoría "General" (protección adicional)
    if (strtolower($categoria['nombre']) === 'general') {
        header('Location: ' . url('pages/categorias/index.php?error=') . urlencode('No se puede eliminar la categoría "General" porque es la categoría por defecto del sistema.'));
        exit;
    }
    
    $pdo->beginTransaction();
    
    // Eliminar la categoría
    $stmt = $pdo->prepare("DELETE FROM categorias WHERE id = :id");
    $stmt->execute([':id' => $id]);
    
    $pdo->commit();
    
    // Log de auditoría
    error_log("Categoría eliminada: ID {$id}, Nombre: {$categoria['nombre']}, Usuario: " . ($_SESSION['username'] ?? 'unknown'));
    
    // Redirigir con mensaje de éxito
    header('Location: ' . url('pages/categorias/index.php?success=') . urlencode("Categoría '{$categoria['nombre']}' eliminada correctamente"));
    exit;
    
} catch (PDOException $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    error_log("Error de base de datos al eliminar categoría: " . $e->getMessage());
    
    $errorMsg = 'Error de base de datos al eliminar categoría.';
    
    // Verificar errores específicos
    if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
        $errorMsg = 'No se puede eliminar la categoría porque tiene productos asociados.';
    }
    
    header('Location: ' . url('pages/categorias/index.php?error=') . urlencode($errorMsg));
    exit;
    
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    error_log("Error general al eliminar categoría: " . $e->getMessage());
    header('Location: ' . url('pages/categorias/index.php?error=') . urlencode('Error inesperado al eliminar categoría.'));
    exit;
}