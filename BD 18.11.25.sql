-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Versión del servidor:         10.4.32-MariaDB - mariadb.org binary distribution
-- SO del servidor:              Win64
-- HeidiSQL Versión:             12.11.0.7065
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Volcando estructura de base de datos para campo_vello
DROP DATABASE IF EXISTS `campo_vello`;
CREATE DATABASE IF NOT EXISTS `campo_vello` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;
USE `campo_vello`;

-- Volcando estructura para tabla campo_vello.categorias
CREATE TABLE IF NOT EXISTS `categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(120) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla campo_vello.categorias: ~5 rows (aproximadamente)
INSERT INTO `categorias` (`id`, `name`) VALUES
	(1, 'Herbicidas'),
	(2, 'Semillas'),
	(3, 'Insecticidas'),
	(4, 'Fertilizantes'),
	(5, 'Equipos y Herramientas');

-- Volcando estructura para tabla campo_vello.clientes
CREATE TABLE IF NOT EXISTS `clientes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) DEFAULT NULL,
  `nit` varchar(80) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla campo_vello.clientes: ~2 rows (aproximadamente)
INSERT INTO `clientes` (`id`, `name`, `nit`, `address`, `phone`, `email`, `created_at`) VALUES
	(1, 'Cliente Demo A', '00000000-1', 'Av. Siempre Viva 123', '+503-1234-5678', 'clienteA@local', '2025-11-13 16:01:44'),
	(2, 'Cliente Demo B', '00000000-2', 'Calle Falsa 456', '+503-8765-4321', 'clienteB@local', '2025-11-13 16:01:44');

-- Volcando estructura para tabla campo_vello.facturas
CREATE TABLE IF NOT EXISTS `facturas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `subtotal` decimal(12,2) DEFAULT 0.00,
  `iva_amount` decimal(12,2) DEFAULT 0.00,
  `total` decimal(12,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `client_id` (`client_id`),
  CONSTRAINT `facturas_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `facturas_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `clientes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla campo_vello.facturas: ~8 rows (aproximadamente)
INSERT INTO `facturas` (`id`, `user_id`, `client_id`, `subtotal`, `iva_amount`, `total`, `created_at`) VALUES
	(1, 2, 1, 0.00, 0.00, 121.00, '2025-11-13 16:47:38'),
	(2, 2, 2, 0.00, 0.00, 78.00, '2025-11-13 16:52:21'),
	(3, 2, 1, 0.00, 0.00, 96.00, '2025-11-16 23:08:51'),
	(4, 2, 1, 0.00, 0.00, 96.00, '2025-11-16 23:12:25'),
	(13, 2, 2, 0.00, 0.00, 50.00, '2025-11-17 03:41:10'),
	(14, 2, 1, 0.00, 0.00, 121.00, '2025-11-17 03:51:52'),
	(15, 2, 2, 80.00, 10.40, 90.40, '2025-11-18 06:05:58'),
	(16, 2, 1, 96.00, 12.48, 108.48, '2025-11-18 06:17:50'),
	(17, 2, 1, 121.00, 15.73, 136.73, '2025-11-18 17:30:29');

-- Volcando estructura para tabla campo_vello.invoice_items
CREATE TABLE IF NOT EXISTS `invoice_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `invoice_id` (`invoice_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `facturas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invoice_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla campo_vello.invoice_items: ~12 rows (aproximadamente)
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `quantity`, `price`) VALUES
	(1, 1, 11, 1, 96.00),
	(2, 1, 8, 1, 25.00),
	(3, 2, 5, 2, 30.00),
	(4, 2, 6, 1, 18.00),
	(5, 3, 11, 1, 96.00),
	(6, 4, 11, 1, 96.00),
	(7, 13, 7, 2, 25.00),
	(8, 14, 11, 1, 96.00),
	(9, 14, 7, 1, 25.00),
	(10, 15, 7, 2, 25.00),
	(11, 15, 10, 2, 15.00),
	(12, 16, 11, 1, 96.00),
	(13, 17, 7, 1, 25.00),
	(14, 17, 11, 1, 96.00);

-- Volcando estructura para tabla campo_vello.productos
CREATE TABLE IF NOT EXISTS `productos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `location` varchar(120) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `productos_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categorias` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla campo_vello.productos: ~8 rows (aproximadamente)
INSERT INTO `productos` (`id`, `name`, `category_id`, `location`, `price`, `stock`) VALUES
	(3, 'Maicillo', 2, 'Estante1', 12.50, 10),
	(4, 'Guantes', 5, 'Estante 2', 5.00, 8),
	(5, 'Semilla Maíz', 2, 'Estante 1', 30.00, 8),
	(6, 'Sulfato de Amonio 45 KG', 4, 'Estante 3', 18.00, 3),
	(7, 'Cipermetrina', 3, 'Estante 4', 25.00, 2),
	(8, 'Gramoxone Galon', 1, 'Estante 5', 25.00, 4),
	(10, 'Gramoxone litro', 1, 'estante 5', 15.00, 3),
	(11, 'Bomba fumigadora de 16 LT Jacto', 5, 'Estante 2', 96.00, 3);

-- Volcando estructura para tabla campo_vello.usuarios
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('admin','cajero') DEFAULT 'cajero',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla campo_vello.usuarios: ~3 rows (aproximadamente)
INSERT INTO `usuarios` (`id`, `nombre`, `email`, `password`, `role`, `created_at`) VALUES
	(1, 'Administrador', 'admin@local', 'Admin123!', 'admin', '2025-11-13 16:01:44'),
	(2, 'Cajero', 'cajero@local', 'Cajero123!', 'cajero', '2025-11-13 16:01:44'),
	(4, 'Carlos Daniel Rauda', 'Rauda@admin.com', 'AdminAdmin', 'admin', '2025-11-13 16:51:23');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
