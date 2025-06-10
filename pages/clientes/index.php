<?php
/**
 * Archivo: pages/clientes/index.php
 * Función: Lista clientes con búsqueda, paginación, exportación CSV.
 * Seguridad: Validación sesión, roles, salida escapada.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../lang/' . ($_SESSION['lang'] ?? 'es') . '.php';

require_role(['administrador', 'vendedor']);

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
include __DIR__ . '/../../includes/nav.php';
?>

<div class="container mx-auto p-4 max-w-4xl">
    <h1 class="text-2xl font-bold mb-4"><?= htmlspecialchars($lang['clientes_listado'] ?? 'Listado de Clientes') ?></h1>

    <form method="GET" class="mb-4 flex space-x-2">
        <input
            type="text"
            name="search"
            placeholder="<?= htmlspecialchars($lang['buscar'] ?? 'Buscar') ?>"
            value="<?= htmlspecialchars($search) ?>"
            class="border rounded px-3 py-2 flex-grow"
        />
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><?= htmlspecialchars($lang['buscar'] ?? 'Buscar') ?></button>
        <a href="?export=csv<?= $search !== '' ? '&search=' . urlencode($search) : '' ?>" class="ml-2 bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700"><?= htmlspecialchars($lang['exportar_csv'] ?? 'Exportar CSV') ?></a>
        <a href="form.php" class="ml-auto bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"><?= htmlspecialchars($lang['nuevo_cliente'] ?? 'Nuevo Cliente') ?></a>
    </form>

    <?php if (count($clientes) === 0): ?>
        <p><?= htmlspecialchars($lang['sin_resultados'] ?? 'No se encontraron clientes.') ?></p>
    <?php else: ?>
        <table class="w-full border-collapse border border-gray-300">
            <thead>
                <tr class="bg-gray-100">
                    <th class="border border-gray-300 px-3 py-2 text-left">ID</th>
                    <th class="border border-gray-300 px-3 py-2 text-left"><?= htmlspecialchars($lang['clientes_nombre'] ?? 'Nombre') ?></th>
                    <th class="border border-gray-300 px-3 py-2 text-left"><?= htmlspecialchars($lang['clientes_email'] ?? 'Email') ?></th>
                    <th class="border border-gray-300 px-3 py-2 text-left"><?= htmlspecialchars($lang['clientes_telefono'] ?? 'Teléfono') ?></th>
                    <th class="border border-gray-300 px-3 py-2 text-left"><?= htmlspecialchars($lang['clientes_estado'] ?? 'Estado') ?></th>
                    <th class="border border-gray-300 px-3 py-2"><?= htmlspecialchars($lang['acciones'] ?? 'Acciones') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clientes as $c): ?>
                    <tr>
                        <td class="border border-gray-300 px-3 py-2"><?= (int)$c['id'] ?></td>
                        <td class="border border-gray-300 px-3 py-2"><?= htmlspecialchars($c['nombre']) ?></td>
                        <td class="border border-gray-300 px-3 py-2"><?= htmlspecialchars($c['email']) ?></td>
                        <td class="border border-gray-300 px-3 py-2"><?= htmlspecialchars($c['telefono']) ?></td>
                        <td class="border border-gray-300 px-3 py-2"><?= htmlspecialchars(ucfirst($c['estado'])) ?></td>
                        <td class="border border-gray-300 px-3 py-2 whitespace-nowrap">
                            <a href="form.php?id=<?= (int)$c['id'] ?>" class="text-blue-600 hover:underline mr-2"><?= htmlspecialchars($lang['editar'] ?? 'Editar') ?></a>
                            <a href="eliminar.php?id=<?= (int)$c['id'] ?>&csrf_token=<?= htmlspecialchars(generate_csrf_token()) ?>" onclick="return confirm('<?= htmlspecialchars($lang['confirmar_eliminar'] ?? '¿Eliminar cliente?') ?>');" class="text-red-600 hover:underline"><?= htmlspecialchars($lang['eliminar'] ?? 'Eliminar') ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
            <nav class="mt-4 flex justify-center space-x-2">
                <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                    <a href="?page=<?= $p ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?>"
                       class="px-3 py-1 border rounded <?= $p === $page ? 'bg-blue-600 text-white' : 'bg-white text-blue-600' ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
