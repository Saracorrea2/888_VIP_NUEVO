-- 888 VIP POS - Corrección de descuentos no autorizados
-- Base de datos: 888_vip
-- IMPORTANTE: primero revisa el SELECT. El UPDATE corrige TODAS las ventas
-- con descuento, dejando total = subtotal. Úsalo solo si confirmas que esos
-- descuentos no fueron autorizados.

USE `888_vip`;

-- 1) Ver qué ventas tienen descuento actualmente.
SELECT id, estado, subtotal, descuento, total, metodo_pago, creado_en, cerrado_en
FROM pedidos
WHERE descuento > 0
ORDER BY id DESC;

-- 2) RESPALDO antes de corregir.
CREATE TABLE IF NOT EXISTS respaldo_descuentos_pedidos (
  id INT NOT NULL,
  estado VARCHAR(20),
  subtotal DECIMAL(12,2),
  descuento DECIMAL(12,2),
  total DECIMAL(12,2),
  metodo_pago VARCHAR(30),
  creado_en TIMESTAMP NULL,
  cerrado_en DATETIME NULL,
  respaldado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO respaldo_descuentos_pedidos
  (id, estado, subtotal, descuento, total, metodo_pago, creado_en, cerrado_en)
SELECT id, estado, subtotal, descuento, total, metodo_pago, creado_en, cerrado_en
FROM pedidos
WHERE descuento > 0
ON DUPLICATE KEY UPDATE
  estado=VALUES(estado), subtotal=VALUES(subtotal), descuento=VALUES(descuento),
  total=VALUES(total), metodo_pago=VALUES(metodo_pago), creado_en=VALUES(creado_en),
  cerrado_en=VALUES(cerrado_en), respaldado_en=CURRENT_TIMESTAMP;

-- 3) CORRECCIÓN: elimina el descuento no autorizado y recupera el total real.
UPDATE pedidos
SET descuento = 0,
    total = subtotal
WHERE descuento > 0;

-- 4) Verificación.
SELECT id, subtotal, descuento, total
FROM pedidos
WHERE descuento > 0
ORDER BY id DESC;
