<?php
/**
 * Archivo: pages/productos/importar.php
 * Función: Importar productos desde archivo CSV
 * Seguridad: Solo admin puede importar productos
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Solo admin puede importar productos
requireRole(['admin']);

$pdo = getPDO();

// Obtener categorías para mapeo
$categorias = getCategorias($pdo);

// Verificar que hay categorías disponibles
if (empty($categorias)) {
    header('Location: index.php?error=' . urlencode('No hay categorías disponibles. Crea al menos una categoría antes de importar productos.'));
    exit;
}

// Obtener token CSRF
$csrf_token = generate_csrf_token();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 py-6">
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                    Importar Productos
                </h1>
                <p class="text-gray-600 dark:text-gray-400 mt-1">
                    Importa múltiples productos desde un archivo CSV
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

    <!-- Instrucciones -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
        <h3 class="text-lg font-medium text-blue-900 mb-4">
            Instrucciones para la Importación
        </h3>
        
        <div class="text-sm text-blue-800 space-y-3">
            <div>
                <strong>Formato del archivo CSV:</strong>
                <ul class="list-disc list-inside mt-2 space-y-1">
                    <li>El archivo debe tener extensión .csv</li>
                    <li>Primera fila debe contener los encabezados</li>
                    <li>Codificación UTF-8 recomendada</li>
                    <li>Separador: coma (,)</li>
                </ul>
            </div>
            
            <div>
                <strong>Columnas requeridas:</strong>
                <ul class="list-disc list-inside mt-2 space-y-1">
                    <li><code>nombre</code> - Nombre del producto (obligatorio)</li>
                    <li><code>categoria</code> - Nombre de la categoría (debe existir)</li>
                    <li><code>precio_venta</code> - Precio de venta (obligatorio)</li>
                </ul>
            </div>
            
            <div>
                <strong>Columnas opcionales:</strong>
                <ul class="list-disc list-inside mt-2 space-y-1">
                    <li><code>descripcion</code> - Descripción del producto</li>
                    <li><code>codigo_sku</code> - Código SKU (se genera automáticamente si está vacío)</li>
                    <li><code>precio_compra</code> - Precio de compra</li>
                    <li><code>stock_actual</code> - Stock inicial (por defecto 0)</li>
                    <li><code>stock_minimo</code> - Stock mínimo (por defecto 0)</li>
                    <li><code>stock_maximo</code> - Stock máximo</li>
                    <li><code>unidad_medida</code> - Unidad (unidad, kg, gramo, litro, metro, caja, paquete)</li>
                </ul>
            </div>
        </div>
        
        <div class="mt-4">
            <a 
                href="<?php echo url('assets/templates/template_productos.csv'); ?>" 
                class="inline-flex items-center px-3 py-2 border border-blue-300 rounded-md text-sm font-medium text-blue-700 bg-blue-100 hover:bg-blue-200"
                download
            >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Descargar plantilla CSV
            </a>
        </div>
    </div>

    <!-- Formulario de importación -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
        <form method="POST" action="<?php echo url('forms/procesar_importacion.php'); ?>" enctype="multipart/form-data" class="space-y-6 p-6">
            <!-- Token CSRF -->
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>" />

            <!-- Selección de archivo -->
            <div>
                <label for="archivo_csv" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Archivo CSV <span class="text-red-500">*</span>
                </label>
                <div class="border-2 border-gray-300 border-dashed rounded-lg p-6 text-center hover:border-gray-400 focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-200">
                    <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <div class="mt-4">
                        <label for="archivo_csv" class="cursor-pointer">
                            <span class="mt-2 block text-sm font-medium text-gray-900 dark:text-white">
                                Seleccionar archivo CSV
                            </span>
                            <input
                                type="file"
                                id="archivo_csv"
                                name="archivo_csv"
                                accept=".csv"
                                required
                                class="sr-only"
                            />
                        </label>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            Solo archivos CSV hasta 10MB
                        </p>
                    </div>
                </div>
                <div id="archivo-info" class="mt-2 text-sm text-gray-600 dark:text-gray-400 hidden"></div>
            </div>

            <!-- Opciones de importación -->
            <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                    Opciones de Importación
                </h3>
                
                <div class="space-y-4">
                    <!-- Categoría por defecto -->
                    <div>
                        <label for="categoria_defecto" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Categoría por Defecto
                        </label>
                        <select
                            id="categoria_defecto"
                            name="categoria_defecto"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                        >
                            <option value="">Sin categoría por defecto</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?php echo $categoria['id']; ?>">
                                    <?php echo htmlspecialchars($categoria['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Se usará si no se especifica categoría en el CSV o si no existe
                        </p>
                    </div>

                    <!-- Comportamiento en duplicados -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Si encuentra productos duplicados (mismo nombre):
                        </label>
                        <div class="space-y-2">
                            <label class="inline-flex items-center">
                                <input type="radio" name="duplicados" value="omitir" checked class="form-radio text-blue-600">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Omitir (no importar duplicados)</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" name="duplicados" value="actualizar" class="form-radio text-blue-600">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Actualizar productos existentes</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" name="duplicados" value="crear" class="form-radio text-blue-600">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Crear de todos modos (con nombre modificado)</span>
                            </label>
                        </div>
                    </div>

                    <!-- Estado inicial -->
                    <div>
                        <label for="estado_inicial" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Estado inicial de productos importados
                        </label>
                        <select
                            id="estado_inicial"
                            name="estado_inicial"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                        >
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
                    </div>

                    <!-- Vista previa -->
                    <div>
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="vista_previa" value="1" checked class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Mostrar vista previa antes de importar</span>
                        </label>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Recomendado para verificar los datos antes de la importación final
                        </p>
                    </div>
                </div>
            </div>

            <!-- Botones de acción -->
            <div class="flex justify-end space-x-4 pt-4 border-t border-gray-200 dark:border-gray-700">
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
                    Procesar Archivo
                </button>
            </div>
        </form>
    </div>

    <!-- Lista de categorías disponibles -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg mt-6">
        <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                Categorías Disponibles
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
                <?php foreach ($categorias as $categoria): ?>
                    <div class="bg-gray-100 dark:bg-gray-700 rounded-md px-3 py-2">
                        <span class="text-sm text-gray-700 dark:text-gray-300">
                            <?php echo htmlspecialchars($categoria['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-4">
                Estas son las categorías que puedes usar en la columna "categoria" del CSV. 
                <a href="<?php echo url('pages/categorias/form.php'); ?>" class="text-blue-600 hover:text-blue-800" target="_blank">
                    Crear nueva categoría
                </a>
            </p>
        </div>
    </div>
</div>

<script>
// Mostrar información del archivo seleccionado
document.getElementById('archivo_csv').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const infoDiv = document.getElementById('archivo-info');
    
    if (file) {
        const size = (file.size / 1024 / 1024).toFixed(2);
        infoDiv.innerHTML = `
            <strong>Archivo seleccionado:</strong> ${file.name}<br>
            <strong>Tamaño:</strong> ${size} MB<br>
            <strong>Tipo:</strong> ${file.type || 'text/csv'}
        `;
        infoDiv.classList.remove('hidden');
        
        // Validar tamaño
        if (file.size > 10 * 1024 * 1024) {
            infoDiv.innerHTML += '<br><span class="text-red-600">Advertencia: El archivo es mayor a 10MB</span>';
        }
        
        // Validar extensión
        if (!file.name.toLowerCase().endsWith('.csv')) {
            infoDiv.innerHTML += '<br><span class="text-red-600">Advertencia: El archivo no tiene extensión .csv</span>';
        }
    } else {
        infoDiv.classList.add('hidden');
    }
});

// Validación del formulario
document.querySelector('form').addEventListener('submit', function(e) {
    const archivo = document.getElementById('archivo_csv').files[0];
    
    if (!archivo) {
        e.preventDefault();
        alert('Debe seleccionar un archivo CSV');
        document.getElementById('archivo_csv').focus();
        return false;
    }
    
    if (archivo.size > 10 * 1024 * 1024) {
        if (!confirm('El archivo es mayor a 10MB. ¿Está seguro de continuar? La importación podría tomar más tiempo.')) {
            e.preventDefault();
            return false;
        }
    }
    
    if (!archivo.name.toLowerCase().endsWith('.csv')) {
        if (!confirm('El archivo no tiene extensión .csv. ¿Está seguro de que es un archivo CSV válido?')) {
            e.preventDefault();
            return false;
        }
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>