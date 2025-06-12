<?php
/**
 * Archivo: test_soft_delete_clientes.php
 * Función: Probar que el soft delete de clientes funciona correctamente
 * Ejecutar desde: /tests/
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';
require_once '../includes/functions.php';

echo "<h1>🧪 Test: Soft Delete de Clientes</h1>\n";
echo "<p>Verificando que la funcionalidad de eliminación/restauración funciona correctamente...</p>\n";
echo "<hr>\n";

try {
    $pdo = getPDO();
    
    // ============================================================================
    // 1. VERIFICAR ESTRUCTURA DE TABLA
    // ============================================================================
    echo "<h2>📋 Verificando estructura de tabla clientes...</h2>\n";
    
    $stmt = $pdo->query("DESCRIBE clientes");
    $columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $columnas_requeridas = ['eliminado', 'fecha_eliminacion', 'eliminado_por'];
    $columnas_existentes = array_column($columnas, 'Field');
    
    foreach ($columnas_requeridas as $col) {
        if (in_array($col, $columnas_existentes)) {
            echo "<p>✅ <strong>$col</strong> - Columna existe</p>\n";
        } else {
            echo "<p>❌ <strong>$col</strong> - FALTA columna (ejecutar migration_soft_delete_clientes.sql)</p>\n";
        }
    }
    
    // ============================================================================
    // 2. VERIFICAR DATOS DE PRUEBA
    // ============================================================================
    echo "<h2>📊 Estadísticas actuales...</h2>\n";
    
    // Contar clientes activos
    $stmt = $pdo->query("SELECT COUNT(*) FROM clientes WHERE eliminado = FALSE OR eliminado IS NULL");
    $activos = $stmt->fetchColumn();
    
    // Contar clientes eliminados
    $stmt = $pdo->query("SELECT COUNT(*) FROM clientes WHERE eliminado = TRUE");
    $eliminados = $stmt->fetchColumn();
    
    // Total
    $stmt = $pdo->query("SELECT COUNT(*) FROM clientes");
    $total = $stmt->fetchColumn();
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr style='background-color: #f0f0f0;'><th style='padding: 8px;'>Estado</th><th style='padding: 8px;'>Cantidad</th></tr>\n";
    echo "<tr><td style='padding: 8px;'>✅ Clientes Activos</td><td style='padding: 8px;'><strong>$activos</strong></td></tr>\n";
    echo "<tr><td style='padding: 8px;'>🗑️ Clientes Eliminados</td><td style='padding: 8px;'><strong>$eliminados</strong></td></tr>\n";
    echo "<tr><td style='padding: 8px;'>📊 Total</td><td style='padding: 8px;'><strong>$total</strong></td></tr>\n";
    echo "</table>\n";
    
    // ============================================================================
    // 3. MOSTRAR CLIENTES ELIMINADOS (si los hay)
    // ============================================================================
    if ($eliminados > 0) {
        echo "<h2>🗑️ Clientes en papelera...</h2>\n";
        
        $stmt = $pdo->query("
            SELECT 
                c.id, c.nombre, c.email, c.eliminado, c.fecha_eliminacion,
                u.username as eliminado_por
            FROM clientes c
            LEFT JOIN usuarios u ON c.eliminado_por = u.id
            WHERE c.eliminado = TRUE
            ORDER BY c.fecha_eliminacion DESC
            LIMIT 10
        ");
        $clientesEliminados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; width: 100%;'>\n";
        echo "<tr style='background-color: #f0f0f0;'>\n";
        echo "<th style='padding: 8px;'>ID</th>\n";
        echo "<th style='padding: 8px;'>Nombre</th>\n";
        echo "<th style='padding: 8px;'>Email</th>\n";
        echo "<th style='padding: 8px;'>Eliminado</th>\n";
        echo "<th style='padding: 8px;'>Fecha Eliminación</th>\n";
        echo "<th style='padding: 8px;'>Por Usuario</th>\n";
        echo "</tr>\n";
        
        foreach ($clientesEliminados as $c) {
            $fecha = $c['fecha_eliminacion'] ? date('d/m/Y H:i', strtotime($c['fecha_eliminacion'])) : '-';
            echo "<tr>\n";
            echo "<td style='padding: 8px;'>{$c['id']}</td>\n";
            echo "<td style='padding: 8px;'>{$c['nombre']}</td>\n";
            echo "<td style='padding: 8px;'>{$c['email']}</td>\n";
            echo "<td style='padding: 8px;'>" . ($c['eliminado'] ? '🗑️ TRUE' : '✅ FALSE') . "</td>\n";
            echo "<td style='padding: 8px;'>$fecha</td>\n";
            echo "<td style='padding: 8px;'>" . ($c['eliminado_por'] ?: 'Sistema') . "</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
    
    // ============================================================================
    // 4. PROBAR FUNCIONALIDAD (solo mostrar SQL)
    // ============================================================================
    echo "<h2>🔧 Consultas SQL para probar...</h2>\n";
    
    echo "<h3>Para ELIMINAR un cliente (soft delete):</h3>\n";
    echo "<pre style='background-color: #f8f9fa; padding: 10px; border-radius: 5px;'>";
    echo "UPDATE clientes SET \n";
    echo "    eliminado = TRUE,\n";
    echo "    fecha_eliminacion = CURRENT_TIMESTAMP,\n";
    echo "    eliminado_por = [ID_USUARIO]\n";
    echo "WHERE id = [ID_CLIENTE] AND eliminado = FALSE;";
    echo "</pre>\n";
    
    echo "<h3>Para RESTAURAR un cliente:</h3>\n";
    echo "<pre style='background-color: #f8f9fa; padding: 10px; border-radius: 5px;'>";
    echo "UPDATE clientes SET \n";
    echo "    eliminado = FALSE,\n";
    echo "    fecha_eliminacion = NULL,\n";
    echo "    eliminado_por = NULL\n";
    echo "WHERE id = [ID_CLIENTE] AND eliminado = TRUE;";
    echo "</pre>\n";
    
    echo "<h3>Para VER clientes eliminados:</h3>\n";
    echo "<pre style='background-color: #f8f9fa; padding: 10px; border-radius: 5px;'>";
    echo "SELECT c.*, u.username as eliminado_por\n";
    echo "FROM clientes c\n";
    echo "LEFT JOIN usuarios u ON c.eliminado_por = u.id\n";
    echo "WHERE c.eliminado = TRUE\n";
    echo "ORDER BY c.fecha_eliminacion DESC;";
    echo "</pre>\n";
    
    // ============================================================================
    // 5. VERIFICAR ARCHIVOS
    // ============================================================================
    echo "<h2>📁 Verificando archivos...</h2>\n";
    
    $archivos = [
        '../pages/clientes/index.php' => 'Lista principal de clientes',
        '../pages/clientes/eliminar.php' => 'Soft delete de clientes',
        '../pages/clientes/restaurar.php' => 'Restaurar clientes eliminados'
    ];
    
    foreach ($archivos as $archivo => $descripcion) {
        if (file_exists($archivo)) {
            $tamaño = number_format(filesize($archivo) / 1024, 1);
            echo "<p>✅ <strong>$archivo</strong> - $descripcion ({$tamaño}KB)</p>\n";
        } else {
            echo "<p>❌ <strong>$archivo</strong> - FALTA: $descripcion</p>\n";
        }
    }
    
    // ============================================================================
    // 6. INSTRUCCIONES FINALES
    // ============================================================================
    echo "<hr>\n";
    echo "<h2>🎯 Instrucciones de uso:</h2>\n";
    
    echo "<div style='background-color: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 10px 0;'>\n";
    echo "<h3>✅ Para usar el soft delete:</h3>\n";
    echo "<ol>\n";
    echo "<li><strong>Eliminar cliente:</strong> Ir a Lista de Clientes → Click 'Eliminar' → Confirmar</li>\n";
    echo "<li><strong>Ver eliminados:</strong> Ir a Lista de Clientes → Click 'Ver Eliminados'</li>\n";
    echo "<li><strong>Restaurar cliente:</strong> En vista eliminados → Click 'Restaurar' → Confirmar</li>\n";
    echo "</ol>\n";
    echo "</div>\n";
    
    echo "<div style='background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 10px 0;'>\n";
    echo "<h3>⚠️ Importante:</h3>\n";
    echo "<ul>\n";
    echo "<li>Solo <strong>administradores</strong> pueden eliminar y restaurar clientes</li>\n";
    echo "<li>Los clientes <strong>no se borran físicamente</strong>, solo se marcan como eliminado=TRUE</li>\n";
    echo "<li>Al restaurar, el cliente vuelve a estar <strong>activo y visible</strong></li>\n";
    echo "<li>Se registra <strong>auditoría</strong> de todas las operaciones</li>\n";
    echo "</ul>\n";
    echo "</div>\n";
    
    echo "<p><a href='../pages/clientes/index.php' target='_blank' style='background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>👥 Ir a Lista de Clientes</a>";
    echo "<a href='../pages/clientes/index.php?eliminados=1' target='_blank' style='background-color: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🗑️ Ver Papelera</a></p>\n";
    
} catch (Exception $e) {
    echo "<div style='background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; color: #721c24;'>\n";
    echo "<h3>❌ Error en test:</h3>\n";
    echo "<p><strong>Mensaje:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p><strong>Archivo:</strong> " . $e->getFile() . " (línea " . $e->getLine() . ")</p>\n";
    echo "</div>\n";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    background-color: #f8f9fa;
}
h1, h2, h3 { color: #2c3e50; }
pre { overflow-x: auto; }
</style>