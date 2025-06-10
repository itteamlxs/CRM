<?php
/**
 * Archivo: pages/clientes/index.php
 * Función: Lista clientes con búsqueda, paginación, exportación CSV.
 * Seguridad: Validación sesión, roles, salida escapada.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(['admin', 'vendedor']);

$pdo = getPDO();

$search = trim($_GET['search'] ?? '');
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Construir condición WHERE si hay búsqueda
$where = '';
$params = [];
if ($search !== '') {
    $where = "WHERE nombre LIKE :search OR email LIKE :search OR telefono LIKE :search";
    $params[':search'] = "%$search%";
}

// Contar total para paginación
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM clientes $where");
$count_stmt->execute($params);
$total_rows = $count_stmt->fetchColumn();
$total_pages = ceil($total_rows / $per_page);

// Obtener registros paginados
$sql = "SELECT id, nombre, email, telefono, estado FROM clientes $where ORDER BY nombre ASC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', (int)$per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->execute();
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
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
            <?php echo htmlspecialchars($lang['client_list'] ?? 'Lista de Clientes', ENT_QUOTES, 'UTF-8'); ?>
        </h1>
        <p class="text-gray-600 dark:text-gray-400 mt-1">
            Gestiona y visualiza todos los clientes del sistema
        </p>
    </div>

    <!-- Barra de búsqueda y acciones -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
        <div class="flex flex-col sm:flex-row gap-4 items-center justify-between">
            <!-- Búsqueda -->
            <form method="GET" class="flex-1 flex gap-2 max-w-md">
                <input
                    type="text"
                    name="search"
                    placeholder="<?php echo htmlspecialchars($lang['search'] ?? 'Buscar clientes...', ENT_QUOTES, 'UTF-8'); ?>"
                    value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"
                    class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                />
                <button 
                    type="submit" 
                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                >
                    <?php echo htmlspecialchars($lang['search'] ?? 'Buscar', ENT_QUOTES, 'UTF-8'); ?>
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
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                <?php echo htmlspecialchars($lang['client_status'] ?? 'Estado', ENT_QUOTES, 'UTF-8'); ?>
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                <?php echo htmlspecialchars($lang['actions'] ?? 'Acciones', ENT_QUOTES, 'UTF-8'); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($clientes as $cliente): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($cliente['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-white">
                                        <a href="mailto:<?php echo htmlspecialchars($cliente['email'], ENT_QUOTES, 'UTF-8'); ?>" class="text-blue-600 hover:text-blue-800">
                                            <?php echo htmlspecialchars($cliente['email'], ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-white">
                                        <?php if ($cliente['telefono']): ?>
                                            <a href="tel:<?php echo htmlspecialchars($cliente['telefono'], ENT_QUOTES, 'UTF-8'); ?>" class="text-blue-600 hover:text-blue-800">
                                                <?php echo htmlspecialchars($cliente['telefono'], ENT_QUOTES, 'UTF-8'); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gray-500">-</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
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
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end space-x-2">
                                        <a 
                                            href="<?php echo url('forms/form_cliente.php?id=' . (int)$cliente['id']); ?>" 
                                            class="text-blue-600 hover:text-blue-900 transition-colors"
                                            title="<?php echo htmlspecialchars($lang['edit'] ?? 'Editar', ENT_QUOTES, 'UTF-8'); ?>"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </a>
                                        
                                        <?php if ($_SESSION['role'] === 'administrador'): ?>
                                            <a 
                                                href="eliminar.php?id=<?php echo (int)$cliente['id']; ?>&csrf_token=<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>" 
                                                onclick="return confirm('<?php echo htmlspecialchars($lang['confirm_delete_client'] ?? '¿Está seguro de eliminar este cliente?', ENT_QUOTES, 'UTF-8'); ?>');" 
                                                class="text-red-600 hover:text-red-900 transition-colors"
                                                title="<?php echo htmlspecialchars($lang['delete'] ?? 'Eliminar', ENT_QUOTES, 'UTF-8'); ?>"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </a>
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

<?php include __DIR__ . '/../../includes/footer.php'; ?>