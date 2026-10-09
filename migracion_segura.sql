USE 888_vip;

-- IMPORTANTE: este archivo NO borra productos ni datos existentes.
-- Solo asegura que las estructuras necesarias para ventas por mesa existan.

CREATE TABLE IF NOT EXISTS pedidos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  mesa_id INT NULL,
  usuario_id INT NULL,
  estado ENUM('abierto','cerrado','anulado') NOT NULL DEFAULT 'abierto',
  metodo_pago ENUM('efectivo','nequi','daviplata','tarjeta','transferencia','otro') NULL,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  descuento DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  observacion VARCHAR(255) NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  cerrado_en DATETIME NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS detalle_pedidos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id INT NOT NULL,
  producto_id INT NOT NULL,
  cantidad DECIMAL(12,2) NOT NULL,
  precio DECIMAL(12,2) NOT NULL,
  costo_unitario DECIMAL(12,2) NOT NULL DEFAULT 0,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  INDEX(pedido_id), INDEX(producto_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS movimientos_caja (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NULL,
  pedido_id INT NULL,
  gasto_id INT NULL,
  tipo ENUM('entrada','salida') NOT NULL,
  monto DECIMAL(12,2) NOT NULL,
  concepto VARCHAR(255) NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX(pedido_id), INDEX(creado_en)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS movimientos_inventario (
  id INT AUTO_INCREMENT PRIMARY KEY,
  producto_id INT NOT NULL,
  usuario_id INT NULL,
  tipo ENUM('entrada','salida','ajuste') NOT NULL,
  cantidad DECIMAL(12,2) NOT NULL,
  stock_anterior DECIMAL(12,2) NOT NULL DEFAULT 0,
  stock_nuevo DECIMAL(12,2) NOT NULL DEFAULT 0,
  motivo VARCHAR(150) NULL,
  observacion VARCHAR(255) NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX(producto_id), INDEX(creado_en)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS historial (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NULL,
  modulo VARCHAR(50) NOT NULL,
  accion VARCHAR(60) NOT NULL,
  descripcion VARCHAR(500) NOT NULL,
  datos TEXT NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS gastos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NULL,
  tipo ENUM('fijo','imprevisto','inventario','otro') NOT NULL DEFAULT 'otro',
  concepto VARCHAR(180) NOT NULL,
  monto DECIMAL(12,2) NOT NULL DEFAULT 0,
  frecuencia ENUM('unico','diario','semanal','quincenal','mensual') NOT NULL DEFAULT 'unico',
  fecha_inicio DATE NOT NULL,
  proxima_fecha DATE NULL,
  estado ENUM('activo','pagado','inactivo') NOT NULL DEFAULT 'activo',
  gasto_origen_id INT NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Corrige productos que quedaron con unidad "0" en la base original.
UPDATE productos SET unidad='unidad' WHERE unidad IS NULL OR unidad='' OR unidad='0';
