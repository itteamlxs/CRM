<?php
/**
 * Archivo: pages/productos/preview_importacion.php
 * Función: Mostrar vista previa de productos a importar antes de confirmar
 * Seguridad: Solo admin puede importar productos
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Solo admin puede importar productos
requireRole(['admin']);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar que hay datos de preview en sesión
if (!isset($_SESSION['importacion_preview'])) {
    header('Location: importar.php?error=' . urlencode('No hay datos de importación para mostrar'));
    exit;
}

$preview = $_SESSION['importacion_preview'];
$productosImportar = $preview['productos_importar'] ?? [];
$productosActualizar = $preview['productos_actualizar'] ?? [];
$productosOmitidos = $preview['productos_omitidos'] ?? [];
$warnings = $preview['warnings'] ?? [];
$opciones = $preview['opciones'] ?? [];

$pdo = getPDO();

// Obtener nombre de categoría por defecto
$categoriaDefectoNombre = '';
if ($opciones['categoria_defecto']) {
    $stmt = $pdo->prepare("SELECT nombre FROM categorias WHERE id = :id");
    $stmt->execute([':id' => $opciones['categoria_defecto']]);
    $categoriaDefectoNombre = $stmt->fetchColumn() ?: '';
}

// Obtener token CSRF para confirmación
$csrf_token = generate_csrf_token();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 py-6">
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                    Vista Previa de Importación
                </h1>
                <p class="text-gray-600 dark:text-gray-400 mt-1">
                    Revisa los productos antes de confirmar la importación
                </p>
            </div>
            <div class="flex space-x-2">
                <a 
                    href="importar.php" 
                    class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Volver a Importar
                </a>
                
                <a 
                    href="index.php" 
                    class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                >
                    Cancelar
                </a>
            </div>
        </div>
    </div>

    <!-- Resumen de la importación -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <!-- Productos a importar -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="h-8 w-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                </div>
                <div class="ml-4">
                    <div class="text-2xl font-bold text-blue-900"><?php echo count($productosImportar); ?></div>
                    <div class="text-sm text-blue-700">Productos nuevos</div>
                </div>
            </div>
        </div>

        <!-- Productos a actualizar -->
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="h-8 w-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                </div>
                <div class="ml-4">
                    <div class="text-2xl font-bold text-yellow-900"><?php echo count($productosActualizar); ?></div>
                    <div class="text-sm text-yellow-700">Productos a actualizar</div>
                </div>
            </div>
        </div>

        <!-- Productos omitidos -->
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="h-8 w-8 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18.364 5.636M5.636 18.364l12.728-12.728" />
                    </svg>
                </div>
                <div class="ml-4">
                    <div class="text-2xl font-bold text-gray-900"><?php echo count($productosOmitidos); ?></div>
                    <div class="text-sm text-gray-700">Productos omitidos</div>
                </div>
            </div>
        </div>

        <!-- Advertencias -->
        <div class="bg-orange-50 border border-orange-200 rounded-lg p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="h-8 w-8 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                    </svg>
                </div>
                <div class="ml-4">
                    <div class="text-2xl font-bold text-orange-900"><?php echo count($warnings); ?></div>
                    <div class="text-sm text-orange-700">Advertencias</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Configuración de importación -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
            Configuración de Importación
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
            <div>
                <span class="font-medium text-gray-700 dark:text-gray-300">Categoría por defecto:</span>
                <div class="text-gray-900 dark:text-white">
                    <?php echo $categoriaDefectoNombre ?: 'Ninguna'; ?>
                </div>
            </div>
            <div>
                <span class="font-medium text-gray-700 dark:text-gray-300">Productos duplicados:</span>
                <div class="text-gray-900 dark:text-white">
                    <?php
                    $duplicadosTexto = [
                        'omitir' => 'Omitir',
                        'actualizar' => 'Actualizar existentes',
                        'crear' => 'Crear de todos modos'
                    ];
                    echo $duplicadosTexto[$opciones['duplicados']] ?? 'Desconocido';
                    ?>
                </div>
            </div>
            <div>
                <span class="font-medium text-gray-700 dark:text-gray-300">Estado inicial:</span>
                <div class="text-gray-900 dark:text-white">
                    <?php echo $opciones['estado_inicial'] ? 'Activo' : 'Inactivo'; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Advertencias -->
    <?php if (!empty($warnings)): ?>
        <div class="bg-orange-50 border border-orange-200 rounded-lg p-4 mb-6">
            <div class="flex">
                <svg class="w-5 h-5 text-orange-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-orange-800">Advertencias de Importación</h3>
                    <div class="mt-2 text-sm text-orange-700">
                        <ul class="list-disc list-inside space-y-1">
                            <?php foreach (array_slice($warnings, 0, 10) as $warning): ?>
                                <li><?php echo htmlspecialchars($warning, ENT_QUOTES, 'UTF-8'); ?></li>
                            <?php endforeach; ?>
                            <?php if (count($warnings) > 10): ?>
                                <li>... y <?php echo count($warnings) - 10; ?> advertencias más</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tabs para diferentes secciones -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
        <div class="border-b border-gray-200 dark:border-gray-700">
            <nav class="-mb-px flex space-x-8 px-6" aria-label="Tabs">
                <button 
                    onclick="showTab('nuevos')" 
                    id="tab-nuevos"
                    class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm tab-button active"
                >
                    Productos Nuevos (<?php echo count($productosImportar); ?>)
                </button>
                
                <?php if (!empty($productosActualizar)): ?>
                    <button 
                        onclick="showTab('actualizar')" 
                        id="tab-actualizar"
                        class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm tab-button"
                    >
                        Productos a Actualizar (<?php echo count($productosActualizar); ?>)
                    </button>
                <?php endif; ?>
                
                <?php if (!empty($productosOmitidos)): ?>
                    <button 
                        onclick="showTab('omitidos')" 
                        id="tab-omitidos"
                        class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm tab-button"
                    >
                        Productos Omitidos (<?php echo count($productosOmitidos); ?>)
                    </button>
                <?php endif; ?>
            </nav>
        </div>

        <!-- Contenido de tabs -->
        <div class="p-6">
            <!-- Tab: Productos nuevos -->
            <div id="content-nuevos" class="tab-content">
                <?php if (empty($productosImportar)): ?>
                    <p class="text-gray-500 dark:text-gray-400 text-center py-8">
                        No hay productos nuevos para importar
                    </p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Línea</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Nombre</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Categoría</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">SKU</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Precio Venta</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Stock</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach (array_slice($productosImportar, 0, 50) as $producto): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $producto['linea']; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                            <?php echo htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php
                                            $stmt = $pdo->prepare("SELECT nombre FROM categorias WHERE id = :id");
                                            $stmt->execute([':id' => $producto['categoria_id']]);
                                            echo htmlspecialchars($stmt->fetchColumn() ?: 'Sin categoría', ENT_QUOTES, 'UTF-8');
                                            ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono">
                                            <?php echo htmlspecialchars($producto['codigo_sku'] ?: 'Auto-generado', ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                            $<?php echo number_format($producto['precio_venta'], 2); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo $producto['stock_actual']; ?> <?php echo $producto['unidad_medida']; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if (count($productosImportar) > 50): ?>
                            <div class="mt-4 text-sm text-gray-500 text-center">
                                Mostrando los primeros 50 productos de <?php echo count($productosImportar); ?> total
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tab: Productos a actualizar -->
            <?php if (!empty($productosActualizar)): ?>
                <div id="content-actualizar" class="tab-content hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Línea</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Nombre Existente</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Precio Actual</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Precio Nuevo</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Cambios</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach ($productosActualizar as $item): ?>
                                    <?php
                                    $producto = $item['datos'];
                                    $productoExistente = getProductoById($pdo, $item['id']);
                                    ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $item['linea']; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                            <?php echo htmlspecialchars($productoExistente['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            $<?php echo number_format($productoExistente['precio_venta'], 2); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                            $<?php echo number_format(floatval($producto['precio_venta']), 2); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            <?php
                                            $cambios = [];
                                            if ($productoExistente['precio_venta'] != floatval($producto['precio_venta'])) {
                                                $cambios[] = 'Precio';
                                            }
                                            if ($productoExistente['stock_actual'] != intval($producto['stock_actual'] ?? 0)) {
                                                $cambios[] = 'Stock';
                                            }
                                            if ($productoExistente['categoria_id'] != $item['categoria_id']) {
                                                $cambios[] = 'Categoría';
                                            }
                                            echo $cambios ? implode(', ', $cambios) : 'Otros cambios';
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Tab: Productos omitidos -->
            <?php if (!empty($productosOmitidos)): ?>
                <div id="content-omitidos" class="tab-content hidden">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($productosOmitidos as $nombre): ?>
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                    <?php echo htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Ya existe en el sistema
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Botones de confirmación -->
    <div class="mt-8 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
        <div class="flex justify-between items-center">
            <div class="text-sm text-gray-600 dark:text-gray-400">
                <strong>Total a procesar:</strong> 
                <?php echo count($productosImportar) + count($productosActualizar); ?> productos
                <?php if (count($productosOmitidos) > 0): ?>
                    (<?php echo count($productosOmitidos); ?> omitidos)
                <?php endif; ?>
            </div>
            
            <div class="flex space-x-4">
                <a 
                    href="importar.php" 
                    class="px-4 py-2 text-gray-600 bg-gray-200 rounded-md hover:bg-gray-300 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
                >
                    Modificar Importación
                </a>
                
                <form method="POST" action="<?php echo url('forms/confirmar_importacion.php'); ?>" class="inline">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>" />
                    <button 
                        type="submit" 
                        onclick="return confirm('¿Está seguro de proceder con la importación? Esta acción no se puede deshacer.')"
                        class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors"
                    >
                        Confirmar Importación
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function showTab(tabName) {
    // Ocultar todos los contenidos
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    // Remover clase active de todos los tabs
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('active', 'border-blue-500', 'text-blue-600');
        button.classList.add('border-transparent', 'text-gray-500');
    });
    
    // Mostrar contenido seleccionado
    document.getElementById('content-' + tabName).classList.remove('hidden');
    
    // Activar tab seleccionado
    const activeTab = document.getElementById('tab-' + tabName);
    activeTab.classList.add('active', 'border-blue-500', 'text-blue-600');
    activeTab.classList.remove('border-transparent', 'text-gray-500');
}

// Activar primer tab por defecto
document.addEventListener('DOMContentLoaded', function() {
    showTab('nuevos');
});
</script>

<style>
.tab-button.active {
    border-color: #3B82F6;
    color: #2563EB;
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>