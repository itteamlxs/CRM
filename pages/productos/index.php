<?php
/**
 * Archivo: pages/productos/index.php
 * Función: Lista de productos con gestión CRUD e inventario
 * Seguridad: Admin y vendedores pueden ver productos
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(['admin', 'vendedor']);

$pdo = getPDO();

$search = trim($_GET['search'] ?? '');
$categoria_filter = filter_input(INPUT_GET, 'categoria', FILTER_VALIDATE_INT);
$estado_filter = $_GET['estado'] ?? '';
$stock_filter = $_GET['stock'] ?? '';
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Construir condición WHERE
$where_conditions = [];
$params = [];

if ($search !== '') {
    $where_conditions[] = "(p.nombre LIKE :search OR p.codigo_sku LIKE :search OR p.descripcion LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($categoria_filter) {
    $where_conditions[] = "p.categoria_id = :categoria";
    $params[':categoria'] = $categoria_filter;
}

if ($estado_filter !== '') {
    if ($estado_filter === 'activo') {
        $where_conditions[] = "p.activo = TRUE";
    } elseif ($estado_filter === 'inactivo') {
        $where_conditions[] = "p.activo = FALSE";
    }
}

if ($stock_filter !== '') {
    if ($stock_filter === 'sin_stock') {
        $where_conditions[] = "p.stock_actual <= 0";
    } elseif ($stock_filter === 'stock_bajo') {
        $where_conditions[] = "p.stock_actual > 0 AND p.stock_actual <= p.stock_minimo";
    } elseif ($stock_filter === 'stock_normal') {
        $where_conditions[] = "p.stock_actual > p.stock_minimo";
    }
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Contar total para paginación
$count_sql = "
    SELECT COUNT(*) 
    FROM productos p 
    INNER JOIN categorias c ON p.categoria_id = c.id 
    $where_clause
";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_rows = $count_stmt->fetchColumn();
$total_pages = ceil($total_rows / $per_page);

// Obtener productos con información de categoría
$sql = "
    SELECT 
        p.*,
        c.nombre as categoria_nombre,
        CASE 
            WHEN p.stock_actual <= 0 THEN 'sin_stock'
            WHEN p.stock_actual <= p.stock_minimo THEN 'stock_bajo'
            ELSE 'stock_normal'
        END as estado_stock,
        (p.precio_venta - p.precio_compra) as margen_ganancia
    FROM productos p 
    INNER JOIN categorias c ON p.categoria_id = c.id 
    $where_clause
    ORDER BY p.nombre ASC 
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', (int)$per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener categorías para filtro
$categorias = getCategorias($pdo);

// Exportar CSV si solicitado
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=productos.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Nombre', 'Código SKU', 'Categoría', 'Precio Compra', 'Precio Venta', 'Stock Actual', 'Stock Mínimo', 'Estado', 'Fecha Creación']);
    
    foreach ($productos as $p) {
        fputcsv($output, [
            $p['id'],
            $p['nombre'],
            $p['codigo_sku'],
            $p['categoria_nombre'],
            $p['precio_compra'],
            $p['precio_venta'],
            $p['stock_actual'],
            $p['stock_minimo'],
            $p['activo'] ? 'Activo' : 'Inactivo',
            $p['fecha_creacion']
        ]);
    }
    fclose($output);
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 py-6">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
            Gestión de Productos
        </h1>
        <p class="text-gray-600 dark:text-gray-400 mt-1">
            Administra el inventario y catálogo de productos
        </p>
    </div>

    <!-- Mensajes de estado -->
    <?php if (isset($_GET['success'])): ?>
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
            <div class="flex">
                <svg class="w-5 h-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <div class="ml-3">
                    <p class="text-sm text-green-800">
                        <?php echo htmlspecialchars($_GET['success'], ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <div class="flex">
                <svg class="w-5 h-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                <div class="ml-3">
                    <p class="text-sm text-red-800">
                        <?php echo htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Filtros y búsqueda -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
        <form method="GET" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <!-- Búsqueda -->
                <div class="lg:col-span-2">
                    <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Buscar productos
                    </label>
                    <input
                        type="text"
                        id="search"
                        name="search"
                        placeholder="Nombre, código SKU o descripción..."
                        value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                    />
                </div>

                <!-- Categoría -->
                <div>
                    <label for="categoria" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Categoría
                    </label>
                    <select
                        id="categoria"
                        name="categoria"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                    >
                        <option value="">Todas las categorías</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $categoria_filter == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Estado -->
                <div>
                    <label for="estado" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Estado
                    </label>
                    <select
                        id="estado"
                        name="estado"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                    >
                        <option value="">Todos los estados</option>
                        <option value="activo" <?php echo $estado_filter === 'activo' ? 'selected' : ''; ?>>Activos</option>
                        <option value="inactivo" <?php echo $estado_filter === 'inactivo' ? 'selected' : ''; ?>>Inactivos</option>
                    </select>
                </div>

                <!-- Stock -->
                <div>
                    <label for="stock" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Stock
                    </label>
                    <select
                        id="stock"
                        name="stock"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                    >
                        <option value="">Todos los niveles</option>
                        <option value="sin_stock" <?php echo $stock_filter === 'sin_stock' ? 'selected' : ''; ?>>Sin stock</option>
                        <option value="stock_bajo" <?php echo $stock_filter === 'stock_bajo' ? 'selected' : ''; ?>>Stock bajo</option>
                        <option value="stock_normal" <?php echo $stock_filter === 'stock_normal' ? 'selected' : ''; ?>>Stock normal</option>
                    </select>
                </div>
            </div>

            <div class="flex flex-wrap gap-2 justify-between items-center">
                <div class="flex gap-2">
                    <button 
                        type="submit" 
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                    >
                        Filtrar
                    </button>
                    
                    <a 
                        href="?" 
                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors"
                    >
                        Limpiar
                    </a>
                </div>

                <div class="flex gap-2">
                    <a 
                        href="?export=csv<?php echo http_build_query(['search' => $search, 'categoria' => $categoria_filter, 'estado' => $estado_filter, 'stock' => $stock_filter], '', '&'); ?>" 
                        class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Exportar CSV
                    </a>
                    
                    <a 
                        href="importar.php" 
                        class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 transition-colors"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                        </svg>
                        Importar CSV
                    </a>
                    
                    <a 
                        href="form.php" 
                        class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Nuevo Producto
                    </a>
                </div>
            </div>
        </form>
        
        <!-- Resultados -->
        <div class="mt-4 text-sm text-gray-600 dark:text-gray-400">
            Mostrando <strong><?php echo count($productos); ?></strong> de <strong><?php echo $total_rows; ?></strong> productos
            <?php if ($search || $categoria_filter || $estado_filter || $stock_filter): ?>
                con filtros aplicados
            <?php endif; ?>
        </div>
    </div>

    <!-- Grid de productos -->
    <?php if (count($productos) === 0): ?>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-8 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
            <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">
                No se encontraron productos
            </h3>
            <p class="mt-2 text-gray-500 dark:text-gray-400">
                <?php if ($search || $categoria_filter || $estado_filter || $stock_filter): ?>
                    Intenta ajustar los filtros o 
                    <a href="?" class="text-blue-600 hover:text-blue-800">ver todos los productos</a>
                <?php else: ?>
                    Comienza agregando tu primer producto al sistema
                <?php endif; ?>
            </p>
            <div class="mt-6">
                <a 
                    href="form.php" 
                    class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    <?php echo $search || $categoria_filter || $estado_filter || $stock_filter ? 'Crear Producto' : 'Crear Primer Producto'; ?>
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php foreach ($productos as $producto): ?>
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden hover:shadow-lg transition-shadow">
                    <!-- Imagen del producto -->
                    <div class="h-48 bg-gray-200 dark:bg-gray-700 relative">
                        <?php if ($producto['imagen']): ?>
                            <img 
                                src="<?php echo getImagenProductoUrl($producto['imagen']); ?>" 
                                alt="<?php echo htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8'); ?>"
                                class="w-full h-full object-cover"
                            />
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center">
                                <svg class="w-16 h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                </svg>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Badge de stock -->
                        <div class="absolute top-2 right-2">
                            <?php
                            $badgeClass = '';
                            $badgeText = '';
                            switch ($producto['estado_stock']) {
                                case 'sin_stock':
                                    $badgeClass = 'bg-red-500 text-white';
                                    $badgeText = 'Sin Stock';
                                    break;
                                case 'stock_bajo':
                                    $badgeClass = 'bg-yellow-500 text-white';
                                    $badgeText = 'Stock Bajo';
                                    break;
                                default:
                                    $badgeClass = 'bg-green-500 text-white';
                                    $badgeText = 'En Stock';
                            }
                            ?>
                            <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $badgeClass; ?>">
                                <?php echo $badgeText; ?>
                            </span>
                        </div>
                        
                        <!-- Badge de estado -->
                        <?php if (!$producto['activo']): ?>
                            <div class="absolute top-2 left-2">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-500 text-white">
                                    Inactivo
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Contenido del producto -->
                    <div class="p-4">
                        <div class="mb-2">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white truncate">
                                <?php echo htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                            </h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                <?php echo htmlspecialchars($producto['categoria_nombre'], ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                        </div>
                        
                        <?php if ($producto['codigo_sku']): ?>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-2 font-mono">
                                SKU: <?php echo htmlspecialchars($producto['codigo_sku'], ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                        <?php endif; ?>
                        
                        <div class="grid grid-cols-2 gap-2 text-sm mb-3">
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Precio:</span>
                                <div class="font-semibold text-gray-900 dark:text-white">
                                    $<?php echo number_format($producto['precio_venta'], 2); ?>
                                </div>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Stock:</span>
                                <div class="font-semibold text-gray-900 dark:text-white">
                                    <?php echo $producto['stock_actual']; ?> <?php echo $producto['unidad_medida']; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Acciones -->
                        <div class="flex space-x-2">
                            <a 
                                href="form.php?id=<?php echo (int)$producto['id']; ?>" 
                                class="flex-1 text-center px-3 py-2 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
                            >
                                Editar
                            </a>
                            
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <a 
                                    href="eliminar.php?id=<?php echo (int)$producto['id']; ?>&csrf_token=<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>" 
                                    onclick="return confirm('¿Está seguro de eliminar este producto?');" 
                                    class="px-3 py-2 text-sm bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors"
                                >
                                    Eliminar
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Paginación -->
        <?php if ($total_pages > 1): ?>
            <div class="mt-8 flex justify-center">
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                    <?php
                    $query_params = ['search' => $search, 'categoria' => $categoria_filter, 'estado' => $estado_filter, 'stock' => $stock_filter];
                    $query_string = http_build_query(array_filter($query_params), '', '&');
                    $query_string = $query_string ? '&' . $query_string : '';
                    ?>
                    
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <a 
                            href="?page=<?php echo $p . $query_string; ?>" 
                            class="<?php echo $p === $page ? 'bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?> relative inline-flex items-center px-4 py-2 border text-sm font-medium"
                        >
                            <?php echo $p; ?>
                        </a>
                    <?php endfor; ?>
                </nav>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>