<?php
/**
 * Archivo: tests/fase1.php
 * Función: Verifica Fase 1 - CON RUTAS CORRECTAS desde /tests/
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>🔍 Verificación Fase 1 - Base Crítica CRM</h1>\n";
echo "<p>Verificando desde: " . __DIR__ . "</p>\n";
echo "<hr>\n";

$errores = [];
$advertencias = [];
$completado = [];

// ============================================================================
// 1. VERIFICAR CONEXIÓN A BASE DE DATOS (RUTAS CORRECTAS)
// ============================================================================
echo "<h2>🗄️ Verificando conexión a base de datos...</h2>\n";

try {
    // RUTAS CORRECTAS desde /tests/
    require_once '../config/database.php';
    echo "<p>✅ <strong>../config/database.php</strong> - Cargado correctamente</p>\n";
    $completado[] = 'config/database.php';
    
    require_once '../includes/functions.php';
    echo "<p>✅ <strong>../includes/functions.php</strong> - Cargado correctamente</p>\n";
    $completado[] = 'includes/functions.php';
    
    // Verificar función getPDO
    if (function_exists('getPDO')) {
        $pdo = getPDO();
        echo "<p>✅ <strong>Conexión BD</strong> - getPDO() funciona correctamente</p>\n";
        $completado[] = 'Conexión BD';
        
        // Verificar tablas críticas
        $tablas_criticas = [
            'usuarios' => 'Usuarios del sistema',
            'clientes' => 'Clientes',
            'categorias' => 'Categorías de productos', 
            'productos' => 'Productos',
            'configuracion' => 'Configuración'
        ];
        
        echo "<h3>📋 Verificando tablas de BD...</h3>\n";
        foreach ($tablas_criticas as $tabla => $descripcion) {
            try {
                $stmt = $pdo->query("SHOW TABLES LIKE '$tabla'");
                if ($stmt->fetchColumn()) {
                    $stmt = $pdo->query("DESCRIBE $tabla");
                    $campos = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    echo "<p>✅ <strong>$tabla</strong> - $descripcion (" . count($campos) . " campos)</p>\n";
                    $completado[] = "Tabla $tabla";
                } else {
                    echo "<p>❌ <strong>$tabla</strong> - FALTA: $descripcion</p>\n";
                    $errores[] = "Tabla faltante: $tabla";
                }
            } catch (Exception $e) {
                echo "<p>❌ <strong>$tabla</strong> - ERROR: " . $e->getMessage() . "</p>\n";
                $errores[] = "Error en tabla $tabla";
            }
        }
        
    } else {
        throw new Exception("Función getPDO() no encontrada");
    }
    
} catch (Exception $e) {
    echo "<p>❌ <strong>Error configuración:</strong> " . $e->getMessage() . "</p>\n";
    $errores[] = "Error de configuración: " . $e->getMessage();
}

// ============================================================================
// 2. VERIFICAR FUNCIONES CRÍTICAS
// ============================================================================
echo "<h2>⚙️ Verificando funciones críticas...</h2>\n";

$funciones_criticas = [
    'getPDO' => 'Conexión PDO',
    'generate_csrf_token' => 'Token CSRF',
    'validate_csrf_token' => 'Validar CSRF',
    'url' => 'Generar URLs'
];

foreach ($funciones_criticas as $funcion => $descripcion) {
    if (function_exists($funcion)) {
        echo "<p>✅ <strong>$funcion()</strong> - $descripcion</p>\n";
        $completado[] = "Función $funcion";
    } else {
        echo "<p>❌ <strong>$funcion()</strong> - FALTA: $descripcion</p>\n";
        $errores[] = "Función faltante: $funcion()";
    }
}

// ============================================================================
// 3. VERIFICAR ARCHIVOS CON RUTAS CORRECTAS
// ============================================================================
echo "<h2>📁 Verificando archivos del proyecto...</h2>\n";

$archivos_criticos = [
    '../config/.env' => 'Variables de entorno',
    '../config/database.php' => 'Configuración BD',
    '../config/globals.php' => 'Configuración global',
    '../includes/auth.php' => 'Autenticación',
    '../includes/functions.php' => 'Funciones helper',
    '../includes/header.php' => 'Header común',
    '../includes/nav.php' => 'Navegación',
    '../forms/procesar_login.php' => 'Procesador login',
    '../forms/form_cliente.php' => 'Formulario clientes',
    '../forms/procesar_cliente.php' => 'Procesador clientes',
    '../pages/login.php' => 'Página de login',
    '../pages/dashboard.php' => 'Dashboard principal',
    '../pages/clientes/index.php' => 'Lista de clientes',
    '../lang/es.php' => 'Idioma español',
    '../lang/en.php' => 'Idioma inglés',
    '../schema.sql' => 'Schema de BD'
];

foreach ($archivos_criticos as $archivo => $descripcion) {
    if (file_exists($archivo)) {
        echo "<p>✅ <strong>$archivo</strong> - $descripcion</p>\n";
        $completado[] = basename($archivo);
    } else {
        echo "<p>❌ <strong>$archivo</strong> - FALTA: $descripcion</p>\n";
        $errores[] = "Archivo faltante: $archivo";
    }
}

// ============================================================================
// 4. VERIFICAR DATOS EN BD (SI EXISTE CONEXIÓN)
// ============================================================================
if (isset($pdo)) {
    echo "<h2>👥 Verificando datos en BD...</h2>\n";
    
    try {
        // Verificar usuarios
        $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE activo = TRUE");
        $usuarios = $stmt->fetchColumn();
        
        if ($usuarios > 0) {
            echo "<p>✅ <strong>Usuarios:</strong> $usuarios usuarios activos</p>\n";
            
            // Mostrar usuarios disponibles
            $stmt = $pdo->query("SELECT username, role FROM usuarios WHERE activo = TRUE ORDER BY role DESC");
            $usuariosList = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "<p style='margin-left: 20px; color: #666;'>Disponibles: ";
            foreach ($usuariosList as $u) {
                echo "<strong>{$u['username']}</strong> ({$u['role']}) ";
            }
            echo "</p>\n";
            $completado[] = 'Usuarios en BD';
        } else {
            echo "<p>⚠️ <strong>Usuarios:</strong> Sin usuarios de prueba</p>\n";
            $advertencias[] = 'Sin usuarios - ejecutar poblar_datos.php';
        }
        
        // Verificar categorías
        $stmt = $pdo->query("SELECT COUNT(*) FROM categorias WHERE activa = TRUE");
        $categorias = $stmt->fetchColumn();
        
        if ($categorias > 0) {
            echo "<p>✅ <strong>Categorías:</strong> $categorias categorías activas</p>\n";
            $completado[] = 'Categorías en BD';
        } else {
            echo "<p>⚠️ <strong>Categorías:</strong> Sin categorías</p>\n";
            $advertencias[] = 'Sin categorías - ejecutar poblar_datos.php';
        }
        
        // Verificar clientes
        $stmt = $pdo->query("SELECT COUNT(*) FROM clientes WHERE estado = 'activo'");
        $clientes = $stmt->fetchColumn();
        
        if ($clientes > 0) {
            echo "<p>✅ <strong>Clientes:</strong> $clientes clientes activos</p>\n";
            $completado[] = 'Clientes en BD';
        } else {
            echo "<p>⚠️ <strong>Clientes:</strong> Sin clientes</p>\n";
            $advertencias[] = 'Sin clientes - ejecutar poblar_datos.php';
        }
        
    } catch (Exception $e) {
        echo "<p>❌ <strong>Error verificando datos:</strong> " . $e->getMessage() . "</p>\n";
        $errores[] = "Error consultando BD: " . $e->getMessage();
    }
}

// ============================================================================
// 5. PROBAR AUTENTICACIÓN
// ============================================================================
echo "<h2>🔐 Verificando sistema de autenticación...</h2>\n";

try {
    require_once '../includes/auth.php';
    echo "<p>✅ <strong>../includes/auth.php</strong> - Cargado correctamente</p>\n";
    
    // Verificar funciones de auth
    $auth_functions = ['requireLogin', 'requireRole', 'getUserId', 'getUsername'];
    foreach ($auth_functions as $func) {
        if (function_exists($func)) {
            echo "<p>✅ <strong>$func()</strong> - Función de auth disponible</p>\n";
            $completado[] = "Auth $func";
        } else {
            echo "<p>❌ <strong>$func()</strong> - FALTA función de auth</p>\n";
            $errores[] = "Función auth faltante: $func";
        }
    }
    
} catch (Exception $e) {
    echo "<p>❌ <strong>Error cargando auth:</strong> " . $e->getMessage() . "</p>\n";
    $errores[] = "Error en auth.php: " . $e->getMessage();
}

// ============================================================================
// 6. RESUMEN FINAL
// ============================================================================
echo "<hr>\n";
echo "<h2>📊 Resumen de Verificación</h2>\n";

echo "<div style='display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin: 20px 0;'>\n";

// Completado
echo "<div style='background-color: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px;'>\n";
echo "<h3 style='color: #155724; margin-top: 0;'>✅ Completado (" . count($completado) . ")</h3>\n";
foreach (array_slice($completado, 0, 8) as $item) {
    echo "<p style='margin: 3px 0; font-size: 12px;'>• $item</p>\n";
}
if (count($completado) > 8) {
    echo "<p style='font-size: 11px; color: #666;'>... y " . (count($completado) - 8) . " más</p>\n";
}
echo "</div>\n";

// Advertencias
echo "<div style='background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px;'>\n";
echo "<h3 style='color: #856404; margin-top: 0;'>⚠️ Advertencias (" . count($advertencias) . ")</h3>\n";
foreach ($advertencias as $warning) {
    echo "<p style='margin: 3px 0; font-size: 12px;'>• $warning</p>\n";
}
if (empty($advertencias)) {
    echo "<p style='font-size: 12px; color: #666;'>Sin advertencias</p>\n";
}
echo "</div>\n";

// Errores
echo "<div style='background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px;'>\n";
echo "<h3 style='color: #721c24; margin-top: 0;'>❌ Errores (" . count($errores) . ")</h3>\n";
foreach ($errores as $error) {
    echo "<p style='margin: 3px 0; font-size: 12px;'>• $error</p>\n";
}
if (empty($errores)) {
    echo "<p style='font-size: 12px; color: #666;'>Sin errores críticos</p>\n";
}
echo "</div>\n";

echo "</div>\n";

// Estado final
if (empty($errores)) {
    echo "<div style='background-color: #d4edda; border: 1px solid #c3e6cb; padding: 20px; border-radius: 5px; text-align: center;'>\n";
    echo "<h2 style='color: #155724;'>🎉 FASE 1 COMPLETADA</h2>\n";
    echo "<p style='color: #155724;'><strong>Sistema funcionando correctamente</strong></p>\n";
    
    if (count($advertencias) > 0) {
        echo "<p style='color: #856404;'>⚠️ Considera ejecutar <strong>poblar_datos.php</strong> para datos de prueba</p>\n";
    }
    
    echo "</div>\n";
} else {
    echo "<div style='background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 20px; border-radius: 5px; text-align: center;'>\n";
    echo "<h2 style='color: #721c24;'>❌ ERRORES ENCONTRADOS</h2>\n";
    echo "<p style='color: #721c24;'>Resolver errores antes de continuar</p>\n";
    echo "</div>\n";
}

// Próximos pasos
echo "<h3>📋 Próximos pasos:</h3>\n";
echo "<ol>\n";

if (in_array('Sin usuarios - ejecutar poblar_datos.php', $advertencias)) {
    echo "<li><strong>Crear usuarios de prueba:</strong> Ejecutar <a href='poblar_datos.php'>poblar_datos.php</a></li>\n";
}

if (empty($errores)) {
    echo "<li><strong>Probar login:</strong> <a href='../pages/login.php' target='_blank'>Ir a Login</a></li>\n";
    echo "<li><strong>Probar dashboard:</strong> Login con admin/Temporal2025#</li>\n";
    echo "<li><strong>Continuar Fase 2:</strong> Completar módulo productos</li>\n";
} else {
    echo "<li><strong>Resolver errores listados arriba</strong></li>\n";
    echo "<li><strong>Verificar configuración de BD</strong></li>\n";
}

echo "</ol>\n";

echo "<p style='margin-top: 20px;'>";
echo "<a href='../pages/login.php' target='_blank' style='background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>🚀 Probar Login</a>";
echo "<a href='poblar_datos.php' style='background-color: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>📊 Poblar Datos</a>";
echo "</p>\n";

?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    background-color: #f8f9fa;
    line-height: 1.4;
}
h1, h2, h3 { color: #2c3e50; }
</style>