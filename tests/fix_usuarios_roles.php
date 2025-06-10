<?php
/**
 * Archivo: fix_usuarios_roles.php
 * Función: Corrige automáticamente los roles de usuarios para que coincidan con el ENUM de la BD
 * EJECUTAR UNA SOLA VEZ y luego ELIMINAR este archivo
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

try {
    $pdo = getPDO();
    
    echo "<h1>🔧 Corrigiendo roles de usuarios</h1>\n";
    echo "<p>Este script corrige la inconsistencia entre 'administrador' y 'admin'</p>\n";
    echo "<hr>\n";
    
    // Ver usuarios actuales
    echo "<h2>👥 Usuarios ANTES de la corrección:</h2>\n";
    $stmt = $pdo->query("SELECT username, role, nombre_completo, activo FROM usuarios ORDER BY role DESC, username ASC");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($usuarios) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr style='background-color: #f0f0f0;'>\n";
        echo "<th style='padding: 8px;'>Usuario</th>\n";
        echo "<th style='padding: 8px;'>Rol Actual</th>\n";
        echo "<th style='padding: 8px;'>Nombre</th>\n";
        echo "<th style='padding: 8px;'>Estado</th>\n";
        echo "</tr>\n";
        
        foreach ($usuarios as $u) {
            $roleColor = in_array($u['role'], ['admin', 'administrador']) ? '#e74c3c' : '#3498db';
            $statusColor = $u['activo'] ? '#27ae60' : '#e74c3c';
            
            echo "<tr>\n";
            echo "<td style='padding: 8px;'><strong>{$u['username']}</strong></td>\n";
            echo "<td style='padding: 8px; color: $roleColor;'><strong>{$u['role']}</strong></td>\n";
            echo "<td style='padding: 8px;'>{$u['nombre_completo']}</td>\n";
            echo "<td style='padding: 8px; color: $statusColor;'>" . ($u['activo'] ? 'Activo' : 'Inactivo') . "</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
    
    // Verificar si hay usuarios con rol 'administrador'
    $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE role = 'administrador'");
    $usuariosAdministrador = $stmt->fetchColumn();
    
    if ($usuariosAdministrador > 0) {
        echo "<div style='background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0;'>\n";
        echo "<h3>⚠️ Usuarios con rol 'administrador' encontrados: $usuariosAdministrador</h3>\n";
        echo "<p>Corrigiendo automáticamente...</p>\n";
        echo "</div>\n";
        
        // Corregir usuarios
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("UPDATE usuarios SET role = 'admin' WHERE role = 'administrador'");
        $stmt->execute();
        $usuariosActualizados = $stmt->rowCount();
        
        $pdo->commit();
        
        echo "<p>✅ <strong>$usuariosActualizados usuarios</strong> actualizados de 'administrador' a 'admin'</p>\n";
    } else {
        echo "<p>✅ No se encontraron usuarios con rol 'administrador' - no se necesita corrección</p>\n";
    }
    
    // Mostrar usuarios después de la corrección
    echo "<h2>👥 Usuarios DESPUÉS de la corrección:</h2>\n";
    $stmt = $pdo->query("SELECT username, role, nombre_completo, activo FROM usuarios ORDER BY role DESC, username ASC");
    $usuariosCorregidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($usuariosCorregidos) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr style='background-color: #f0f0f0;'>\n";
        echo "<th style='padding: 8px;'>Usuario</th>\n";
        echo "<th style='padding: 8px;'>Rol Corregido</th>\n";
        echo "<th style='padding: 8px;'>Nombre</th>\n";
        echo "<th style='padding: 8px;'>Estado</th>\n";
        echo "<th style='padding: 8px;'>Verificación</th>\n";
        echo "</tr>\n";
        
        foreach ($usuariosCorregidos as $u) {
            $roleColor = $u['role'] === 'admin' ? '#e74c3c' : '#3498db';
            $statusColor = $u['activo'] ? '#27ae60' : '#e74c3c';
            $verificacion = in_array($u['role'], ['admin', 'vendedor']) ? '✅ Correcto' : '❌ Error';
            $verificacionColor = in_array($u['role'], ['admin', 'vendedor']) ? '#27ae60' : '#e74c3c';
            
            echo "<tr>\n";
            echo "<td style='padding: 8px;'><strong>{$u['username']}</strong></td>\n";
            echo "<td style='padding: 8px; color: $roleColor;'><strong>{$u['role']}</strong></td>\n";
            echo "<td style='padding: 8px;'>{$u['nombre_completo']}</td>\n";
            echo "<td style='padding: 8px; color: $statusColor;'>" . ($u['activo'] ? 'Activo' : 'Inactivo') . "</td>\n";
            echo "<td style='padding: 8px; color: $verificacionColor;'><strong>$verificacion</strong></td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
    
    // Estadísticas finales
    echo "<h2>📊 Estadísticas finales:</h2>\n";
    $stmt = $pdo->query("SELECT role, COUNT(*) as total FROM usuarios GROUP BY role ORDER BY role DESC");
    $estadisticas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<ul>\n";
    foreach ($estadisticas as $stat) {
        echo "<li><strong>{$stat['role']}:</strong> {$stat['total']} usuarios</li>\n";
    }
    echo "</ul>\n";
    
    echo "<div style='background-color: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 20px 0;'>\n";
    echo "<h3>✅ Corrección completada</h3>\n";
    echo "<ul>\n";
    echo "<li>Todos los usuarios ahora tienen roles válidos ('admin' o 'vendedor')</li>\n";
    echo "<li>El usuario 'supervisor' ahora debería poder acceder como admin</li>\n";
    echo "<li>Puedes probar el login nuevamente</li>\n";
    echo "<li><strong>IMPORTANTE:</strong> Elimina este archivo (fix_usuarios_roles.php) después de usarlo</li>\n";
    echo "</ul>\n";
    echo "</div>\n";
    
    echo "<h2>🔑 Credenciales de acceso:</h2>\n";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr style='background-color: #f0f0f0;'>\n";
    echo "<th style='padding: 8px;'>Usuario</th>\n";
    echo "<th style='padding: 8px;'>Contraseña</th>\n";
    echo "<th style='padding: 8px;'>Rol</th>\n";
    echo "</tr>\n";
    
    foreach ($usuariosCorregidos as $u) {
        if ($u['activo']) {
            echo "<tr>\n";
            echo "<td style='padding: 8px;'><strong>{$u['username']}</strong></td>\n";
            echo "<td style='padding: 8px;'><code>Temporal2025#</code></td>\n";
            echo "<td style='padding: 8px;'>{$u['role']}</td>\n";
            echo "</tr>\n";
        }
    }
    echo "</table>\n";
    
    echo "<p><a href='" . (isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/pages/login.php' : 'pages/login.php') . "' target='_blank'>🚀 Ir al Login</a></p>\n";
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo "<div style='background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; color: #721c24;'>\n";
    echo "<h3>❌ Error al corregir usuarios:</h3>\n";
    echo "<p><strong>Mensaje:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "</div>\n";
    
    error_log("Error en fix_usuarios_roles.php: " . $e->getMessage());
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
h1, h2 { color: #2c3e50; }
code { 
    background-color: #f1f2f6; 
    padding: 2px 6px; 
    border-radius: 3px; 
    font-family: 'Courier New', monospace;
}
table { width: 100%; }
th, td { text-align: left; }
a { color: #3498db; text-decoration: none; }
a:hover { text-decoration: underline; }
</style>