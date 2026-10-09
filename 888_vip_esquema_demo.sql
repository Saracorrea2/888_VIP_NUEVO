-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generaciÃ³n: 11-09-2026 a las 03:28:29
-- VersiÃ³n del servidor: 10.4.32-MariaDB
-- VersiÃ³n de PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `888_vip`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias`
--

CREATE TABLE `categorias` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `icono` varchar(20) NOT NULL DEFAULT '?',
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `categorias`
--


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_pedidos`
--

CREATE TABLE `detalle_pedidos` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` decimal(12,2) NOT NULL,
  `precio` decimal(12,2) NOT NULL,
  `costo_unitario` decimal(12,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `detalle_pedidos`
--


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `gastos`
--

CREATE TABLE `gastos` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `producto_id` int(11) DEFAULT NULL,
  `cantidad_inventario` decimal(12,2) DEFAULT NULL,
  `caja_id` int(11) DEFAULT NULL,
  `tipo` enum('fijo','imprevisto','inventario','otro') NOT NULL DEFAULT 'otro',
  `concepto` varchar(180) NOT NULL,
  `monto` decimal(12,2) NOT NULL DEFAULT 0.00,
  `frecuencia` enum('unico','diario','semanal','quincenal','mensual') NOT NULL DEFAULT 'unico',
  `fecha_inicio` date NOT NULL,
  `proxima_fecha` date DEFAULT NULL,
  `estado` enum('activo','pagado','inactivo') NOT NULL DEFAULT 'activo',
  `gasto_origen_id` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `gastos`
--


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial`
--

CREATE TABLE `historial` (
  `id` bigint(20) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `modulo` varchar(50) NOT NULL,
  `accion` varchar(60) NOT NULL,
  `descripcion` varchar(500) NOT NULL,
  `datos` text DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `historial`
--


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cajas`
--
CREATE TABLE `cajas` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `apertura_en` datetime NOT NULL DEFAULT current_timestamp(),
  `monto_inicial` decimal(12,2) NOT NULL DEFAULT 0.00,
  `monto_esperado` decimal(12,2) NOT NULL DEFAULT 0.00,
  `monto_final` decimal(12,2) DEFAULT NULL,
  `diferencia` decimal(12,2) DEFAULT NULL,
  `estado` enum('abierta','cerrada') NOT NULL DEFAULT 'abierta',
  `cierre_en` datetime DEFAULT NULL,
  `observacion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Estructura de tabla para la tabla `mesas`
--

CREATE TABLE `mesas` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `estado` enum('libre','ocupada','reservada') NOT NULL DEFAULT 'libre',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `mesas`
--


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `movimientos_caja`
--

CREATE TABLE `movimientos_caja` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `caja_id` int(11) DEFAULT NULL,
  `pedido_id` int(11) DEFAULT NULL,
  `gasto_id` int(11) DEFAULT NULL,
  `tipo` enum('entrada','salida') NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `concepto` varchar(255) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `movimientos_caja`
--


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `movimientos_inventario`
--

CREATE TABLE `movimientos_inventario` (
  `id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `tipo` enum('entrada','salida','ajuste') NOT NULL,
  `origen` enum('compra','venta','salida_manual','ajuste','inicial') NOT NULL DEFAULT 'ajuste',
  `cantidad` decimal(12,2) NOT NULL,
  `stock_anterior` decimal(12,2) NOT NULL DEFAULT 0.00,
  `stock_nuevo` decimal(12,2) NOT NULL DEFAULT 0.00,
  `motivo` varchar(150) DEFAULT NULL,
  `observacion` varchar(255) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `movimientos_inventario`
--


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedidos`
--

CREATE TABLE `pedidos` (
  `id` int(11) NOT NULL,
  `mesa_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `caja_id` int(11) DEFAULT NULL,
  `estado` enum('abierto','cerrado','anulado') NOT NULL DEFAULT 'abierto',
  `metodo_pago` enum('efectivo','nequi','daviplata','tarjeta','transferencia','otro') DEFAULT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `descuento` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `observacion` varchar(255) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `cerrado_en` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `pedidos`
--


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `precio_compra` decimal(12,2) NOT NULL DEFAULT 0.00,
  `precio` decimal(12,2) NOT NULL DEFAULT 0.00,
  `stock` decimal(12,2) NOT NULL DEFAULT 0.00,
  `stock_minimo` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unidad` varchar(20) NOT NULL DEFAULT 'unidad',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `productos`
--


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `clave` varchar(255) NOT NULL,
  `rol` enum('admin','cajero','mesero') NOT NULL DEFAULT 'cajero',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--


-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_productos_inventario`
-- (VÃ©ase abajo para la vista actual)
--
CREATE TABLE `vista_productos_inventario` (
`id` int(11)
,`nombre` varchar(150)
,`descripcion` varchar(255)
,`precio_compra` decimal(12,2)
,`precio` decimal(12,2)
,`stock` decimal(12,2)
,`stock_minimo` decimal(12,2)
,`unidad` varchar(20)
,`activo` tinyint(1)
,`categoria` varchar(100)
,`icono` varchar(20)
,`stock_bajo` int(1)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_ventas`
-- (VÃ©ase abajo para la vista actual)
--
CREATE TABLE `vista_ventas` (
`id` int(11)
,`fecha` date
,`hora` time
,`metodo_pago` enum('efectivo','nequi','daviplata','tarjeta','transferencia','otro')
,`total` decimal(12,2)
,`estado` enum('abierto','cerrado','anulado')
,`usuario_id` int(11)
,`mesa_id` int(11)
);

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_productos_inventario`
--
DROP TABLE IF EXISTS `vista_productos_inventario`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `vista_productos_inventario`  AS SELECT `p`.`id` AS `id`, `p`.`nombre` AS `nombre`, `p`.`descripcion` AS `descripcion`, `p`.`precio_compra` AS `precio_compra`, `p`.`precio` AS `precio`, `p`.`stock` AS `stock`, `p`.`stock_minimo` AS `stock_minimo`, `p`.`unidad` AS `unidad`, `p`.`activo` AS `activo`, `c`.`nombre` AS `categoria`, `c`.`icono` AS `icono`, CASE WHEN `p`.`stock` <= `p`.`stock_minimo` THEN 1 ELSE 0 END AS `stock_bajo` FROM (`productos` `p` left join `categorias` `c` on(`c`.`id` = `p`.`categoria_id`)) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_ventas`
--
DROP TABLE IF EXISTS `vista_ventas`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `vista_ventas`  AS SELECT `pe`.`id` AS `id`, cast(`pe`.`creado_en` as date) AS `fecha`, cast(`pe`.`creado_en` as time) AS `hora`, `pe`.`metodo_pago` AS `metodo_pago`, `pe`.`total` AS `total`, `pe`.`estado` AS `estado`, `pe`.`usuario_id` AS `usuario_id`, `pe`.`mesa_id` AS `mesa_id` FROM `pedidos` AS `pe` WHERE `pe`.`estado` = 'cerrado' ;

--
-- Ãndices para tablas volcadas
--

--
-- Indices de la tabla `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `detalle_pedidos`
--
ALTER TABLE `detalle_pedidos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_detalle_producto` (`producto_id`),
  ADD KEY `idx_detalle_pedido` (`pedido_id`);

--
-- Indices de la tabla `gastos`
--
ALTER TABLE `gastos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_gastos_usuario` (`usuario_id`),
  ADD KEY `fk_gasto_origen` (`gasto_origen_id`),
  ADD KEY `idx_gastos_fecha` (`fecha_inicio`),
  ADD KEY `idx_gastos_tipo` (`tipo`),
  ADD KEY `idx_gastos_proxima` (`proxima_fecha`);

--
-- Indices de la tabla `historial`
--
ALTER TABLE `historial`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_historial_usuario` (`usuario_id`),
  ADD KEY `idx_historial_fecha` (`creado_en`),
  ADD KEY `idx_historial_modulo` (`modulo`);

--
-- Indices de la tabla `mesas`
--
ALTER TABLE `mesas`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `movimientos_caja`
--
ALTER TABLE `movimientos_caja`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_caja_usuario` (`usuario_id`),
  ADD KEY `fk_caja_pedido` (`pedido_id`),
  ADD KEY `fk_caja_gasto` (`gasto_id`),
  ADD KEY `idx_caja_fecha` (`creado_en`);

--
-- Indices de la tabla `movimientos_inventario`
--
ALTER TABLE `movimientos_inventario`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_mov_inv_usuario` (`usuario_id`),
  ADD KEY `idx_mov_inv_fecha` (`creado_en`),
  ADD KEY `idx_mov_inv_producto` (`producto_id`),
  ADD KEY `idx_mov_inv_origen` (`origen`),
  ADD KEY `idx_mov_inv_fecha_origen` (`creado_en`,`origen`);

--
-- Indices de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pedidos_mesa` (`mesa_id`),
  ADD KEY `fk_pedidos_usuario` (`usuario_id`),
  ADD KEY `idx_pedidos_fecha` (`creado_en`),
  ADD KEY `idx_pedidos_estado` (`estado`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_productos_categoria` (`categoria_id`),
  ADD KEY `idx_productos_stock` (`stock`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario` (`usuario`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id` int(11) NOT NULL, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `detalle_pedidos`
--
ALTER TABLE `detalle_pedidos`
  MODIFY `id` int(11) NOT NULL, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `gastos`
--
ALTER TABLE `gastos`
  MODIFY `id` int(11) NOT NULL, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `cajas`
--
ALTER TABLE `cajas` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- AUTO_INCREMENT de la tabla `historial`
--
ALTER TABLE `historial`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `mesas`
--
ALTER TABLE `mesas`
  MODIFY `id` int(11) NOT NULL, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `movimientos_caja`
--
ALTER TABLE `movimientos_caja`
  MODIFY `id` int(11) NOT NULL, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `movimientos_inventario`
--
ALTER TABLE `movimientos_inventario`
  MODIFY `id` int(11) NOT NULL, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  MODIFY `id` int(11) NOT NULL, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id` int(11) NOT NULL, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL, AUTO_INCREMENT=2;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `detalle_pedidos`
--
ALTER TABLE `detalle_pedidos`
  ADD CONSTRAINT `fk_detalle_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_detalle_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`);

--
-- Filtros para la tabla `gastos`
--
ALTER TABLE `gastos`
  ADD CONSTRAINT `fk_gasto_origen` FOREIGN KEY (`gasto_origen_id`) REFERENCES `gastos` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_gastos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `historial`
--
ALTER TABLE `historial`
  ADD CONSTRAINT `fk_historial_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `movimientos_caja`
--
ALTER TABLE `movimientos_caja`
  ADD CONSTRAINT `fk_caja_gasto` FOREIGN KEY (`gasto_id`) REFERENCES `gastos` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_caja_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_caja_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `movimientos_inventario`
--
ALTER TABLE `movimientos_inventario`
  ADD CONSTRAINT `fk_mov_inv_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mov_inv_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD CONSTRAINT `fk_pedidos_mesa` FOREIGN KEY (`mesa_id`) REFERENCES `mesas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_pedidos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `productos`
--
ALTER TABLE `productos`
  ADD CONSTRAINT `fk_productos_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
