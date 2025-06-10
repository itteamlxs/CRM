<?php
/**
 * Archivo: poblar_datos.php
 * Función: Inserta datos de prueba en la base de datos para testing.
 * Seguridad: Solo debe ejecutarse una vez y en entorno seguro.
 * Requiere: Configuración correcta en /config/database.php
 */

require_once __DIR__ . '/../config/database.php';

try {
    $pdo->beginTransaction();

    // Limpiar tablas (opcional, cuidado en producción)
    $tablas = ['venta_productos', 'ventas', 'cotizacion_productos', 'cotizaciones', 'productos', 'clientes', 'categorias', 'usuarios'];
    foreach ($tablas as $tabla) {
        $pdo->exec("DELETE FROM $tabla");
        $pdo->exec("ALTER TABLE $tabla AUTO_INCREMENT = 1");
    }

    // Insertar usuarios
    $usuarios = [
        ['admin', 'admin123', 'admin@example.com', 'admin', 'Administrador Principal'],
        ['vendedor1', 'vendedor123', 'vendedor1@example.com', 'vendedor', 'Vendedor Uno'],
        ['vendedor2', 'vendedor123', 'vendedor2@example.com', 'vendedor', 'Vendedor Dos'],
    ];

    $stmtUser = $pdo->prepare("INSERT INTO usuarios (username, password_hash, email, role, nombre_completo) VALUES (?, ?, ?, ?, ?)");
    foreach ($usuarios as $u) {
        $password_hash = password_hash($u[1], PASSWORD_DEFAULT);
        $stmtUser->execute([$u[0], $password_hash, $u[2], $u[3], $u[4]]);
    }

    // Insertar categorías
    $categorias = ['Software', 'Hardware', 'Servicios', 'Consultoría', 'Soporte Técnico'];
    $stmtCat = $pdo->prepare("INSERT INTO categorias (nombre) VALUES (?)");
    foreach ($categorias as $cat) {
        $stmtCat->execute([$cat]);
    }

    // Insertar clientes (30 clientes)
    $stmtCli = $pdo->prepare("INSERT INTO clientes (nombre, email, telefono, direccion, estado) VALUES (?, ?, ?, ?, 'activo')");
    for ($i = 1; $i <= 30; $i++) {
        $nombre = "Cliente $i";
        $email = "cliente$i@example.com";
        $telefono = sprintf("+1-555-01%03d", $i);
        $direccion = "Calle Falsa $i, Ciudad Ejemplo";
        $stmtCli->execute([$nombre, $email, $telefono, $direccion]);
    }

    // Obtener IDs de usuarios y categorías para asignar
    $usuarios_ids = $pdo->query("SELECT id FROM usuarios")->fetchAll(PDO::FETCH_COLUMN);
    $categorias_ids = $pdo->query("SELECT id FROM categorias")->fetchAll(PDO::FETCH_COLUMN);
    $clientes_ids = $pdo->query("SELECT id FROM clientes")->fetchAll(PDO::FETCH_COLUMN);

    // Insertar productos (30 productos)
    $stmtProd = $pdo->prepare("INSERT INTO productos (nombre, descripcion, categoria_id, precio_base, impuesto_porcentaje, unidad_medida, stock, moneda, activo) VALUES (?, ?, ?, ?, 21.00, 'unidad', ?, 'USD', TRUE)");
    for ($i = 1; $i <= 30; $i++) {
        $nombre = "Producto $i";
        $descripcion = "Descripción detallada del producto $i.";
        $categoria_id = $categorias_ids[array_rand($categorias_ids)];
        $precio_base = rand(10, 500) + rand(0, 99)/100;
        $stock = rand(0, 100);
        $stmtProd->execute([$nombre, $descripcion, $categoria_id, $precio_base, $stock]);
    }

    // Insertar cotizaciones (30 cotizaciones)
    $stmtCot = $pdo->prepare("INSERT INTO cotizaciones (cliente_id, usuario_id, estado, total, moneda) VALUES (?, ?, 'abierta', 0.00, 'USD')");
    for ($i = 1; $i <= 30; $i++) {
        $cliente_id = $clientes_ids[array_rand($clientes_ids)];
        $usuario_id = $usuarios_ids[array_rand($usuarios_ids)];
        $stmtCot->execute([$cliente_id, $usuario_id]);
    }

    // Obtener IDs cotizaciones y productos
    $cotizaciones_ids = $pdo->query("SELECT id FROM cotizaciones")->fetchAll(PDO::FETCH_COLUMN);
    $productos_ids = $pdo->query("SELECT id FROM productos")->fetchAll(PDO::FETCH_COLUMN);

    // Insertar productos en cotizaciones (cada cotización tiene 1-5 productos)
    $stmtCP = $pdo->prepare("INSERT INTO cotizacion_productos (cotizacion_id, producto_id, cantidad, precio_unitario, impuesto_porcentaje) VALUES (?, ?, ?, ?, 21.00)");
    foreach ($cotizaciones_ids as $cot_id) {
        $num_items = rand(1, 5);
        $items = array_rand($productos_ids, $num_items);
        if (!is_array($items)) $items = [$items];
        foreach ($items as $prod_key) {
            $producto_id = $productos_ids[$prod_key];
            $cantidad = rand(1, 10);
            // Precio unitario tomado del producto actual
            $precio_unitario = $pdo->prepare("SELECT precio_base FROM productos WHERE id = ?");
            $precio_unitario->execute([$producto_id]);
            $precio = $precio_unitario->fetchColumn() ?: 10.00;

            $stmtCP->execute([$cot_id, $producto_id, $cantidad, $precio]);
        }
    }

    // Insertar ventas para algunas cotizaciones convertidas (15 de las 30)
    $stmtVent = $pdo->prepare("INSERT INTO ventas (cotizacion_id, total, moneda) VALUES (?, ?, 'USD')");
    $stmtVP = $pdo->prepare("INSERT INTO venta_productos (venta_id, producto_id, cantidad, precio_unitario, impuesto_porcentaje) VALUES (?, ?, ?, ?, 21.00)");

    // Seleccionar 15 cotizaciones al azar para venta
    $cotizaciones_venta = (array)array_slice($cotizaciones_ids, 0, 15);

    foreach ($cotizaciones_venta as $cot_id) {
        // Calcular total sumando los productos de la cotización
        $productos_cot = $pdo->prepare("SELECT producto_id, cantidad, precio_unitario FROM cotizacion_productos WHERE cotizacion_id = ?");
        $productos_cot->execute([$cot_id]);
        $total = 0;
        $detalles = $productos_cot->fetchAll(PDO::FETCH_ASSOC);
        foreach ($detalles as $item) {
            $total += $item['precio_unitario'] * $item['cantidad'];
        }

        // Insertar venta
        $stmtVent->execute([$cot_id, $total]);
        $venta_id = $pdo->lastInsertId();

        // Insertar productos en venta
        foreach ($detalles as $item) {
            $stmtVP->execute([$venta_id, $item['producto_id'], $item['cantidad'], $item['precio_unitario']]);
        }

        // Actualizar estado de cotización a convertida
        $pdo->prepare("UPDATE cotizaciones SET estado = 'convertida', total = ? WHERE id = ?")->execute([$total, $cot_id]);
    }

    $pdo->commit();

    echo "Datos de prueba insertados correctamente.\n";

} catch (Exception $e) {
    $pdo->rollBack();
    die("Error al insertar datos de prueba: " . $e->getMessage());
}
