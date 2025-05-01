-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 11-01-2025 a las 17:22:23
-- Versión del servidor: 9.1.0
-- Versión de PHP: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `panda_bd`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `administradores`
--

DROP TABLE IF EXISTS `administradores`;
CREATE TABLE IF NOT EXISTS `administradores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `administradores`
--

INSERT INTO `administradores` (`id`, `username`, `password`, `created_at`) VALUES
(1, 'Anibal Martinez', '$2y$10$XymcKegO41JevNvXEJutQeqGqBvNxbNXJMQQE47B/mpK.w7nhwkeW', '2025-01-11 00:05:27');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias`
--

DROP TABLE IF EXISTS `categorias`;
CREATE TABLE IF NOT EXISTS `categorias` (
  `id_categoria` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` text,
  PRIMARY KEY (`id_categoria`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `categorias`
--

INSERT INTO `categorias` (`id_categoria`, `nombre`, `descripcion`) VALUES
(1, 'anillos', 'matrimonio'),
(2, 'reloj', 'relojes');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `colecciones`
--

DROP TABLE IF EXISTS `colecciones`;
CREATE TABLE IF NOT EXISTS `colecciones` (
  `id_coleccion` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text,
  `fecha_lanzamiento` date DEFAULT NULL,
  PRIMARY KEY (`id_coleccion`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `materiales`
--

DROP TABLE IF EXISTS `materiales`;
CREATE TABLE IF NOT EXISTS `materiales` (
  `id_material` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` text,
  PRIMARY KEY (`id_material`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `materiales`
--

INSERT INTO `materiales` (`id_material`, `nombre`, `descripcion`) VALUES
(1, 'oro', 'anillos');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `piedraspreciosas`
--

DROP TABLE IF EXISTS `piedraspreciosas`;
CREATE TABLE IF NOT EXISTS `piedraspreciosas` (
  `id_piedra` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` text,
  PRIMARY KEY (`id_piedra`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productoimagenes`
--

DROP TABLE IF EXISTS `productoimagenes`;
CREATE TABLE IF NOT EXISTS `productoimagenes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_producto` int DEFAULT NULL,
  `imagen_url` varchar(255) NOT NULL,
  `orden` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_producto` (`id_producto`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `productoimagenes`
--

INSERT INTO `productoimagenes` (`id`, `id_producto`, `imagen_url`, `orden`) VALUES
(1, 2, 'uploads/6781bf8a715c5.png', 1),
(2, 2, 'uploads/6781bf8a71a1f.png', 2),
(3, 1, 'uploads/6781c34787b4c.png', 1),
(4, 3, 'uploads/6781c8ec15087.png', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

DROP TABLE IF EXISTS `productos`;
CREATE TABLE IF NOT EXISTS `productos` (
  `id_producto` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `id_categoria` int DEFAULT NULL,
  `id_material` int DEFAULT NULL,
  `descripcion` text,
  `precio` decimal(10,2) NOT NULL,
  `stock` int NOT NULL,
  `peso` decimal(6,2) DEFAULT NULL,
  `dimensiones` varchar(50) DEFAULT NULL,
  `imagen_url` varchar(255) DEFAULT NULL,
  `fecha_adicion` date DEFAULT NULL,
  `destacado` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id_producto`),
  KEY `id_categoria` (`id_categoria`),
  KEY `id_material` (`id_material`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id_producto`, `nombre`, `id_categoria`, `id_material`, `descripcion`, `precio`, `stock`, `peso`, `dimensiones`, `imagen_url`, `fecha_adicion`, `destacado`) VALUES
(1, 'anillos', 1, 1, '0', 1200000.00, 20, 30.00, '2*5', NULL, NULL, 1),
(2, 'anillos', 1, 1, '0', 12000000.00, 30, 1.90, '2*5', NULL, NULL, 1),
(3, 'reloj', 2, 1, '0', 1000000.00, 20, 300.00, '2*5', NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productoscolecciones`
--

DROP TABLE IF EXISTS `productoscolecciones`;
CREATE TABLE IF NOT EXISTS `productoscolecciones` (
  `id_producto` int NOT NULL,
  `id_coleccion` int NOT NULL,
  PRIMARY KEY (`id_producto`,`id_coleccion`),
  KEY `id_coleccion` (`id_coleccion`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productospiedras`
--

DROP TABLE IF EXISTS `productospiedras`;
CREATE TABLE IF NOT EXISTS `productospiedras` (
  `id_producto` int NOT NULL,
  `id_piedra` int NOT NULL,
  `cantidad` int DEFAULT NULL,
  `quilates` decimal(5,2) DEFAULT NULL,
  PRIMARY KEY (`id_producto`,`id_piedra`),
  KEY `id_piedra` (`id_piedra`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
