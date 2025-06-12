<?php
/**
 * Archivo: pages/clientes/index.php - REPARADO CON FILTROS
 * Función: Lista clientes con búsqueda PURE CSS/JS + Filtros (sin AJAX)
 * Mejora: Búsqueda instantánea + filtro activos/inactivos
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(['admin', 'vendedor']);

$pdo = getPDO();

// Parámetros de búsqueda tradicional (solo para backend si es necesario)
$search_backend = trim($_GET['search'] ?? '');
$mostrar_eliminados = isset($_GET['eliminados']) && $_GET['eliminados'] === '1' && $_SESSION['role'] === 'admin';
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$per_page = 50; // Aumentamos para cargar más clientes para búsqueda frontend
$offset = ($page - 1) * $per_page;

// Construir condición WHERE
$where_conditions = [];
$search_params = [];

// FILTRO PRINCIPAL: Excluir eliminados (a menos que admin quiera verlos)
if (!$mostrar_eliminados) {
    $where_conditions[] = "eliminado = FALSE";
} else {
    $where_conditions[] = "eliminado = TRUE";
}

// Filtro de búsqueda backend (solo si es necesario)
if ($search_backend !== '') {
    $where_conditions[] = "(nombre LIKE :search OR email LIKE :search OR telefono LIKE :search)";
    $search_params[':search'] = "%$search_backend%";
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Contar total para paginación
$count_sql = "SELECT COUNT(*) FROM clientes $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($search_params);
$total_rows = $count_stmt->fetchColumn();
$total_pages = ceil($total_rows / $per_page);

// Obtener clientes
if ($mostrar_eliminados) {
    $sql = "
        SELECT 
            c.id, c.nombre, c.email, c.telefono, c.estado,
            c.fecha_eliminacion, u.username as eliminado_por_usuario
        FROM clientes c 
        LEFT JOIN usuarios u ON c.eliminado_por = u.id
        $where_clause 
        ORDER BY c.fecha_eliminacion DESC 
        LIMIT $per_page OFFSET $offset
    ";
} else {
    $sql = "SELECT id, nombre, email, telefono, estado FROM clientes $where_clause ORDER BY nombre ASC LIMIT $per_page OFFSET $offset";
}
$stmt = $pdo->prepare($sql);
$stmt->execute($search_params);
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Exportar CSV si solicitado
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=clientes.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Nombre', 'Email', 'Teléfono', 'Estado']);
    foreach ($clientes as $c) {
        fputcsv($output, [
            $c['id'],
            $c['nombre'],
            $c['email'],
            $c['telefono'],
            $c['estado'],
        ]);
    }
    fclose($output);
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 py-6">
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                    <?php if ($mostrar_eliminados): ?>
                        📋 Clientes Eliminados
                    <?php else: ?>
                        Lista de Clientes
                    <?php endif; ?>
                </h1>
                <p class="text-gray-600 dark:text-gray-400 mt-1">
                    <?php if ($mostrar_eliminados): ?>
                        Clientes eliminados del sistema (solo visible para administradores)
                    <?php else: ?>
                        Gestiona y visualiza todos los clientes activos del sistema
                    <?php endif; ?>
                </p>
            </div>
            
            <!-- Toggle para admin: ver eliminados -->
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <div class="flex items-center space-x-2">
                    <?php if ($mostrar_eliminados): ?>
                        <a href="?" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                            </svg>
                            Ver Clientes Activos
                        </a>
                    <?php else: ?>
                        <a href="?eliminados=1" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Ver Eliminados (<?php 
                                $stmt = $pdo->prepare("SELECT COUNT(*) FROM clientes WHERE eliminado = TRUE");
                                $stmt->execute();
                                echo $stmt->fetchColumn();
                            ?>)
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Barra de búsqueda INSTANTÁNEA con FILTROS -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
        <div class="flex flex-col lg:flex-row gap-4 items-center justify-between">
            <!-- Búsqueda instantánea -->
            <div class="flex-1 max-w-md">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input
                        type="text"
                        id="busqueda-instantanea"
                        placeholder="🔍 Buscar por nombre, email o teléfono..."
                        class="block w-full pl-10 pr-10 py-3 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        autocomplete="off"
                        spellcheck="false"
                    />
                    <!-- Botón limpiar -->
                    <button
                        type="button"
                        id="limpiar-busqueda"
                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 hidden"
                        onclick="limpiarTodo()"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Filtros -->
            <div class="flex items-center gap-4">
                <!-- Filtro por estado -->
                <div class="flex items-center gap-2">
                    <label for="filtro-estado" class="text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">
                        Estado:
                    </label>
                    <select 
                        id="filtro-estado" 
                        class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                    >
                        <option value="">Todos</option>
                        <option value="activo">✅ Activos</option>
                        <option value="inactivo">❌ Inactivos</option>
                    </select>
                </div>

                <!-- Contador de resultados -->
                <div id="contador-resultados" class="text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">
                    <span id="total-visible"><?php echo count($clientes); ?></span> de 
                    <span id="total-clientes"><?php echo count($clientes); ?></span> clientes
                </div>
            </div>

            <!-- Acciones -->
            <div class="flex gap-2">
                <a 
                    href="?export=csv<?php echo $search_backend !== '' ? '&search=' . urlencode($search_backend) : ''; ?>" 
                    class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Exportar CSV
                </a>
                
                <a 
                    href="<?php echo url('forms/form_cliente.php'); ?>" 
                    class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Nuevo Cliente
                </a>
            </div>
        </div>
        
        <!-- Mensaje cuando no hay resultados -->
        <div id="sin-resultados" class="hidden mt-4 text-center py-8">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <h3 class="mt-2 text-lg font-medium text-gray-900 dark:text-white">Sin resultados</h3>
            <p class="mt-1 text-gray-500 dark:text-gray-400">No se encontraron clientes con los filtros aplicados.</p>
        </div>
    </div>

    <!-- Tabla de clientes -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
        <?php if (count($clientes) === 0): ?>
            <div class="p-8 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">
                    No se encontraron clientes
                </h3>
                <p class="mt-2 text-gray-500 dark:text-gray-400">
                    Comienza agregando tu primer cliente al sistema
                </p>
                <div class="mt-6">
                    <a 
                        href="<?php echo url('forms/form_cliente.php'); ?>" 
                        class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Crear Primer Cliente
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Nombre
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Email
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Teléfono
                            </th>
                            <?php if ($mostrar_eliminados): ?>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Fecha Eliminación
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Eliminado Por
                                </th>
                            <?php else: ?>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Estado
                                </th>
                            <?php endif; ?>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Acciones
                            </th>
                        </tr>
                    </thead>
                    <tbody id="tabla-clientes" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($clientes as $cliente): ?>
                            <tr class="cliente-row hover:bg-gray-50 dark:hover:bg-gray-700 <?php echo $mostrar_eliminados ? 'opacity-75' : ''; ?>"
                                data-nombre="<?php echo htmlspecialchars(strtolower($cliente['nombre']), ENT_QUOTES, 'UTF-8'); ?>"
                                data-email="<?php echo htmlspecialchars(strtolower($cliente['email']), ENT_QUOTES, 'UTF-8'); ?>"
                                data-telefono="<?php echo htmlspecialchars(strtolower($cliente['telefono'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-estado="<?php echo htmlspecialchars($cliente['estado'], ENT_QUOTES, 'UTF-8'); ?>"
                            >
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        <span class="searchable-nombre"><?php echo htmlspecialchars($cliente['nombre'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php if ($mostrar_eliminados): ?>
                                            <span class="ml-2 inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                                Eliminado
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-white">
                                        <?php if (!$mostrar_eliminados): ?>
                                            <a href="mailto:<?php echo htmlspecialchars($cliente['email'], ENT_QUOTES, 'UTF-8'); ?>" class="text-blue-600 hover:text-blue-800">
                                                <span class="searchable-email"><?php echo htmlspecialchars($cliente['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            </a>
                                        <?php else: ?>
                                            <span class="searchable-email"><?php echo htmlspecialchars($cliente['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-white">
                                        <?php if ($cliente['telefono']): ?>
                                            <?php if (!$mostrar_eliminados): ?>
                                                <a href="tel:<?php echo htmlspecialchars($cliente['telefono'], ENT_QUOTES, 'UTF-8'); ?>" class="text-blue-600 hover:text-blue-800">
                                                    <span class="searchable-telefono"><?php echo htmlspecialchars($cliente['telefono'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                </a>
                                            <?php else: ?>
                                                <span class="searchable-telefono"><?php echo htmlspecialchars($cliente['telefono'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-gray-500">-</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                
                                <?php if ($mostrar_eliminados): ?>
                                    <!-- Fecha de eliminación -->
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $cliente['fecha_eliminacion'] ? date('d/m/Y H:i', strtotime($cliente['fecha_eliminacion'])) : '-'; ?>
                                    </td>
                                    <!-- Eliminado por -->
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($cliente['eliminado_por_usuario'] ?? 'Desconocido', ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                <?php else: ?>
                                    <!-- Estado para clientes activos -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($cliente['estado'] === 'activo'): ?>
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                Activo
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                                Inactivo
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                
                                <!-- Acciones -->
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end space-x-2">
                                        <?php if ($mostrar_eliminados): ?>
                                            <!-- Acciones para clientes eliminados -->
                                            <a 
                                                href="restaurar.php?id=<?php echo (int)$cliente['id']; ?>&csrf_token=<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>" 
                                                onclick="return confirm('¿Está seguro de restaurar este cliente?');" 
                                                class="text-green-600 hover:text-green-900 transition-colors"
                                                title="Restaurar cliente"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                </svg>
                                            </a>
                                        <?php else: ?>
                                            <!-- Acciones para clientes activos -->
                                            <a 
                                                href="<?php echo url('forms/form_cliente.php?id=' . (int)$cliente['id']); ?>" 
                                                class="text-blue-600 hover:text-blue-900 transition-colors"
                                                title="Editar"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </a>
                                            
                                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                                <a 
                                                    href="eliminar.php?id=<?php echo (int)$cliente['id']; ?>&csrf_token=<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>" 
                                                    onclick="return confirm('¿Está seguro de eliminar este cliente?');" 
                                                    class="text-red-600 hover:text-red-900 transition-colors"
                                                    title="Eliminar"
                                                >
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <?php if ($total_pages > 1): ?>
                <div class="bg-white dark:bg-gray-800 px-4 py-3 border-t border-gray-200 dark:border-gray-700 sm:px-6">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 flex justify-between sm:hidden">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?><?php echo $search_backend !== '' ? '&search=' . urlencode($search_backend) : ''; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    Anterior
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?><?php echo $search_backend !== '' ? '&search=' . urlencode($search_backend) : ''; ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    Siguiente
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700 dark:text-gray-300">
                                    Mostrando
                                    <span class="font-medium"><?php echo $offset + 1; ?></span>
                                    de
                                    <span class="font-medium"><?php echo min($offset + $per_page, $total_rows); ?></span>
                                    de
                                    <span class="font-medium"><?php echo $total_rows; ?></span>
                                    resultados
                                </p>
                            </div>
                            
                            <div>
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                                        <a 
                                            href="?page=<?php echo $p; ?><?php echo $search_backend !== '' ? '&search=' . urlencode($search_backend) : ''; ?>" 
                                            class="<?php echo $p === $page ? 'bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?> relative inline-flex items-center px-4 py-2 border text-sm font-medium"
                                        >
                                            <?php echo $p; ?>
                                        </a>
                                    <?php endfor; ?>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
/**
 * Búsqueda instantánea con filtros - Pure JavaScript
 */
class BusquedaConFiltros {
    constructor() {
        this.input = document.getElementById('busqueda-instantanea');
        this.filtroEstado = document.getElementById('filtro-estado');
        this.btnLimpiar = document.getElementById('limpiar-busqueda');
        this.filas = document.querySelectorAll('.cliente-row');
        this.sinResultados = document.getElementById('sin-resultados');
        this.contadorVisible = document.getElementById('total-visible');
        this.contadorTotal = document.getElementById('total-clientes');
        
        this.totalClientes = this.filas.length;
        this.contadorTotal.textContent = this.totalClientes;
        
        this.init();
    }
    
    init() {
        this.input.addEventListener('input', () => this.aplicarFiltros());
        this.filtroEstado.addEventListener('change', () => this.aplicarFiltros());
        this.btnLimpiar.addEventListener('click', () => this.limpiarTodo());
        
        this.input.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.limpiarTodo();
            }
        });
        
        console.log('Búsqueda con filtros inicializada - ' + this.totalClientes + ' clientes');
    }
    
    aplicarFiltros() {
        const termino = this.input.value.toLowerCase().trim();
        const estadoFiltro = this.filtroEstado.value;
        
        if (termino || estadoFiltro) {
            this.btnLimpiar.classList.remove('hidden');
        } else {
            this.btnLimpiar.classList.add('hidden');
        }
        
        let visibles = 0;
        
        this.filas.forEach(fila => {
            const mostrar = this.evaluarFila(fila, termino, estadoFiltro);
            
            if (mostrar) {
                fila.style.display = '';
                if (termino) {
                    this.resaltarTermino(fila, termino);
                } else {
                    this.removerResaltado(fila);
                }
                visibles++;
            } else {
                fila.style.display = 'none';
                this.removerResaltado(fila);
            }
        });
        
        this.contadorVisible.textContent = visibles;
        
        if (visibles === 0 && (termino !== '' || estadoFiltro !== '')) {
            this.sinResultados.classList.remove('hidden');
        } else {
            this.sinResultados.classList.add('hidden');
        }
    }
    
    evaluarFila(fila, termino, estadoFiltro) {
        if (estadoFiltro && fila.dataset.estado !== estadoFiltro) {
            return false;
        }
        
        if (!termino) {
            return true;
        }
        
        const nombre = fila.dataset.nombre || '';
        const email = fila.dataset.email || '';
        const telefono = fila.dataset.telefono || '';
        
        return nombre.includes(termino) || 
               email.includes(termino) || 
               telefono.includes(termino);
    }
    
    resaltarTermino(fila, termino) {
        const elementos = fila.querySelectorAll('.searchable-nombre, .searchable-email, .searchable-telefono');
        
        elementos.forEach(elemento => {
            const textoOriginal = elemento.textContent;
            const regex = new RegExp('(' + this.escaparRegex(termino) + ')', 'gi');
            const textoResaltado = textoOriginal.replace(regex, '<mark class="bg-yellow-200 dark:bg-yellow-600 px-1 rounded">$1</mark>');
            elemento.innerHTML = textoResaltado;
        });
    }
    
    removerResaltado(fila) {
        const elementos = fila.querySelectorAll('.searchable-nombre, .searchable-email, .searchable-telefono');
        
        elementos.forEach(elemento => {
            const textoLimpio = elemento.textContent;
            elemento.innerHTML = textoLimpio;
        });
    }
    
    escaparRegex(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
    
    limpiarTodo() {
        this.input.value = '';
        this.filtroEstado.value = '';
        this.aplicarFiltros();
        this.input.focus();
    }
}

function limpiarTodo() {
    if (window.busquedaInstancia) {
        window.busquedaInstancia.limpiarTodo();
    }
}

function limpiarBusqueda() {
    limpiarTodo();
}

document.addEventListener('DOMContentLoaded', function() {
    window.busquedaInstancia = new BusquedaConFiltros();
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>