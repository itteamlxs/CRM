<?php
/**
 * Archivo: tests/poblar_datos.php  
 * Función: Datos de prueba compatibles con schema corregido y archivos PHP existentes
 * Ejecutar UNA vez después de aplicar el schema.sql corregido
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';
require_once '../includes/functions.php';

try {
    $pdo = getPDO();
    
    echo "<h1>🚀 Poblando datos de prueba CRM</h1>\n";
    echo "<p>Insertando datos compatibles con el schema corregido...</p>\n";
    echo "<hr>\n";
    
    $pdo->beginTransaction();
    
    // ============================================================================
    // 1. CREAR USUARIOS DE PRUEBA
    // ============================================================================
    echo "<h2>👥 Creando usuarios...</h2>\n";
    
    $usuarios = [
        ['admin', 'Temporal2025#', 'admin@crm.local', 'admin', 'Administrador Principal'],
        ['vendedor1', 'Temporal2025#', 'vendedor1@crm.local', 'vendedor', 'Juan Pérez Vendedor'],
        ['vendedor2', 'Temporal2025#', 'vendedor2@crm.local', 'vendedor', 'María González Vendedor'],
        ['supervisor', 'Temporal2025#', 'supervisor@crm.local', 'admin', 'Carlos López Supervisor'],
    ];

    $usuariosCreados = 0;
    foreach ($usuarios as $u) {
        // Verificar si existe
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE username = :username OR email = :email");
        $stmt->execute([':username' => $u[0], ':email' => $u[2]]);
        
        if ($stmt->fetchColumn() == 0) {
            $password_hash = password_hash($u[1], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO usuarios (username, password_hash, email, role, nombre_completo, activo) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([$u[0], $password_hash, $u[2], $u[3], $u[4]]);
            echo "<p>✅ Usuario <strong>{$u[0]}</strong> ({$u[3]}) creado</p>\n";
            $usuariosCreados++;
        } else {
            echo "<p>⚠️ Usuario <strong>{$u[0]}</strong> ya existe, saltando...</p>\n";
        }
    }
    
    // ============================================================================
    // 2. CREAR CATEGORÍAS (campos compatibles con schema corregido)
    // ============================================================================
    echo "<h2>📁 Creando categorías...</h2>\n";
    
    $categorias = [
        ['Software', 'Programas y aplicaciones informáticas'],
        ['Hardware', 'Equipos y componentes físicos'],
        ['Servicios', 'Servicios profesionales y consultoría'],
        ['Soporte Técnico', 'Servicios de mantenimiento y soporte'],
        ['Licencias', 'Licencias de software y suscripciones'],
        ['Equipos de Red', 'Routers, switches y equipos de conectividad'],
        ['Periféricos', 'Teclados, ratones, monitores y accesorios'],
        ['Almacenamiento', 'Discos duros, SSDs y sistemas de backup']
    ];

    $categoriasCreadas = 0;
    foreach ($categorias as $cat) {
        // Verificar si existe
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM categorias WHERE nombre = :nombre");
        $stmt->execute([':nombre' => $cat[0]]);
        
        if ($stmt->fetchColumn() == 0) {
            $stmt = $pdo->prepare("INSERT INTO categorias (nombre, descripcion, activa) VALUES (?, ?, TRUE)");
            $stmt->execute([$cat[0], $cat[1]]);
            echo "<p>✅ Categoría <strong>{$cat[0]}</strong> creada</p>\n";
            $categoriasCreadas++;
        } else {
            echo "<p>⚠️ Categoría <strong>{$cat[0]}</strong> ya existe, saltando...</p>\n";
        }
    }

    // ============================================================================
    // 3. CREAR CLIENTES
    // ============================================================================
    echo "<h2>👥 Creando clientes...</h2>\n";
    
    $clientesEjemplo = [
        ['Empresa Tecnológica SA', 'contacto@tecno.com', '+1-555-0101', 'Av. Tecnología 123, Ciudad Tech'],
        ['Consultores Modernos SL', 'info@consultores.com', '+1-555-0102', 'Calle Consultoría 456, Tech Park'],
        ['Desarrollo Web Corp', 'admin@webdev.com', '+1-555-0103', 'Plaza Digital 789, Innovation District'],
        ['Sistemas Integrados SA', 'ventas@sistemas.com', '+1-555-0104', 'Av. Integración 321, Business Center'],
        ['Soluciones IT Ltda', 'contacto@soluciones.com', '+1-555-0105', 'Torre IT, Piso 15, Tech City'],
        ['Innovación Digital SAS', 'hello@innovacion.com', '+1-555-0106', 'Campus Digital 654, StartUp Valley'],
        ['Automatización Pro', 'support@automation.com', '+1-555-0107', 'Industrial Park 987, AutoCity'],
        ['Cloud Solutions Inc', 'sales@cloudsol.com', '+1-555-0108', 'Cloud Tower 147, DataCenter Ave']
    ];

    $clientesCreados = 0;
    foreach ($clientesEjemplo as $cli) {
        // Verificar si existe
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM clientes WHERE email = :email");
        $stmt->execute([':email' => $cli[1]]);
        
        if ($stmt->fetchColumn() == 0) {
            $stmt = $pdo->prepare("INSERT INTO clientes (nombre, email, telefono, direccion, estado) VALUES (?, ?, ?, ?, 'activo')");
            $stmt->execute([$cli[0], $cli[1], $cli[2], $cli[3]]);
            echo "<p>✅ Cliente <strong>{$cli[0]}</strong> creado</p>\n";
            $clientesCreados++;
        }
    }

    // Agregar más clientes genéricos
    for ($i = 1; $i <= 12; $i++) {
        $nombre = "Cliente Empresarial $i";
        $email = "empresa$i@ejemplo.com";
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM clientes WHERE email = :email");
        $stmt->execute([':email' => $email]);
        
        if ($stmt->fetchColumn() == 0) {
            $telefono = sprintf("+1-555-02%02d", $i);
            $direccion = "Dirección Comercial $i, Zona Empresarial";
            $stmt = $pdo->prepare("INSERT INTO clientes (nombre, email, telefono, direccion, estado) VALUES (?, ?, ?, ?, 'activo')");
            $stmt->execute([$nombre, $email, $telefono, $direccion]);
            $clientesCreados++;
        }
    }
    echo "<p>📊 Total clientes creados: <strong>$clientesCreados</strong></p>\n";

    // ============================================================================
    // 4. CREAR PRODUCTOS (compatibles con campos corregidos del schema)
    // ============================================================================
    echo "<h2>📦 Creando productos...</h2>\n";
    
    // Obtener IDs de categorías
    $stmt = $pdo->query("SELECT id, nombre FROM categorias WHERE activa = TRUE");
    $categoriasDB = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    if (empty($categoriasDB)) {
        throw new Exception("No hay categorías disponibles para crear productos");
    }
    
    // Productos de ejemplo con CAMPOS CORRECTOS del schema
    $productosEjemplo = [
        // [nombre, descripcion, categoria, codigo_sku, precio_compra, precio_venta, stock_actual, stock_minimo, stock_maximo, unidad_medida]
        ['Licencia Windows Server 2022', 'Licencia de sistema operativo servidor', 'Software', 'WIN-SRV-2022', 450.00, 699.00, 25, 5, 50, 'unidad'],
        ['Office 365 Business Premium', 'Suite ofimática en la nube anual', 'Software', 'O365-BP-001', 180.00, 299.00, 100, 10, 200, 'unidad'],
        ['Antivirus Kaspersky Endpoint', 'Protección antivirus empresarial', 'Software', 'KAS-EP-001', 35.00, 59.00, 150, 20, 300, 'unidad'],
        
        ['Laptop Dell Latitude 5520', 'Laptop empresarial i5, 16GB RAM, 512GB SSD', 'Hardware', 'DELL-LAT-5520', 850.00, 1299.00, 12, 3, 25, 'unidad'],
        ['Monitor Samsung 27" 4K', 'Monitor profesional 27 pulgadas resolución 4K', 'Hardware', 'SAM-MON-27-4K', 280.00, 449.00, 18, 5, 30, 'unidad'],
        ['Impresora HP LaserJet Pro', 'Impresora láser monocromática profesional', 'Hardware', 'HP-LJ-PRO-001', 165.00, 279.00, 8, 2, 15, 'unidad'],
        
        ['Consultoría IT - Hora', 'Hora de consultoría especializada', 'Servicios', 'CONS-IT-HORA', 60.00, 120.00, 0, 0, null, 'unidad'],
        ['Implementación de Red', 'Diseño e implementación de red empresarial', 'Servicios', 'IMPL-RED-001', 800.00, 1500.00, 0, 0, null, 'unidad'],
        ['Migración a la Nube', 'Servicio de migración completa a cloud', 'Servicios', 'MIG-CLOUD-001', 1200.00, 2500.00, 0, 0, null, 'unidad'],
        
        ['Soporte Técnico Mensual', 'Contrato de soporte técnico mensual', 'Soporte Técnico', 'SOPORTE-MES', 80.00, 150.00, 0, 0, null, 'unidad'],
        ['Mantenimiento Preventivo', 'Servicio de mantenimiento preventivo equipos', 'Soporte Técnico', 'MANT-PREV-001', 45.00, 89.00, 0, 0, null, 'unidad'],
        
        ['Router Cisco 2911', 'Router empresarial Cisco 2911', 'Equipos de Red', 'CISCO-2911', 1200.00, 1899.00, 5, 1, 10, 'unidad'],
        ['Switch TP-Link 24 Puertos', 'Switch administrable 24 puertos gigabit', 'Equipos de Red', 'TPL-SW24-001', 180.00, 299.00, 12, 3, 20, 'unidad'],
        ['Access Point Ubiquiti', 'Punto de acceso WiFi 6 empresarial', 'Equipos de Red', 'UBI-AP-001', 95.00, 159.00, 20, 5, 40, 'unidad'],
        
        ['Disco SSD Samsung 1TB', 'Disco estado sólido 1TB SATA', 'Almacenamiento', 'SAM-SSD-1TB', 85.00, 149.00, 30, 10, 60, 'unidad'],
        ['NAS Synology 2 Bahías', 'Sistema almacenamiento en red 2 bahías', 'Almacenamiento', 'SYN-NAS-2BAY', 220.00, 399.00, 8, 2, 15, 'unidad']
    ];

    $productosCreados = 0;
    foreach ($productosEjemplo as $prod) {
        // Buscar ID de categoría
        $categoria_id = null;
        foreach ($categoriasDB as $id => $nombre) {
            if ($nombre === $prod[2]) {
                $categoria_id = $id;
                break;
            }
        }
        
        if (!$categoria_id) {
            // Usar primera categoría disponible como fallback
            $categoria_id = array_key_first($categoriasDB);
        }
        
        // Verificar si el producto ya existe
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM productos WHERE codigo_sku = :sku OR nombre = :nombre");
        $stmt->execute([':sku' => $prod[3], ':nombre' => $prod[0]]);
        
        if ($stmt->fetchColumn() == 0) {
            // INSERTAR CON CAMPOS QUE EXISTEN ACTUALMENTE
            $stmt = $pdo->prepare("
                INSERT INTO productos (
                    nombre, descripcion, categoria_id, codigo_sku, precio_base, 
                    precio_compra, precio_venta, stock, stock_actual, stock_minimo, stock_maximo, 
                    unidad_medida, activo
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE)
            ");
            
            $stmt->execute([
                $prod[0], // nombre
                $prod[1], // descripcion
                $categoria_id, // categoria_id
                $prod[3], // codigo_sku
                $prod[5], // precio_base (usar precio_venta como base)
                $prod[4], // precio_compra
                $prod[5], // precio_venta
                $prod[6], // stock (campo viejo)
                $prod[6], // stock_actual (campo nuevo)
                $prod[7], // stock_minimo
                $prod[8], // stock_maximo
                $prod[9]  // unidad_medida
            ]);
            
            $productoId = $pdo->lastInsertId();
            
            // Registrar stock inicial si es mayor a 0 (usando tabla inventario_movimientos)
            if ($prod[6] > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO inventario_movimientos 
                    (producto_id, tipo_movimiento, cantidad, stock_anterior, stock_nuevo, motivo, usuario_id) 
                    VALUES (?, 'entrada', ?, 0, ?, 'Stock inicial de datos de prueba', 1)
                ");
                $stmt->execute([$productoId, $prod[6], $prod[6]]);
            }
            
            echo "<p>✅ Producto <strong>{$prod[0]}</strong> creado (SKU: {$prod[3]})</p>\n";
            $productosCreados++;
        }
    }
    echo "<p>📊 Total productos creados: <strong>$productosCreados</strong></p>\n";

    $pdo->commit();
    
    // ============================================================================
    // 5. MOSTRAR ESTADÍSTICAS FINALES
    // ============================================================================
    echo "<hr>\n";
    echo "<h2>📊 Estadísticas Finales</h2>\n";
    
    $stats = [
        'usuarios' => $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn(),
        'categorias' => $pdo->query("SELECT COUNT(*) FROM categorias WHERE activa = TRUE")->fetchColumn(),
        'clientes' => $pdo->query("SELECT COUNT(*) FROM clientes WHERE estado = 'activo'")->fetchColumn(),
        'productos' => $pdo->query("SELECT COUNT(*) FROM productos WHERE activo = TRUE")->fetchColumn(),
        'movimientos' => $pdo->query("SELECT COUNT(*) FROM inventario_movimientos")->fetchColumn()
    ];
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr style='background-color: #f0f0f0;'><th style='padding: 8px;'>Tabla</th><th style='padding: 8px;'>Registros</th></tr>\n";
    foreach ($stats as $tabla => $cantidad) {
        echo "<tr><td style='padding: 8px;'>".ucfirst($tabla)."</td><td style='padding: 8px;'><strong>$cantidad</strong></td></tr>\n";
    }
    echo "</table>\n";
    
    echo "\n<div style='background-color: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 20px 0;'>\n";
    echo "<h3>✅ Datos de prueba creados exitosamente</h3>\n";
    echo "<ul>\n";
    echo "<li><strong>Schema corregido aplicado</strong></li>\n";
    echo "<li>Usuarios creados con contraseña: <code>Temporal2025#</code></li>\n";
    echo "<li>Productos con campos correctos: precio_compra, precio_venta, stock_actual, etc.</li>\n";
    echo "<li>Movimientos de inventario registrados</li>\n";
    echo "<li>Vista vista_productos funcionando</li>\n";
    echo "</ul>\n";
    echo "</div>\n";
    
    echo "<h2>🔑 Usuarios para testing:</h2>\n";
    $stmt = $pdo->query("SELECT username, role, nombre_completo FROM usuarios WHERE activo = TRUE ORDER BY role DESC");
    $usuariosDB = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr style='background-color: #f0f0f0;'><th style='padding: 8px;'>Usuario</th><th style='padding: 8px;'>Rol</th><th style='padding: 8px;'>Nombre</th><th style='padding: 8px;'>Contraseña</th></tr>\n";
    foreach ($usuariosDB as $u) {
        $roleColor = $u['role'] === 'admin' ? '#e74c3c' : '#3498db';
        echo "<tr>\n";
        echo "<td style='padding: 8px;'><strong>{$u['username']}</strong></td>\n";
        echo "<td style='padding: 8px; color: $roleColor;'><strong>{$u['role']}</strong></td>\n";
        echo "<td style='padding: 8px;'>{$u['nombre_completo']}</td>\n";
        echo "<td style='padding: 8px;'><code>Temporal2025#</code></td>\n";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    echo "<p><a href='../pages/login.php' target='_blank' style='background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>🚀 Ir al Login del CRM</a>";
    echo "<a href='fase1.php' style='background-color: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔍 Verificar Fase 1</a></p>\n";
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo "<div style='background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; color: #721c24;'>\n";
    echo "<h3>❌ Error al poblar datos:</h3>\n";
    echo "<p><strong>Mensaje:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p><strong>Archivo:</strong> " . $e->getFile() . " (línea " . $e->getLine() . ")</p>\n";
    echo "</div>\n";
    
    error_log("Error en poblar_datos.php: " . $e->getMessage());
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