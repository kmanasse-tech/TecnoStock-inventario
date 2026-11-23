-- Script SQL Reproducible - Sistema de Inventario TecnoStock
DROP DATABASE IF EXISTS tecnostock;
CREATE DATABASE tecnostock CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tecnostock;

-- 1. Tabla Usuario
CREATE TABLE usuario (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(120) NOT NULL UNIQUE,
    clave VARCHAR(255) NOT NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- 2. Tabla Categoría
CREATE TABLE categoria (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL UNIQUE,
    descripcion VARCHAR(255) NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- 3. Tabla Producto (con código único y validación de valores no negativos)
CREATE TABLE producto (
    id_producto INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(30) NOT NULL UNIQUE,
    nombre VARCHAR(120) NOT NULL,
    descripcion TEXT NULL,
    precio DECIMAL(10, 2) NOT NULL CHECK (precio >= 0),
    stock_actual INT NOT NULL DEFAULT 0 CHECK (stock_actual >= 0),
    stock_minimo INT NOT NULL DEFAULT 0 CHECK (stock_minimo >= 0),
    estado TINYINT(1) NOT NULL DEFAULT 1,
    id_categoria INT NOT NULL,
    CONSTRAINT fk_producto_categoria FOREIGN KEY (id_categoria)
        REFERENCES categoria(id_categoria)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 4. Tabla Movimiento (registra entradas/salidas con producto y usuario responsable)
CREATE TABLE movimiento (
    id_movimiento INT AUTO_INCREMENT PRIMARY KEY,
    fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tipo ENUM('ENTRADA', 'SALIDA') NOT NULL,
    cantidad INT NOT NULL CHECK (cantidad > 0),
    observacion VARCHAR(255) NULL,
    id_producto INT NOT NULL,
    id_usuario INT NOT NULL,
    CONSTRAINT fk_movimiento_producto FOREIGN KEY (id_producto)
        REFERENCES producto(id_producto)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_movimiento_usuario FOREIGN KEY (id_usuario)
        REFERENCES usuario(id_usuario)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ==========================================
-- DATOS DE PRUEBA INICIALES
-- ==========================================

-- Usuario administrador (Contraseña: admin123)
INSERT INTO usuario (nombre, correo, clave, estado) VALUES
('Encargado TecnoStock', 'admin@tecnostock.cl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);

-- Categorías de accesorios tecnológicos
INSERT INTO categoria (nombre, descripcion, estado) VALUES
('Periféricos', 'Teclados, mouses, audífonos y webcams', 1),
('Cables y Adaptadores', 'Cables HDMI, USB-C, cargadores y hubs', 1),
('Almacenamiento', 'Pendrives, discos externos y memorias SD', 1);

-- Productos iniciales (incluye uno con stock bajo para probar la alerta de inmediato)
INSERT INTO producto (codigo, nombre, descripcion, precio, stock_actual, stock_minimo, estado, id_categoria) VALUES
('TEC-001', 'Mouse Inalámbrico Óptico', 'Mouse ergonómico 1600 DPI con receptor USB', 12990.00, 15, 5, 1, 1),
('TEC-002', 'Teclado Mecánico RGB', 'Teclado compacto switches blue en español', 34990.00, 3, 5, 1, 1),
('TEC-003', 'Cable HDMI 2.1 Alta Velocidad', 'Cable mallado de 2 metros soporte 4K', 8990.00, 20, 8, 1, 2),
('TEC-004', 'Pendrive USB 3.2 64GB', 'Memoria flash metálica alta velocidad', 9990.00, 2, 4, 1, 3);

-- Movimientos iniciales de referencia
INSERT INTO movimiento (tipo, cantidad, observacion, id_producto, id_usuario) VALUES
('ENTRADA', 15, 'Stock inicial de apertura', 1, 1),
('ENTRADA', 3, 'Stock inicial de apertura', 2, 1),
('ENTRADA', 20, 'Stock inicial de apertura', 3, 1),
('ENTRADA', 2, 'Stock inicial de apertura', 4, 1);
