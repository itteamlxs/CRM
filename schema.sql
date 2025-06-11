-- ----------------------------
-- Schema CRM - CORREGIDO para coincidir con archivos PHP existentes
-- Basado en: forms/procesar_producto.php, functions.php, etc.
-- ----------------------------

CREATE DATABASE IF NOT EXISTS crm_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE crm_db;

-- Tabla usuarios (MANTENER IGUAL - ya funciona)
CREATE TABLE usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    role ENUM('admin', 'vendedor') NOT NULL DEFAULT 'vendedor',
    nombre_completo VARCHAR(100) NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    INDEX idx_username (username),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla configuración (MANTENER IGUAL)
CREATE TABLE configuracion (
    id INT PRIMARY KEY DEFAULT 1,
    nombre_empresa VARCHAR(150) NOT NULL DEFAULT 'Mi Empresa',
    idioma ENUM('es', 'en') NOT NULL DEFAULT 'es',
    moneda VARCHAR(10) NOT NULL DEFAULT 'USD',
    simbolo_moneda VARCHAR(5) NOT NULL DEFAULT '$',
    impuesto_porcentaje DECIMAL(5,2) NOT NULL DEFAULT 21.00,
    zona_horaria VARCHAR(50) NOT NULL DEFAULT 'America/New_York',
    tema ENUM('claro', 'oscuro') NOT NULL DEFAULT 'claro',
    logo_path VARCHAR(255) NULL,
    CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla clientes (AGREGAR fecha_actualizacion)
CREATE TABLE clientes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    telefono VARCHAR(30),
    direccion TEXT,
    estado ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_estado (estado),
    INDEX idx_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla categorías (CORREGIDA - agregar campos que usan los PHP)
CREATE TABLE categorias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT,
    activa BOOLEAN NOT NULL DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_nombre (nombre),
    INDEX idx_activa (activa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla productos (CORREGIDA - campos que esperan los PHP)
CREATE TABLE productos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(200) NOT NULL,
    descripcion TEXT,
    categoria_id INT UNSIGNED NOT NULL,
    codigo_sku VARCHAR(50) UNIQUE,
    precio_compra DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    precio_venta DECIMAL(12,2) NOT NULL,
    stock_actual INT UNSIGNED NOT NULL DEFAULT 0,
    stock_minimo INT UNSIGNED NOT NULL DEFAULT 0,
    stock_maximo INT UNSIGNED NULL,
    unidad_medida ENUM('unidad', 'kg', 'gramo', 'litro', 'metro', 'caja', 'paquete') NOT NULL DEFAULT 'unidad',
    imagen VARCHAR(255) NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_nombre (nombre),
    INDEX idx_codigo_sku (codigo_sku),
    INDEX idx_categoria (categoria_id),
    INDEX idx_activo (activo),
    INDEX idx_stock_bajo (stock_actual, stock_minimo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla inventario_movimientos (NUEVA - requerida por functions.php)
CREATE TABLE inventario_movimientos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    producto_id INT UNSIGNED NOT NULL,
    tipo_movimiento ENUM('entrada', 'salida', 'ajuste') NOT NULL,
    cantidad INT NOT NULL,
    stock_anterior INT UNSIGNED NOT NULL,
    stock_nuevo INT UNSIGNED NOT NULL,
    motivo TEXT NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_producto (producto_id),
    INDEX idx_tipo (tipo_movimiento),
    INDEX idx_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla cotizaciones (AGREGAR fecha_actualizacion)
CREATE TABLE cotizaciones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('abierta', 'cerrada', 'convertida') NOT NULL DEFAULT 'abierta',
    total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    moneda VARCHAR(10) NOT NULL DEFAULT 'USD',
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_cliente (cliente_id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_estado (estado),
    INDEX idx_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla cotizacion_productos (MANTENER IGUAL)
CREATE TABLE cotizacion_productos (
    cotizacion_id INT UNSIGNED NOT NULL,
    producto_id INT UNSIGNED NOT NULL,
    cantidad INT UNSIGNED NOT NULL DEFAULT 1,
    precio_unitario DECIMAL(12,2) NOT NULL,
    impuesto_porcentaje DECIMAL(5,2) NOT NULL DEFAULT 21.00,
    PRIMARY KEY (cotizacion_id, producto_id),
    FOREIGN KEY (cotizacion_id) REFERENCES cotizaciones(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_producto (producto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla ventas (MANTENER IGUAL)
CREATE TABLE ventas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cotizacion_id INT UNSIGNED NOT NULL UNIQUE,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(12,2) NOT NULL,
    moneda VARCHAR(10) NOT NULL DEFAULT 'USD',
    FOREIGN KEY (cotizacion_id) REFERENCES cotizaciones(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla venta_productos (MANTENER IGUAL)
CREATE TABLE venta_productos (
    venta_id INT UNSIGNED NOT NULL,
    producto_id INT UNSIGNED NOT NULL,
    cantidad INT UNSIGNED NOT NULL DEFAULT 1,
    precio_unitario DECIMAL(12,2) NOT NULL,
    impuesto_porcentaje DECIMAL(5,2) NOT NULL DEFAULT 21.00,
    PRIMARY KEY (venta_id, producto_id),
    FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_producto (producto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla auditoria (MANTENER IGUAL)
CREATE TABLE auditoria (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NULL,
    accion VARCHAR(100) NOT NULL,
    descripcion TEXT,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_usuario VARCHAR(45) NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_accion (accion),
    INDEX idx_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- VISTA para productos (NUEVA - requerida por getProductoById en functions.php)
CREATE VIEW vista_productos AS
SELECT 
    p.*,
    c.nombre as categoria_nombre,
    c.descripcion as categoria_descripcion,
    CASE 
        WHEN p.stock_actual <= 0 THEN 'sin_stock'
        WHEN p.stock_actual <= p.stock_minimo THEN 'stock_bajo'
        ELSE 'stock_normal'
    END as estado_stock
FROM productos p
INNER JOIN categorias c ON p.categoria_id = c.id;

-- DATOS INICIALES REQUERIDOS
-- Insertar configuración por defecto
INSERT INTO configuracion (id, nombre_empresa, idioma, moneda, simbolo_moneda) 
VALUES (1, 'Mi Empresa CRM', 'es', 'USD', '$')
ON DUPLICATE KEY UPDATE nombre_empresa = nombre_empresa;

-- Insertar categoría por defecto (REQUERIDA para productos)
INSERT INTO categorias (nombre, descripcion, activa) 
VALUES ('General', 'Categoría por defecto del sistema', TRUE)
ON DUPLICATE KEY UPDATE nombre = nombre;