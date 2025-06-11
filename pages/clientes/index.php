<?php
/**
 * Archivo: pages/clientes/index.php - CORREGIDO
 * Función: Lista clientes con búsqueda, paginación, exportación CSV.
 * Seguridad: Validación sesión, roles, salida escapada.
 * CORREGIDO: Manejo de parámetros PDO para búsqueda sin errores 500
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(['admin', 'vendedor']);

$pdo = getPDO();

$search = trim($_GET['search'] ?? '');
$mostrar_eliminados = isset($_GET['eliminados']) && $_GET['eliminados'] === '1' && $_SESSION['role'] === 'admin';
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Construir condición WHERE y parámetros de búsqueda
$where_conditions = [];
$search_params = [];

// FILTRO PRINCIPAL: Excluir eliminados (a menos que admin quiera verlos)
if (!$mostrar_eliminados) {
    $where_conditions[] = "eliminado = FALSE";
} else {
    $where_conditions[] = "eliminado = TRUE";
}

// Filtro de búsqueda
if ($search !== '') {
    $where_conditions[] = "(nombre LIKE :search OR email LIKE :search OR telefono LIKE :search)";
    $search_params[':search'] = "%$search%";
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Contar total para paginación (SIN LIMIT/OFFSET)
$count_sql = "SELECT COUNT(*) FROM clientes $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($search_params);
$total_rows = $count_stmt->fetchColumn();
$total_pages = ceil($total_rows / $per_page);

// Obtener registros paginados con información de eliminación si es necesario
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

include __DIR__ . '/../../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 py-6">
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                    <?php if ($mostrar_eliminados): ?>
                        📋 Clientes Eliminados
                    <?php else: ?>
                        <?php echo htmlspecialchars($lang['client_list'] ?? 'Lista de Clientes', ENT_QUOTES, 'UTF-8'); ?>
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

    <!-- Barra de búsqueda y acciones -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
        <div class="flex flex-col sm:flex-row gap-4 items-center justify-between">
            <!-- Búsqueda con autocompletado -->
            <form method="GET" class="flex-1 flex gap-2 max-w-md" id="busqueda-form">
                <div class="flex-1 relative">
                    <input
                        type="text"
                        name="search"
                        placeholder="<?php echo htmlspecialchars($lang['search'] ?? 'Buscar clientes en tiempo real...', ENT_QUOTES, 'UTF-8'); ?>"
                        value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                        autocomplete="off"
                        spellcheck="false"
                    />
                    <!-- Los resultados de búsqueda en tiempo real aparecerán aquí -->
                </div>
                <button 
                    type="submit" 
                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                    title="Búsqueda tradicional (fallback)"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </button>
            </form>

            <!-- Acciones -->
            <div class="flex gap-2">
                <a 
                    href="?export=csv<?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?>" 
                    class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <?php echo htmlspecialchars($lang['export_csv'] ?? 'Exportar CSV', ENT_QUOTES, 'UTF-8'); ?>
                </a>
                
                <a 
                    href="<?php echo url('forms/form_cliente.php'); ?>" 
                    class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    <?php echo htmlspecialchars($lang['new_client'] ?? 'Nuevo Cliente', ENT_QUOTES, 'UTF-8'); ?>
                </a>
            </div>
        </div>
        
        <!-- Resultados de búsqueda -->
        <?php if ($search !== ''): ?>
            <div class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                <span>
                    <?php echo htmlspecialchars($lang['showing'] ?? 'Mostrando', ENT_QUOTES, 'UTF-8'); ?> 
                    <strong><?php echo count($clientes); ?></strong>
                    <?php echo htmlspecialchars($lang['results'] ?? 'resultados', ENT_QUOTES, 'UTF-8'); ?> 
                    <?php echo htmlspecialchars($lang['of'] ?? 'de', ENT_QUOTES, 'UTF-8'); ?> 
                    <strong><?php echo $total_rows; ?></strong>
                    para "<strong><?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?></strong>"
                </span>
                <a href="?" class="ml-2 text-blue-600 hover:text-blue-800">Limpiar búsqueda</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Tabla de clientes -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
        <?php if (count($clientes) === 0): ?>
            <div class="p-8 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">
                    <?php echo htmlspecialchars($lang['no_clients_found'] ?? 'No se encontraron clientes', ENT_QUOTES, 'UTF-8'); ?>
                </h3>
                <p class="mt-2 text-gray-500 dark:text-gray-400">
                    <?php if ($search !== ''): ?>
                        Intenta con otros términos de búsqueda o 
                        <a href="?" class="text-blue-600 hover:text-blue-800">ver todos los clientes</a>
                    <?php else: ?>
                        Comienza agregando tu primer cliente al sistema
                    <?php endif; ?>
                </p>
                <?php if ($search === ''): ?>
                    <div class="mt-6">
                        <a 
                            href="<?php echo url('forms/form_cliente.php'); ?>" 
                            class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            <?php echo htmlspecialchars($lang['new_client'] ?? 'Crear Primer Cliente', ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                <?php echo htmlspecialchars($lang['client_name'] ?? 'Nombre', ENT_QUOTES, 'UTF-8'); ?>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                <?php echo htmlspecialchars($lang['client_email'] ?? 'Email', ENT_QUOTES, 'UTF-8'); ?>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                <?php echo htmlspecialchars($lang['client_phone'] ?? 'Teléfono', ENT_QUOTES, 'UTF-8'); ?>
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
                                    <?php echo htmlspecialchars($lang['client_status'] ?? 'Estado', ENT_QUOTES, 'UTF-8'); ?>
                                </th>
                            <?php endif; ?>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                <?php echo htmlspecialchars($lang['actions'] ?? 'Acciones', ENT_QUOTES, 'UTF-8'); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($clientes as $cliente): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 <?php echo $mostrar_eliminados ? 'opacity-75' : ''; ?>">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($cliente['nombre'], ENT_QUOTES, 'UTF-8'); ?>
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
                                                <?php echo htmlspecialchars($cliente['email'], ENT_QUOTES, 'UTF-8'); ?>
                                            </a>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($cliente['email'], ENT_QUOTES, 'UTF-8'); ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-white">
                                        <?php if ($cliente['telefono']): ?>
                                            <?php if (!$mostrar_eliminados): ?>
                                                <a href="tel:<?php echo htmlspecialchars($cliente['telefono'], ENT_QUOTES, 'UTF-8'); ?>" class="text-blue-600 hover:text-blue-800">
                                                    <?php echo htmlspecialchars($cliente['telefono'], ENT_QUOTES, 'UTF-8'); ?>
                                                </a>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($cliente['telefono'], ENT_QUOTES, 'UTF-8'); ?>
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
                                                <?php echo htmlspecialchars($lang['status_active'] ?? 'Activo', ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                                <?php echo htmlspecialchars($lang['status_inactive'] ?? 'Inactivo', ENT_QUOTES, 'UTF-8'); ?>
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
                                            
                                            <button 
                                                onclick="mostrarDetallesEliminacion(<?php echo (int)$cliente['id']; ?>)" 
                                                class="text-blue-600 hover:text-blue-900 transition-colors"
                                                title="Ver detalles"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </button>
                                        <?php else: ?>
                                            <!-- Acciones para clientes activos -->
                                            <a 
                                                href="<?php echo url('forms/form_cliente.php?id=' . (int)$cliente['id']); ?>" 
                                                class="text-blue-600 hover:text-blue-900 transition-colors"
                                                title="<?php echo htmlspecialchars($lang['edit'] ?? 'Editar', ENT_QUOTES, 'UTF-8'); ?>"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </a>
                                            
                                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                                <a 
                                                    href="eliminar.php?id=<?php echo (int)$cliente['id']; ?>&csrf_token=<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>" 
                                                    onclick="return confirm('¿Está seguro de eliminar este cliente? Esta acción se puede deshacer desde la papelera.');" 
                                                    class="text-red-600 hover:text-red-900 transition-colors"
                                                    title="<?php echo htmlspecialchars($lang['delete'] ?? 'Eliminar', ENT_QUOTES, 'UTF-8'); ?>"
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
                                <a href="?page=<?php echo $page - 1; ?><?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    <?php echo htmlspecialchars($lang['previous'] ?? 'Anterior', ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?><?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    <?php echo htmlspecialchars($lang['next'] ?? 'Siguiente', ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700 dark:text-gray-300">
                                    <?php echo htmlspecialchars($lang['showing'] ?? 'Mostrando', ENT_QUOTES, 'UTF-8'); ?>
                                    <span class="font-medium"><?php echo $offset + 1; ?></span>
                                    <?php echo htmlspecialchars($lang['of'] ?? 'de', ENT_QUOTES, 'UTF-8'); ?>
                                    <span class="font-medium"><?php echo min($offset + $per_page, $total_rows); ?></span>
                                    <?php echo htmlspecialchars($lang['of'] ?? 'de', ENT_QUOTES, 'UTF-8'); ?>
                                    <span class="font-medium"><?php echo $total_rows; ?></span>
                                    <?php echo htmlspecialchars($lang['results'] ?? 'resultados', ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                            </div>
                            
                            <div>
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                                        <a 
                                            href="?page=<?php echo $p; ?><?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?>" 
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

<!-- JavaScript para búsqueda en tiempo real -->
<script>
/**
 * Búsqueda en tiempo real para clientes
 * Versión integrada directamente en la página
 */

class BusquedaTiempoReal {
    constructor(options = {}) {
        this.searchInput = null;
        this.resultsContainer = null;
        this.currentIndex = -1;
        this.results = [];
        this.debounceTimer = null;
        
        // Configuración
        this.config = {
            minChars: 2,
            debounceDelay: 300,
            maxResults: 10,
            endpoint: options.endpoint || 'buscar_ajax.php',
            onSelect: options.onSelect || this.defaultOnSelect.bind(this),
            ...options
        };
        
        this.init();
    }
    
    init() {
        this.createSearchContainer();
        this.bindEvents();
        this.injectStyles();
    }
    
    createSearchContainer() {
        // Encontrar el input de búsqueda
        this.searchInput = document.querySelector('input[name="search"]');
        if (!this.searchInput) return;
        
        // Crear contenedor de resultados
        this.resultsContainer = document.createElement('div');
        this.resultsContainer.className = 'busqueda-resultados';
        this.resultsContainer.style.display = 'none';
        
        // Insertar después del input
        this.searchInput.parentNode.style.position = 'relative';
        this.searchInput.parentNode.appendChild(this.resultsContainer);
    }
    
    bindEvents() {
        // Evento de escritura
        this.searchInput.addEventListener('input', (e) => {
            this.handleInput(e.target.value);
        });
        
        // Navegación por teclado
        this.searchInput.addEventListener('keydown', (e) => {
            this.handleKeydown(e);
        });
        
        // Cerrar al hacer click fuera
        document.addEventListener('click', (e) => {
            if (!this.searchInput.parentNode.contains(e.target)) {
                this.hideResults();
            }
        });
        
        // Mostrar al enfocar si hay texto
        this.searchInput.addEventListener('focus', () => {
            if (this.searchInput.value.length >= this.config.minChars && this.results.length > 0) {
                this.showResultsContainer();
            }
        });
    }
    
    handleInput(query) {
        clearTimeout(this.debounceTimer);
        
        if (query.length < this.config.minChars) {
            this.hideResults();
            return;
        }
        
        this.debounceTimer = setTimeout(() => {
            this.search(query);
        }, this.config.debounceDelay);
    }
    
    handleKeydown(e) {
        if (!this.isResultsVisible()) return;
        
        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                this.navigateDown();
                break;
            case 'ArrowUp':
                e.preventDefault();
                this.navigateUp();
                break;
            case 'Enter':
                e.preventDefault();
                this.selectCurrent();
                break;
            case 'Escape':
                e.preventDefault();
                this.hideResults();
                break;
        }
    }
    
    async search(query) {
        try {
            this.showLoading();
            
            const response = await fetch(`buscar_ajax.php?q=${encodeURIComponent(query)}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.results = data.results;
                this.displayResults();
            } else {
                this.showError(data.message);
            }
            
        } catch (error) {
            console.error('Error búsqueda:', error);
            this.showError('Error de conexión');
        }
    }
    
    showLoading() {
        this.resultsContainer.innerHTML = `
            <div class="px-4 py-3 text-gray-500 flex items-center">
                <svg class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"></circle>
                    <path fill="currentColor" class="opacity-75" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                Buscando...
            </div>
        `;
        this.showResultsContainer();
    }
    
    displayResults() {
        if (this.results.length === 0) {
            this.showNoResults();
            return;
        }
        
        const items = this.results.map((cliente, index) => `
            <div class="busqueda-item px-4 py-3 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 border-b border-gray-100 dark:border-gray-600 last:border-b-0" 
                 data-index="${index}" data-id="${cliente.id}">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="font-medium text-gray-900 dark:text-white">
                            ${cliente.nombre_highlight}
                        </div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            ${cliente.email_highlight}
                            ${cliente.telefono ? ` • ${cliente.telefono}` : ''}
                        </div>
                    </div>
                    <div class="ml-4">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                            ${cliente.estado}
                        </span>
                    </div>
                </div>
            </div>
        `).join('');
        
        this.resultsContainer.innerHTML = items;
        this.showResultsContainer();
        this.currentIndex = -1;
        this.bindResultEvents();
    }
    
    showNoResults() {
        this.resultsContainer.innerHTML = `
            <div class="px-4 py-6 text-center text-gray-500">
                <svg class="mx-auto h-8 w-8 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                No se encontraron clientes
            </div>
        `;
        this.showResultsContainer();
    }
    
    showError(message) {
        this.resultsContainer.innerHTML = `
            <div class="px-4 py-6 text-center text-red-500">
                <svg class="mx-auto h-8 w-8 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                ${message}
            </div>
        `;
        this.showResultsContainer();
    }
    
    bindResultEvents() {
        const items = this.resultsContainer.querySelectorAll('.busqueda-item');
        items.forEach((item, index) => {
            item.addEventListener('click', () => this.selectResult(index));
            item.addEventListener('mouseenter', () => this.setActiveIndex(index));
        });
    }
    
    navigateDown() {
        const newIndex = this.currentIndex < this.results.length - 1 ? this.currentIndex + 1 : 0;
        this.setActiveIndex(newIndex);
    }
    
    navigateUp() {
        const newIndex = this.currentIndex > 0 ? this.currentIndex - 1 : this.results.length - 1;
        this.setActiveIndex(newIndex);
    }
    
    setActiveIndex(index) {
        // Remover clase activa anterior
        this.resultsContainer.querySelectorAll('.busqueda-item').forEach(item => {
            item.classList.remove('bg-blue-50', 'dark:bg-blue-900');
        });
        
        this.currentIndex = index;
        
        if (index >= 0) {
            const item = this.resultsContainer.querySelector(`[data-index="${index}"]`);
            if (item) {
                item.classList.add('bg-blue-50', 'dark:bg-blue-900');
                item.scrollIntoView({ block: 'nearest' });
            }
        }
    }
    
    selectCurrent() {
        if (this.currentIndex >= 0) {
            this.selectResult(this.currentIndex);
        }
    }
    
    selectResult(index) {
        const cliente = this.results[index];
        if (cliente) {
            this.config.onSelect(cliente);
            this.hideResults();
        }
    }
    
    defaultOnSelect(cliente) {
        // Ir a editar cliente
        window.location.href = cliente.url_editar;
    }
    
    showResultsContainer() {
        this.resultsContainer.style.display = 'block';
    }
    
    hideResults() {
        this.resultsContainer.style.display = 'none';
        this.currentIndex = -1;
    }
    
    isResultsVisible() {
        return this.resultsContainer.style.display === 'block';
    }
    
    injectStyles() {
        if (document.getElementById('busqueda-styles')) return;
        
        const style = document.createElement('style');
        style.id = 'busqueda-styles';
        style.textContent = `
            .busqueda-resultados {
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                z-index: 50;
                background: white;
                border: 1px solid #e5e7eb;
                border-radius: 0.375rem;
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
                max-height: 400px;
                overflow-y: auto;
                margin-top: 4px;
            }
            
            .dark .busqueda-resultados {
                background: #374151;
                border-color: #4b5563;
            }
            
            mark {
                background-color: #fef3c7;
                color: #92400e;
                padding: 0;
                border-radius: 2px;
            }
            
            .dark mark {
                background-color: #d97706;
                color: #fff;
            }
        `;
        document.head.appendChild(style);
    }
}

// Inicializar búsqueda en tiempo real
document.addEventListener('DOMContentLoaded', function() {
    new BusquedaTiempoReal();
});

// Función para mostrar detalles de eliminación
function mostrarDetallesEliminacion(clienteId) {
    // Aquí podrías hacer una llamada AJAX para obtener más detalles
    // Por ahora, simplemente mostramos una alerta
    alert('Detalles de eliminación del cliente ID: ' + clienteId + '\n\nPuedes implementar un modal aquí para mostrar más información como:\n- Historial de cotizaciones\n- Ventas asociadas\n- Motivo de eliminación');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>