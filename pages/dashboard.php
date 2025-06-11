<?php
/**
 * Archivo: dashboard.php - COMPLETO CON SOFT DELETE
 * Función: Página principal del sistema con resumen y estadísticas ROBUSTAS.
 * Seguridad: Sesión activa, roles autorizados.
 * Actualización: Manejo robusto de errores, consultas seguras y SOFT DELETE implementado
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Validar que el usuario esté logueado con rol permitido
requireRole(['admin', 'vendedor']);

$pdo = getPDO();

// Inicializar variables con valores por defecto
$totalClientes = 0;
$totalClientesActivos = 0;
$totalClientesInactivos = 0;
$totalClientesEliminados = 0;
$totalProductos = 0;
$totalVentasMes = 0;
$totalVentas = 0;
$totalFacturadoMes = 0.0;
$ventasRecientes = [];
$productosTop = [];
$clientesRecientes = [];
$errores = []; // Para debugging

// ============================================================================
// 1. VERIFICAR TABLA CLIENTES Y OBTENER ESTADÍSTICAS CON SOFT DELETE
// ============================================================================
echo "<!-- Dashboard debug: Iniciando consultas -->\n";

try {
    // Contar clientes activos (NO eliminados y estado activo)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM clientes WHERE estado = 'activo' AND eliminado = FALSE");
    $stmt->execute();
    $totalClientesActivos = (int) $stmt->fetchColumn();
    
    // Contar clientes inactivos (NO eliminados pero estado inactivo)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM clientes WHERE estado = 'inactivo' AND eliminado = FALSE");
    $stmt->execute();
    $totalClientesInactivos = (int) $stmt->fetchColumn();
    
    // Total clientes (solo los no eliminados)
    $totalClientes = $totalClientesActivos + $totalClientesInactivos;
    
    // Solo admin puede ver estadísticas de eliminados
    if ($_SESSION['role'] === 'admin') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM clientes WHERE eliminado = TRUE");
        $stmt->execute();
        $totalClientesEliminados = (int) $stmt->fetchColumn();
    }
    
    echo "<!-- Debug - Clientes activos: $totalClientesActivos, inactivos: $totalClientesInactivos, eliminados: $totalClientesEliminados -->\n";
    
} catch (PDOException $e) {
    $errores[] = "Error al contar clientes: " . $e->getMessage();
    error_log("Error dashboard clientes: " . $e->getMessage());
}

// ============================================================================
// 2. VERIFICAR SI TABLA PRODUCTOS EXISTE Y OBTENER ESTADÍSTICAS
// ============================================================================
try {
    // Verificar si la tabla productos existe
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'productos'");
    $stmt->execute();
    $tablaProductosExiste = $stmt->fetchColumn() !== false;
    
    if ($tablaProductosExiste) {
        // Verificar si la tabla categorias existe (requerida por productos)
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'categorias'");
        $stmt->execute();
        $tablaCategoriasExiste = $stmt->fetchColumn() !== false;
        
        if ($tablaCategoriasExiste) {
            // Contar productos activos (no eliminados)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM productos WHERE activo = TRUE AND eliminado = FALSE");
            $stmt->execute();
            $totalProductos = (int) $stmt->fetchColumn();
            echo "<!-- Debug - Total productos activos: $totalProductos -->\n";
        } else {
            $errores[] = "Tabla 'categorias' no existe - no se pueden contar productos";
            echo "<!-- Debug - Tabla categorias no existe -->\n";
        }
    } else {
        $errores[] = "Tabla 'productos' no existe aún";
        echo "<!-- Debug - Tabla productos no existe -->\n";
    }
    
} catch (PDOException $e) {
    $errores[] = "Error al verificar productos: " . $e->getMessage();
    error_log("Error dashboard productos: " . $e->getMessage());
}

// ============================================================================
// 3. VENTAS (solo si las tablas existen)
// ============================================================================
try {
    // Verificar si las tablas de ventas existen
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'ventas'");
    $stmt->execute();
    $tablaVentasExiste = $stmt->fetchColumn() !== false;
    
    if ($tablaVentasExiste) {
        // Total ventas este mes
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM ventas WHERE DATE_FORMAT(fecha, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')");
        $stmt->execute();
        $totalVentasMes = (int) $stmt->fetchColumn();
        
        // Total ventas general
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM ventas");
        $stmt->execute();
        $totalVentas = (int) $stmt->fetchColumn();
        
        // Total facturado este mes
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM ventas WHERE DATE_FORMAT(fecha, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')");
        $stmt->execute();
        $totalFacturadoMes = (float) $stmt->fetchColumn();
        
        echo "<!-- Debug - Ventas mes: $totalVentasMes, Total ventas: $totalVentas, Facturado mes: $totalFacturadoMes -->\n";
        
        // Ventas recientes (solo si hay datos y clientes no eliminados)
        if ($totalVentas > 0) {
            $stmt = $pdo->prepare("
                SELECT v.id, v.fecha, v.total, v.moneda, c.nombre as cliente_nombre 
                FROM ventas v 
                INNER JOIN cotizaciones co ON v.cotizacion_id = co.id 
                INNER JOIN clientes c ON co.cliente_id = c.id 
                WHERE c.eliminado = FALSE
                ORDER BY v.fecha DESC 
                LIMIT 5
            ");
            $stmt->execute();
            $ventasRecientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
    } else {
        echo "<!-- Debug - Tabla ventas no existe -->\n";
    }
    
} catch (PDOException $e) {
    $errores[] = "Error al consultar ventas: " . $e->getMessage();
    error_log("Error dashboard ventas: " . $e->getMessage());
}

// ============================================================================
// 4. TOP PRODUCTOS VENDIDOS (solo si hay datos)
// ============================================================================
try {
    if ($tablaVentasExiste && $tablaProductosExiste && $totalVentas > 0) {
        $stmt = $pdo->prepare("
            SELECT p.nombre, SUM(vp.cantidad) as total_vendido 
            FROM venta_productos vp 
            INNER JOIN productos p ON vp.producto_id = p.id 
            WHERE p.eliminado = FALSE
            GROUP BY p.id, p.nombre 
            ORDER BY total_vendido DESC 
            LIMIT 5
        ");
        $stmt->execute();
        $productosTop = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<!-- Debug - Productos top encontrados: " . count($productosTop) . " -->\n";
    }
    
} catch (PDOException $e) {
    $errores[] = "Error al consultar productos top: " . $e->getMessage();
    error_log("Error dashboard productos top: " . $e->getMessage());
}

// ============================================================================
// 5. CLIENTES RECIENTES (NO ELIMINADOS)
// ============================================================================
try {
    $stmt = $pdo->prepare("
        SELECT nombre, email, fecha_creacion 
        FROM clientes 
        WHERE eliminado = FALSE 
        ORDER BY fecha_creacion DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $clientesRecientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<!-- Debug - Clientes recientes encontrados: " . count($clientesRecientes) . " -->\n";
    
} catch (PDOException $e) {
    $errores[] = "Error al consultar clientes recientes: " . $e->getMessage();
    error_log("Error dashboard clientes recientes: " . $e->getMessage());
}

// ============================================================================
// 6. DEBUG INFO - Solo mostrar si hay errores Y el usuario es admin
// ============================================================================
$mostrarDebug = !empty($errores) && ($_SESSION['role'] === 'admin');

?>

<div class="max-w-7xl mx-auto px-4 py-6">
    <!-- Encabezado de bienvenida -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
            <?php echo e($lang['dashboard_welcome'] ?? 'Bienvenido'); ?>, 
            <?php echo e($_SESSION['username'] ?? 'Usuario'); ?>!
        </h1>
        <p class="text-gray-600 dark:text-gray-400 mt-2">
            <?php echo e(date('l, F j, Y')); ?> - 
            <span class="capitalize"><?php echo e($_SESSION['role'] ?? ''); ?></span>
        </p>
    </div>

    <!-- DEBUG INFO (solo para admin si hay errores) -->
    <?php if ($mostrarDebug): ?>
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
            <h3 class="text-lg font-medium text-yellow-800 mb-2">Información de Debug (solo visible para admin)</h3>
            <div class="text-sm text-yellow-700">
                <p><strong>Total de errores:</strong> <?php echo count($errores); ?></p>
                <ul class="list-disc list-inside mt-2">
                    <?php foreach ($errores as $error): ?>
                        <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
                <p class="mt-2"><strong>Sugerencia:</strong> Verifica que todas las tablas existan ejecutando el schema.sql completo</p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tarjetas de estadísticas principales -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Clientes Activos -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                Clientes Activos
                            </dt>
                            <dd class="text-lg font-medium text-gray-900 dark:text-white">
                                <?php echo number_format($totalClientesActivos); ?>
                                <?php if ($totalClientesActivos === 0): ?>
                                    <span class="text-xs text-gray-500 block">Sin clientes activos</span>
                                <?php else: ?>
                                    <span class="text-xs text-gray-500 block">
                                        Total: <?php echo number_format($totalClientes); ?>
                                        <?php if ($totalClientesInactivos > 0): ?>
                                            (<?php echo $totalClientesInactivos; ?> inactivos)
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700 px-5 py-3">
                <div class="text-sm">
                    <a href="<?php echo url('pages/clientes/index.php'); ?>" class="font-medium text-blue-600 hover:text-blue-500">
                        Ver clientes
                    </a>
                    <?php if ($_SESSION['role'] === 'admin' && $totalClientesEliminados > 0): ?>
                        <span class="mx-2 text-gray-400">•</span>
                        <a href="<?php echo url('pages/clientes/index.php?eliminados=1'); ?>" class="font-medium text-gray-600 hover:text-gray-500">
                            <?php echo $totalClientesEliminados; ?> eliminados
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Total Productos -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                <?php echo e($lang['dashboard_total_products'] ?? 'Total Productos'); ?>
                            </dt>
                            <dd class="text-lg font-medium text-gray-900 dark:text-white">
                                <?php echo number_format($totalProductos); ?>
                                <?php if ($totalProductos === 0 && isset($tablaProductosExiste) && !$tablaProductosExiste): ?>
                                    <span class="text-xs text-gray-500 block">Tabla no creada</span>
                                <?php elseif ($totalProductos === 0): ?>
                                    <span class="text-xs text-gray-500 block">Sin productos</span>
                                <?php else: ?>
                                    <span class="text-xs text-gray-500 block">Productos activos</span>
                                <?php endif; ?>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700 px-5 py-3">
                <div class="text-sm">
                    <a href="<?php echo url('pages/productos/index.php'); ?>" class="font-medium text-green-600 hover:text-green-500">
                        <?php echo e($lang['nav_products'] ?? 'Ver productos'); ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Ventas este mes -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                Ventas este mes
                            </dt>
                            <dd class="text-lg font-medium text-gray-900 dark:text-white">
                                <?php echo number_format($totalVentasMes); ?>
                                <?php if ($totalVentasMes === 0 && isset($tablaVentasExiste) && !$tablaVentasExiste): ?>
                                    <span class="text-xs text-gray-500 block">Tabla no creada</span>
                                <?php elseif ($totalVentasMes === 0): ?>
                                    <span class="text-xs text-gray-500 block">Sin ventas</span>
                                <?php else: ?>
                                    <span class="text-xs text-gray-500 block">Total: <?php echo $totalVentas; ?></span>
                                <?php endif; ?>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700 px-5 py-3">
                <div class="text-sm">
                    <a href="<?php echo url('pages/ventas/index.php'); ?>" class="font-medium text-purple-600 hover:text-purple-500">
                        <?php echo e($lang['nav_sales'] ?? 'Ver ventas'); ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Total facturado este mes -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                Facturado este mes
                            </dt>
                            <dd class="text-lg font-medium text-gray-900 dark:text-white">
                                $<?php echo number_format($totalFacturadoMes, 2); ?>
                                <?php if ($totalFacturadoMes > 0): ?>
                                    <span class="text-xs text-gray-500 block">
                                        <?php echo date('F Y'); ?>
                                    </span>
                                <?php endif; ?>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700 px-5 py-3">
                <div class="text-sm">
                    <a href="<?php echo url('pages/reportes.php'); ?>" class="font-medium text-yellow-600 hover:text-yellow-500">
                        <?php echo e($lang['nav_reports'] ?? 'Ver reportes'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Sección de contenido adicional en dos columnas -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Ventas recientes -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
                    Ventas Recientes
                </h3>
                
                <?php if (empty($ventasRecientes)): ?>
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                        <p class="mt-2 text-gray-500 dark:text-gray-400">No hay ventas registradas.</p>
                        <div class="mt-4">
                            <a href="<?php echo url('pages/cotizaciones/form.php'); ?>" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700">
                                Nueva cotización
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($ventasRecientes as $venta): ?>
                            <div class="flex items-center justify-between py-2 border-b border-gray-200 dark:border-gray-700 last:border-b-0">
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        <?php echo e($venta['cliente_nombre']); ?>
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        <?php echo e(date('d/m/Y H:i', strtotime($venta['fecha']))); ?>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        <?php echo e($venta['moneda']); ?> <?php echo number_format($venta['total'], 2); ?>
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        ID: <?php echo $venta['id']; ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-4 text-center">
                        <a href="<?php echo url('pages/ventas/index.php'); ?>" class="text-sm text-blue-600 hover:text-blue-500">
                            Ver todas las ventas →
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Top productos -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
                    Productos Más Vendidos
                </h3>
                
                <?php if (empty($productosTop)): ?>
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        <p class="mt-2 text-gray-500 dark:text-gray-400">No hay datos de productos vendidos.</p>
                        <div class="mt-4">
                            <a href="<?php echo url('pages/productos/form.php'); ?>" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-green-600 hover:bg-green-700">
                                Nuevo producto
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($productosTop as $index => $producto): ?>
                            <div class="flex items-center justify-between py-2 border-b border-gray-200 dark:border-gray-700 last:border-b-0">
                                <div class="flex items-center">
                                    <span class="flex-shrink-0 w-6 h-6 bg-blue-600 text-white text-xs rounded-full flex items-center justify-center mr-3">
                                        <?php echo $index + 1; ?>
                                    </span>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        <?php echo e($producto['nombre']); ?>
                                    </p>
                                </div>
                                <div>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo number_format($producto['total_vendido']); ?> vendidos
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-4 text-center">
                        <a href="<?php echo url('pages/reportes.php'); ?>" class="text-sm text-green-600 hover:text-green-500">
                            Ver reporte completo →
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sección adicional: Clientes recientes y estado del sistema -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-8">
        <!-- Clientes recientes (NO ELIMINADOS) -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
                    Clientes Recientes
                </h3>
                
                <?php if (empty($clientesRecientes)): ?>
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <p class="mt-2 text-gray-500 dark:text-gray-400">No hay clientes registrados aún.</p>
                        <div class="mt-4">
                            <a href="<?php echo url('forms/form_cliente.php'); ?>" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                Crear primer cliente
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($clientesRecientes as $cliente): ?>
                            <div class="flex items-center justify-between py-2 border-b border-gray-200 dark:border-gray-700 last:border-b-0">
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        <?php echo e($cliente['nombre']); ?>
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        <?php echo e($cliente['email']); ?>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        <?php echo e(date('d/m/Y', strtotime($cliente['fecha_creacion']))); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-4 text-center">
                        <a href="<?php echo url('pages/clientes/index.php'); ?>" class="text-sm text-blue-600 hover:text-blue-500">
                            Ver todos los clientes →
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Estado del sistema -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
                    Estado del Sistema
                </h3>
                
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Tabla clientes:</span>
                        <span class="text-sm font-medium text-green-600">✓ Activa (con soft delete)</span>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Tabla productos:</span>
                        <span class="text-sm font-medium <?php echo isset($tablaProductosExiste) && $tablaProductosExiste ? 'text-green-600' : 'text-yellow-600'; ?>">
                            <?php echo isset($tablaProductosExiste) && $tablaProductosExiste ? '✓ Activa' : '⚠ Pendiente'; ?>
                        </span>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Tabla categorías:</span>
                        <span class="text-sm font-medium <?php echo isset($tablaCategoriasExiste) && $tablaCategoriasExiste ? 'text-green-600' : 'text-yellow-600'; ?>">
                            <?php echo isset($tablaCategoriasExiste) && $tablaCategoriasExiste ? '✓ Activa' : '⚠ Pendiente'; ?>
                        </span>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Tabla ventas:</span>
                        <span class="text-sm font-medium <?php echo isset($tablaVentasExiste) && $tablaVentasExiste ? 'text-green-600' : 'text-yellow-600'; ?>">
                            <?php echo isset($tablaVentasExiste) && $tablaVentasExiste ? '✓ Activa' : '⚠ Pendiente'; ?>
                        </span>
                    </div>

                    <!-- Información adicional sobre soft delete para admin -->
                    <?php if ($_SESSION['role'] === 'admin' && $totalClientesEliminados > 0): ?>
                        <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900 rounded-md">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-blue-800 dark:text-blue-300">Clientes en papelera:</span>
                                <span class="text-sm font-medium text-blue-900 dark:text-blue-200">
                                    <?php echo $totalClientesEliminados; ?>
                                </span>
                            </div>
                            <div class="mt-2">
                                <a href="<?php echo url('pages/clientes/index.php?eliminados=1'); ?>" class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                    Gestionar papelera →
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($errores)): ?>
                        <div class="mt-4 p-3 bg-yellow-50 dark:bg-yellow-900 rounded-md">
                            <p class="text-sm text-yellow-800 dark:text-yellow-200">
                                <strong>Algunas funciones están limitadas.</strong><br>
                                Ejecuta el schema.sql completo para habilitar todas las características.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Acciones rápidas -->
    <div class="mt-8 bg-white dark:bg-gray-800 shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
                <?php echo e($lang['dashboard_quick_actions'] ?? 'Acciones Rápidas'); ?>
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <a href="<?php echo url('forms/form_cliente.php'); ?>" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 transition-colors">
                    <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <?php echo e($lang['new_client'] ?? 'Nuevo Cliente'); ?>
                </a>
                
                <a href="<?php echo url('pages/productos/form.php'); ?>" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 transition-colors">
                    <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <?php echo e($lang['new_product'] ?? 'Nuevo Producto'); ?>
                </a>
                
                <a href="<?php echo url('pages/cotizaciones/form.php'); ?>" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700 transition-colors">
                    <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <?php echo e($lang['new_quote'] ?? 'Nueva Cotización'); ?>
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>