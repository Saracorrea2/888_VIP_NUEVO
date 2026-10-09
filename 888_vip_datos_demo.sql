-- Datos iniciales ficticios para una instalacion de prueba.
-- Importar solo despues de 888_vip_esquema_demo.sql.

INSERT IGNORE INTO `categorias` (`nombre`, `icono`, `activa`) VALUES
('Cervezas', 'C', 1),
('Bebidas', 'B', 1),
('Licores', 'L', 1),
('Comidas', 'M', 1),
('Otros', 'O', 1);

INSERT INTO `mesas` (`nombre`, `estado`)
SELECT 'Mesa 1', 'libre'
WHERE NOT EXISTS (SELECT 1 FROM `mesas` WHERE `nombre` = 'Mesa 1');

INSERT INTO `mesas` (`nombre`, `estado`)
SELECT 'Mesa 2', 'libre'
WHERE NOT EXISTS (SELECT 1 FROM `mesas` WHERE `nombre` = 'Mesa 2');

INSERT INTO `mesas` (`nombre`, `estado`)
SELECT 'Mesa 3', 'libre'
WHERE NOT EXISTS (SELECT 1 FROM `mesas` WHERE `nombre` = 'Mesa 3');

INSERT INTO `mesas` (`nombre`, `estado`)
SELECT 'Mesa 4', 'libre'
WHERE NOT EXISTS (SELECT 1 FROM `mesas` WHERE `nombre` = 'Mesa 4');

INSERT INTO `mesas` (`nombre`, `estado`)
SELECT 'Mesa 5', 'libre'
WHERE NOT EXISTS (SELECT 1 FROM `mesas` WHERE `nombre` = 'Mesa 5');

INSERT INTO `mesas` (`nombre`, `estado`)
SELECT 'Mesa 6', 'libre'
WHERE NOT EXISTS (SELECT 1 FROM `mesas` WHERE `nombre` = 'Mesa 6');
