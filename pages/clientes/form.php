<?php
/**
 * Archivo: pages/clientes/form.php
 * Función: Formulario para crear o editar clientes.
 * Seguridad: Incluye auth, validación, sanitización, CSRF y uso PDO.
 */

require_once __DIR__ . '/../../includes/auth.php';  // Valida sesión y permisos
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../lang/' . ($_SESSION['lang'] ?? 'es') . '.php';

// Solo administradores y vendedores pueden acceder
require_role(['admin', 'vendedor']);

$pdo = getPDO();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$cliente = [
    'nombre' => '',
    'email' => '',
    'telefono' => '',
    'direccion' => '',
    'estado' => 'activo'
];

if ($id) {
    // Obtener datos para editar
    $stmt = $pdo->prepare("SELECT nombre, email, telefono, direccion, estado FROM clientes WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $cliente_db = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($cliente_db) {
        $cliente = $cliente_db;
    } else {
        // No encontrado, redirigir al listado
        header('Location: index.php');
        exit;
    }
}

// Generar token CSRF para el formulario
$csrf_token = generate_csrf_token();

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';
?>

<div class="container mx-auto p-4 max-w-lg">
    <h1 class="text-2xl font-bold mb-4">
        <?= $id ? htmlspecialchars($lang['clientes_editar'] ?? 'Editar Cliente') : htmlspecialchars($lang['clientes_nuevo'] ?? 'Nuevo Cliente') ?>
    </h1>

    <form method="POST" action="procesar.php" novalidate class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <?php if ($id): ?>
            <input type="hidden" name="id" value="<?= (int)$id ?>">
        <?php endif; ?>
        <div>
            <label for="nombre" class="block font-semibold mb-1"><?= htmlspecialchars($lang['clientes_nombre'] ?? 'Nombre') ?> *</label>
            <input type="text" name="nombre" id="nombre" required maxlength="100" value="<?= htmlspecialchars($cliente['nombre']) ?>" class="w-full border rounded px-3 py-2" />
        </div>

        <div>
            <label for="email" class="block font-semibold mb-1"><?= htmlspecialchars($lang['clientes_email'] ?? 'Email') ?> *</label>
            <input type="email" name="email" id="email" required maxlength="100" value="<?= htmlspecialchars($cliente['email']) ?>" class="w-full border rounded px-3 py-2" />
        </div>

        <div>
            <label for="telefono" class="block font-semibold mb-1"><?= htmlspecialchars($lang['clientes_telefono'] ?? 'Teléfono') ?></label>
            <input type="tel" name="telefono" id="telefono" maxlength="20" value="<?= htmlspecialchars($cliente['telefono']) ?>" class="w-full border rounded px-3 py-2" />
        </div>

        <div>
            <label for="direccion" class="block font-semibold mb-1"><?= htmlspecialchars($lang['clientes_direccion'] ?? 'Dirección') ?></label>
            <textarea name="direccion" id="direccion" maxlength="255" class="w-full border rounded px-3 py-2"><?= htmlspecialchars($cliente['direccion']) ?></textarea>
        </div>

        <div>
            <label for="estado" class="block font-semibold mb-1"><?= htmlspecialchars($lang['clientes_estado'] ?? 'Estado') ?></label>
            <select name="estado" id="estado" class="w-full border rounded px-3 py-2">
                <option value="activo" <?= $cliente['estado'] === 'activo' ? 'selected' : '' ?>><?= htmlspecialchars($lang['estado_activo'] ?? 'Activo') ?></option>
                <option value="inactivo" <?= $cliente['estado'] === 'inactivo' ? 'selected' : '' ?>><?= htmlspecialchars($lang['estado_inactivo'] ?? 'Inactivo') ?></option>
            </select>
        </div>

        <div>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                <?= $id ? htmlspecialchars($lang['guardar_cambios'] ?? 'Guardar Cambios') : htmlspecialchars($lang['crear_cliente'] ?? 'Crear Cliente') ?>
            </button>
            <a href="index.php" class="ml-4 text-gray-600 hover:underline"><?= htmlspecialchars($lang['cancelar'] ?? 'Cancelar') ?></a>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
