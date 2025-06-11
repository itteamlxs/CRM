<?php
/**
 * Archivo: pages/clientes/buscar_ajax.php
 * Función: Endpoint AJAX para búsqueda en tiempo real de clientes
 * Seguridad: Validación de sesión, sanitización, límite de resultados
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Solo permitir AJAX requests
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    http_response_code(400);
    exit('Bad Request');
}

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Verificar permisos
requireRole(['admin', 'vendedor']);

// Obtener término de búsqueda
$query = trim($_GET['q'] ?? '');

// Validar término de búsqueda
if (strlen($query) < 2) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Mínimo 2 caracteres para buscar',
        'results' => []
    ]);
    exit;
}

if (strlen($query) > 100) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Término de búsqueda demasiado largo',
        'results' => []
    ]);
    exit;
}

try {
    $pdo = getPDO();
    
    // Búsqueda con LIMIT para performance y FILTRO de eliminados
    $sql = "
        SELECT 
            id, 
            nombre, 
            email, 
            telefono, 
            estado,
            CASE 
                WHEN nombre LIKE :exact_match THEN 1
                WHEN nombre LIKE :starts_with THEN 2  
                WHEN email LIKE :starts_with_email THEN 3
                ELSE 4
            END as relevance
        FROM clientes 
        WHERE 
            (nombre LIKE :search OR email LIKE :search OR telefono LIKE :search)
            AND estado = 'activo'
            AND eliminado = FALSE
        ORDER BY relevance ASC, nombre ASC 
        LIMIT 10
    ";
    
    $searchTerm = "%$query%";
    $exactMatch = "$query%";
    $startsWithEmail = "$query%";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':search' => $searchTerm,
        ':exact_match' => $exactMatch,
        ':starts_with' => $exactMatch,
        ':starts_with_email' => $startsWithEmail
    ]);
    
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear resultados para el frontend
    $results = [];
    foreach ($clientes as $cliente) {
        // Resaltar el término de búsqueda
        $nombreHighlight = highlightSearchTerm($cliente['nombre'], $query);
        $emailHighlight = highlightSearchTerm($cliente['email'], $query);
        
        $results[] = [
            'id' => (int)$cliente['id'],
            'nombre' => $cliente['nombre'],
            'nombre_highlight' => $nombreHighlight,
            'email' => $cliente['email'],
            'email_highlight' => $emailHighlight,
            'telefono' => $cliente['telefono'],
            'estado' => $cliente['estado'],
            'url_editar' => url('forms/form_cliente.php?id=' . $cliente['id'])
        ];
    }
    
    // Respuesta JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'query' => $query,
        'count' => count($results),
        'results' => $results,
        'message' => count($results) > 0 
            ? count($results) . ' cliente(s) encontrado(s)' 
            : 'No se encontraron clientes'
    ]);

} catch (PDOException $e) {
    error_log("Error búsqueda AJAX clientes: " . $e->getMessage());
    
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error en la búsqueda',
        'results' => []
    ]);
}

/**
 * Resalta el término de búsqueda en el texto
 */
function highlightSearchTerm(string $text, string $term): string
{
    if (empty($term)) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
    
    $highlightedText = preg_replace(
        '/(' . preg_quote($term, '/') . ')/i',
        '<mark class="bg-yellow-200 dark:bg-yellow-600">$1</mark>',
        htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
    );
    
    return $highlightedText ?: htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}