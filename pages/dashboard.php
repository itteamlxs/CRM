<?php
/**
 * Archivo: dashboard.php
 * Función: Página principal del sistema con resumen y estadísticas.
 * Seguridad: Sesión activa, roles autorizados.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Validar que el usuario esté logueado con rol permitido
requireRole(['administrador', 'vendedor']);

$pdo = getPDO();

try {
    // Total clientes
    $stmt = $pdo->query("SELECT COUNT(*) FROM clientes WHERE estado = 'activo'");
    $totalClientes = (int) $stmt->fetchColumn();

    // Total productos activos
    $stmt = $pdo->query("SELECT COUNT(*) FROM productos WHERE activo = TRUE");
    $totalProductos = (int) $stmt->fetchColumn();

    // Total ventas este mes
    $stmt = $pdo->query("SELECT COUNT(*) FROM ventas WHERE DATE_FORMAT(fecha, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')");
    $totalVentasMes = (int) $stmt->fetchColumn();

    // Total ventas general
    $stmt = $pdo->query("SELECT COUNT(*) FROM ventas");
    $totalVentas = (int) $stmt->fetchColumn();

    // Total facturado este mes
    $stmt = $pdo->query("SELECT COALESCE(SUM(total), 0) FROM ventas WHERE DATE_FORMAT(fecha, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')");
    $totalFacturadoMes = (float) $stmt->fetchColumn();

    // Ventas recientes (últimas 5)
    $stmt = $pdo->prepare("
        SELECT v.id, v.fecha, v.total, v.moneda, c.nombre as cliente_nombre 
        FROM ventas v 
        INNER JOIN cotizaciones co ON v.cotizacion_id = co.id 
        INNER JOIN clientes c ON co.cliente_id = c.id 
        ORDER BY v.fecha DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $ventasRecientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top 5 productos más vendidos
    $stmt = $pdo->prepare("
        SELECT p.nombre, SUM(vp.cantidad) as total_vendido 
        FROM venta_productos vp 
        INNER JOIN productos p ON vp.producto_id = p.id 
        GROUP BY p.id, p.nombre 
        ORDER BY total_vendido DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $productosTop = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error en dashboard: " . $e->getMessage());
    $totalClientes = $totalProductos = $totalVentasMes = $totalVentas = 0;
    $totalFacturadoMes = 0.0;
    $ventasRecientes = $productosTop = [];
}

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

    <!-- Tarjetas de estadísticas -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Clientes -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                <?php echo e($lang['dashboard_total_clients'] ?? 'Total Clientes'); ?>
                            </dt>
                            <dd class="text-lg font-medium text-gray-900 dark:text-white">
                                <?php echo number_format($totalClientes); ?>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700 px-5 py-3">
                <div class="text-sm">
                    <a href="/pages/clientes/index.php" class="font-medium text-blue-600 hover:text-blue-500">
                        <?php echo e($lang['nav_clients'] ?? 'Ver clientes'); ?>
                    </a>
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
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700 px-5 py-3">
                <div class="text-sm">
                    <a href="/pages/productos/index.php" class="font-medium text-green-600 hover:text-green-500">
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
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700 px-5 py-3">
                <div class="text-sm">
                    <a href="/pages/ventas/index.php" class="font-medium text-purple-600 hover:text-purple-500">
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
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700 px-5 py-3">
                <div class="text-sm">
                    <a href="/pages/reportes.php" class="font-medium text-yellow-600 hover:text-yellow-500">
                        <?php echo e($lang['nav_reports'] ?? 'Ver reportes'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Sección de contenido adicional -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Ventas recientes -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
                    Ventas Recientes
                </h3>
                
                <?php if (empty($ventasRecientes)): ?>
                    <p class="text-gray-500 dark:text-gray-400">No hay ventas registradas.</p>
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
                                </div>
                            </div>
                        <?php endforeach; ?>
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
                    <p class="text-gray-500 dark:text-gray-400">No hay datos de productos vendidos.</p>
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
                <?php endif; ?>
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
                <a href="/forms/form_cliente.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                    <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <?php echo e($lang['new_client'] ?? 'Nuevo Cliente'); ?>
                </a>
                
                <a href="/pages/productos/form.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700">
                    <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <?php echo e($lang['new_product'] ?? 'Nuevo Producto'); ?>
                </a>
                
                <a href="/pages/cotizaciones/form.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700">
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