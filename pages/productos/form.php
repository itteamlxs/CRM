<?php
/**
 * Archivo: pages/productos/form.php
 * Función: Formulario para crear/editar productos
 * Seguridad: Admin y vendedores pueden gestionar productos
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(['admin', 'vendedor']);

$pdo = getPDO();

// Determinar si es edición o creación
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$esEdicion = $id !== null && $id !== false;

// Datos por defecto
$producto = [
    'id' => '',
    'nombre' => '',
    'descripcion' => '',
    'categoria_id' => '',
    'codigo_sku' => '',
    'precio_compra' => '',
    'precio_venta' => '',
    'stock_actual' => 0,
    'stock_minimo' => 0,
    'stock_maximo' => '',
    'unidad_medida' => 'unidad',
    'imagen' => '',
    'activo' => true
];

// Si es edición, cargar datos existentes
if ($esEdicion) {
    $producto_db = getProductoById($pdo, $id);
    if (!$producto_db) {
        header('Location: index.php?error=' . urlencode('Producto no encontrado'));
        exit;
    }
    $producto = $producto_db;
}

// Obtener categorías para el select
$categorias = getCategorias($pdo);

// Verificar que hay categorías disponibles
if (empty($categorias)) {
    header('Location: index.php?error=' . urlencode('No hay categorías disponibles. Crea al menos una categoría antes de agregar productos.'));
    exit;
}

// Obtener token CSRF
$csrf_token = generate_csrf_token();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="max-w-5xl mx-auto px-4 py-6">
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                    <?php echo $esEdicion ? 'Editar Producto' : 'Nuevo Producto'; ?>
                </h1>
                <p class="text-gray-600 dark:text-gray-400 mt-1">
                    <?php echo $esEdicion ? 'Modifica los datos del producto' : 'Completa los datos para crear un nuevo producto'; ?>
                </p>
            </div>
            <a 
                href="index.php" 
                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Volver a Lista
            </a>
        </div>
    </div>

    <!-- Mensajes de error -->
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

    <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
        <form method="POST" action="<?php echo url('forms/procesar_producto.php'); ?>" enctype="multipart/form-data" class="space-y-8 p-6">
            <!-- Token CSRF -->
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>" />
            
            <?php if ($esEdicion): ?>
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($producto['id'], ENT_QUOTES, 'UTF-8'); ?>" />
            <?php endif; ?>

            <!-- Información básica -->
            <div class="border-b border-gray-200 dark:border-gray-700 pb-8">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-6">
                    Información Básica
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Nombre -->
                    <div class="md:col-span-2">
                        <label for="nombre" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Nombre del Producto <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="nombre"
                            name="nombre"
                            value="<?php echo htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8'); ?>"
                            required
                            maxlength="200"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                            placeholder="Ej: Laptop HP Pavilion 15, Mesa de Centro"
                        />
                    </div>

                    <!-- Categoría -->
                    <div>
                        <label for="categoria_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Categoría <span class="text-red-500">*</span>
                        </label>
                        <select
                            id="categoria_id"
                            name="categoria_id"
                            required
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                        >
                            <option value="">Seleccionar categoría</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?php echo $categoria['id']; ?>" <?php echo $producto['categoria_id'] == $categoria['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($categoria['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            <a href="<?php echo url('pages/categorias/form.php'); ?>" class="text-blue-600 hover:text-blue-800" target="_blank">
                                Crear nueva categoría
                            </a>
                        </p>
                    </div>

                    <!-- Código SKU -->
                    <div>
                        <label for="codigo_sku" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Código SKU
                        </label>
                        <input
                            type="text"
                            id="codigo_sku"
                            name="codigo_sku"
                            value="<?php echo htmlspecialchars($producto['codigo_sku'], ENT_QUOTES, 'UTF-8'); ?>"
                            maxlength="50"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                            placeholder="Se generará automáticamente si se deja vacío"
                        />
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Código único de identificación del producto
                        </p>
                    </div>

                    <!-- Estado -->
                    <div>
                        <label for="activo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Estado
                        </label>
                        <select
                            id="activo"
                            name="activo"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                        >
                            <option value="1" <?php echo $producto['activo'] ? 'selected' : ''; ?>>Activo</option>
                            <option value="0" <?php echo !$producto['activo'] ? 'selected' : ''; ?>>Inactivo</option>
                        </select>
                    </div>

                    <!-- Unidad de medida -->
                    <div>
                        <label for="unidad_medida" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Unidad de Medida
                        </label>
                        <select
                            id="unidad_medida"
                            name="unidad_medida"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                        >
                            <option value="unidad" <?php echo $producto['unidad_medida'] === 'unidad' ? 'selected' : ''; ?>>Unidad</option>
                            <option value="kg" <?php echo $producto['unidad_medida'] === 'kg' ? 'selected' : ''; ?>>Kilogramo</option>
                            <option value="gramo" <?php echo $producto['unidad_medida'] === 'gramo' ? 'selected' : ''; ?>>Gramo</option>
                            <option value="litro" <?php echo $producto['unidad_medida'] === 'litro' ? 'selected' : ''; ?>>Litro</option>
                            <option value="metro" <?php echo $producto['unidad_medida'] === 'metro' ? 'selected' : ''; ?>>Metro</option>
                            <option value="caja" <?php echo $producto['unidad_medida'] === 'caja' ? 'selected' : ''; ?>>Caja</option>
                            <option value="paquete" <?php echo $producto['unidad_medida'] === 'paquete' ? 'selected' : ''; ?>>Paquete</option>
                        </select>
                    </div>
                </div>

                <!-- Descripción -->
                <div class="mt-6">
                    <label for="descripcion" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Descripción
                    </label>
                    <textarea
                        id="descripcion"
                        name="descripcion"
                        rows="4"
                        maxlength="1000"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                        placeholder="Describe las características, beneficios y detalles del producto..."
                    ><?php echo htmlspecialchars($producto['descripcion'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Máximo 1000 caracteres
                    </p>
                </div>
            </div>

            <!-- Precios -->
            <div class="border-b border-gray-200 dark:border-gray-700 pb-8">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-6">
                    Información de Precios
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Precio de compra -->
                    <div>
                        <label for="precio_compra" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Precio de Compra
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-500 sm:text-sm">$</span>
                            </div>
                            <input
                                type="number"
                                id="precio_compra"
                                name="precio_compra"
                                value="<?php echo $producto['precio_compra']; ?>"
                                min="0"
                                step="0.01"
                                class="w-full pl-7 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                                placeholder="0.00"
                            />
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Costo del producto para calcular ganancias
                        </p>
                    </div>

                    <!-- Precio de venta -->
                    <div>
                        <label for="precio_venta" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Precio de Venta <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-500 sm:text-sm">$</span>
                            </div>
                            <input
                                type="number"
                                id="precio_venta"
                                name="precio_venta"
                                value="<?php echo $producto['precio_venta']; ?>"
                                required
                                min="0.01"
                                step="0.01"
                                class="w-full pl-7 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                                placeholder="0.00"
                            />
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Precio al que se vende al cliente
                        </p>
                    </div>

                    <!-- Margen calculado -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Margen de Ganancia
                        </label>
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-md p-3">
                            <div id="margen-info" class="text-sm text-gray-600 dark:text-gray-400">
                                <div>Ganancia: <span id="ganancia-valor">$0.00</span></div>
                                <div>Porcentaje: <span id="porcentaje-valor">0%</span></div>
                            </div>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Se calcula automáticamente
                        </p>
                    </div>
                </div>
            </div>

            <!-- Inventario -->
            <div class="border-b border-gray-200 dark:border-gray-700 pb-8">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-6">
                    Control de Inventario
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Stock actual -->
                    <div>
                        <label for="stock_actual" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Stock Actual <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="number"
                            id="stock_actual"
                            name="stock_actual"
                            value="<?php echo $producto['stock_actual']; ?>"
                            required
                            min="0"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                        />
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Cantidad disponible en inventario
                        </p>
                    </div>

                    <!-- Stock mínimo -->
                    <div>
                        <label for="stock_minimo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Stock Mínimo <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="number"
                            id="stock_minimo"
                            name="stock_minimo"
                            value="<?php echo $producto['stock_minimo']; ?>"
                            required
                            min="0"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                        />
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Nivel mínimo antes de reabastecer
                        </p>
                    </div>

                    <!-- Stock máximo -->
                    <div>
                        <label for="stock_maximo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Stock Máximo
                        </label>
                        <input
                            type="number"
                            id="stock_maximo"
                            name="stock_maximo"
                            value="<?php echo $producto['stock_maximo']; ?>"
                            min="0"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                        />
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Nivel máximo de inventario (opcional)
                        </p>
                    </div>
                </div>

                <!-- Alertas de stock -->
                <div class="mt-4" id="stock-alerts"></div>
            </div>

            <!-- Imagen -->
            <div class="border-b border-gray-200 dark:border-gray-700 pb-8">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-6">
                    Imagen del Producto
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Vista previa actual -->
                    <?php if ($esEdicion && $producto['imagen']): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Imagen Actual
                            </label>
                            <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-4">
                                <img 
                                    src="<?php echo getImagenProductoUrl($producto['imagen']); ?>" 
                                    alt="Imagen actual del producto"
                                    class="w-full h-48 object-cover rounded-lg"
                                />
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                                    <?php echo htmlspecialchars($producto['imagen'], ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                                <div class="mt-2">
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" name="eliminar_imagen" value="1" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                        <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Eliminar imagen actual</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Subir nueva imagen -->
                    <div>
                        <label for="imagen" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            <?php echo $esEdicion && $producto['imagen'] ? 'Nueva Imagen' : 'Imagen del Producto'; ?>
                        </label>
                        <div class="border-2 border-gray-300 border-dashed rounded-lg p-6 text-center hover:border-gray-400 focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-200">
                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <div class="mt-4">
                                <label for="imagen" class="cursor-pointer">
                                    <span class="mt-2 block text-sm font-medium text-gray-900 dark:text-white">
                                        Seleccionar archivo
                                    </span>
                                    <input
                                        type="file"
                                        id="imagen"
                                        name="imagen"
                                        accept="image/*"
                                        class="sr-only"
                                    />
                                </label>
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    PNG, JPG, GIF hasta 5MB
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Información adicional (solo en edición) -->
            <?php if ($esEdicion): ?>
                <div class="border-b border-gray-200 dark:border-gray-700 pb-8">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-6">
                        Información Adicional
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">ID</dt>
                            <dd class="text-lg font-semibold text-gray-900 dark:text-white"><?php echo $producto['id']; ?></dd>
                        </div>
                        
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Fecha Creación</dt>
                            <dd class="text-lg font-semibold text-gray-900 dark:text-white">
                                <?php echo date('d/m/Y H:i', strtotime($producto['fecha_creacion'])); ?>
                            </dd>
                        </div>
                        
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Última Actualización</dt>
                            <dd class="text-lg font-semibold text-gray-900 dark:text-white">
                                <?php echo date('d/m/Y H:i', strtotime($producto['fecha_actualizacion'])); ?>
                            </dd>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Botones de acción -->
            <div class="flex justify-end space-x-4 pt-4">
                <a 
                    href="index.php" 
                    class="px-4 py-2 text-gray-600 bg-gray-200 rounded-md hover:bg-gray-300 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
                >
                    Cancelar
                </a>
                
                <button 
                    type="submit" 
                    class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                >
                    <?php echo $esEdicion ? 'Actualizar Producto' : 'Crear Producto'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Calcular margen de ganancia automáticamente
function calcularMargen() {
    const precioCompra = parseFloat(document.getElementById('precio_compra').value) || 0;
    const precioVenta = parseFloat(document.getElementById('precio_venta').value) || 0;
    
    const ganancia = precioVenta - precioCompra;
    const porcentaje = precioCompra > 0 ? ((ganancia / precioCompra) * 100) : 0;
    
    document.getElementById('ganancia-valor').textContent = '$' + ganancia.toFixed(2);
    document.getElementById('porcentaje-valor').textContent = porcentaje.toFixed(1) + '%';
    
    // Cambiar color según el margen
    const margenInfo = document.getElementById('margen-info');
    if (porcentaje < 10) {
        margenInfo.className = 'text-sm text-red-600 dark:text-red-400';
    } else if (porcentaje < 25) {
        margenInfo.className = 'text-sm text-yellow-600 dark:text-yellow-400';
    } else {
        margenInfo.className = 'text-sm text-green-600 dark:text-green-400';
    }
}

// Validar stock
function validarStock() {
    const stockActual = parseInt(document.getElementById('stock_actual').value) || 0;
    const stockMinimo = parseInt(document.getElementById('stock_minimo').value) || 0;
    const stockMaximo = parseInt(document.getElementById('stock_maximo').value) || 0;
    
    const alertsDiv = document.getElementById('stock-alerts');
    alertsDiv.innerHTML = '';
    
    if (stockActual <= 0) {
        alertsDiv.innerHTML = '<div class="bg-red-50 border border-red-200 rounded-lg p-3"><span class="text-sm text-red-800">Sin stock disponible</span></div>';
    } else if (stockActual <= stockMinimo) {
        alertsDiv.innerHTML = '<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3"><span class="text-sm text-yellow-800">Stock bajo - considera reabastecer</span></div>';
    } else if (stockMaximo > 0 && stockActual > stockMaximo) {
        alertsDiv.innerHTML = '<div class="bg-blue-50 border border-blue-200 rounded-lg p-3"><span class="text-sm text-blue-800">Stock por encima del máximo recomendado</span></div>';
    }
}

// Vista previa de imagen
document.getElementById('imagen').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            // Crear vista previa
            const preview = document.createElement('img');
            preview.src = e.target.result;
            preview.className = 'mt-4 max-w-full h-32 object-cover rounded-lg';
            
            // Remover vista previa anterior
            const existingPreview = document.querySelector('.image-preview');
            if (existingPreview) {
                existingPreview.remove();
            }
            
            // Agregar nueva vista previa
            preview.className += ' image-preview';
            document.getElementById('imagen').parentNode.parentNode.appendChild(preview);
        };
        reader.readAsDataURL(file);
    }
});

// Event listeners
document.getElementById('precio_compra').addEventListener('input', calcularMargen);
document.getElementById('precio_venta').addEventListener('input', calcularMargen);
document.getElementById('stock_actual').addEventListener('input', validarStock);
document.getElementById('stock_minimo').addEventListener('input', validarStock);
document.getElementById('stock_maximo').addEventListener('input', validarStock);

// Calcular al cargar la página
calcularMargen();
validarStock();

// Validación del formulario
document.querySelector('form').addEventListener('submit', function(e) {
    const nombre = document.getElementById('nombre').value.trim();
    const categoria = document.getElementById('categoria_id').value;
    const precioVenta = parseFloat(document.getElementById('precio_venta').value);
    
    if (!nombre) {
        e.preventDefault();
        alert('El nombre del producto es obligatorio');
        document.getElementById('nombre').focus();
        return false;
    }
    
    if (!categoria) {
        e.preventDefault();
        alert('Debe seleccionar una categoría');
        document.getElementById('categoria_id').focus();
        return false;
    }
    
    if (!precioVenta || precioVenta <= 0) {
        e.preventDefault();
        alert('El precio de venta debe ser mayor a 0');
        document.getElementById('precio_venta').focus();
        return false;
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>