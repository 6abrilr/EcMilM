-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 27-11-2025 a las 18:45:13
-- Versión del servidor: 10.11.13-MariaDB-0ubuntu0.24.04.1
-- Versión de PHP: 8.3.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `inspecciones`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `areas`
--

CREATE TABLE `areas` (
  `id` int(11) NOT NULL,
  `codigo` varchar(20) DEFAULT NULL,
  `nombre` varchar(255) DEFAULT NULL,
  `orden` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `areas`
--

INSERT INTO `areas` (`id`, `codigo`, `nombre`, `orden`) VALUES
(1, 'S1', 'S1', 1),
(2, 'S2', 'S2', 2),
(3, 'S3', 'S3', 3),
(4, 'S4', 'S4', 4),
(5, 'S5', 'S5', 5);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `checklist`
--

CREATE TABLE `checklist` (
  `id` int(11) NOT NULL,
  `file_rel` varchar(512) NOT NULL,
  `row_idx` int(11) NOT NULL,
  `estado` enum('si','no') DEFAULT NULL,
  `observacion` text DEFAULT NULL,
  `evidencia_path` varchar(512) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `caracter` varchar(100) DEFAULT NULL,
  `updated_by` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `checklist`
--

INSERT INTO `checklist` (`id`, `file_rel`, `row_idx`, `estado`, `observacion`, `evidencia_path`, `updated_at`, `caracter`, `updated_by`) VALUES
(1, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 1, NULL, '', NULL, '2025-11-18 14:07:45', NULL, 'CNEIRA'),
(2, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 2, NULL, '', NULL, '2025-11-18 14:07:45', NULL, 'CNEIRA'),
(3, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 3, NULL, '', NULL, '2025-11-18 14:07:45', NULL, 'CNEIRA'),
(4, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 4, NULL, '', NULL, '2025-11-18 14:07:45', NULL, 'CNEIRA'),
(5, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 5, NULL, '', NULL, '2025-11-18 14:07:45', NULL, 'CNEIRA'),
(6, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 6, NULL, '', NULL, '2025-11-18 14:07:45', NULL, 'CNEIRA'),
(7, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 7, NULL, '', NULL, '2025-11-18 14:07:45', NULL, 'CNEIRA'),
(8, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 8, NULL, '', NULL, '2025-11-18 14:07:45', NULL, 'CNEIRA'),
(9, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 9, NULL, '', NULL, '2025-11-18 14:07:45', NULL, 'CNEIRA'),
(10, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 10, NULL, '', NULL, '2025-11-18 14:07:45', NULL, 'CNEIRA'),
(11, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 11, NULL, '', NULL, '2025-11-18 14:07:45', NULL, 'CNEIRA'),
(12, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 12, NULL, '', NULL, '2025-11-18 14:07:45', NULL, 'CNEIRA'),
(13, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 13, NULL, '', NULL, '2025-11-18 14:07:45', NULL, 'CNEIRA'),
(14, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 14, NULL, '', NULL, '2025-11-18 14:07:45', NULL, 'CNEIRA'),
(15, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 15, NULL, '', NULL, '2025-11-18 14:07:45', NULL, 'CNEIRA'),
(16, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 16, NULL, '', NULL, '2025-11-18 14:07:45', NULL, 'CNEIRA'),
(17, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 17, NULL, '', NULL, '2025-11-18 14:07:45', NULL, 'CNEIRA'),
(18, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 18, NULL, '', NULL, '2025-11-18 14:07:45', NULL, 'CNEIRA'),
(19, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 19, NULL, '', NULL, '2025-11-18 14:07:45', NULL, 'CNEIRA'),
(20, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 20, NULL, '', NULL, '2025-11-18 14:07:45', NULL, 'CNEIRA'),
(21, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 21, NULL, '', NULL, '2025-11-18 14:01:01', NULL, 'ABALDI'),
(22, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 22, NULL, '', NULL, '2025-11-18 14:01:01', NULL, 'ABALDI'),
(23, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 23, NULL, '', NULL, '2025-11-18 14:01:01', NULL, 'ABALDI'),
(24, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 24, NULL, '', NULL, '2025-11-18 14:01:01', NULL, 'ABALDI'),
(25, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 25, NULL, '', NULL, '2025-11-18 14:01:01', NULL, 'ABALDI'),
(26, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 26, NULL, '', NULL, '2025-11-18 14:01:01', NULL, 'ABALDI'),
(27, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 27, NULL, '', NULL, '2025-11-18 14:01:02', NULL, 'ABALDI'),
(28, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 28, NULL, '', NULL, '2025-11-18 14:01:02', NULL, 'ABALDI'),
(29, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 29, NULL, '', NULL, '2025-11-18 14:01:02', NULL, 'ABALDI'),
(30, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 30, NULL, '', NULL, '2025-11-18 14:01:02', NULL, 'ABALDI'),
(31, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 31, NULL, '', NULL, '2025-11-18 14:01:02', NULL, 'ABALDI'),
(32, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 32, NULL, '', NULL, '2025-11-18 14:01:02', NULL, 'ABALDI'),
(33, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 33, NULL, '', NULL, '2025-11-18 14:01:02', NULL, 'ABALDI'),
(34, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 34, NULL, '', NULL, '2025-11-18 14:01:02', NULL, 'ABALDI'),
(35, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 35, NULL, '', NULL, '2025-11-18 14:01:02', NULL, 'ABALDI'),
(36, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 36, NULL, '', NULL, '2025-11-18 14:01:02', NULL, 'ABALDI'),
(37, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 37, NULL, '', NULL, '2025-11-18 14:01:02', NULL, 'ABALDI'),
(38, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 38, NULL, '', NULL, '2025-11-18 14:01:02', NULL, 'ABALDI'),
(39, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 39, NULL, '', NULL, '2025-11-18 14:01:02', NULL, 'ABALDI'),
(40, 'storage/listas_control/S3/Campo de la Conducción/Informática.xlsx', 40, NULL, '', NULL, '2025-11-18 14:01:02', NULL, 'ABALDI'),
(41, 'storage/listas_control/S3/Campo de la Conducción/Núcleo Instrucción Básico.xlsx', 1, NULL, '', NULL, '2025-11-18 14:08:04', NULL, 'CNEIRA'),
(42, 'storage/listas_control/S3/Campo de la Conducción/Núcleo Instrucción Básico.xlsx', 2, NULL, '', NULL, '2025-11-18 14:08:04', NULL, 'CNEIRA'),
(43, 'storage/listas_control/S3/Campo de la Conducción/Núcleo Instrucción Básico.xlsx', 3, NULL, '', NULL, '2025-11-18 14:08:04', NULL, 'CNEIRA'),
(44, 'storage/listas_control/S3/Campo de la Conducción/Núcleo Instrucción Básico.xlsx', 4, NULL, '', NULL, '2025-11-18 14:08:04', NULL, 'CNEIRA'),
(45, 'storage/listas_control/S3/Campo de la Conducción/Núcleo Instrucción Básico.xlsx', 5, NULL, '', NULL, '2025-11-18 14:08:04', NULL, 'CNEIRA'),
(46, 'storage/listas_control/S3/Campo de la Conducción/Núcleo Instrucción Básico.xlsx', 6, NULL, '', NULL, '2025-11-18 14:08:04', NULL, 'CNEIRA'),
(47, 'storage/listas_control/S3/Campo de la Conducción/Núcleo Instrucción Básico.xlsx', 7, NULL, '', NULL, '2025-11-18 14:08:04', NULL, 'CNEIRA'),
(48, 'storage/listas_control/S3/Campo de la Conducción/Núcleo Instrucción Básico.xlsx', 8, NULL, '', NULL, '2025-11-18 14:08:04', NULL, 'CNEIRA'),
(49, 'storage/listas_control/S3/Campo de la Conducción/Núcleo Instrucción Básico.xlsx', 9, NULL, '', NULL, '2025-11-18 14:08:04', NULL, 'CNEIRA'),
(50, 'storage/listas_control/S3/Campo de la Conducción/Núcleo Instrucción Básico.xlsx', 10, NULL, '', NULL, '2025-11-18 14:08:04', NULL, 'CNEIRA'),
(51, 'storage/listas_control/S3/Campo de la Conducción/Núcleo Instrucción Básico.xlsx', 11, NULL, '', NULL, '2025-11-18 14:08:04', NULL, 'CNEIRA'),
(52, 'storage/listas_control/S3/Campo de la Conducción/Núcleo Instrucción Básico.xlsx', 12, NULL, '', NULL, '2025-11-18 14:08:04', NULL, 'CNEIRA'),
(53, 'storage/listas_control/S3/Campo de la Conducción/Núcleo Instrucción Básico.xlsx', 13, NULL, '', NULL, '2025-11-18 14:08:04', NULL, 'CNEIRA'),
(54, 'storage/ultima_inspeccion/Operaciones (S-3)/Ámbito Funcional.xlsx', 1, 'no', '', NULL, '2025-11-20 13:51:02', NULL, 'GALMADA'),
(55, 'storage/ultima_inspeccion/Operaciones (S-3)/Ámbito Funcional.xlsx', 2, NULL, 'Agregar en el PEU 2026', NULL, '2025-11-20 13:51:02', NULL, 'GALMADA'),
(56, 'storage/ultima_inspeccion/Operaciones (S-3)/Ámbito Funcional.xlsx', 3, NULL, '', NULL, '2025-11-20 13:51:02', NULL, 'GALMADA'),
(57, 'storage/ultima_inspeccion/Operaciones (S-3)/Ámbito Funcional.xlsx', 4, NULL, 'Realizados en el año 2025', NULL, '2025-11-20 13:51:02', NULL, 'GALMADA'),
(58, 'storage/ultima_inspeccion/Operaciones (S-3)/Ámbito Funcional.xlsx', 5, NULL, '', NULL, '2025-11-20 13:51:02', NULL, 'GALMADA'),
(59, 'storage/ultima_inspeccion/Operaciones (S-3)/Ámbito Funcional.xlsx', 6, NULL, '', NULL, '2025-11-20 13:51:02', NULL, 'GALMADA'),
(60, 'storage/ultima_inspeccion/Operaciones (S-3)/Ámbito Funcional.xlsx', 7, NULL, '', NULL, '2025-11-20 13:51:02', NULL, 'GALMADA'),
(61, 'storage/ultima_inspeccion/Operaciones (S-3)/Ámbito Funcional.xlsx', 8, NULL, '', NULL, '2025-11-20 13:51:02', NULL, 'GALMADA'),
(62, 'storage/ultima_inspeccion/Operaciones (S-3)/Ámbito Funcional.xlsx', 9, NULL, '', NULL, '2025-11-20 13:51:02', NULL, 'GALMADA'),
(63, 'storage/ultima_inspeccion/Operaciones (S-3)/Ámbito Funcional.xlsx', 10, NULL, '', NULL, '2025-11-20 13:51:02', NULL, 'GALMADA'),
(64, 'storage/ultima_inspeccion/Operaciones (S-3)/Ámbito Funcional.xlsx', 11, NULL, '', NULL, '2025-11-20 13:51:02', NULL, 'GALMADA'),
(65, 'storage/listas_control/S1/Area Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 1, NULL, '', NULL, '2025-11-18 15:46:01', NULL, 'RIBARROLA'),
(66, 'storage/listas_control/S1/Area Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 2, NULL, '', NULL, '2025-11-18 15:46:01', NULL, 'RIBARROLA'),
(67, 'storage/listas_control/S1/Area Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 3, NULL, '', NULL, '2025-11-18 15:46:01', NULL, 'RIBARROLA'),
(68, 'storage/listas_control/S1/Area Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 4, NULL, '', NULL, '2025-11-18 15:46:02', NULL, 'RIBARROLA'),
(69, 'storage/listas_control/S1/Area Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 5, NULL, '', NULL, '2025-11-18 15:46:02', NULL, 'RIBARROLA'),
(70, 'storage/listas_control/S1/Area Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 6, NULL, '', NULL, '2025-11-18 15:46:02', NULL, 'RIBARROLA'),
(71, 'storage/listas_control/S1/Area Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 7, NULL, '', NULL, '2025-11-18 15:46:02', NULL, 'RIBARROLA'),
(72, 'storage/listas_control/S1/Area Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 8, NULL, '', NULL, '2025-11-18 15:46:02', NULL, 'RIBARROLA'),
(73, 'storage/listas_control/S1/Area Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 9, NULL, '', NULL, '2025-11-18 15:46:02', NULL, 'RIBARROLA'),
(74, 'storage/listas_control/S1/Area Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 10, NULL, '', NULL, '2025-11-18 15:46:02', NULL, 'RIBARROLA'),
(75, 'storage/listas_control/S1/Area Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 11, NULL, '', NULL, '2025-11-18 15:46:02', NULL, 'RIBARROLA'),
(76, 'storage/listas_control/S1/Area Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 12, NULL, '', NULL, '2025-11-18 15:46:02', NULL, 'RIBARROLA'),
(77, 'storage/listas_control/S1/Area Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 13, NULL, '', NULL, '2025-11-18 15:46:02', NULL, 'RIBARROLA'),
(78, 'storage/listas_control/S1/Area Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 14, NULL, '', NULL, '2025-11-18 15:46:02', NULL, 'RIBARROLA'),
(79, 'storage/listas_control/S1/Area Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 15, NULL, '', NULL, '2025-11-18 15:46:02', NULL, 'RIBARROLA'),
(80, 'storage/listas_control/S1/Area Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 16, NULL, '', NULL, '2025-11-18 15:46:02', NULL, 'RIBARROLA'),
(81, 'storage/listas_control/S1/Area Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 17, NULL, '', NULL, '2025-11-18 15:46:02', NULL, 'RIBARROLA'),
(82, 'storage/listas_control/S1/Area Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 18, NULL, '', NULL, '2025-11-18 15:46:02', NULL, 'RIBARROLA'),
(83, 'storage/listas_control/S1/Area Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 19, NULL, '', NULL, '2025-11-18 15:46:02', NULL, 'RIBARROLA'),
(84, 'storage/listas_control/S1/Area Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 20, NULL, '', NULL, '2025-11-18 15:46:02', NULL, 'RIBARROLA'),
(85, 'storage/ultima_inspeccion/Aspectos Generales/Ámbito Funcional – Área Formal/Ámbito Funcional – Área Formal.xlsx', 1, NULL, '', NULL, '2025-11-18 15:43:30', NULL, 'RIBARROLA'),
(86, 'storage/ultima_inspeccion/Aspectos Generales/Ámbito Funcional – Área Formal/Ámbito Funcional – Área Formal.xlsx', 2, NULL, '', NULL, '2025-11-18 15:43:30', NULL, 'RIBARROLA'),
(87, 'storage/ultima_inspeccion/Aspectos Generales/Ámbito Funcional – Área Formal/Ámbito Funcional – Área Formal.xlsx', 3, NULL, '', NULL, '2025-11-18 15:43:30', NULL, 'RIBARROLA'),
(88, 'storage/ultima_inspeccion/Aspectos Generales/Ámbito Funcional – Área Formal/Ámbito Funcional – Área Formal.xlsx', 4, 'si', 'se programamo instrucción en la semana 9', NULL, '2025-11-18 15:43:30', 'baja', 'RIBARROLA'),
(89, 'storage/ultima_inspeccion/Aspectos Generales/Ámbito Funcional – Área Formal/Ámbito Funcional – Área Formal.xlsx', 5, NULL, '', NULL, '2025-11-18 15:43:30', NULL, 'RIBARROLA'),
(90, 'storage/listas_control/S3/Área Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 1, NULL, '', NULL, '2025-11-18 19:06:33', NULL, 'RIBARROLA'),
(91, 'storage/listas_control/S3/Área Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 2, NULL, '', NULL, '2025-11-18 19:06:33', NULL, 'RIBARROLA'),
(92, 'storage/listas_control/S3/Área Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 3, NULL, '', NULL, '2025-11-18 19:06:33', NULL, 'RIBARROLA'),
(93, 'storage/listas_control/S3/Área Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 4, NULL, '', NULL, '2025-11-18 19:06:33', NULL, 'RIBARROLA'),
(94, 'storage/listas_control/S3/Área Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 5, NULL, '', NULL, '2025-11-18 19:06:33', NULL, 'RIBARROLA'),
(95, 'storage/listas_control/S3/Área Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 6, NULL, '', NULL, '2025-11-18 19:06:33', NULL, 'RIBARROLA'),
(96, 'storage/listas_control/S3/Área Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 7, NULL, '', NULL, '2025-11-18 19:06:33', NULL, 'RIBARROLA'),
(97, 'storage/listas_control/S3/Área Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 8, NULL, '', NULL, '2025-11-18 19:06:33', NULL, 'RIBARROLA'),
(98, 'storage/listas_control/S3/Área Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 9, NULL, '', NULL, '2025-11-18 19:06:33', NULL, 'RIBARROLA'),
(99, 'storage/listas_control/S3/Área Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 10, NULL, '', NULL, '2025-11-18 19:06:33', NULL, 'RIBARROLA'),
(100, 'storage/listas_control/S3/Área Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 11, NULL, '', NULL, '2025-11-18 19:06:33', NULL, 'RIBARROLA'),
(101, 'storage/listas_control/S3/Área Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 12, NULL, '', NULL, '2025-11-18 19:06:33', NULL, 'RIBARROLA'),
(102, 'storage/listas_control/S3/Área Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 13, NULL, '', NULL, '2025-11-18 19:06:33', NULL, 'RIBARROLA'),
(103, 'storage/listas_control/S3/Área Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 14, NULL, '', NULL, '2025-11-18 19:06:33', NULL, 'RIBARROLA'),
(104, 'storage/listas_control/S3/Área Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 15, NULL, '', NULL, '2025-11-18 19:06:33', NULL, 'RIBARROLA'),
(105, 'storage/listas_control/S3/Área Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 16, NULL, '', NULL, '2025-11-18 19:06:33', NULL, 'RIBARROLA'),
(106, 'storage/listas_control/S3/Área Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 17, NULL, '', NULL, '2025-11-18 19:06:33', NULL, 'RIBARROLA'),
(107, 'storage/listas_control/S3/Área Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 18, NULL, '', NULL, '2025-11-18 19:06:33', NULL, 'RIBARROLA'),
(108, 'storage/listas_control/S3/Área Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 19, NULL, '', NULL, '2025-11-18 19:06:33', NULL, 'RIBARROLA'),
(109, 'storage/listas_control/S3/Área Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 20, NULL, '', NULL, '2025-11-18 19:06:33', NULL, 'RIBARROLA'),
(110, 'storage/listas_control/S3/Área Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 61, NULL, '', NULL, '2025-11-18 19:07:26', NULL, 'RIBARROLA'),
(111, 'storage/listas_control/S3/Área Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 62, NULL, '', NULL, '2025-11-18 19:07:26', NULL, 'RIBARROLA'),
(112, 'storage/listas_control/S3/Área Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 63, NULL, '', NULL, '2025-11-18 19:07:26', NULL, 'RIBARROLA'),
(113, 'storage/listas_control/S3/Área Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 64, NULL, '', NULL, '2025-11-18 19:07:26', NULL, 'RIBARROLA'),
(114, 'storage/listas_control/S3/Área Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 65, NULL, '', NULL, '2025-11-18 19:07:26', NULL, 'RIBARROLA'),
(115, 'storage/listas_control/S3/Área Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 66, NULL, '', NULL, '2025-11-18 19:07:26', NULL, 'RIBARROLA'),
(116, 'storage/listas_control/S3/Área Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 67, NULL, '', NULL, '2025-11-18 19:07:26', NULL, 'RIBARROLA'),
(117, 'storage/listas_control/S3/Área Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 68, NULL, '', NULL, '2025-11-18 19:07:26', NULL, 'RIBARROLA'),
(118, 'storage/listas_control/S3/Área Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 69, NULL, '', NULL, '2025-11-18 19:07:26', NULL, 'RIBARROLA'),
(119, 'storage/listas_control/S3/Área Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 70, NULL, '', NULL, '2025-11-18 19:07:26', NULL, 'RIBARROLA'),
(120, 'storage/listas_control/S3/Área Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 71, NULL, '', NULL, '2025-11-18 19:07:26', NULL, 'RIBARROLA'),
(121, 'storage/listas_control/S3/Área Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 72, NULL, '', NULL, '2025-11-18 19:07:26', NULL, 'RIBARROLA'),
(122, 'storage/listas_control/S3/Área Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 73, NULL, '', NULL, '2025-11-18 19:07:26', NULL, 'RIBARROLA'),
(123, 'storage/listas_control/S3/Área Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 74, NULL, '', NULL, '2025-11-18 19:07:26', NULL, 'RIBARROLA'),
(124, 'storage/listas_control/S3/Área Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 75, NULL, '', NULL, '2025-11-18 19:07:26', NULL, 'RIBARROLA'),
(125, 'storage/listas_control/S3/Área Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 76, NULL, '', NULL, '2025-11-18 19:07:26', NULL, 'RIBARROLA'),
(126, 'storage/ultima_inspeccion/Materiales (S-4)/Ámbito Funcional.xlsx', 1, 'no', '', NULL, '2025-11-19 12:53:08', NULL, 'RIBARROLA'),
(127, 'storage/ultima_inspeccion/Materiales (S-4)/Ámbito Funcional.xlsx', 2, NULL, '', NULL, '2025-11-19 12:53:08', NULL, 'RIBARROLA'),
(128, 'storage/ultima_inspeccion/Materiales (S-4)/Ámbito Funcional.xlsx', 3, NULL, '', NULL, '2025-11-19 12:53:08', NULL, 'RIBARROLA'),
(129, 'storage/ultima_inspeccion/Materiales (S-4)/Ámbito Funcional.xlsx', 4, NULL, '', NULL, '2025-11-19 12:53:08', NULL, 'RIBARROLA'),
(130, 'storage/ultima_inspeccion/Materiales (S-4)/Ámbito Funcional.xlsx', 5, NULL, '', NULL, '2025-11-19 12:53:08', NULL, 'RIBARROLA'),
(131, 'storage/ultima_inspeccion/Materiales (S-4)/Ámbito Funcional.xlsx', 6, NULL, '', NULL, '2025-11-19 12:53:08', NULL, 'RIBARROLA'),
(132, 'storage/ultima_inspeccion/Materiales (S-4)/Ámbito Funcional.xlsx', 7, NULL, '', NULL, '2025-11-19 12:53:08', NULL, 'RIBARROLA'),
(133, 'storage/ultima_inspeccion/Materiales (S-4)/Ámbito Funcional.xlsx', 8, NULL, '', NULL, '2025-11-19 12:53:08', NULL, 'RIBARROLA'),
(134, 'storage/ultima_inspeccion/Materiales (S-4)/Ámbito Funcional.xlsx', 9, NULL, '', NULL, '2025-11-19 12:53:08', NULL, 'RIBARROLA'),
(135, 'storage/ultima_inspeccion/Materiales (S-4)/Ámbito Funcional.xlsx', 10, NULL, '', NULL, '2025-11-19 12:53:08', NULL, 'RIBARROLA'),
(136, 'storage/ultima_inspeccion/Materiales (S-4)/Ámbito Funcional.xlsx', 11, NULL, '', NULL, '2025-11-19 12:53:08', NULL, 'RIBARROLA'),
(137, 'storage/ultima_inspeccion/Personal (S-1)/Ámbito Funcional/Ámbito Funcional.xlsx', 1, 'no', '', NULL, '2025-11-20 12:02:37', NULL, 'GALMADA'),
(138, 'storage/ultima_inspeccion/Personal (S-1)/Ámbito Funcional/Ámbito Funcional.xlsx', 2, NULL, '', NULL, '2025-11-20 12:02:37', NULL, 'GALMADA'),
(139, 'storage/ultima_inspeccion/Personal (S-1)/Ámbito Funcional/Ámbito Funcional.xlsx', 3, NULL, '', NULL, '2025-11-20 12:02:37', NULL, 'GALMADA'),
(140, 'storage/ultima_inspeccion/Personal (S-1)/Ámbito Funcional/Ámbito Funcional.xlsx', 4, NULL, '', NULL, '2025-11-20 12:02:37', NULL, 'GALMADA'),
(141, 'storage/ultima_inspeccion/Personal (S-1)/Ámbito Funcional/Ámbito Funcional.xlsx', 5, NULL, '', NULL, '2025-11-20 12:02:37', NULL, 'GALMADA'),
(142, 'storage/ultima_inspeccion/Personal (S-1)/Ámbito Funcional/Ámbito Funcional.xlsx', 6, NULL, '', NULL, '2025-11-20 12:02:37', NULL, 'GALMADA'),
(143, 'storage/ultima_inspeccion/Personal (S-1)/Ámbito Funcional/Ámbito Funcional.xlsx', 7, NULL, '', NULL, '2025-11-20 12:02:37', NULL, 'GALMADA'),
(144, 'storage/ultima_inspeccion/Personal (S-1)/Ámbito Funcional/Ámbito Funcional.xlsx', 8, NULL, '', NULL, '2025-11-20 12:02:37', NULL, 'GALMADA'),
(145, 'storage/ultima_inspeccion/Personal (S-1)/Ámbito Funcional/Ámbito Funcional.xlsx', 9, NULL, '', NULL, '2025-11-20 12:02:37', NULL, 'GALMADA'),
(146, 'storage/ultima_inspeccion/Personal (S-1)/Ámbito Funcional/Ámbito Funcional.xlsx', 10, NULL, '', NULL, '2025-11-20 12:02:37', NULL, 'GALMADA'),
(147, 'storage/ultima_inspeccion/Personal (S-1)/Ámbito Funcional/Ámbito Funcional.xlsx', 11, NULL, '', NULL, '2025-11-20 12:02:37', NULL, 'GALMADA'),
(148, 'storage/ultima_inspeccion/Personal (S-1)/Ámbito Funcional/Ámbito Funcional.xlsx', 12, NULL, '', NULL, '2025-11-20 12:02:37', NULL, 'GALMADA'),
(149, 'storage/visitas_de_estado_mayor/Operaciones (S-3)/Área Informatica - Ciberdefensa/Área Informatica - Ciberdefensa.xlsx', 1, 'si', '', NULL, '2025-11-20 13:46:30', 'baja', 'GALMADA'),
(150, 'storage/visitas_de_estado_mayor/Operaciones (S-3)/Área Informatica - Ciberdefensa/Área Informatica - Ciberdefensa.xlsx', 2, NULL, '', NULL, '2025-11-20 13:46:30', NULL, 'GALMADA'),
(151, 'storage/visitas_de_estado_mayor/Operaciones (S-3)/Área Informatica - Ciberdefensa/Área Informatica - Ciberdefensa.xlsx', 3, NULL, '', NULL, '2025-11-20 13:46:30', NULL, 'GALMADA'),
(152, 'storage/visitas_de_estado_mayor/Operaciones (S-3)/Área Informatica - Ciberdefensa/Área Informatica - Ciberdefensa.xlsx', 4, NULL, '', NULL, '2025-11-20 13:46:31', NULL, 'GALMADA'),
(153, 'storage/visitas_de_estado_mayor/Operaciones (S-3)/Área Informatica - Ciberdefensa/Área Informatica - Ciberdefensa.xlsx', 5, NULL, '', NULL, '2025-11-20 13:46:31', NULL, 'GALMADA'),
(154, 'storage/visitas_de_estado_mayor/Operaciones (S-3)/Área Informatica - Ciberdefensa/Área Informatica - Ciberdefensa.xlsx', 6, NULL, '', NULL, '2025-11-20 13:46:31', NULL, 'GALMADA'),
(155, 'storage/visitas_de_estado_mayor/Operaciones (S-3)/Área Informatica - Ciberdefensa/Área Informatica - Ciberdefensa.xlsx', 7, NULL, '', NULL, '2025-11-20 13:46:31', NULL, 'GALMADA'),
(156, 'storage/visitas_de_estado_mayor/Operaciones (S-3)/Área Informatica - Ciberdefensa/Área Informatica - Ciberdefensa.xlsx', 8, NULL, '', NULL, '2025-11-20 13:46:31', NULL, 'GALMADA'),
(157, 'storage/visitas_de_estado_mayor/Inteligencia (S-2)/Área Inteligencia/Área Inteligencia.xlsx', 1, 'si', '', NULL, '2025-11-26 13:38:14', NULL, 'RIBARROLA'),
(158, 'storage/visitas_de_estado_mayor/Inteligencia (S-2)/Área Inteligencia/Área Inteligencia.xlsx', 2, 'no', '', NULL, '2025-11-26 13:38:14', NULL, 'RIBARROLA'),
(159, 'storage/visitas_de_estado_mayor/Inteligencia (S-2)/Área Inteligencia/Área Inteligencia.xlsx', 3, 'no', '', NULL, '2025-11-26 13:38:14', NULL, 'RIBARROLA'),
(160, 'storage/visitas_de_estado_mayor/Inteligencia (S-2)/Área Inteligencia/Área Inteligencia.xlsx', 4, 'no', '', NULL, '2025-11-26 13:38:14', NULL, 'RIBARROLA'),
(161, 'storage/visitas_de_estado_mayor/Inteligencia (S-2)/Área Inteligencia/Área Inteligencia.xlsx', 5, 'no', '', NULL, '2025-11-26 13:38:14', NULL, 'RIBARROLA'),
(162, 'storage/visitas_de_estado_mayor/Inteligencia (S-2)/Área Inteligencia/Área Inteligencia.xlsx', 6, NULL, '', NULL, '2025-11-26 13:38:14', NULL, 'RIBARROLA'),
(163, 'storage/visitas_de_estado_mayor/Inteligencia (S-2)/Área Inteligencia/Área Inteligencia.xlsx', 7, NULL, '', NULL, '2025-11-26 13:38:14', NULL, 'RIBARROLA'),
(164, 'storage/visitas_de_estado_mayor/Inteligencia (S-2)/Área Inteligencia/Área Inteligencia.xlsx', 8, NULL, '', NULL, '2025-11-26 13:38:14', NULL, 'RIBARROLA'),
(165, 'storage/visitas_de_estado_mayor/Inteligencia (S-2)/Área Inteligencia/Área Inteligencia.xlsx', 9, NULL, '', NULL, '2025-11-26 13:38:14', NULL, 'RIBARROLA'),
(166, 'storage/visitas_de_estado_mayor/Inteligencia (S-2)/Área Inteligencia/Área Inteligencia.xlsx', 10, NULL, '', NULL, '2025-11-26 13:38:14', NULL, 'RIBARROLA'),
(167, 'storage/visitas_de_estado_mayor/Inteligencia (S-2)/Área Inteligencia/Área Inteligencia.xlsx', 11, NULL, '', NULL, '2025-11-26 13:38:14', NULL, 'RIBARROLA'),
(168, 'storage/visitas_de_estado_mayor/Inteligencia (S-2)/Área Inteligencia/Área Inteligencia.xlsx', 12, NULL, '', NULL, '2025-11-26 13:38:14', NULL, 'RIBARROLA'),
(169, 'storage/visitas_de_estado_mayor/Inteligencia (S-2)/Área Inteligencia/Área Inteligencia.xlsx', 13, NULL, '', NULL, '2025-11-26 13:38:14', NULL, 'RIBARROLA'),
(170, 'storage/visitas_de_estado_mayor/Inteligencia (S-2)/Área Inteligencia/Área Inteligencia.xlsx', 14, NULL, '', NULL, '2025-11-26 13:38:14', NULL, 'RIBARROLA');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `checklist_form`
--

CREATE TABLE `checklist_form` (
  `id` int(11) NOT NULL,
  `file_rel` varchar(512) NOT NULL,
  `row_idx` int(11) NOT NULL,
  `field_key` varchar(128) NOT NULL,
  `field_value` text DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `documentos`
--

CREATE TABLE `documentos` (
  `id` int(11) NOT NULL,
  `area_id` int(11) DEFAULT NULL,
  `nombre_archivo` varchar(255) DEFAULT NULL,
  `ruta` varchar(255) DEFAULT NULL,
  `hash_sha1` varchar(64) DEFAULT NULL,
  `estado_ingesta` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `documentos`
--

INSERT INTO `documentos` (`id`, `area_id`, `nombre_archivo`, `ruta`, `hash_sha1`, `estado_ingesta`) VALUES
(1, 1, 'Liasta de Control Nro 1 - 0001 - Actividades previas a la Inspección. (1).pdf', 'storage/pdf_ok/S1/Liasta de Control Nro 1 - 0001 - Actividades previas a la Inspección. (1).pdf', 'c3fcfc5898ddbeed1738edb6729afb81110a63d6', 'ok'),
(2, 1, 'Lista Control Nro 1 - 0003 - Formación de Tropas.pdf', 'storage/pdf_ok/S1/Lista Control Nro 1 - 0003 - Formación de Tropas.pdf', 'f52721525ce4d1d38249b5766fc609359ffc166f', 'ok'),
(3, 1, 'Lista Control Nro 1 - 0004 - Desfile a Pie.pdf', 'storage/pdf_ok/S1/Lista Control Nro 1 - 0004 - Desfile a Pie.pdf', '76a38d7abda0d84ef0029130151460a23eb53585', 'ok'),
(4, 1, 'Lista Control Nro 1 - 0005 - Revista de Vehículos.pdf', 'storage/pdf_ok/S1/Lista Control Nro 1 - 0005 - Revista de Vehículos.pdf', 'c7749e1f641b2e4f8625bf1bd735d9727483b070', 'ok'),
(5, 1, 'Lista Control Nro 1 - 0006 - Presentación de Cuadros.pdf', 'storage/pdf_ok/S1/Lista Control Nro 1 - 0006 - Presentación de Cuadros.pdf', 'a81ce63567b20ae7a86323fccae0c9b9f3bc3771', 'ok'),
(6, 1, 'Lista Control Nro 1 - 0007 - Exposición del Jefe de Elemento.pdf', 'storage/pdf_ok/S1/Lista Control Nro 1 - 0007 - Exposición del Jefe de Elemento.pdf', '593cbaffc8baf1b2b15ef46392ba2bc8cc3c9fcf', 'ok'),
(7, 1, 'Lista Control Nro 1 - 0009 - Recorrida de la Jefatura.pdf', 'storage/pdf_ok/S1/Lista Control Nro 1 - 0009 - Recorrida de la Jefatura.pdf', 'db38f3f9b3bd66bddd56acfd724ac2a8f21917ac', 'ok'),
(8, 1, 'Lista de Control Nro 1 - 0008 - Recorrida de Instalaciones..pdf', 'storage/pdf_ok/S1/Lista de Control Nro 1 - 0008 - Recorrida de Instalaciones..pdf', 'be5bc6a3ff8fbbc7fa0c3c33e370b46dfa75ee36', 'ok'),
(9, 1, 'Apresto de la FRI.pdf', 'storage/pdf_ok/S1/Apresto de la FRI.pdf', 'db77eebd312a5b26ffd2dccf60183010dee7d621', 'ok'),
(10, 1, 'Aviación de Ejército.pdf', 'storage/pdf_ok/S1/Aviación de Ejército.pdf', 'aed7b7e4f09589616efe5defd7935224b054ef9c', 'ok'),
(11, 1, 'BIENESTAR (Guarnicional).pdf', 'storage/pdf_ok/S1/BIENESTAR (Guarnicional).pdf', 'dcaa8221d594c53c427a656c84310dccf2448d65', 'ok'),
(12, 1, 'Bromatología y Salud Pública.pdf', 'storage/pdf_ok/S1/Bromatología y Salud Pública.pdf', '0319194151301b034f926426d1d12d7a8d3a22bb', 'ok'),
(13, 1, 'Dirección de Intendencia.pdf', 'storage/pdf_ok/S1/Dirección de Intendencia.pdf', '39f208cdb0f64675ed5278a6cea5111391cc1b40', 'ok'),
(14, 1, 'Divisiones; Secciones Intendencia Jurisdiccional - GGUU.pdf', 'storage/pdf_ok/S1/Divisiones; Secciones Intendencia Jurisdiccional - GGUU.pdf', '87ee1c077421964af9a79c545ffeaa74fbeb1d95', 'ok'),
(15, 1, 'Helipuerto de campaña.pdf', 'storage/pdf_ok/S1/Helipuerto de campaña.pdf', '2659e774230bdb17fe7884c7ca113a6fc54faeb8', 'ok'),
(16, 1, 'Informática.pdf', 'storage/pdf_ok/S1/Informática.pdf', '7f27e9b704d92485f2a393003341ff749403897d', 'ok'),
(17, 1, 'Justicia Militar - Pel(s) Just(s) Elem(s).pdf', 'storage/pdf_ok/S1/Justicia Militar - Pel(s) Just(s) Elem(s).pdf', 'b3e05f571d2720a1e42a5c148ba8d65d4e88a9b3', 'ok'),
(18, 1, 'Lista de control de Farmacias y depósito de Efectos Cl II Y IV San.pdf', 'storage/pdf_ok/S1/Lista de control de Farmacias y depósito de Efectos Cl II Y IV San.pdf', 'fb38a30ae2863fe0757cda576a552236f9dbfcc8', 'ok'),
(19, 1, 'LISTA DE CONTROL DEL PROCESO A CONTROLAR DE PERSONAL Nro 01.pdf', 'storage/pdf_ok/S1/LISTA DE CONTROL DEL PROCESO A CONTROLAR DE PERSONAL Nro 01.pdf', '198b4869a56720e8b42e2fa1265d090681be3a32', 'ok'),
(20, 1, 'LISTA DE CONTROL DEL PROCESO A CONTROLAR DE PERSONAL Nro 02.pdf', 'storage/pdf_ok/S1/LISTA DE CONTROL DEL PROCESO A CONTROLAR DE PERSONAL Nro 02.pdf', '55ea1a88b7f2008f74335716714205643cee41ca', 'ok'),
(21, 1, 'LISTA DE CONTROL DEL PROCESO A CONTROLAR DE PERSONAL Nro 03.pdf', 'storage/pdf_ok/S1/LISTA DE CONTROL DEL PROCESO A CONTROLAR DE PERSONAL Nro 03.pdf', '9e93ec9ec9435590416420e7edb522e795307fd2', 'ok'),
(22, 1, 'LISTA DE CONTROL DEL PROCESO A CONTROLAR DE PERSONAL Nro 04.pdf', 'storage/pdf_ok/S1/LISTA DE CONTROL DEL PROCESO A CONTROLAR DE PERSONAL Nro 04.pdf', 'bcaa7a911190ffa9d81a171e4ceaf1ba1f20e585', 'ok'),
(23, 1, 'Núcleo Instrucción Básico.pdf', 'storage/pdf_ok/S1/Núcleo Instrucción Básico.pdf', 'f414b86200c112cbcb320f3102a6433637d2120e', 'ok'),
(24, 1, 'ORGANIZACIÓN Y FUNCIONAMIENTO DE LA SECCIÓN SANIDAD DE UNIDAD.pdf', 'storage/pdf_ok/S1/ORGANIZACIÓN Y FUNCIONAMIENTO DE LA SECCIÓN SANIDAD DE UNIDAD.pdf', 'f76cc49597bb5fc859e29c501e89b0c7e86e8033', 'ok'),
(25, 1, 'Organización y funcionamiento de la Sección Sanidad.pdf', 'storage/pdf_ok/S1/Organización y funcionamiento de la Sección Sanidad.pdf', 'c1c416bfb0a18f618d1b40964077e8bc3b96cc1b', 'ok'),
(26, 1, 'Personal Civil - Cdo(s) - UU - Org(S).pdf', 'storage/pdf_ok/S1/Personal Civil - Cdo(s) - UU - Org(S).pdf', '0b6ecf05c7f5efdc1bca0756954db501a807bcef', 'ok'),
(27, 1, 'Personal Docente Civil.pdf', 'storage/pdf_ok/S1/Personal Docente Civil.pdf', '69c98e4c18d6b4a1215f3bc8b29179f40bb6727c', 'ok'),
(28, 1, 'Preservación del Medio Ambiente.pdf', 'storage/pdf_ok/S1/Preservación del Medio Ambiente.pdf', 'e5b439ab4bcd942e2f59268a7b0298e6a9f7f565', 'ok'),
(29, 1, 'Programación de AMI.pdf', 'storage/pdf_ok/S1/Programación de AMI.pdf', 'e009e88fa6a1aace0cb818aea364ff86a07eb398', 'ok'),
(30, 1, 'Protección Civil (GUB - GUC - Elementos).pdf', 'storage/pdf_ok/S1/Protección Civil (GUB - GUC - Elementos).pdf', '8c2c19e66398223dcc07076b34492f839f18b77f', 'ok'),
(31, 1, 'Red Guarnicional Movil.pdf', 'storage/pdf_ok/S1/Red Guarnicional Movil.pdf', '8ca225fb2e34ce456c079da19801d3fab6b4aa22', 'ok'),
(32, 1, 'Relaciones Institucionales y Ceremonial.pdf', 'storage/pdf_ok/S1/Relaciones Institucionales y Ceremonial.pdf', 'dea959007b8e0f56aaa544afa584460e70be7517', 'ok'),
(33, 1, 'Sección-Gupo Intendencia - Direcciones; Unidades,Subunidades Independientes.pdf', 'storage/pdf_ok/S1/Sección-Gupo Intendencia - Direcciones; Unidades,Subunidades Independientes.pdf', '4c5097bca9eaff9158337bbaedaa16860d201336', 'ok'),
(34, 1, 'SUCOIGE.pdf', 'storage/pdf_ok/S1/SUCOIGE.pdf', '7e1f6473e27850b4e4462ffb74dbfde7805f9d08', 'ok'),
(35, 1, 'UU con Perros de Guerra.pdf', 'storage/pdf_ok/S1/UU con Perros de Guerra.pdf', '59c74957485f3ef8dbbeaaa52302c3726dd58bcc', 'ok'),
(36, 1, 'Área Jurídica (Para todos los Elementos que tengan Oficial Auditor).pdf', 'storage/pdf_ok/S1/Área Jurídica (Para todos los Elementos que tengan Oficial Auditor).pdf', '0443523c9dbfe1a5133b922a25d3263a1bc6a161', 'ok'),
(37, 1, 'Lista de Control Nro 13-0001 - Seguridad y salud ocupacional.pdf', 'storage/pdf_ok/S1/Lista de Control Nro 13-0001 - Seguridad y salud ocupacional.pdf', '4ca68ce3b36863f71df34d240bd602536fb5f675', 'ok'),
(38, 1, 'Lista de Control Nro 13-0003 - Precursores químicos.pdf', 'storage/pdf_ok/S1/Lista de Control Nro 13-0003 - Precursores químicos.pdf', 'ff795c374d34ca625b8ce0eda9d1830f0ebb0271', 'ok'),
(39, 1, 'Lista de Control Nro 13-0005 - Seguridad e higiene en el trabajo - Establecimientos Rurales.pdf', 'storage/pdf_ok/S1/Lista de Control Nro 13-0005 - Seguridad e higiene en el trabajo - Establecimientos Rurales.pdf', '275741619294f85febb314e8fe633028c8fc7aa6', 'ok'),
(40, 1, 'Lista de Control Nro 13-0006 - Control en centros de producción.pdf', 'storage/pdf_ok/S1/Lista de Control Nro 13-0006 - Control en centros de producción.pdf', '6b37df209e98b959d5a23b8d61afb5eebd1afd21', 'ok'),
(41, 1, 'Lista de Control Nro 13-0007 - Control de producción - Direcciones.pdf', 'storage/pdf_ok/S1/Lista de Control Nro 13-0007 - Control de producción - Direcciones.pdf', 'ee631e8e76fda4e9a250691137d3ead81835950b', 'ok'),
(42, 1, 'Lista de Control Nro 13-0008 - Control de producción de Sastrería Militar.pdf', 'storage/pdf_ok/S1/Lista de Control Nro 13-0008 - Control de producción de Sastrería Militar.pdf', '1e27b85b0d5158efa32cbd491ebd439b590684ab', 'ok'),
(43, 1, 'Lista de Control Nro 13-0009 - Control de producción del B Int 601 ANTONIO DEL PINO (Compañía Autoabastecimiento).pdf', 'storage/pdf_ok/S1/Lista de Control Nro 13-0009 - Control de producción del B Int 601 ANTONIO DEL PINO (Compañía Autoabastecimiento).pdf', 'bdeb41b054ada5f8714aac10cad094a4c2d97803', 'ok'),
(44, 1, 'Lista de Control Nro 14-0001 - Supervisión Funcional DGE - CAAE.pdf', 'storage/pdf_ok/S1/Lista de Control Nro 14-0001 - Supervisión Funcional DGE - CAAE.pdf', '9135e95cc1893d35bdf6ad7ee11e1a0b8d671ea3', 'ok'),
(45, 1, 'Lista de Control Nro 14-0002 - Supervisión Funcional GGUU-Equivalentes.pdf', 'storage/pdf_ok/S1/Lista de Control Nro 14-0002 - Supervisión Funcional GGUU-Equivalentes.pdf', 'bd522ca4643727adbf0fea552845c05a9e285eec', 'ok'),
(46, 1, 'Lista de Control Nro 14-0003 - Supervisión Funcional Elementos dependientes de la DGE.pdf', 'storage/pdf_ok/S1/Lista de Control Nro 14-0003 - Supervisión Funcional Elementos dependientes de la DGE.pdf', '39dd8e19fce3a410af04328bf7c64ee0b2790589', 'ok'),
(47, 1, 'Lista de Control Nro 14-0004 - Supervisión Funcional Unidades y Subunidades Independientes de la FO.pdf', 'storage/pdf_ok/S1/Lista de Control Nro 14-0004 - Supervisión Funcional Unidades y Subunidades Independientes de la FO.pdf', '024ca1a7af6ea66e794d2abd2561f9c9f1de8943', 'ok'),
(48, 1, 'Lista de Control Nro 14-0005 - Pruebas de Aptitud Física Básica.pdf', 'storage/pdf_ok/S1/Lista de Control Nro 14-0005 - Pruebas de Aptitud Física Básica.pdf', 'b114eb3a8d2c403da5f63de643e6372903d18ee3', 'ok'),
(49, 1, 'Lista de Control Nro 14-0006 - Pasaje de la Pista de Combate.pdf', 'storage/pdf_ok/S1/Lista de Control Nro 14-0006 - Pasaje de la Pista de Combate.pdf', 'e6213a170f3ad87a3b3fc92c86bb6bc8e877dd01', 'ok'),
(50, 1, 'Lista de Control Nro 14-0007 - Marcha a Pie con Equipo.pdf', 'storage/pdf_ok/S1/Lista de Control Nro 14-0007 - Marcha a Pie con Equipo.pdf', 'd77e396444664907b12e1076810f13e17074ff17', 'ok'),
(51, 1, 'Lista de Control Nro 14-0008 - Lanzamiento de Granada de Mano.pdf', 'storage/pdf_ok/S1/Lista de Control Nro 14-0008 - Lanzamiento de Granada de Mano.pdf', '48a77b530e07bfff02e0a1dcc3121dee46d17c64', 'ok'),
(52, 1, 'Lista de Control Nro 14-0009 - Trepar la Cuerda.pdf', 'storage/pdf_ok/S1/Lista de Control Nro 14-0009 - Trepar la Cuerda.pdf', '1d3cfcda9a2686d1000cb5cfffe2d2a758bfe9e2', 'ok'),
(53, 1, 'Lista de Control Nro 14-0010 - PAFO-Transporte de Heridos.pdf', 'storage/pdf_ok/S1/Lista de Control Nro 14-0010 - PAFO-Transporte de Heridos.pdf', 'f15df45aee79a8fcbe3fe1cf4267f68419d7cf57', 'ok'),
(54, 2, 'Lista de Control Nro 15-0001 - Cursos del Ser Bda(s) Mil(s) (CMN - ESESC).pdf', 'storage/pdf_ok/S2/Lista de Control Nro 15-0001 - Cursos del Ser Bda(s) Mil(s) (CMN - ESESC).pdf', '76261f9d6ab48459efb62ed55084d05dd43eb63f', 'ok'),
(55, 2, 'Lista de Control Nro 15-0002 - Bandas y Fanfarrias Militares - Elementos.pdf', 'storage/pdf_ok/S2/Lista de Control Nro 15-0002 - Bandas y Fanfarrias Militares - Elementos.pdf', '018d8e7c32ec332d399a6bfba44c026892bfdd50', 'ok'),
(56, 2, 'Lista de Control Nro 15-0003 - Para Div Bda Mil (GGUUB - DGE - Cdo Guar Mil Bs As).pdf', 'storage/pdf_ok/S2/Lista de Control Nro 15-0003 - Para Div Bda Mil (GGUUB - DGE - Cdo Guar Mil Bs As).pdf', 'e9a8746f5566227f5925e5fb69d78b600b0462a7', 'ok'),
(57, 2, 'Lista de Control Nro 15-0004 - Div Bda(S) Mil(s) - Dir Int.pdf', 'storage/pdf_ok/S2/Lista de Control Nro 15-0004 - Div Bda(S) Mil(s) - Dir Int.pdf', 'dbd1744c80bc1a84b4c0e7d8ebc7d3f2c8421b6e', 'ok'),
(58, 2, 'Lista de Control Nro 15-0005 - Servicio Bda(s) Mil(s) - DGOD.pdf', 'storage/pdf_ok/S2/Lista de Control Nro 15-0005 - Servicio Bda(s) Mil(s) - DGOD.pdf', '09659ba6a26d1c4b214b1a5dadc96a2421d3bcd1', 'ok'),
(59, 2, 'Lista de Control Nro 15-0006 - Instrumentos y Accesorios Musicales - Bandas y Fanfarrias Militares.pdf', 'storage/pdf_ok/S2/Lista de Control Nro 15-0006 - Instrumentos y Accesorios Musicales - Bandas y Fanfarrias Militares.pdf', '0cca5af7ddae57ce4e1e88d67156ec9f48efd873', 'ok'),
(60, 2, 'Lista de Control Nro 15-0007 - TALLER CENTRAL DE INSTRUMENTOS Y ACCESORIOS MUSICALES – B Int 601.pdf', 'storage/pdf_ok/S2/Lista de Control Nro 15-0007 - TALLER CENTRAL DE INSTRUMENTOS Y ACCESORIOS MUSICALES – B Int 601.pdf', '3fbfd96d100ce921c59c2d3a32b5333d878c6826', 'ok'),
(61, 2, 'Lista de Control 12-0001 - Construcciones - Elemento.pdf', 'storage/pdf_ok/S2/Lista de Control 12-0001 - Construcciones - Elemento.pdf', '9404ef35c356ab4d364ea447c2f882dba8cc029f', 'ok'),
(62, 2, 'Lista de Control 12-0002 - Bienes Raíces.pdf', 'storage/pdf_ok/S2/Lista de Control 12-0002 - Bienes Raíces.pdf', '8a04464b9ac7560899e2227ee61c2ddeb6ce3e97', 'ok'),
(63, 2, 'Lista de Control 12-0003 - Construcciones Barrios Militares.pdf', 'storage/pdf_ok/S2/Lista de Control 12-0003 - Construcciones Barrios Militares.pdf', '835617e1285d257d4660c365f385f837ad606a06', 'ok'),
(64, 2, 'Lista de Control 12-0004 - Aspectos Relevantes para TODOS los Barrios Militares, BBRR y Construcciones..pdf', 'storage/pdf_ok/S2/Lista de Control 12-0004 - Aspectos Relevantes para TODOS los Barrios Militares, BBRR y Construcciones..pdf', '32dee2780620f68731a55b1aab1161dd1bdfeb01', 'ok'),
(65, 2, 'Lista Control Nro 16-0001 - Gestión Logística (GGUU, Organismos y Elementos).pdf', 'storage/pdf_ok/S2/Lista Control Nro 16-0001 - Gestión Logística (GGUU, Organismos y Elementos).pdf', '6af7bd0aa00913e3429286d28ca6e35c423a8ce5', 'ok'),
(66, 2, 'Lista Control Nro 16-0002 - Control de Gestión Logístico Elementos Usuarios.pdf', 'storage/pdf_ok/S2/Lista Control Nro 16-0002 - Control de Gestión Logístico Elementos Usuarios.pdf', 'eb6c9394ab01c3a18ea50990833a57154e66753d', 'ok'),
(67, 2, 'Lista Control Nro 16-0003 - Finanzas - Unidades e Institutos - Barrios Militares.pdf', 'storage/pdf_ok/S2/Lista Control Nro 16-0003 - Finanzas - Unidades e Institutos - Barrios Militares.pdf', 'd226f2e2a786b9659c66cb9ad4c5227db11010db', 'ok'),
(68, 2, 'Lista Control Nro 16-0004 - Finanzas - CGE.pdf', 'storage/pdf_ok/S2/Lista Control Nro 16-0004 - Finanzas - CGE.pdf', 'e57cfa2d7acce9de41c0bf37f83a01d1e82abf3b', 'ok'),
(69, 2, 'Lista Control Nro 16-0005 - Control de Gestión Logística - Funciones del G5 (GGUU, Direcciones, Agrupaciones, y Otros organismos).pdf', 'storage/pdf_ok/S2/Lista Control Nro 16-0005 - Control de Gestión Logística - Funciones del G5 (GGUU, Direcciones, Agrupaciones, y Otros organismos).pdf', 'bd850d1a600a60022623163c00cb4d34fd7b0db2', 'ok');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `items`
--

CREATE TABLE `items` (
  `id` int(11) NOT NULL,
  `documento_id` int(11) DEFAULT NULL,
  `nro` varchar(50) DEFAULT NULL,
  `texto` text DEFAULT NULL,
  `obligatorio` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `personal_unidad`
--

CREATE TABLE `personal_unidad` (
  `id` int(11) NOT NULL,
  `grado` varchar(50) DEFAULT NULL,
  `arma` varchar(50) DEFAULT NULL,
  `nombre_apellido` varchar(255) NOT NULL,
  `dni` varchar(20) DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `peso_kg` decimal(5,2) DEFAULT NULL,
  `altura_cm` smallint(6) DEFAULT NULL,
  `destino` varchar(255) DEFAULT NULL,
  `situacion` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `personal_unidad`
--

INSERT INTO `personal_unidad` (`id`, `grado`, `arma`, `nombre_apellido`, `dni`, `fecha_nacimiento`, `peso_kg`, `altura_cm`, `destino`, `situacion`, `updated_at`, `updated_by`) VALUES
(1, 'TC', 'Com', 'Mariano Oscar GOMEZ', '11111111', NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(2, 'MY', 'Com', 'Maria Eugenia ROTELA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(3, 'MY', 'Com', 'Gerardo Hugo ALMADA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(4, 'MY', 'Com', 'Roberto Carlos Jesús CESPEDES', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(5, 'MY', 'Com', 'Andres FIDALGO', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(6, 'CT', 'Com', 'Nicolás Hugo REINA MARTINEZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(7, 'CT', 'Com', 'Braian RAMOS', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(8, 'CT', 'Com', 'Christian AUSILI', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(9, 'CT', 'Com', 'Pamela del Rosario MARTINEZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(10, 'TP', 'SCD', 'Gabriel Oscar GONZALEZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(11, 'TP', 'Com', 'Natalia ESPINOSA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(12, 'TP', 'Com', 'Natalia Carolina GONZALEZ BAIGORRIA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(13, 'TP', 'SCD', 'Jonás Fernando GOMEZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(14, 'TP', 'SCD', 'Juan Laureano MENDEZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(15, 'TP', 'SCD', 'Jorge Sebastian VILLAFAÑE', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(16, 'TP', 'Com', 'Florencia CARABAJAL', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(17, 'TT', 'SCD', 'Cristian Oscar ROSALES', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(18, 'ST', 'SCD', 'Gabriel Elisandro VEGA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(19, 'ST', 'SCD', 'Ivan Nicolas GALARZA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(20, 'ST', 'SCD Reclut Loc', 'Luis Daniel CRUZ OCHOA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(21, 'ST', 'SCD Reclut Loc', 'Isabel PEREZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(22, 'ST', 'SCD Reclut Loc', 'Guadalupe Ayelen KUCSERA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(23, 'SM', 'Mec Eq(s) Fij', 'Adrián Ariel PEREYRA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(24, 'SM', 'Mec Eq(s) Fij', 'Agustin FeliX Dionisio RIVAS', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(25, 'SP', 'Com', 'Javier PEREYRA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(26, 'SP', 'Mec Eq(s) Fij', 'Darío Adolfo ALIAGA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(27, 'SP', 'Mec Eq(s) Fij', 'María de los Angeles QUIÑONEZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(28, 'SP', 'Mec Camp', 'Romina Isabel BENITEZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(29, 'SP', 'Mec Eq(s) Fij', 'Ismael Ruben RAMOS', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(30, 'SP', 'Mec Eq(s) Fij', 'Ismael Ruben RAMOS', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(31, 'SP', 'Com', 'Macarena Emilia SUELDA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(32, 'SA', 'Com', 'Carlos Ruben DELGADO', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(33, 'SA', 'Mec Eq(s) Fij', 'Sergio Omar CHAVARRIA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(34, 'SA', 'Cond Mot', 'Enzo Reinaldo ARAMAYO', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(35, 'SI', 'Mec Eq(s) Fij', 'Ezequiel MaXimiliano ROSALES', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(36, 'SI', 'Com', 'Cristian Eduardo NEIRA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(37, 'SI', 'Com', 'Jorgelina Beatriz LERA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(38, 'SI', 'Mec Eq(s) Fij', 'Diego Alberto  CHAMBI', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(39, 'SI', 'Mec Eq(s) Fij', 'Jose Antonio FLORES', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(40, 'SI', 'Com', 'Julia Lorena DIAZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(41, 'SI', 'Mec Eq(s) Fij', 'Samuel Ángel PEÑALOZA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(42, 'SI', 'Mec Eq(s) Fij', 'Franco Mario Emanuel ESPECHE', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(43, 'SI', 'Mec Eq(s) Fij', 'Cristian David MORALES', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(44, 'SI', 'Mec Eq(s) Fij', 'Ricardo Daniel ESTOPIÑAN', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(45, 'SI', 'Mec Eq(s) Fij', 'Mauro Agustin BOURLOT', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(46, 'SG', 'Com', 'Walter Daniel BENVENUTTO', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(47, 'SG', 'Com', 'Armando Sebastían BALDI', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(48, 'SG', 'Mec Eq(s) Fij', 'Daiana Jaqueline FLEITAS', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(49, 'SG', 'Mec Eq(s) Fij', 'Verónica María CORTEZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(50, 'SG', 'Com', 'Alberto Jorge COSTILLA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(51, 'SG', 'Com', 'Yolanda Yamel DOMINGUEZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(52, 'SG', 'Mec Eq(s) Fij', 'Emanuel Rubén Darío VARGAS', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(53, 'SG', 'Mec Eq(s) Fij', 'Jose Alejandro BOGADO', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(54, 'SG', 'Mec Info', 'Víctor Sebastián VILLADA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(55, 'SG', 'Mec Eq(s) Fij', 'Elías Fernando VILLOLDO', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(56, 'SG', 'Com', 'Alejandro MARTINEZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(57, 'SG', 'Mec Camp', 'Jonathan Alejadro MIÑO', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(58, 'SG', 'Mec Info', 'Diego Sebastian GIMENEZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(59, 'SG', 'Cond Mot', 'Javier Alejandro PEDRAZA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(60, 'CI', 'Mec Eq(s) Fij', 'Cármen BESBERGEN', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(61, 'CI', 'Mec Info', 'Jésica Magalí TRINDADES', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(62, 'CI', 'Mec Eq(s) Fij', 'Diego Damian MAYA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(63, 'CI', 'Com', 'Gonzalo ZUÑIGA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(64, 'CI', 'Mec Info', 'Esteban Lionel GONZALEZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(65, 'CI', 'Com', 'Lara SAHMALZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(66, 'CI', 'Mec Info', 'Juan Antonio Sebastián MAMANI', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(67, 'CI', 'Mec Info', 'Jairo Alejandro YEVARA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(68, 'CI', 'Mec Camp(s)', 'Andrea Jaquelina PILQUIMAN', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(69, 'CI', 'Mec Eq(s) Fij', 'Fabiola Mariana de los Angeles CRUZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(70, 'CI', 'Mec Info', 'Yamil Mariano MONTIEL', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(71, 'CI', 'Mec Eq(s) Fij', 'Luis Agustín CORONEL', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(72, 'CI', 'Mec Mot Rue/Cond', 'Luis Fernando CAYO OLIVA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(73, 'CI', 'Ofic', 'Emiliano Damian PEREZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(74, 'CI', 'Cond Mot', 'Andres Ismael ORDOÑEZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(75, 'CI', 'Mec Eq(s) Fij', 'Pablo René SCHUH', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(76, 'CI', 'Mec Eq(s) Fij', 'Kevin Brain LUFFI ARENAS', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(77, 'CI', 'Com', 'Fernando Daniel CASTAÑO', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(78, 'CI', 'Mec Eq(s) Fij', 'Débora Cecilia TARNOWSKI', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(79, 'CB', 'Cond Mot', 'Juan Esteban SARACHO', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(80, 'CB', 'Mec Info', 'Ronaldo Roque LUNDA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(81, 'CB', 'Mec Eq(s) Fij', 'Walter Ezequiel BRITOS', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(82, 'CB', 'Mec Eq(s) Fij', 'Mariano Javier PIÑERO DA SILVA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(83, 'CB', 'Carp', 'Francisco Nicolás MAMANÍ OVIEDO', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(84, 'CB', 'Cond Mot', 'Araceli Vanessa MORAIS', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(85, 'CB', 'Mec Eq(s) Fij', 'Fernando Leonel QUINTANA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(86, 'CB', 'Mec Eq(s) Fij', 'Angel Hugo Jose VILCA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(87, 'CB', 'Mec Info', 'Patricia Noemí ZACARÍAS', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(88, 'CB', 'Mec Eq(s) Fij', 'Facundo Ariel VILLARREAL', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(89, 'CB', 'Mec Eq(s) Fij', 'Matias Ezequiel ALBORNOZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(90, 'CB', 'Mec Eq(s) Fij', 'Cristian Emanuel VILLAVERDE', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(91, 'CB', 'Mec Eq(s) Fij', 'Valentin Emanuel OLIVERA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(92, 'CB', 'Mec Info', 'Priscila Ayelen ROJAS LESCANO', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(93, 'CB', 'Mec Info', 'Evelin Edith MARTINEZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(94, 'CB', 'Mec Info', 'Jonatan Sergio FIGUEROA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(95, 'CB', 'Mec Info', 'Andrea Rita MARTINEZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(96, 'CB', 'Mec Eq(s) Fij', 'Evelin Aylen MORE', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(97, 'CB', 'Mec Eq(s) Fij', 'Candelaria Milagros DIAZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(98, 'CB', 'Mec Eq(s) Fij', 'Carlos Samuel ARRUA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(99, 'CB', 'Com', 'Sabrina Belén LEZCANO', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(100, 'CB', 'Mec Eq(s) Fij', 'Ramon Ismael MEDINA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(101, 'CB', 'Mec Eq(s) Fij', 'Juan Gabriel LOPEZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(102, 'CB', 'Mec Eq(s) Fij', 'Damián Hugo DUARTE', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(103, 'CB', 'Mec Eq(s) Fij', 'Franco Ismael LOPEZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(104, 'CB', 'Mec Eq(s) Fij', 'Juan Gabriel ANDRADE', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(105, 'CB', 'Mec Eq(s) Fij', 'Nicolás Raúl MERCADO', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(106, 'CB', 'Mec Eq(s) Fij', 'Alejandro Ramón Enrique SANCHEZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(107, 'CB', 'Mec Eq(s) Fij', 'Mauro Emmanuel MARTIN', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(108, 'CB', 'Mec Eq(s) Fij', 'Aida Zulema VARGAS', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(109, 'CB', 'Mec Eq(s) Fij', 'Ramon Alberto FIGUEREDO', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(110, 'VP', 'Com', 'Guadalupe FARIAS VECCHIO', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(111, 'VP', 'Com', 'Mauro Federico Ilaín LOPEZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(112, 'VP', 'Com', 'Ariel Evaristo FARRAPEIRA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(113, 'VP', 'Com', 'Aldana Yanet GEREZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(114, 'VP', 'Com', 'Virginia Itatí FUNES', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(115, 'VP', 'Com', 'Daniela Beatríz VALENZUELA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(116, 'VP', 'Com', 'Yohana Beatríz GAUTO', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(117, 'VP', 'Com', 'Ezequiel Ignacio GOMEZ BARVISAN', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(118, 'VP', 'Cond Mot', 'Lautaro Federico BANDA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(119, 'VP', 'Com', 'Ailen Jazmin RIVAS', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(120, 'VP', 'Com', 'Valentín Alejandro BAEZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(121, 'VP', 'Esp', 'Iván DUBOWIK', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(122, 'VS', 'Com', 'Juan Carlos VILLCA CALISAYA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(123, 'VS', 'Com', 'Priscila Luján FALCON', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(124, 'VS', 'Com', 'Melanie Janil FRETE', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(125, 'VS', 'Com', 'Cristian Javier GONZALEZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(126, 'VS', 'Com', 'Milagros Teresa  RODRIGUEZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(127, 'VS', 'Com', 'Erika Selena Marisol ALTAMIRANO', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(128, 'VS', 'Com', 'Blanca Cecilia CONDORI', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(129, 'VS', 'Com', 'David NAVARRO', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(130, 'VS', 'Esp', 'Oriana Ayelen VAZQUEZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(131, 'VS \"EC\"', 'Com', 'Azul Nicole ARROYO', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(132, 'VS \"EC\"', 'Com', 'Rocío Agostina ROMERO', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(133, 'VS \"EC\"', 'Ars', 'Joaquin Nicolas GRAJALES', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(134, 'VS \"EC\"', 'Com', 'Penélope Ailin JARA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(135, 'VS \"EC\"', 'Com', 'Mateo FUENZALIDA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(136, 'VS \"EC\"', 'Com', 'Loana Tais GARCIA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(137, 'VS \"EC\"', 'Com', 'Angel Sebastian MARIÑO', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(138, 'VS \"EC\"', 'Com', 'Angel Uriel MOYANO', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(139, 'VS \"EC\"', 'Com', 'Marcos Miguel MONZON', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(140, 'VS \"EC\"', 'Com', 'Hernán Dario ALBASETTI', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(141, 'VS \"EC\"', 'Com', 'Facundo Arian  VELIZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(142, 'VS \"EC\"', 'Com', 'Adrián Nicolas FERNANDEZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(143, 'VS \"EC\"', 'Com', 'Denise Naomi BEHR', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(144, 'VS \"EC\"', 'Com', 'Mariano Lautaro Fabián VAZQUEZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(145, 'VS \"EC\"', 'Com', 'Kevin Ezequiel  LEDESMA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(146, 'VS \"EC\"', 'Com', 'Fernanda Belén DIAZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(147, 'VS \"EC\"', 'Com', 'Sergio Leonel  LEZCANO', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(148, 'VS \"EC\"', 'Com', 'Matías Ezequiel RAMIREZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(149, 'VS \"EC\"', 'Com', 'Candela Ailen  BERRETO', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(150, 'VS \"EC\"', 'Com', 'Lourdes Juliana BERDAN', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(151, 'VS \"EC\"', 'Com', 'Damián Emanuel GONZALES', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(152, 'VS \"EC\"', 'Com', 'Elías Benjamín QUINQUINTE', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(153, 'VS \"EC\"', 'Com', 'Aylen Magali MARTINEZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(154, 'VS \"EC\"', 'Com', 'Julián Gastón VELAZQUES', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(155, 'VS \"EC\"', 'Com', 'Lionel MANZANO', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(156, 'VS \"EC\"', 'Com', 'Gastón CABALLERO', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(157, 'VS \"EC\"', 'Com', 'Germán GALVAN', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(158, 'VS \"EC\"', 'Com', 'Belén CAMOS', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(159, 'VS \"EC\"', 'Com', 'Javier MARTINEZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(160, 'A/C', 'Tec V GR 05', 'Américo TARIFA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(161, 'A/C', 'Tec V', 'Iván Leonel PASARELLI', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(162, 'A/C', 'A/C', 'María Alejadra TERRAZA', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(163, 'A/C', 'Mant Ser', 'Mariana Fabiana RODRIGUEZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(164, 'A/C', 'Adm IV', 'Ariel Alejandro VALLEJO', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(165, 'A/C', 'Tec III GR 08', 'Paulo Daniel SCAIANO', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(166, 'A/C', 'Adm VI GR', 'María Marcela PAEZ', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas'),
(167, 'A/C', 'Adm IV', 'Ramona HUGHETTI', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 15:32:21', 'nesrojas');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `respuestas`
--

CREATE TABLE `respuestas` (
  `id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `estado` enum('si','no','na') DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `evidencia` varchar(255) DEFAULT NULL,
  `creado_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles_locales`
--

CREATE TABLE `roles_locales` (
  `id` int(11) NOT NULL,
  `rol_combate` varchar(255) DEFAULT NULL,
  `grado` varchar(100) DEFAULT NULL,
  `nombre_apellido` varchar(255) DEFAULT NULL,
  `dni` varchar(20) DEFAULT NULL,
  `rol_administrativo` varchar(255) DEFAULT NULL,
  `rol` enum('usuario','administrador') NOT NULL DEFAULT 'usuario',
  `rol_app` enum('admin','usuario') NOT NULL DEFAULT 'usuario',
  `areas_acceso` longtext DEFAULT NULL COMMENT 'JSON con ids o codigos de areas permitidas'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `roles_locales`
--

INSERT INTO `roles_locales` (`id`, `rol_combate`, `grado`, `nombre_apellido`, `dni`, `rol_administrativo`, `rol`, `rol_app`, `areas_acceso`) VALUES
(1, 'Jefe', 'TC OEM', 'Mariano Oscar GOMEZ', '26440468', NULL, 'usuario', 'admin', '[\"GRAL\"]'),
(2, 'Segundo Jefe', 'MY OIM', 'Maria Eugenia ROTELA', '26809400', 'Comunicacion Institu', 'usuario', 'usuario', '[\"GRAL\"]'),
(3, 'Encargado de Elem', 'SM', 'Adrian Ariel PEREYRA', '26263075', 'Oficial de Bienestar', 'usuario', 'usuario', '[\"GRAL\"]'),
(4, 'Oficial de Personal', 'SA', 'Sergio Omar CHAVARRIA', '28193244', 'Oficial de Bienestar', 'usuario', 'usuario', '[\"S1\"]'),
(5, 'Oficial de Operacio', 'MY OEM', 'Gerardo Hugo ALMADA', '29725290', 'Oficial AFM', 'usuario', 'admin', '[\"GRAL\"]'),
(6, 'Oficial de Material', 'CT', 'Nicolás Hugo REINA MARTINEZ', '32877640', NULL, 'usuario', 'usuario', '[\"S4\"]'),
(7, 'Oficial de Presupu', 'TP', 'Natalia ESPINOSA', '38165393', NULL, 'usuario', 'usuario', '[\"S4\",\"S5\"]'),
(8, 'Jefe', 'SP', 'Romina Isabel BENITEZ', '28489259', NULL, 'usuario', 'usuario', '[]'),
(9, 'Jefe de Grupo', 'SI', 'Franco Mario ESPECHE', '33047730', 'Of Vcerm MSCI', 'usuario', 'usuario', '[\"S2\"]'),
(10, 'Jefe de Grupo Oper', 'SI', 'Cristian Eduardo NEIRA', '29065461', NULL, 'usuario', 'usuario', '[\"GRAL\"]'),
(11, 'Jefe de Pelotón Op', 'SG', 'Walter Daniel BENVENUTTO', '31241049', 'J Gpo Ab Ef(s) CI II y IV', 'usuario', 'usuario', '[\"S3\"]'),
(12, 'J Grupo Materiales', 'SA', 'Carlos Ruben DELGADO', '29288753', NULL, 'usuario', 'usuario', '[\"S4\"]'),
(13, 'Jefe', 'ST EC', 'Isabel PEREZ', '39635128', 'Oficial Vedor de Const', 'usuario', 'usuario', '[\"S4\",\"S5\"]'),
(14, 'Jefe', 'SI', 'Diego Alberto CHAMBI', '31733237', NULL, 'usuario', 'usuario', '[]'),
(15, 'Jefe de Compañía', 'TP', 'Natalia Carolina GONZALEZ BAIGORRIA', '34953503', 'Oficial de Claves', 'usuario', 'usuario', '[]'),
(16, 'Jefe', 'SP', 'Maria de los Angeles QUIÑONEZ', '26586146', 'Oficial Seguridad Cont Acc', 'usuario', 'usuario', '[]'),
(17, 'Jefe de Compañía', 'MY OIM', 'Roberto Carlos CESPEDES', '31920858', 'Oficial Informático', 'usuario', 'usuario', '[\"S3\"]'),
(18, 'Jefe de Sección', 'TP', 'Juan Laureano MENDEZ', '37288615', 'Oficial Veed RGM - Of GDE', 'usuario', 'admin', '[\"S3\"]'),
(19, 'Jefe de Sección', 'ST', 'Gabriel Elisandro VEGA', '41182809', 'Oficial de Ciberdefens', 'usuario', 'usuario', '[]'),
(20, 'Jefe de Sección', 'ST', 'Luis Daniel CRUZ OCHOA', '32291941', 'Oficial Histórico', 'usuario', 'usuario', '[]'),
(21, 'Desarrollador', 'ST', 'Ruben Alberto IBARROLA', '42604303', 'Desarrollador', 'usuario', 'admin', '[\"GRAL\"]'),
(22, 'Desarrollador', 'ST', 'Nestor Gabriel ROJAS', '41742406', 'Desarrollador', 'usuario', 'admin', '[\"GRAL\"]'),
(23, 'Jefe de Compañía', 'CT', 'Pamela del Rosario MARTINEZ', '33271837', 'Auxiliar oficial de Educación Física - Oficial de Tiro - Oficial Veedor de control de gestión', 'usuario', 'usuario', '[]'),
(24, 'Auxiliar Grupo Operaciones', 'SG', 'Armando Sebastian BALDI', '34363117', NULL, 'usuario', 'usuario', '[\"S3\"]'),
(33, 'Materiales', 'SG', 'Emanuel Ruben Dario VARGAS', '33764894', NULL, 'usuario', 'usuario', '[\"S4\"]'),
(34, NULL, 'CT', 'Zeballos', '30406618', NULL, 'usuario', 'usuario', '[\"S4\",\"S5\"]');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `s3_alocuciones`
--

CREATE TABLE `s3_alocuciones` (
  `id` int(11) NOT NULL,
  `nro` int(11) DEFAULT NULL,
  `fecha` varchar(100) DEFAULT NULL,
  `acontecimiento` text DEFAULT NULL,
  `responsable` varchar(255) DEFAULT NULL,
  `cumplio` enum('','si','no','en_ejecucion') DEFAULT '',
  `documento` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `s3_alocuciones`
--

INSERT INTO `s3_alocuciones` (`id`, `nro`, `fecha`, `acontecimiento`, `responsable`, `cumplio`, `documento`, `updated_at`, `updated_by`) VALUES
(1, 1, '23 Ene / 30 Ene', 'Copamiento a los Cuarteles de La Tablada', 'TP Com ESPINOSA Natalia Belén', 'si', '', NULL, NULL),
(2, 2, '30 Ene', 'Entregarse sin limitaciones al régimen del servicio', 'CI Mec Info MONZON MARTINEZ Hernán', 'no', '', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `s3_clases`
--

CREATE TABLE `s3_clases` (
  `id` int(11) NOT NULL,
  `semana` varchar(10) DEFAULT NULL,
  `fecha` varchar(50) DEFAULT NULL,
  `clase_trabajo` varchar(100) DEFAULT NULL,
  `tema` text DEFAULT NULL,
  `responsable` varchar(255) DEFAULT NULL,
  `participantes` varchar(255) DEFAULT NULL,
  `lugar` varchar(255) DEFAULT NULL,
  `cumplio` enum('','si','no','en_ejecucion') DEFAULT '',
  `documento` varchar(255) DEFAULT NULL,
  `participantes_pdf` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `s3_clases`
--

INSERT INTO `s3_clases` (`id`, `semana`, `fecha`, `clase_trabajo`, `tema`, `responsable`, `participantes`, `lugar`, `cumplio`, `documento`, `participantes_pdf`, `updated_at`, `updated_by`) VALUES
(1, '10', NULL, NULL, 'Sistema de Justicia Militar: Ley 26.394, Código de Disciplina FFAA, Reglamentos', NULL, 'Cuadros', NULL, NULL, 'PE-00-03.pdf', NULL, '2025-11-27 15:51:56', 'NESROJAS'),
(2, '11', NULL, NULL, NULL, NULL, 'Cuadros', NULL, NULL, NULL, NULL, '2025-11-27 15:51:56', 'NESROJAS');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `s3_cursos_regulares`
--

CREATE TABLE `s3_cursos_regulares` (
  `id` int(11) NOT NULL,
  `sigla` varchar(20) DEFAULT NULL,
  `denominacion` varchar(255) DEFAULT NULL,
  `participantes` text DEFAULT NULL,
  `desde` varchar(50) DEFAULT NULL,
  `hasta` varchar(50) DEFAULT NULL,
  `cumplio` enum('','si','no','en_ejecucion') DEFAULT 'en_ejecucion',
  `documento` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `s3_cursos_regulares`
--

INSERT INTO `s3_cursos_regulares` (`id`, `sigla`, `denominacion`, `participantes`, `desde`, `hasta`, `cumplio`, `documento`, `updated_at`, `updated_by`) VALUES
(1, 'RC-07', 'Curso Básico de Comando y Plana Mayor', 'TP de las Armas (Prom 146)', '10 Mar', '13 Oct', 'en_ejecucion', '', NULL, NULL),
(2, 'RC-10', 'Curso de Asesor de Estado Mayor a distancia (CAEMD)', 'CT(s) Armas que aprobaron CBUT', '11 Feb', '12 Dic', 'en_ejecucion', '', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `s3_tiro_ami`
--

CREATE TABLE `s3_tiro_ami` (
  `id` int(11) NOT NULL,
  `grado` varchar(50) DEFAULT NULL,
  `nombre` varchar(255) DEFAULT NULL,
  `ejercicio` varchar(100) DEFAULT NULL,
  `resultado` enum('APROBO','NO_APROBO') DEFAULT 'NO_APROBO',
  `observaciones` text DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `documento` varchar(255) DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `actualizado_por` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `s3_tiro_b9`
--

CREATE TABLE `s3_tiro_b9` (
  `id` int(11) NOT NULL,
  `grado` varchar(50) DEFAULT NULL,
  `nombre` varchar(255) DEFAULT NULL,
  `ejercicio` varchar(100) DEFAULT NULL,
  `resultado` enum('APROBO','NO_APROBO') DEFAULT 'NO_APROBO',
  `observaciones` text DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `documento` varchar(255) DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `actualizado_por` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `s3_tiro_ejercicios`
--

CREATE TABLE `s3_tiro_ejercicios` (
  `id` int(11) NOT NULL,
  `codigo` varchar(50) DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `participantes` varchar(100) DEFAULT NULL,
  `resultado` enum('APROBO','NO_APROBO') DEFAULT 'NO_APROBO',
  `fecha` date DEFAULT NULL,
  `documento` varchar(255) DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `actualizado_por` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `s3_tiro_municion`
--

CREATE TABLE `s3_tiro_municion` (
  `id` int(11) NOT NULL,
  `calibre` varchar(50) DEFAULT NULL,
  `cantidad` int(11) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `uso` varchar(255) DEFAULT NULL,
  `documento` varchar(255) DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `actualizado_por` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `s3_trabajos_gabinete`
--

CREATE TABLE `s3_trabajos_gabinete` (
  `id` int(11) NOT NULL,
  `semana` varchar(10) DEFAULT NULL,
  `tema` text DEFAULT NULL,
  `responsable_grado` varchar(50) DEFAULT NULL,
  `responsable_nombre` varchar(255) DEFAULT NULL,
  `cumplio` enum('','si','no','en_ejecucion') DEFAULT '',
  `documento` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `s3_trabajos_gabinete`
--

INSERT INTO `s3_trabajos_gabinete` (`id`, `semana`, `tema`, `responsable_grado`, `responsable_nombre`, `cumplio`, `documento`, `updated_at`, `updated_by`) VALUES
(1, '1', 'Sistema de gestión de tráfico de red en REDISE', 'CT Com', 'CETTOUR Martín Miguel', 'si', 'informe_redise.pdf', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `xlsx_prefs`
--

CREATE TABLE `xlsx_prefs` (
  `file_rel` varchar(512) NOT NULL,
  `mode_num_is` enum('title','item') NOT NULL DEFAULT 'title',
  `updated_at` timestamp NULL DEFAULT NULL,
  `table_fmt` enum('classic','form') NOT NULL DEFAULT 'classic'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `xlsx_prefs`
--

INSERT INTO `xlsx_prefs` (`file_rel`, `mode_num_is`, `updated_at`, `table_fmt`) VALUES
('storage/listas_control/S3/Área Formal/Lista de Control Nro 1 - 0001 - Actividades previas a la Inspección..xlsx', 'item', '2025-11-18 19:48:48', 'classic'),
('storage/ultima_inspeccion/Operaciones (S-3)/Ámbito Funcional.xlsx', 'item', '2025-11-20 12:36:06', 'form');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `areas`
--
ALTER TABLE `areas`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `checklist`
--
ALTER TABLE `checklist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_file_row` (`file_rel`,`row_idx`);

--
-- Indices de la tabla `checklist_form`
--
ALTER TABLE `checklist_form`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_file_row_field` (`file_rel`,`row_idx`,`field_key`);

--
-- Indices de la tabla `documentos`
--
ALTER TABLE `documentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `area_id` (`area_id`);

--
-- Indices de la tabla `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `documento_id` (`documento_id`);

--
-- Indices de la tabla `personal_unidad`
--
ALTER TABLE `personal_unidad`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_personal_unidad_dni` (`dni`);

--
-- Indices de la tabla `respuestas`
--
ALTER TABLE `respuestas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indices de la tabla `roles_locales`
--
ALTER TABLE `roles_locales`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `s3_alocuciones`
--
ALTER TABLE `s3_alocuciones`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `s3_clases`
--
ALTER TABLE `s3_clases`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `s3_cursos_regulares`
--
ALTER TABLE `s3_cursos_regulares`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `s3_tiro_ami`
--
ALTER TABLE `s3_tiro_ami`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `s3_tiro_b9`
--
ALTER TABLE `s3_tiro_b9`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `s3_tiro_ejercicios`
--
ALTER TABLE `s3_tiro_ejercicios`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `s3_tiro_municion`
--
ALTER TABLE `s3_tiro_municion`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `s3_trabajos_gabinete`
--
ALTER TABLE `s3_trabajos_gabinete`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `xlsx_prefs`
--
ALTER TABLE `xlsx_prefs`
  ADD PRIMARY KEY (`file_rel`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `areas`
--
ALTER TABLE `areas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `checklist`
--
ALTER TABLE `checklist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=171;

--
-- AUTO_INCREMENT de la tabla `checklist_form`
--
ALTER TABLE `checklist_form`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `documentos`
--
ALTER TABLE `documentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT de la tabla `items`
--
ALTER TABLE `items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `personal_unidad`
--
ALTER TABLE `personal_unidad`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=168;

--
-- AUTO_INCREMENT de la tabla `respuestas`
--
ALTER TABLE `respuestas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `roles_locales`
--
ALTER TABLE `roles_locales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT de la tabla `s3_alocuciones`
--
ALTER TABLE `s3_alocuciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `s3_clases`
--
ALTER TABLE `s3_clases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `s3_cursos_regulares`
--
ALTER TABLE `s3_cursos_regulares`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `s3_tiro_ami`
--
ALTER TABLE `s3_tiro_ami`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `s3_tiro_b9`
--
ALTER TABLE `s3_tiro_b9`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `s3_tiro_ejercicios`
--
ALTER TABLE `s3_tiro_ejercicios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `s3_tiro_municion`
--
ALTER TABLE `s3_tiro_municion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `s3_trabajos_gabinete`
--
ALTER TABLE `s3_trabajos_gabinete`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `documentos`
--
ALTER TABLE `documentos`
  ADD CONSTRAINT `documentos_ibfk_1` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`);

--
-- Filtros para la tabla `items`
--
ALTER TABLE `items`
  ADD CONSTRAINT `items_ibfk_1` FOREIGN KEY (`documento_id`) REFERENCES `documentos` (`id`);

--
-- Filtros para la tabla `respuestas`
--
ALTER TABLE `respuestas`
  ADD CONSTRAINT `respuestas_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
