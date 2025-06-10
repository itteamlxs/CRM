<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/database.php';     // conexión
require_once __DIR__ . '/includes/functions.php';  // aquí está getPDO()

try {
    $pdo = getPDO();

    $username = 'admin';
    $password_plain = 'Admin123!';
    $rol = 'administrador';

    $password_hash = password_hash($password_plain, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE username = :username");
    $stmt->execute([':username' => $username]);

    if ($stmt->fetchColumn() > 0) {
        exit("❌ El usuario '$username' ya existe. No se creó uno nuevo.\n");
    }

$stmt = $pdo->prepare("INSERT INTO usuarios (username, password_hash, email, role, nombre_completo) 
                       VALUES (:username, :password_hash, :email, :role, :nombre_completo)");


    echo "✅ Usuario administrador '$username' creado con contraseña '$password_plain'.\n";
    echo "⚠️ Elimina este archivo por seguridad.\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
