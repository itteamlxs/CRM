<?php
/**
 * Archivo: crear_usuarios_test.php
 * Función: Crea usuarios de prueba con diferentes roles para testing.
 * Seguridad: Solo ejecutar en entorno de desarrollo.
 * IMPORTANTE: Eliminar este archivo después de usar.
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

try {
    $pdo = getPDO();
    
    // Contraseña común para todos los usuarios de prueba
    $password_plain = 'Temporal2025#';
    $password_hash = password_hash($password_plain, PASSWORD_DEFAULT);
    
    // Usuarios a crear con diferentes roles
    $usuarios = [
        [
            'username' => 'admin',
            'email' => 'admin@crm.local',
            'role' => 'admin',
            'nombre_completo' => 'Administrador Principal',
            'descripcion' => 'Usuario administrador con acceso completo'
        ],
        [
            'username' => 'vendedor1',
            'email' => 'vendedor1@crm.local', 
            'role' => 'vendedor',
            'nombre_completo' => 'Juan Pérez Vendedor',
            'descripcion' => 'Usuario vendedor de la sucursal Norte'
        ],
        [
            'username' => 'vendedor2',
            'email' => 'vendedor2@crm.local',
            'role' => 'vendedor', 
            'nombre_completo' => 'María González Vendedor',
            'descripcion' => 'Usuario vendedor de la sucursal Sur'
        ],
        [
            'username' => 'supervisor',
            'email' => 'supervisor@crm.local',
            'role' => 'admin',
            'nombre_completo' => 'Carlos López Supervisor',
            'descripcion' => 'Usuario supervisor con permisos administrativos'
        ]
    ];
    
    echo "<h1>🚀 Creando usuarios de prueba para CRM</h1>\n";
    echo "<p><strong>Contraseña para todos:</strong> <code>$password_plain</code></p>\n";
    echo "<hr>\n";
    
    $pdo->beginTransaction();
    
    $usuariosCreados = 0;
    $usuariosExistentes = 0;
    
    foreach ($usuarios as $usuario) {
        // Verificar si el usuario ya existe (por username o email)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE username = :username OR email = :email");
        $stmt->execute([
            ':username' => $usuario['username'],
            ':email' => $usuario['email']
        ]);
        
        if ($stmt->fetchColumn() > 0) {
            echo "<p>⚠️  <strong>{$usuario['username']}</strong> ({$usuario['role']}) - Ya existe, saltando...</p>\n";
            $usuariosExistentes++;
            continue;
        }
        
        // Insertar nuevo usuario
        $stmt = $pdo->prepare("
            INSERT INTO usuarios (username, password_hash, email, role, nombre_completo, activo) 
            VALUES (:username, :password_hash, :email, :role, :nombre_completo, 1)
        ");
        
        $stmt->execute([
            ':username' => $usuario['username'],
            ':password_hash' => $password_hash,
            ':email' => $usuario['email'],
            ':role' => $usuario['role'],
            ':nombre_completo' => $usuario['nombre_completo']
        ]);
        
        echo "<p>✅ <strong>{$usuario['username']}</strong> ({$usuario['role']}) - {$usuario['descripcion']}</p>\n";
        $usuariosCreados++;
    }
    
    $pdo->commit();
    
    echo "<hr>\n";
    echo "<h2>📊 Resumen:</h2>\n";
    echo "<ul>\n";
    echo "<li><strong>Usuarios creados:</strong> $usuariosCreados</li>\n";
    echo "<li><strong>Usuarios que ya existían:</strong> $usuariosExistentes</li>\n";
    echo "<li><strong>Total procesados:</strong> " . count($usuarios) . "</li>\n";
    echo "</ul>\n";
    
    if ($usuariosCreados > 0) {
        echo "\n<h2>🎯 Cómo usar:</h2>\n";
        echo "<ol>\n";
        echo "<li>Ve a: <a href='" . (isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/pages/login.php' : 'pages/login.php') . "' target='_blank'>Login del CRM</a></li>\n";
        echo "<li>Usa cualquiera de estos usuarios:</li>\n";
        echo "</ol>\n";
        
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr style='background-color: #f0f0f0;'>\n";
        echo "<th style='padding: 8px;'>Usuario</th>\n";
        echo "<th style='padding: 8px;'>Rol</th>\n";
        echo "<th style='padding: 8px;'>Descripción</th>\n";
        echo "<th style='padding: 8px;'>Contraseña</th>\n";
        echo "</tr>\n";
        
        foreach ($usuarios as $usuario) {
            $roleColor = $usuario['role'] === 'admin' ? '#e74c3c' : '#3498db';
            echo "<tr>\n";
            echo "<td style='padding: 8px;'><strong>{$usuario['username']}</strong></td>\n";
            echo "<td style='padding: 8px; color: $roleColor;'><strong>{$usuario['role']}</strong></td>\n";
            echo "<td style='padding: 8px;'>{$usuario['descripcion']}</td>\n";
            echo "<td style='padding: 8px;'><code>$password_plain</code></td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
    
    echo "\n<h2>🔒 Permisos por rol:</h2>\n";
    echo "<ul>\n";
    echo "<li><strong>Admin:</strong> Acceso completo - puede gestionar usuarios, configuración, ver todo</li>\n";
    echo "<li><strong>Vendedor:</strong> Acceso limitado - puede gestionar clientes, productos, cotizaciones y ventas</li>\n";
    echo "</ul>\n";
    
    echo "\n<div style='background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0;'>\n";
    echo "<h3>⚠️ IMPORTANTE - Seguridad:</h3>\n";
    echo "<ul>\n";
    echo "<li><strong>Elimina este archivo</strong> (crear_usuarios_test.php) después de crear los usuarios</li>\n";
    echo "<li>Cambia las contraseñas de los usuarios en producción</li>\n";
    echo "<li>Estos usuarios son solo para pruebas y desarrollo</li>\n";
    echo "</ul>\n";
    echo "</div>\n";
    
    // Mostrar usuarios existentes en la base de datos
    echo "\n<h2>👥 Usuarios actuales en la base de datos:</h2>\n";
    $stmt = $pdo->query("SELECT username, email, role, nombre_completo, activo, fecha_creacion FROM usuarios ORDER BY role DESC, username ASC");
    $usuariosDB = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($usuariosDB) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; width: 100%;'>\n";
        echo "<tr style='background-color: #f0f0f0;'>\n";
        echo "<th style='padding: 8px;'>Usuario</th>\n";
        echo "<th style='padding: 8px;'>Email</th>\n";
        echo "<th style='padding: 8px;'>Rol</th>\n";
        echo "<th style='padding: 8px;'>Nombre Completo</th>\n";
        echo "<th style='padding: 8px;'>Estado</th>\n";
        echo "<th style='padding: 8px;'>Creado</th>\n";
        echo "</tr>\n";
        
        foreach ($usuariosDB as $u) {
            $roleColor = $u['role'] === 'admin' ? '#e74c3c' : '#3498db';
            $statusColor = $u['activo'] ? '#27ae60' : '#e74c3c';
            $statusText = $u['activo'] ? 'Activo' : 'Inactivo';
            
            echo "<tr>\n";
            echo "<td style='padding: 8px;'><strong>{$u['username']}</strong></td>\n";
            echo "<td style='padding: 8px;'>{$u['email']}</td>\n";
            echo "<td style='padding: 8px; color: $roleColor;'><strong>{$u['role']}</strong></td>\n";
            echo "<td style='padding: 8px;'>{$u['nombre_completo']}</td>\n";
            echo "<td style='padding: 8px; color: $statusColor;'><strong>$statusText</strong></td>\n";
            echo "<td style='padding: 8px;'>" . date('d/m/Y H:i', strtotime($u['fecha_creacion'])) . "</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo "<div style='background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; color: #721c24;'>\n";
    echo "<h3>❌ Error al crear usuarios:</h3>\n";
    echo "<p><strong>Mensaje:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p><strong>Archivo:</strong> " . $e->getFile() . " (línea " . $e->getLine() . ")</p>\n";
    echo "</div>\n";
    
    error_log("Error en crear_usuarios_test.php: " . $e->getMessage());
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