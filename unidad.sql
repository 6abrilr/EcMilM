-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 08-03-2026 a las 22:04:16
-- Versión del servidor: 10.11.14-MariaDB-0ubuntu0.24.04.1
-- Versión de PHP: 8.3.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `unidad`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `calendario_diario`
--

CREATE TABLE `calendario_diario` (
  `id` int(11) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `area_code` varchar(20) NOT NULL,
  `fecha` date NOT NULL,
  `detalle` text NOT NULL,
  `creado_por` varchar(80) DEFAULT NULL,
  `creado_por_id` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `calendario_diario`
--

INSERT INTO `calendario_diario` (`id`, `unidad_id`, `area_code`, `fecha`, `detalle`, `creado_por`, `creado_por_id`, `updated_at`, `created_at`) VALUES
(1, 1, 'INFORMATICA', '2026-02-19', 'Formateo PC Operaciones\nDesarrollo página Ec Mil M\nVideoconferencia Deop', 'Rojas Gabriel', 1, '2026-02-19 22:54:48', '2026-02-19 22:54:48'),
(2, 1, 'INFORMATICA', '2026-02-18', 'Cableado SAF\nEmpece a formatear PC - Operaciones', 'Rojas Gabriel', 1, '2026-02-19 22:56:57', '2026-02-19 22:56:57');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `calendario_tareas`
--

CREATE TABLE `calendario_tareas` (
  `id` int(11) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `area_code` varchar(20) NOT NULL,
  `titulo` varchar(120) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `estado` enum('POR_HACER','EN_PROCESO','REALIZADA') NOT NULL DEFAULT 'POR_HACER',
  `prioridad` enum('BAJA','MEDIA','ALTA') NOT NULL DEFAULT 'MEDIA',
  `inicio` datetime DEFAULT NULL,
  `fin` datetime DEFAULT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `asignado_a` varchar(80) DEFAULT NULL,
  `creado_por` varchar(80) DEFAULT NULL,
  `creado_por_id` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `calendario_tareas`
--

INSERT INTO `calendario_tareas` (`id`, `unidad_id`, `area_code`, `titulo`, `descripcion`, `estado`, `prioridad`, `inicio`, `fin`, `fecha_vencimiento`, `asignado_a`, `creado_por`, `creado_por_id`, `updated_at`, `created_at`) VALUES
(1, 1, 'INFORMATICA', 'Cableado oficina subdirector', 'Se realizo el cableado en la oficina del subdirector, agregando una roseta', 'REALIZADA', 'ALTA', '2026-02-06 09:00:00', '2026-02-06 11:30:00', NULL, 'ST SCD ROJAS', '41742406', NULL, '2026-02-07 19:17:11', '2026-02-07 19:17:11'),
(2, 1, 'INFORMATICA', 'Cableado SAF', 'Se realizo cableado nuevo en la oficina\nSe agregaron pacheras\nSe identificaron cables utp\nSe movio de lugar la impresora\nSe coloco un switch\nSe acomodo cableado y fichas\nTerminado el 13FEB26', 'REALIZADA', 'MEDIA', '2026-02-04 08:00:00', '2026-02-13 13:00:00', '2026-02-10', 'ST SCD ROJAS', '41742406', NULL, '2026-02-19 22:24:18', '2026-02-07 19:20:28');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `checklist`
--

CREATE TABLE `checklist` (
  `id` int(11) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `file_rel` varchar(512) NOT NULL,
  `row_idx` int(11) NOT NULL,
  `estado` enum('si','no') DEFAULT NULL,
  `observacion` text DEFAULT NULL,
  `evidencia_path` text DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `caracter` varchar(100) DEFAULT NULL,
  `updated_by_id` int(11) DEFAULT NULL,
  `updated_by` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `checklist_form`
--

CREATE TABLE `checklist_form` (
  `id` int(11) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `file_rel` varchar(512) NOT NULL,
  `titulo` varchar(255) DEFAULT NULL,
  `nota` text DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `destino`
--

CREATE TABLE `destino` (
  `id` int(11) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `codigo` varchar(30) DEFAULT NULL,
  `nombre` varchar(255) NOT NULL,
  `ruta` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `destino`
--

INSERT INTO `destino` (`id`, `unidad_id`, `codigo`, `nombre`, `ruta`, `activo`) VALUES
(1, 1, 'S1', 'Personal', 'personal/personal.php', 1),
(2, 1, 'S2', 'Inteligencia', 'inteligencia/inteligencia.php', 1),
(3, 1, 'S3', 'Operaciones', 'operaciones/operaciones.php', 1),
(4, 1, 'S4', 'Materiales', 'materiales/materiales.php', 1),
(5, 1, 'S5', 'Presupuesto', 'presupuesto/presupuesto.php', 1),
(6, 1, 'SAF', 'SAF', 'SAF/SAF.php', 1),
(7, 1, 'INF', 'Informática', 'informatica/informatica.php', 1),
(8, 1, 'DIR', 'Dirección', 'direccion/direccion.php', 1),
(9, 1, 'SAN', 'Sanidad', 'sanidad/anidad.php', 1),
(23, 1, 'IGE', 'Inspeccion General Ejercito', 'ige/ige.php', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `documentos`
--

CREATE TABLE `documentos` (
  `id` int(11) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `destino_id` int(11) DEFAULT NULL,
  `categoria` varchar(255) DEFAULT NULL,
  `titulo` varchar(255) DEFAULT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `path` varchar(512) DEFAULT NULL,
  `mime` varchar(120) DEFAULT NULL,
  `bytes` bigint(20) DEFAULT NULL,
  `sha256` char(64) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by_id` int(11) DEFAULT NULL,
  `created_by_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `educacion_tropa_actividades`
--

CREATE TABLE `educacion_tropa_actividades` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `unidad_id` int(11) NOT NULL DEFAULT 0,
  `ciclo` varchar(10) NOT NULL,
  `sem` varchar(20) NOT NULL DEFAULT '',
  `fecha` date DEFAULT NULL,
  `tema` varchar(200) NOT NULL,
  `responsable` varchar(200) NOT NULL DEFAULT '',
  `participantes` varchar(200) NOT NULL DEFAULT '',
  `lugar` varchar(200) NOT NULL DEFAULT '',
  `cumplio` tinyint(1) NOT NULL DEFAULT 0,
  `doc` varchar(255) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  `created_by` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `educacion_tropa_personal_ciclos`
--

CREATE TABLE `educacion_tropa_personal_ciclos` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `unidad_id` int(11) NOT NULL DEFAULT 0,
  `personal_unidad_id` bigint(20) NOT NULL,
  `ciclo` varchar(10) NOT NULL,
  `cumplido` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_by` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `educacion_tropa_personal_ciclos`
--

INSERT INTO `educacion_tropa_personal_ciclos` (`id`, `unidad_id`, `personal_unidad_id`, `ciclo`, `cumplido`, `updated_at`, `updated_by`) VALUES
(1, 0, 1, '1', 0, '2026-02-01 22:44:40', NULL),
(3, 0, 1, '2', 0, '2026-02-01 22:44:40', NULL),
(4, 0, 1, '3', 0, '2026-02-01 22:44:40', NULL),
(5, 0, 1, 'nia', 0, '2026-02-01 22:44:40', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inf_cat_estado_dispositivo`
--

CREATE TABLE `inf_cat_estado_dispositivo` (
  `id` int(11) NOT NULL,
  `codigo` varchar(40) NOT NULL,
  `nombre` varchar(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `inf_cat_estado_dispositivo`
--

INSERT INTO `inf_cat_estado_dispositivo` (`id`, `codigo`, `nombre`) VALUES
(1, 'activo', 'Activo'),
(2, 'deposito', 'Depósito'),
(3, 'reparacion', 'En reparación'),
(4, 'baja', 'Baja');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inf_dispositivo_detalle`
--

CREATE TABLE `inf_dispositivo_detalle` (
  `id` int(11) NOT NULL,
  `red_dispositivo_id` int(11) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `estado_id` int(11) DEFAULT NULL,
  `etiqueta` varchar(120) DEFAULT NULL,
  `hostname` varchar(120) DEFAULT NULL,
  `fabricante` varchar(120) DEFAULT NULL,
  `modelo` varchar(120) DEFAULT NULL,
  `serial` varchar(120) DEFAULT NULL,
  `inventario` varchar(120) DEFAULT NULL,
  `so` varchar(120) DEFAULT NULL,
  `firmware` varchar(120) DEFAULT NULL,
  `edificio_id` int(11) DEFAULT NULL,
  `piso_id` int(11) DEFAULT NULL,
  `oficina_id` int(11) DEFAULT NULL,
  `asignado_personal_id` int(11) DEFAULT NULL,
  `nota` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inf_redes`
--

CREATE TABLE `inf_redes` (
  `id` int(11) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `vlan` varchar(60) DEFAULT NULL,
  `cidr` varchar(60) DEFAULT NULL,
  `gateway` varchar(45) DEFAULT NULL,
  `dns` varchar(180) DEFAULT NULL,
  `nota` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `items`
--

CREATE TABLE `items` (
  `id` int(11) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `file_rel` varchar(512) NOT NULL,
  `row_idx` int(11) NOT NULL,
  `texto` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `it_activos`
--

CREATE TABLE `it_activos` (
  `id` int(11) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `tipo` enum('pc','camara','herramienta','mueble','insumo','otro') NOT NULL DEFAULT 'otro',
  `etiqueta` varchar(120) DEFAULT NULL,
  `descripcion` varchar(255) NOT NULL,
  `marca` varchar(190) DEFAULT NULL,
  `modelo` varchar(190) DEFAULT NULL,
  `nro_serie` varchar(190) DEFAULT NULL,
  `estado` enum('operativo','mantenimiento','baja','roto','prestamo') NOT NULL DEFAULT 'operativo',
  `condicion` enum('activo','deposito') NOT NULL DEFAULT 'activo',
  `edificio_id` int(11) DEFAULT NULL,
  `area_id` int(11) DEFAULT NULL,
  `ubicacion_detalle` varchar(190) DEFAULT NULL,
  `asignado_personal_id` int(11) DEFAULT NULL,
  `fuente_fondos_id` int(11) DEFAULT NULL,
  `fecha_alta` date DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `dispositivo_tipo` enum('PC','NOTEBOOK','SERVIDOR','IMPRESORA','MODEM','ROUTER','SWITCH','AP','OTRO') DEFAULT NULL,
  `equipo_nombre` varchar(120) DEFAULT NULL,
  `usuario_asignado` varchar(160) DEFAULT NULL,
  `sistema_operativo` varchar(120) DEFAULT NULL,
  `antivirus` varchar(120) DEFAULT NULL,
  `office_version` varchar(120) DEFAULT NULL,
  `serial_windows` varchar(120) DEFAULT NULL,
  `cpu` varchar(120) DEFAULT NULL,
  `ram_gb` decimal(6,2) DEFAULT NULL,
  `disco_tipo` enum('HDD','SSD','NVME','EMMC','OTRO') DEFAULT NULL,
  `disco_gb` int(11) DEFAULT NULL,
  `monitor` varchar(120) DEFAULT NULL,
  `perifericos` text DEFAULT NULL,
  `mac` varchar(32) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `ip_gateway` varchar(45) DEFAULT NULL,
  `dns1` varchar(45) DEFAULT NULL,
  `dns2` varchar(45) DEFAULT NULL,
  `switch_puerto` varchar(60) DEFAULT NULL,
  `patchera_puerto` varchar(60) DEFAULT NULL,
  `sector_red` varchar(120) DEFAULT NULL,
  `vlan` varchar(40) DEFAULT NULL,
  `ip_fija` tinyint(1) NOT NULL DEFAULT 0,
  `categoria` varchar(30) NOT NULL DEFAULT 'informatica'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `it_edificios`
--

CREATE TABLE `it_edificios` (
  `id` int(11) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `numero` int(11) DEFAULT NULL,
  `nombre` varchar(190) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `ip_rango_desde` varchar(45) DEFAULT NULL,
  `ip_rango_hasta` varchar(45) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `it_edificios`
--

INSERT INTO `it_edificios` (`id`, `unidad_id`, `numero`, `nombre`, `descripcion`, `ip_rango_desde`, `ip_rango_hasta`, `creado_en`) VALUES
(1, 1, 20, 'Plana Prueba', 'Edificio N°18', '192.168.18.1', '192.168.18.254', '2026-02-08 16:50:08');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `it_fuentes_fondos`
--

CREATE TABLE `it_fuentes_fondos` (
  `id` int(11) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `nombre` varchar(190) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `it_internet`
--

CREATE TABLE `it_internet` (
  `id` int(11) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `edificio_id` int(11) NOT NULL,
  `proveedor` varchar(120) NOT NULL,
  `servicio` varchar(120) DEFAULT NULL,
  `plan` varchar(120) DEFAULT NULL,
  `velocidad` varchar(80) DEFAULT NULL,
  `costo` decimal(12,2) DEFAULT NULL,
  `ip_publica` varchar(60) DEFAULT NULL,
  `nota` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `it_mantenimientos`
--

CREATE TABLE `it_mantenimientos` (
  `id` int(11) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `edificio_id` int(11) NOT NULL,
  `activo_id` int(11) DEFAULT NULL,
  `fecha` date NOT NULL,
  `tipo` varchar(80) NOT NULL DEFAULT 'preventivo',
  `detalle` text NOT NULL,
  `realizado_por` varchar(120) DEFAULT NULL,
  `costo` decimal(12,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `personal_documentos`
--

CREATE TABLE `personal_documentos` (
  `id` int(11) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `personal_id` int(11) NOT NULL,
  `sanidad_id` int(11) DEFAULT NULL,
  `evento_id` int(11) DEFAULT NULL,
  `tipo` varchar(120) DEFAULT NULL,
  `titulo` varchar(255) DEFAULT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `mime` varchar(120) DEFAULT NULL,
  `bytes` bigint(20) DEFAULT NULL,
  `sha256` char(64) DEFAULT NULL,
  `path` varchar(512) DEFAULT NULL,
  `nota` text DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `personal_eventos`
--

CREATE TABLE `personal_eventos` (
  `id` int(11) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `personal_id` int(11) NOT NULL,
  `tipo` varchar(60) NOT NULL,
  `desde` date DEFAULT NULL,
  `hasta` date DEFAULT NULL,
  `estado` varchar(30) DEFAULT NULL,
  `titulo` varchar(255) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `data_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data_json`)),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `created_by_id` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `personal_unidad`
--

CREATE TABLE `personal_unidad` (
  `id` int(11) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `dni` varchar(20) NOT NULL,
  `cuil` varchar(20) DEFAULT NULL,
  `fecha_nac` date DEFAULT NULL,
  `peso` decimal(6,2) DEFAULT NULL,
  `altura` decimal(5,2) DEFAULT NULL,
  `sexo` varchar(10) DEFAULT NULL,
  `domicilio` varchar(255) DEFAULT NULL,
  `estado_civil` varchar(60) DEFAULT NULL,
  `hijos` int(11) DEFAULT NULL,
  `nou` varchar(60) DEFAULT NULL,
  `nro_cta` varchar(60) DEFAULT NULL,
  `cbu` varchar(60) DEFAULT NULL,
  `alias_banco` varchar(100) DEFAULT NULL,
  `fecha_ultimo_anexo27` date DEFAULT NULL,
  `tiene_parte_enfermo` tinyint(1) NOT NULL DEFAULT 0,
  `parte_enfermo_desde` date DEFAULT NULL,
  `parte_enfermo_hasta` date DEFAULT NULL,
  `cantidad_parte_enfermo` int(11) DEFAULT NULL,
  `destino_interno` varchar(255) DEFAULT NULL,
  `rol` varchar(255) DEFAULT NULL,
  `anios_en_destino` decimal(5,2) DEFAULT NULL,
  `fracc` varchar(60) DEFAULT NULL,
  `jerarquia` enum('OFICIAL','SUBOFICIAL','SOLDADO','AGENTE_CIVIL') DEFAULT NULL,
  `grado` varchar(60) DEFAULT NULL,
  `arma` varchar(60) DEFAULT NULL,
  `apellido_nombre` varchar(255) DEFAULT NULL,
  `apellido` varchar(120) DEFAULT NULL,
  `nombre` varchar(120) DEFAULT NULL,
  `destino_id` int(11) DEFAULT NULL,
  `funcion` varchar(255) DEFAULT NULL,
  `telefono` varchar(60) DEFAULT NULL,
  `correo` varchar(120) DEFAULT NULL,
  `fecha_alta` date DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `role_id` int(11) NOT NULL DEFAULT 3,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by_id` int(11) DEFAULT NULL,
  `extra_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`extra_json`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `personal_unidad`
--

INSERT INTO `personal_unidad` (`id`, `unidad_id`, `dni`, `cuil`, `fecha_nac`, `peso`, `altura`, `sexo`, `domicilio`, `estado_civil`, `hijos`, `nou`, `nro_cta`, `cbu`, `alias_banco`, `fecha_ultimo_anexo27`, `tiene_parte_enfermo`, `parte_enfermo_desde`, `parte_enfermo_hasta`, `cantidad_parte_enfermo`, `destino_interno`, `rol`, `anios_en_destino`, `fracc`, `jerarquia`, `grado`, `arma`, `apellido_nombre`, `apellido`, `nombre`, `destino_id`, `funcion`, `telefono`, `correo`, `fecha_alta`, `observaciones`, `role_id`, `created_at`, `updated_at`, `updated_by_id`, `extra_json`) VALUES
(156, 1, '41742406', NULL, NULL, NULL, NULL, 'M', 'Vice Almirante John Oconnor 1068, Bariloche, Argentina', 'Soltero', 0, NULL, NULL, NULL, NULL, NULL, 0, '2026-12-31', '2027-01-01', 0, 'Informatica', NULL, 1.00, '2', 'OFICIAL', 'ST', 'SCD', 'Rojas Gabriel', NULL, NULL, 7, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-26 01:25:45', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"OFICIAL\", \"jerarquia_label\": \"OFICIALES\"}'),
(158, 1, '43618351', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '1', 'SUBOFICIAL', 'CB', 'Mus', 'ALVES LENCINA Josué', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(159, 1, '37695054', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'D', 'SUBOFICIAL', 'CB', 'I', 'ARGEL Néstor Paul', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(160, 1, '41901928', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '1', 'SUBOFICIAL', 'CB', 'Conduc Mot', 'ESPINOSA Juan Sebastian Alejandro', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RETIRO:', 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(161, 1, '42736155', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '3', 'SUBOFICIAL', 'CB EC', 'Mec Info', 'FERNANDEZ Brian Ignacio', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(162, 1, '41755576', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '3', 'SUBOFICIAL', 'CB EC', 'Mec Inst', 'PEREZ Sergio Daniel', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(163, 1, '43804935', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '5', 'SUBOFICIAL', 'CB EC', 'Mus', 'VIEIRA DE LIMA Gustavo Rafael', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(164, 1, '39229210', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '3', 'SUBOFICIAL', 'CI', 'Mec Eq Fij', 'BAEZ FARIÑA Gabriel Moises', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(165, 1, '38585795', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '4', 'SUBOFICIAL', 'CI', 'Ofic', 'CABALLERO LUNA Carolina Mariel', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(166, 1, '40628111', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '4', 'SUBOFICIAL', 'CI', 'Mus', 'FLORES Cintia Abigail', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(167, 1, '35709978', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '1', 'SUBOFICIAL', 'CI', 'Tal', 'FRANCO Blas Ariel Alejandro', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(168, 1, '40488065', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '5', 'SUBOFICIAL', 'CI', 'I', 'FRANCO Ezequiel Julian', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(169, 1, '42069719', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '5', 'SUBOFICIAL', 'CI', 'I', 'GAITAN Elio Jesus', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(170, 1, '36837146', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '4', 'SUBOFICIAL', 'CI', 'Mus', 'GUTIERREZ Pedro Hernan', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(171, 1, '35818697', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2', 'SUBOFICIAL', 'CI', 'Herr', 'MALDONADO GUERRERO Mauricio Esteban', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(172, 1, '39675068', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2', 'SUBOFICIAL', 'CI', 'Mus', 'MARTINEZ Yanina Adriana', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(173, 1, '36614359', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2', 'SUBOFICIAL', 'CI', 'Mec Eq Camp', 'MELENDEZ Alejandro Damian', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(174, 1, '19040619', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '3', 'SUBOFICIAL', 'CI', 'I', 'MONDO Agustin Vicente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(175, 1, '41054639', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '1', 'SUBOFICIAL', 'CI', 'Int', 'ORTEGA SALAZAR Sebastian Nicolas', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'LICENCIA: No tiene fecha presentación (SAF)', 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(176, 1, '33918403', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '1', 'SUBOFICIAL', 'CI', 'Mus', 'PAINEFIL Cristian David', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(177, 1, '35954614', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '1', 'SUBOFICIAL', 'CI', 'Mus', 'QUISPE Lucas Jesús', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(178, 1, '40329557', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '4', 'SUBOFICIAL', 'CI', 'Mus', 'ROBLES Martin Miguel', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RETIRO:', 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(179, 1, '38789882', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '3', 'SUBOFICIAL', 'CI', 'Mus', 'RUEDA Sebastian Jose', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(180, 1, '35077099', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '1', 'SUBOFICIAL', 'CI Art 11', 'Sas', 'CIRULLI Mauricio Daniel', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(181, 1, '36233006', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '5', 'SUBOFICIAL', 'CI Art 11', 'Enf Grl', 'CUELLO Emanuel Sergio', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(182, 1, '34714645', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'F', 'SUBOFICIAL', 'CI Art 11', 'I', 'MOLINAS Santiago Andrés', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RETIRO:', 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(183, 1, '38925650', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '4', 'SUBOFICIAL', 'CI EC', 'Cama', 'TOLOSA Braian Emanuel', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RETIRO:', 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(184, 1, '40516245', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '3', 'SUBOFICIAL', 'CI EC', 'Coc', 'VEGA Anibal Leonel', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RETIRO:', 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(185, 1, '38335684', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '4', 'SUBOFICIAL', 'CI EC', 'Coc', 'VEGA Mauricio Nicolás', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(186, 1, '25402368', NULL, '1976-10-28', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '3', 'SUBOFICIAL', 'SA', 'I', 'BARRIA VARGAS Enrique Antonio', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(187, 1, '31485399', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '5', 'SUBOFICIAL', 'SA', 'I', 'BRITEZ Leonardo Ariel', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(188, 1, '27632543', NULL, '1980-01-13', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '3', 'SUBOFICIAL', 'SA', 'Int', 'DEL CID Manuel Antonio', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(189, 1, '30669051', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '5', 'SUBOFICIAL', 'SA', 'Mus', 'FERNANDEZ MACIAS Rodrigo Héctor', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(190, 1, '22366012', NULL, '1971-07-30', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'D', 'SUBOFICIAL', 'SA', 'Mec Inst', 'FLORES Luis Antonio', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'LICENCIA: 01/03/2026 · RETIRO: 31/08/2026', 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(191, 1, '27747507', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '5', 'SUBOFICIAL', 'SA', 'Conduc Mot', 'GOÑI VILLALBA Nahuel Nelson', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(192, 1, '28577701', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '5', 'SUBOFICIAL', 'SA', 'I', 'HIGUERAS VILLAGRA Héctor Hugo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(193, 1, '26249322', NULL, '1978-02-10', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2', 'SUBOFICIAL', 'SA', 'I', 'JURADO Carlos Héctor', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(194, 1, '28190245', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '1', 'SUBOFICIAL', 'SA', 'Mus', 'LOPEZ Fernando Raul', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(195, 1, '23745244', NULL, '1974-11-10', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2', 'SUBOFICIAL', 'SA', 'Carp', 'MARTIN Sergio Bernardo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(196, 1, '30268302', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '3', 'SUBOFICIAL', 'SA', 'Enf Grl', 'MOYANO David Marcelo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(197, 1, '26951915', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '5', 'SUBOFICIAL', 'SA', 'Baq', 'NAVARRO Daniel Alejandro', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(198, 1, '31153974', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2', 'SUBOFICIAL', 'SA', 'Mec', 'NOGALES Daniel Alejandro', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(199, 1, '28490734', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '5', 'SUBOFICIAL', 'SA', 'I', 'ORTIZ Diego Fabián', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(200, 1, '27008470', NULL, '1978-12-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'D', 'SUBOFICIAL', 'SA', 'I(c)', 'PICONE Juan Manuel', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(201, 1, '27943349', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '5', 'SUBOFICIAL', 'SA', 'Mus', 'RATTO Martin Miguel', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(202, 1, '30602133', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '5', 'SUBOFICIAL', 'SA', 'Ing', 'RONDAN Matías Daniel Armando', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(203, 1, '28646442', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '5', 'SUBOFICIAL', 'SA', 'I', 'TAPIA Fernando Adrián', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(204, 1, '29716411', NULL, '1983-01-21', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2', 'SUBOFICIAL', 'SA', 'Com', 'TORRES GONZALEZ Andrés Nicolás', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(205, 1, '21323602', NULL, '1970-11-14', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '3', 'SUBOFICIAL', 'SA', 'I', 'VALLEJOS Roberto Carlos', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(206, 1, '26727367', NULL, '1978-08-21', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2', 'SUBOFICIAL', 'SA', 'C', 'VILLAFAÑE Carlos Sandro', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(207, 1, '24200730', NULL, '1974-10-26', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2', 'SUBOFICIAL', 'SA', 'Cama', 'VISUARA Pedro José', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(208, 1, '28259195', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '5', 'SUBOFICIAL', 'SA', 'Int', 'ZAMORA Ivana Gabriela', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(209, 1, '26567574', NULL, '1978-05-11', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '3', 'SUBOFICIAL', 'SA', 'Cama', 'ZONINO Leandro Walter', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(210, 1, '36174185', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '1', 'SUBOFICIAL', 'SG', 'Mec Mot Rueda', 'ACEVEDO Eduardo Horacio', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(211, 1, '34714452', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '4', 'SUBOFICIAL', 'SG', 'I', 'ALCARAZ Juan Alejandro', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(212, 1, '31007476', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '1', 'SUBOFICIAL', 'SG', 'C', 'ARRIAGADA Pablo Jose', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(213, 1, '34340814', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '3', 'SUBOFICIAL', 'SG', 'I', 'AYALA Ignacio Evaristo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(214, 1, '36809263', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '3', 'SUBOFICIAL', 'SG', 'Baq', 'AZOCAR Diego Nicolás', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(215, 1, '33954988', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '4', 'SUBOFICIAL', 'SG', 'Carp', 'BENITEZ Guillermo Sebastian', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RETIRO:', 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(216, 1, '34521400', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '3', 'SUBOFICIAL', 'SG', 'Mus', 'BIDART Claudio Alejandro', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(217, 1, '30784152', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '1', 'SUBOFICIAL', 'SG', 'Conduc Mot', 'BUSTOS Matías Gonzalo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(218, 1, '33426767', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '3', 'SUBOFICIAL', 'SG', 'I', 'CABEZAS Adrian', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(219, 1, '33042967', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '4', 'SUBOFICIAL', 'SG', 'I', 'CARRASCO Juan', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(220, 1, '32263444', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '1', 'SUBOFICIAL', 'SG', 'Enf Grl', 'CORONEL Victor Ezequiel', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RETIRO:', 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(221, 1, '25354264', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'D', 'SUBOFICIAL', 'SG', 'Baq', 'GARCIA David Nelson', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(222, 1, '36320839', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '3', 'SUBOFICIAL', 'SG', 'Int', 'GONZALEZ Florencia Cristina', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(223, 1, '31083567', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '1', 'SUBOFICIAL', 'SG', 'Baq', 'GONZALEZ Sergio Damián', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(224, 1, '35036679', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '3', 'SUBOFICIAL', 'SG', 'I', 'GRAGEDA David Jonathan', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RETIRO:', 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(225, 1, '38173615', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '5', 'SUBOFICIAL', 'SG', 'Condcuc Mot', 'HOLMAN Alan Franco', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'LICENCIA: No tiene fecha presentación (SAF)', 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(226, 1, '32645836', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2', 'SUBOFICIAL', 'SG', 'I', 'HUAIQUILAF Miguel Angel', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(227, 1, '34775404', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '3', 'SUBOFICIAL', 'SG', 'Env Vet', 'LEIVA Eduardo Luis', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(228, 1, '35595466', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '3', 'SUBOFICIAL', 'SG', 'Conduc Mot', 'LEIVA Jonathan Segundo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(229, 1, '39917711', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '5', 'SUBOFICIAL', 'SG', 'Enf Grl', 'MEDINA Natalia Soledad', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(230, 1, '25487062', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'D', 'SUBOFICIAL', 'SG', 'Baq', 'ÑANCURPAY Gustavo Javier', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(231, 1, '33659938', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '5', 'SUBOFICIAL', 'SG', 'Mus', 'OYARZO Valeria de las Nieves', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(232, 1, '35459870', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '4', 'SUBOFICIAL', 'SG', 'Int', 'PEREZ Ariel Alejandro', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(233, 1, '34666961', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '5', 'SUBOFICIAL', 'SG', 'Mus', 'QUIJADA Veronica', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(234, 1, '33327936', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '4', 'SUBOFICIAL', 'SG', 'Herr', 'QUIÑENAO Valdemar Segundo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(235, 1, '37256482', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '5', 'SUBOFICIAL', 'SG', 'Ofic', 'SANTILLAN Luis Ernesto', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RETIRO:', 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(236, 1, '34945277', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '5', 'SUBOFICIAL', 'SG', 'Cama', 'TORO TORCIBIA Jesús Elías', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(237, 1, '35928720', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2', 'SUBOFICIAL', 'SG', 'Mec Info', 'VALDIVIEZO Cecilia Nadia Tamara', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(238, 1, '35593401', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '4', 'SUBOFICIAL', 'SG', 'Baq', 'VEGA Rubén', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(239, 1, '35027266', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '5', 'SUBOFICIAL', 'SG', 'Coc', 'VILLALBA Damian Esteban', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(240, 1, '29736108', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '4', 'SUBOFICIAL', 'SI', 'Conduc Mot', 'ALARCON Diego Armando', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(241, 1, '25722823', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '1', 'SUBOFICIAL', 'SI', 'Mus', 'ALBA Néstor Cristóbal', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(242, 1, '25856603', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '3', 'SUBOFICIAL', 'SI', 'Vet', 'BOMPADRE Eugenio Fernando', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(243, 1, '30784104', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '3', 'SUBOFICIAL', 'SI', 'I', 'CABEZA Ricardo Nicolás', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(244, 1, '30857263', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '5', 'SUBOFICIAL', 'SI', 'I', 'CALA Cesar Cristian', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(245, 1, '31858665', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '5', 'SUBOFICIAL', 'SI', 'Baq', 'CASTAÑEDA Julio', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(246, 1, '28720666', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '3', 'SUBOFICIAL', 'SI', 'I', 'CORSO Néstor Mauricio', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(247, 1, '29659801', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2', 'SUBOFICIAL', 'SI', 'Coc', 'EDUARDS Gustavo Omar', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(248, 1, '30482789', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2', 'SUBOFICIAL', 'SI', 'Mus', 'FERNANDEZ Claudio Matías', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(249, 1, '29428568', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '3', 'SUBOFICIAL', 'SI', 'Coc', 'FERNANDEZ Gustavo Javier', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RETIRO:', 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(250, 1, '30889331', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2', 'SUBOFICIAL', 'SI', 'I', 'FLORES Ricardo Sebastian', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(251, 1, '27435089', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '1', 'SUBOFICIAL', 'SI', 'I', 'GALLARDO Gabriel Germán', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(252, 1, '30344887', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '5', 'SUBOFICIAL', 'SI', 'Conduc Mot', 'GIMENEZ Victor Jesús', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(253, 1, '31706085', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '4', 'SUBOFICIAL', 'SI', 'I', 'GONZALEZ Raúl Alberto', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(254, 1, '32011637', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '3', 'SUBOFICIAL', 'SI', 'Zap', 'HERRERA Matias Adrian', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(255, 1, '31865182', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '5', 'SUBOFICIAL', 'SI', 'I', 'MENDEZ Gonzalo Andrés', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(256, 1, '32308597', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '4', 'SUBOFICIAL', 'SI', 'Mus', 'MOGAYA Cristian Emmanuel', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(257, 1, '30594078', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '5', 'SUBOFICIAL', 'SI', 'Mus', 'MONTERO Edgar Alejandro', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(258, 1, '31215603', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '5', 'SUBOFICIAL', 'SI', 'Conduc Mot', 'MORINICO Natalia Cintia Soledad', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(259, 1, '30391943', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '4', 'SUBOFICIAL', 'SI', 'I', 'NAVARRO Gastón Damián', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(260, 1, '26013530', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2', 'SUBOFICIAL', 'SI', 'Cama', 'PINTOS Juan José', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(261, 1, '29280196', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '3', 'SUBOFICIAL', 'SI', 'Conduc Mot', 'RIVAS Miguel Ángel', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(262, 1, '28000250', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2', 'SUBOFICIAL', 'SI', 'I', 'SANTIBAÑEZ Félix Gabriel', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(263, 1, '30231828', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2', 'SUBOFICIAL', 'SI', 'Ing', 'TOLEDO Carolina Marcela', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(264, 1, '33494778', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '3', 'SUBOFICIAL', 'SI', 'Mec Mot', 'TORO Sergio Daniel', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(265, 1, '28667939', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2', 'SUBOFICIAL', 'SI', 'I', 'URIBE Cesar Horacio', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(266, 1, '31118613', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '3', 'SUBOFICIAL', 'SI', 'I', 'VERA Miguel Alberto', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(267, 1, '25241958', NULL, '1976-06-04', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'D', 'SUBOFICIAL', 'SM', 'Ofic', 'BEARZZOTTI Diego Sebastián', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(268, 1, '23402081', NULL, '1973-08-22', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'D', 'SUBOFICIAL', 'SM', 'Mec Eq Fij', 'CARDOZO Ariel Ramón Alejandro', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'LICENCIA: 01/03/2026 · RETIRO: 30/08/2026', 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(269, 1, '26844587', NULL, '1978-10-12', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'D', 'SUBOFICIAL', 'SM', 'Int', 'HENANDEZ Dardo Eliseo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(270, 1, '24038848', NULL, '1974-05-31', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'D', 'SUBOFICIAL', 'SM', 'Mus', 'LEAL CALQUIN Pablo Marcelo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'LICENCIA: 01/01/2027 · RETIRO: 30/06/2027', 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(271, 1, '22643215', NULL, '1972-04-27', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'D', 'SUBOFICIAL', 'SM', 'Ing', 'MOREIRA Hugo Daniel', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(272, 1, '22951566', NULL, '1972-12-02', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'D', 'SUBOFICIAL', 'SM', 'Mus', 'OBERTI David Alejandro', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(273, 1, '22843745', NULL, '1972-12-05', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'D', 'SUBOFICIAL', 'SM', 'Conduc Mot', 'PEREYRA Gustavo Daniel', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'LICENCIA: 01/03/2026 · RETIRO: 30/08/2026', 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(274, 1, '26260962', NULL, '1978-04-15', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'D', 'SUBOFICIAL', 'SM', 'Cama', 'SEQUEIRA José Luis', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(275, 1, '24346947', NULL, '1975-03-29', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'D', 'SUBOFICIAL', 'SM', '2do Mtro', 'SUAREZ Raúl Federico', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(276, 1, '24095875', NULL, '1973-03-19', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'D', 'SUBOFICIAL', 'SM', 'Mus', 'VERA Edgardo José', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'LICENCIA: 01/01/2027 · RETIRO: 30/06/2027', 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(277, 1, '27988394', NULL, '1979-12-11', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'F', 'SUBOFICIAL', 'SP', 'I', 'BRIGNOLI David Maximiliano', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(278, 1, '28523569', NULL, '1980-12-22', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '4', 'SUBOFICIAL', 'SP', 'Ofic', 'CELAYES Soledad Elizabet', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'RETIRO:', 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(279, 1, '24799030', NULL, '1975-08-13', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'D', 'SUBOFICIAL', 'SP', 'Mec Mot Elec', 'CHOCOBAR Sergio Valentín', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(280, 1, '27839269', NULL, '1980-07-22', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2', 'SUBOFICIAL', 'SP', 'Mus', 'CORREA Juan Carlos', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(281, 1, '25599777', NULL, '1975-06-19', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'D', 'SUBOFICIAL', 'SP', 'Mus', 'GOMEZ Gustavo Alfredo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(282, 1, '24174548', NULL, '1974-11-12', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '3', 'SUBOFICIAL', 'SP', 'I', 'GONZALEZ Sergio', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(283, 1, '23997633', NULL, '1974-05-08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '3', 'SUBOFICIAL', 'SP', 'I', 'GUAIQUIL Luis Héctor', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(284, 1, '26469299', NULL, '1978-04-08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2', 'SUBOFICIAL', 'SP', 'Coc', 'MARIN Javier Alberto', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(285, 1, '25102918', NULL, '1976-04-04', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '1', 'SUBOFICIAL', 'SP', 'Cond Mot', 'MATUZ Hector Marcelino', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(286, 1, '24574710', NULL, '1975-03-25', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2', 'SUBOFICIAL', 'SP', 'A', 'MERINO Ariel Ambrocio', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(287, 1, '27489199', NULL, '1979-11-16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '1', 'SUBOFICIAL', 'SP', 'Conduc Mot', 'MONSALVE Javier Arturo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}');
INSERT INTO `personal_unidad` (`id`, `unidad_id`, `dni`, `cuil`, `fecha_nac`, `peso`, `altura`, `sexo`, `domicilio`, `estado_civil`, `hijos`, `nou`, `nro_cta`, `cbu`, `alias_banco`, `fecha_ultimo_anexo27`, `tiene_parte_enfermo`, `parte_enfermo_desde`, `parte_enfermo_hasta`, `cantidad_parte_enfermo`, `destino_interno`, `rol`, `anios_en_destino`, `fracc`, `jerarquia`, `grado`, `arma`, `apellido_nombre`, `apellido`, `nombre`, `destino_id`, `funcion`, `telefono`, `correo`, `fecha_alta`, `observaciones`, `role_id`, `created_at`, `updated_at`, `updated_by_id`, `extra_json`) VALUES
(288, 1, '24469692', NULL, '1975-05-29', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '4', 'SUBOFICIAL', 'SP', 'Baq', 'MUÑOZ Claudio Ernesto', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(289, 1, '25424051', NULL, '1976-02-12', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '3', 'SUBOFICIAL', 'SP', 'Coc', 'ÑANCUCHEO Antonio Nicolás', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(290, 1, '31733898', NULL, '1979-11-10', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '1', 'SUBOFICIAL', 'SP', 'Com', 'PORTAL Orlando Héctor', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(291, 1, '25616225', NULL, '1977-01-16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2', 'SUBOFICIAL', 'SP', 'Mec Inst', 'RIQUELME Claudio Marcelo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(292, 1, '25193625', NULL, '1976-04-10', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'F', 'SUBOFICIAL', 'SP', 'Mus', 'RUPPEL Nelson Daniel', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(293, 1, '21780085', NULL, '1970-11-14', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '1', 'SUBOFICIAL', 'SP', 'I', 'SEGUEL Néstor De La Cruz', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(294, 1, '23721243', NULL, '1974-05-02', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'F', 'SUBOFICIAL', 'SP', 'Int', 'SOLA Ariel Gustavo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(295, 1, '23727771', NULL, '1976-03-18', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '1', 'SUBOFICIAL', 'SP', 'I', 'URIBE Carlos Daniel', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(296, 1, '26349261', NULL, '1978-01-24', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'D', 'SUBOFICIAL', 'SP', 'A', 'VELAZQUEZ Héctor Emiliano', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}'),
(297, 1, '25384474', NULL, '1975-12-11', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '1', 'SUBOFICIAL', 'SP', 'Mec Ing', 'VIRGILIO Maximiliano Abel', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-02-26 01:26:03', '2026-02-26 01:26:11', 156, '{\"jerarquia\": \"SUBOFICIAL\", \"jerarquia_label\": \"SUBOFICIALES\"}');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `red_dispositivos`
--

CREATE TABLE `red_dispositivos` (
  `id` int(11) NOT NULL,
  `piso_id` int(11) NOT NULL,
  `tipo` varchar(30) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `mac` varchar(32) DEFAULT NULL,
  `nota` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `red_dispositivos`
--

INSERT INTO `red_dispositivos` (`id`, `piso_id`, `tipo`, `nombre`, `ip`, `mac`, `nota`, `created_at`) VALUES
(3, 1, 'pc', 'pc lopez', '192.168.10.1', NULL, 'hjgkjh', '2026-02-07 16:54:29'),
(4, 1, 'switch', 'U2285', '192.168.10.2', NULL, NULL, '2026-02-07 16:54:55'),
(6, 1, 'pc', 'pc 2', NULL, NULL, NULL, '2026-02-08 19:44:25');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `red_edificios`
--

CREATE TABLE `red_edificios` (
  `id` int(11) NOT NULL,
  `it_edificio_id` int(11) DEFAULT NULL,
  `unidad_id` int(11) DEFAULT NULL,
  `numero` int(11) DEFAULT NULL,
  `nombre` varchar(120) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `red_edificios`
--

INSERT INTO `red_edificios` (`id`, `it_edificio_id`, `unidad_id`, `numero`, `nombre`, `descripcion`, `created_at`) VALUES
(1, NULL, 1, NULL, 'Edificio Principal', NULL, '2026-02-07 15:32:26'),
(2, NULL, 1, NULL, 'Plana Mayor', NULL, '2026-02-07 17:33:27'),
(4, NULL, 1, 20, 'Plana Prueba', 'Edificio N°18 - Plana Mayor', '2026-02-08 19:49:24');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `red_edificio_meta`
--

CREATE TABLE `red_edificio_meta` (
  `id` int(11) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `edificio_id` int(11) NOT NULL,
  `max_dispositivos` int(11) DEFAULT NULL,
  `ip_desde` varchar(45) DEFAULT NULL,
  `ip_hasta` varchar(45) DEFAULT NULL,
  `nota` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `red_edificio_meta`
--

INSERT INTO `red_edificio_meta` (`id`, `unidad_id`, `edificio_id`, `max_dispositivos`, `ip_desde`, `ip_hasta`, `nota`, `updated_at`) VALUES
(1, 1, 1, NULL, '192.168.10.1', '192.168.10.254', NULL, '2026-02-07 17:10:15');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `red_enlaces`
--

CREATE TABLE `red_enlaces` (
  `id` int(11) NOT NULL,
  `plano_id` int(11) NOT NULL,
  `origen_id` int(11) NOT NULL,
  `destino_id` int(11) NOT NULL,
  `tipo` varchar(20) NOT NULL DEFAULT 'cable',
  `etiqueta` varchar(120) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `red_oficinas`
--

CREATE TABLE `red_oficinas` (
  `id` int(11) NOT NULL,
  `edificio_id` int(11) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `codigo` varchar(40) DEFAULT NULL,
  `destino_id` int(11) DEFAULT NULL,
  `tipo` varchar(30) NOT NULL DEFAULT 'oficina',
  `orden` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `red_oficinas`
--

INSERT INTO `red_oficinas` (`id`, `edificio_id`, `nombre`, `codigo`, `destino_id`, `tipo`, `orden`, `created_at`) VALUES
(1, 4, 'Subdirector', 'SUBDIR', NULL, 'oficina', 10, '2026-02-08 19:49:24'),
(2, 4, 'Operaciones', 'S3', 3, 'oficina', 20, '2026-02-08 19:49:24'),
(3, 4, 'Personal', 'S1', 1, 'oficina', 30, '2026-02-08 19:49:24'),
(4, 4, 'Materiales', 'S4', 4, 'oficina', 40, '2026-02-08 19:49:24');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `red_pisos`
--

CREATE TABLE `red_pisos` (
  `id` int(11) NOT NULL,
  `edificio_id` int(11) NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `red_pisos`
--

INSERT INTO `red_pisos` (`id`, `edificio_id`, `nombre`, `created_at`) VALUES
(1, 1, 'PB', '2026-02-07 15:32:26'),
(2, 1, '1° Piso', '2026-02-07 15:32:26'),
(3, 2, 'PB', '2026-02-07 17:33:27'),
(4, 4, 'PB', '2026-02-08 19:54:37');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `red_planos`
--

CREATE TABLE `red_planos` (
  `id` int(11) NOT NULL,
  `piso_id` int(11) NOT NULL,
  `archivo` varchar(255) NOT NULL,
  `ancho` int(11) DEFAULT NULL,
  `alto` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `red_planos`
--

INSERT INTO `red_planos` (`id`, `piso_id`, `archivo`, `ancho`, `alto`, `created_at`) VALUES
(1, 1, 'ed_1_20260207_171328_c6e5bfbb.png', 172, 132, '2026-02-07 16:13:28'),
(2, 1, 'ed_1_20260207_173454_d9d1b023.png', 1280, 810, '2026-02-07 16:34:54');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `red_posiciones`
--

CREATE TABLE `red_posiciones` (
  `dispositivo_id` int(11) NOT NULL,
  `plano_id` int(11) NOT NULL,
  `x` float NOT NULL DEFAULT 0,
  `y` float NOT NULL DEFAULT 0,
  `rot` float NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `red_posiciones`
--

INSERT INTO `red_posiciones` (`dispositivo_id`, `plano_id`, `x`, `y`, `rot`, `updated_at`) VALUES
(3, 2, 375, 223.295, 0, '2026-02-07 16:55:12'),
(4, 2, 56.9091, 76.3864, 0, '2026-02-08 19:44:38'),
(6, 2, 58.8438, 187.234, 0, '2026-02-08 19:44:38');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `red_posiciones_ext`
--

CREATE TABLE `red_posiciones_ext` (
  `id` int(11) NOT NULL,
  `dispositivo_id` int(11) NOT NULL,
  `plano_id` int(11) NOT NULL,
  `scale` decimal(6,3) NOT NULL DEFAULT 1.000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `red_posiciones_ext`
--

INSERT INTO `red_posiciones_ext` (`id`, `dispositivo_id`, `plano_id`, `scale`) VALUES
(1, 3, 2, 1.000),
(2, 4, 2, 1.000),
(6, 6, 2, 1.000);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `red_tipos_dispositivo`
--

CREATE TABLE `red_tipos_dispositivo` (
  `id` int(11) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `tipo` varchar(64) NOT NULL,
  `label` varchar(32) NOT NULL,
  `color` varchar(20) NOT NULL DEFAULT '#8ecae6',
  `icon_svg` mediumtext DEFAULT NULL,
  `is_builtin` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `red_tipos_dispositivo`
--

INSERT INTO `red_tipos_dispositivo` (`id`, `unidad_id`, `tipo`, `label`, `color`, `icon_svg`, `is_builtin`, `created_at`) VALUES
(1, 1, 'pc', 'PC', '#8ecae6', '<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\"><path fill=\"currentColor\" d=\"M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-5v2h2a1 1 0 1 1 0 2H7a1 1 0 1 1 0-2h2v-2H6a2 2 0 0 1-2-2V5Zm2 0v9h12V5H6Z\"/></svg>', 1, '2026-02-07 16:49:19'),
(2, 1, 'switch', 'SW', '#ffd166', '<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\"><path fill=\"currentColor\" d=\"M4 7a3 3 0 0 1 3-3h10a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3V7Zm3-1a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1H7Zm1 3h2v2H8V9Zm4 0h2v2h-2V9Zm4 0h2v2h-2V9Z\"/></svg>', 1, '2026-02-07 16:49:19'),
(3, 1, 'router', 'RTR', '#f8961e', '<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\"><path fill=\"currentColor\" d=\"M3 14a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4Zm2 0v4h14v-4H5Zm2 1h2v2H7v-2Zm-1-9a1 1 0 0 1 1-1c3.314 0 6 2.686 6 6a1 1 0 1 1-2 0 4 4 0 0 0-4-4 1 1 0 0 1-1-1Zm8 0a1 1 0 0 1 1-1c3.314 0 6 2.686 6 6a1 1 0 1 1-2 0 4 4 0 0 0-4-4 1 1 0 0 1-1-1Z\"/></svg>', 1, '2026-02-07 16:49:19'),
(4, 1, 'ap', 'AP', '#90be6d', '<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\"><path fill=\"currentColor\" d=\"M12 3a8 8 0 0 1 8 8 1 1 0 1 1-2 0 6 6 0 0 0-12 0 1 1 0 1 1-2 0 8 8 0 0 1 8-8Zm0 5a3 3 0 0 1 3 3 1 1 0 1 1-2 0 1 1 0 0 0-2 0 1 1 0 1 1-2 0 3 3 0 0 1 3-3Zm-5 9a5 5 0 0 1 10 0v3H7v-3Zm2 0v1h6v-1a3 3 0 0 0-6 0Z\"/></svg>', 1, '2026-02-07 16:49:19'),
(5, 1, 'servidor', 'SRV', '#577590', '<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\"><path fill=\"currentColor\" d=\"M4 5a3 3 0 0 1 3-3h10a3 3 0 0 1 3 3v4a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3V5Zm3-1a1 1 0 0 0-1 1v4a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V5a1 1 0 0 0-1-1H7Zm-3 11a3 3 0 0 1 3-3h10a3 3 0 0 1 3 3v4a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3v-4Zm3-1a1 1 0 0 0-1 1v4a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1v-4a1 1 0 0 0-1-1H7ZM8 6h2v2H8V6Zm0 10h2v2H8v-2Z\"/></svg>', 1, '2026-02-07 16:49:19'),
(6, 1, 'impresora', 'IMP', '#cdb4db', '<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\"><path fill=\"currentColor\" d=\"M7 3h10v4H7V3Zm-2 6a3 3 0 0 1 3-3h8a3 3 0 0 1 3 3v1h1a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2h-1v2H6v-2H5a2 2 0 0 1-2-2v-6a2 2 0 0 1 2-2h0V9Zm3 10h10v-5H8v5Zm12-7h-2v-1a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v1H5v6h1v-2h14v2h1v-6Z\"/></svg>', 1, '2026-02-07 16:49:19'),
(7, 1, 'camara', 'CAM', '#f94144', '<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\"><path fill=\"currentColor\" d=\"M6 7a3 3 0 0 1 3-3h6a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3H9a3 3 0 0 1-3-3V7Zm3-1a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1H9Zm3 2a4 4 0 1 1 0 8 4 4 0 0 1 0-8Zm0 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z\"/></svg>', 1, '2026-02-07 16:49:19'),
(8, 1, 'rack', 'RACK', '#adb5bd', '<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\"><path fill=\"currentColor\" d=\"M6 2h12a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Zm0 2v4h12V4H6Zm2 1h2v2H8V5Zm-2 7h12a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2Zm0 2v4h12v-4H6Zm2 1h2v2H8v-2Z\"/></svg>', 1, '2026-02-07 16:49:19');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `respuestas`
--

CREATE TABLE `respuestas` (
  `id` int(11) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `file_rel` varchar(512) NOT NULL,
  `row_idx` int(11) NOT NULL,
  `estado` enum('si','no') DEFAULT NULL,
  `observacion` text DEFAULT NULL,
  `evidencia_path` text DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `caracter` varchar(100) DEFAULT NULL,
  `updated_by_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `codigo` varchar(40) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `nivel` int(11) NOT NULL DEFAULT 0,
  `descripcion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id`, `codigo`, `nombre`, `nivel`, `descripcion`) VALUES
(1, 'SUPERADMIN', 'Superadministrador', 100, 'Control total del sistema'),
(2, 'ADMIN', 'Administrador', 50, 'Administra módulos/usuarios'),
(3, 'USUARIO', 'Usuario', 10, 'Acceso estándar');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles_locales`
--

CREATE TABLE `roles_locales` (
  `id` int(11) NOT NULL,
  `dni` varchar(20) NOT NULL,
  `unidad_id` int(11) DEFAULT NULL,
  `areas_acceso` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`areas_acceso`)),
  `role_id` int(11) DEFAULT NULL,
  `rol_app` enum('admin','usuario') NOT NULL DEFAULT 'usuario',
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rol_combate`
--

CREATE TABLE `rol_combate` (
  `id` int(11) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `categoria` varchar(120) DEFAULT NULL,
  `rol` varchar(255) NOT NULL,
  `orden` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `rol_combate`
--

INSERT INTO `rol_combate` (`id`, `unidad_id`, `categoria`, `rol`, `orden`) VALUES
(1, 2, 'elemento', 'Rol de Combate de la Jefatura', 1),
(2, 2, 'elemento', 'Rol de Combate de la Plana Mayor', 2),
(3, 2, 'elemento', 'Rol de Combate de la Compañía Comando y Servicio', 3),
(4, 2, 'elemento', 'Rol de Combate de la Compañía CCIG del EMGE', 4),
(5, 2, 'elemento', 'Rol de Combate de la Compañía Redes y Sistemas', 5),
(6, 2, 'elemento', 'Rol de Combate de la Compañía Infraestructura de Red', 6),
(7, 2, 'elemento', 'Rol de Combate de la Compañía Telepuerto Satelital', 7),
(8, 2, 'elemento', 'Personal en Comisión', 8),
(9, 1, 'elemento', 'Rol de Combate de la Jefatura', 1),
(10, 1, 'elemento', 'Rol de Combate de la Plana Mayor', 2),
(11, 1, 'elemento', 'Rol de Combate de la Compañía Comando y Servicio', 3),
(12, 1, 'elemento', 'Rol de Combate de la Compañía CCIG del EMGE', 4),
(13, 1, 'elemento', 'Rol de Combate de la Compañía Redes y Sistemas', 5),
(14, 1, 'elemento', 'Rol de Combate de la Compañía Infraestructura de Red', 6),
(15, 1, 'elemento', 'Rol de Combate de la Compañía Telepuerto Satelital', 7),
(16, 1, 'elemento', 'Personal en Comisión', 8);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rol_combate_asignaciones`
--

CREATE TABLE `rol_combate_asignaciones` (
  `id` int(11) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `rol_id` int(11) NOT NULL,
  `personal_id` int(11) NOT NULL,
  `desde` date DEFAULT NULL,
  `hasta` date DEFAULT NULL,
  `nota` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `created_by_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sanidad_partes_enfermo`
--

CREATE TABLE `sanidad_partes_enfermo` (
  `id` int(11) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `personal_id` int(11) NOT NULL,
  `tiene_parte` enum('si','no') NOT NULL DEFAULT 'no',
  `evento` enum('parte','alta') NOT NULL DEFAULT 'parte',
  `inicio` date DEFAULT NULL,
  `fin` date DEFAULT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 0,
  `observaciones` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `created_by_id` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `unidades`
--

CREATE TABLE `unidades` (
  `id` int(11) NOT NULL,
  `slug` varchar(60) NOT NULL,
  `nombre_corto` varchar(120) NOT NULL,
  `nombre_completo` varchar(255) DEFAULT NULL,
  `subnombre` varchar(255) DEFAULT NULL,
  `logo_path` varchar(512) DEFAULT NULL,
  `escudo_path` varchar(512) DEFAULT NULL,
  `bg_path` varchar(512) DEFAULT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `unidades`
--

INSERT INTO `unidades` (`id`, `slug`, `nombre_corto`, `nombre_completo`, `subnombre`, `logo_path`, `escudo_path`, `bg_path`, `activa`, `created_at`, `updated_at`) VALUES
(1, 'ecmilm', 'EC MIL M', 'Escuela Militar de Montaña', '“La montaña nos une”', 'storage/unidades/ec_mil_m/branding/logo.png', 'storage/unidades/ec_mil_m/branding/escudo.png', 'storage/unidades/ec_mil_m/branding/bg.png', 1, '2026-01-31 17:20:21', '2026-01-31 23:04:46'),
(2, 'bcom602', 'B Com 602', 'Batallón de Comunicaciones 602', 'Hogar de las Comunicaciones Fijas del Ejército', 'storage/unidades/bcom602/branding/logo.png', NULL, NULL, 0, '2026-01-31 17:20:21', '2026-01-31 22:48:13'),
(3, 'ccig_pdl', 'CCIG Pdl', 'CCIG Paso de los Libres', NULL, NULL, NULL, NULL, 0, '2026-01-31 17:20:21', '2026-01-31 19:31:26');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario_roles`
--

CREATE TABLE `usuario_roles` (
  `id` bigint(20) NOT NULL,
  `personal_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `unidad_id` int(11) DEFAULT NULL,
  `destino_id` int(11) DEFAULT NULL,
  `areas_acceso` longtext DEFAULT NULL,
  `granted_by_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_personal_rol_actual`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_personal_rol_actual` (
`personal_id` int(11)
,`dni` varchar(20)
,`unidad_id` int(11)
,`unidad_slug` varchar(60)
,`unidad_nombre` varchar(120)
,`apellido` varchar(120)
,`nombre` varchar(120)
,`grado` varchar(60)
,`arma` varchar(60)
,`destino_id` int(11)
,`destino_codigo` varchar(30)
,`destino_nombre` varchar(255)
,`role_id` int(11)
,`role_codigo` varchar(40)
,`role_nombre` varchar(120)
,`funcion` varchar(255)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `xlsx_prefs`
--

CREATE TABLE `xlsx_prefs` (
  `unidad_id` int(11) NOT NULL,
  `file_rel` varchar(512) NOT NULL,
  `sheet_idx` int(11) NOT NULL DEFAULT 0,
  `row_start` int(11) NOT NULL DEFAULT 1,
  `col_estado` varchar(10) DEFAULT NULL,
  `col_obs` varchar(10) DEFAULT NULL,
  `col_caracter` varchar(10) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by_id` int(11) DEFAULT NULL,
  `table_fmt` enum('classic','form') NOT NULL DEFAULT 'classic',
  `mode_num_is` enum('title','item') NOT NULL DEFAULT 'item'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_personal_rol_actual`
--
DROP TABLE IF EXISTS `v_personal_rol_actual`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_personal_rol_actual`  AS SELECT `pu`.`id` AS `personal_id`, `pu`.`dni` AS `dni`, `pu`.`unidad_id` AS `unidad_id`, `u`.`slug` AS `unidad_slug`, `u`.`nombre_corto` AS `unidad_nombre`, `pu`.`apellido` AS `apellido`, `pu`.`nombre` AS `nombre`, `pu`.`grado` AS `grado`, `pu`.`arma` AS `arma`, `d`.`id` AS `destino_id`, `d`.`codigo` AS `destino_codigo`, `d`.`nombre` AS `destino_nombre`, `r`.`id` AS `role_id`, `r`.`codigo` AS `role_codigo`, `r`.`nombre` AS `role_nombre`, `pu`.`funcion` AS `funcion` FROM (((`personal_unidad` `pu` join `unidades` `u` on(`u`.`id` = `pu`.`unidad_id`)) left join `destino` `d` on(`d`.`id` = `pu`.`destino_id`)) join `roles` `r` on(`r`.`id` = `pu`.`role_id`)) ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `calendario_diario`
--
ALTER TABLE `calendario_diario`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_unidad` (`unidad_id`),
  ADD KEY `idx_area` (`area_code`),
  ADD KEY `idx_fecha` (`fecha`),
  ADD KEY `idx_uaf` (`unidad_id`,`area_code`,`fecha`);

--
-- Indices de la tabla `calendario_tareas`
--
ALTER TABLE `calendario_tareas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_unidad` (`unidad_id`),
  ADD KEY `idx_area` (`area_code`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_inicio` (`inicio`),
  ADD KEY `idx_venc` (`fecha_vencimiento`);

--
-- Indices de la tabla `checklist`
--
ALTER TABLE `checklist`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_checklist_lookup` (`unidad_id`,`file_rel`,`row_idx`),
  ADD KEY `idx_checklist_updated_by` (`updated_by_id`);

--
-- Indices de la tabla `checklist_form`
--
ALTER TABLE `checklist_form`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_checklist_form_file` (`unidad_id`,`file_rel`),
  ADD KEY `idx_cf_updated_by` (`updated_by_id`);

--
-- Indices de la tabla `destino`
--
ALTER TABLE `destino`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_destino_unidad_codigo` (`unidad_id`,`codigo`),
  ADD KEY `idx_destino_unidad` (`unidad_id`),
  ADD KEY `idx_destino_unidad_activo` (`unidad_id`,`activo`),
  ADD KEY `idx_destino_unidad_orden` (`unidad_id`);

--
-- Indices de la tabla `documentos`
--
ALTER TABLE `documentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_documentos_unidad_destino` (`unidad_id`,`destino_id`),
  ADD KEY `idx_documentos_created_by` (`created_by_id`),
  ADD KEY `fk_documentos_destino` (`destino_id`),
  ADD KEY `idx_documentos_sha256` (`sha256`),
  ADD KEY `idx_documentos_path` (`unidad_id`,`path`),
  ADD KEY `fk_documentos_updated_by` (`updated_by_id`);

--
-- Indices de la tabla `educacion_tropa_actividades`
--
ALTER TABLE `educacion_tropa_actividades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ciclo_unidad` (`unidad_id`,`ciclo`),
  ADD KEY `idx_fecha` (`fecha`);

--
-- Indices de la tabla `educacion_tropa_personal_ciclos`
--
ALTER TABLE `educacion_tropa_personal_ciclos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_personal_ciclo_unidad` (`unidad_id`,`personal_unidad_id`,`ciclo`),
  ADD KEY `idx_ciclo_unidad` (`unidad_id`,`ciclo`);

--
-- Indices de la tabla `inf_cat_estado_dispositivo`
--
ALTER TABLE `inf_cat_estado_dispositivo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_codigo` (`codigo`);

--
-- Indices de la tabla `inf_dispositivo_detalle`
--
ALTER TABLE `inf_dispositivo_detalle`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_detalle_red` (`red_dispositivo_id`),
  ADD KEY `idx_det_unidad` (`unidad_id`),
  ADD KEY `idx_det_estado` (`estado_id`),
  ADD KEY `idx_det_personal` (`asignado_personal_id`),
  ADD KEY `fk_det_edificio` (`edificio_id`),
  ADD KEY `fk_det_piso` (`piso_id`),
  ADD KEY `fk_det_oficina` (`oficina_id`);

--
-- Indices de la tabla `inf_redes`
--
ALTER TABLE `inf_redes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_unidad_nombre` (`unidad_id`,`nombre`),
  ADD KEY `idx_inf_redes_unidad` (`unidad_id`);

--
-- Indices de la tabla `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_items_lookup` (`unidad_id`,`file_rel`,`row_idx`);

--
-- Indices de la tabla `it_activos`
--
ALTER TABLE `it_activos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_it_activos_u` (`unidad_id`,`tipo`,`condicion`,`estado`),
  ADD KEY `idx_it_activos_loc` (`unidad_id`,`edificio_id`,`area_id`),
  ADD KEY `idx_it_activos_asig` (`unidad_id`,`asignado_personal_id`),
  ADD KEY `fk_it_activos_edif` (`edificio_id`),
  ADD KEY `fk_it_activos_fuente` (`fuente_fondos_id`),
  ADD KEY `idx_it_activos_area` (`area_id`),
  ADD KEY `idx_it_activos_asignado` (`asignado_personal_id`),
  ADD KEY `idx_it_activos_edificio` (`edificio_id`),
  ADD KEY `idx_it_activos_ip` (`ip`),
  ADD KEY `idx_it_activos_mac` (`mac`),
  ADD KEY `idx_it_activos_disp` (`dispositivo_tipo`);

--
-- Indices de la tabla `it_edificios`
--
ALTER TABLE `it_edificios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_it_edif` (`unidad_id`,`nombre`),
  ADD UNIQUE KEY `uq_it_edif_unidad_numero` (`unidad_id`,`numero`);

--
-- Indices de la tabla `it_fuentes_fondos`
--
ALTER TABLE `it_fuentes_fondos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_it_fuente` (`unidad_id`,`nombre`);

--
-- Indices de la tabla `it_internet`
--
ALTER TABLE `it_internet`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_it_internet_unidad` (`unidad_id`),
  ADD KEY `idx_it_internet_edificio` (`edificio_id`);

--
-- Indices de la tabla `it_mantenimientos`
--
ALTER TABLE `it_mantenimientos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_it_mant_unidad` (`unidad_id`),
  ADD KEY `idx_it_mant_edificio` (`edificio_id`),
  ADD KEY `idx_it_mant_activo` (`activo_id`);

--
-- Indices de la tabla `personal_documentos`
--
ALTER TABLE `personal_documentos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_pd_unidad_path` (`unidad_id`,`path`),
  ADD KEY `idx_pd_unidad_personal` (`unidad_id`,`personal_id`),
  ADD KEY `idx_pd_created_by` (`created_by_id`),
  ADD KEY `fk_pd_personal` (`personal_id`),
  ADD KEY `idx_pd_sanidad` (`sanidad_id`),
  ADD KEY `idx_pd_evento` (`evento_id`),
  ADD KEY `idx_pd_tipo_fecha` (`unidad_id`,`personal_id`,`tipo`,`fecha`),
  ADD KEY `idx_pd_path` (`unidad_id`,`path`),
  ADD KEY `idx_pd_sha256` (`sha256`);

--
-- Indices de la tabla `personal_eventos`
--
ALTER TABLE `personal_eventos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pe_unidad_personal` (`unidad_id`,`personal_id`),
  ADD KEY `idx_pe_tipo_fechas` (`unidad_id`,`tipo`,`desde`,`hasta`),
  ADD KEY `idx_pe_created_by` (`created_by_id`),
  ADD KEY `idx_pe_updated_by` (`updated_by_id`),
  ADD KEY `fk_pe_personal` (`personal_id`);

--
-- Indices de la tabla `personal_unidad`
--
ALTER TABLE `personal_unidad`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_personal_unidad_dni` (`unidad_id`,`dni`),
  ADD KEY `idx_personal_unidad` (`unidad_id`),
  ADD KEY `idx_personal_destino` (`destino_id`),
  ADD KEY `idx_personal_role` (`role_id`),
  ADD KEY `idx_personal_updated_by` (`updated_by_id`),
  ADD KEY `idx_pu_apellido_nombre` (`apellido_nombre`),
  ADD KEY `idx_pu_cuil` (`cuil`),
  ADD KEY `idx_pu_destino_interno` (`destino_interno`),
  ADD KEY `idx_pu_parte_estado` (`unidad_id`,`tiene_parte_enfermo`,`parte_enfermo_desde`,`parte_enfermo_hasta`),
  ADD KEY `idx_pu_unidad_jerarquia` (`unidad_id`,`jerarquia`);

--
-- Indices de la tabla `red_dispositivos`
--
ALTER TABLE `red_dispositivos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `piso_id` (`piso_id`);

--
-- Indices de la tabla `red_edificios`
--
ALTER TABLE `red_edificios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_red_edif_unidad_numero` (`unidad_id`,`numero`),
  ADD UNIQUE KEY `uq_red_edif_unidad_nombre` (`unidad_id`,`nombre`),
  ADD UNIQUE KEY `uq_red_edif_it` (`it_edificio_id`),
  ADD KEY `idx_red_edif_it` (`it_edificio_id`);

--
-- Indices de la tabla `red_edificio_meta`
--
ALTER TABLE `red_edificio_meta`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_meta_unidad_edificio` (`unidad_id`,`edificio_id`),
  ADD KEY `idx_meta_unidad` (`unidad_id`),
  ADD KEY `idx_meta_edificio` (`edificio_id`);

--
-- Indices de la tabla `red_enlaces`
--
ALTER TABLE `red_enlaces`
  ADD PRIMARY KEY (`id`),
  ADD KEY `plano_id` (`plano_id`),
  ADD KEY `origen_id` (`origen_id`),
  ADD KEY `destino_id` (`destino_id`);

--
-- Indices de la tabla `red_oficinas`
--
ALTER TABLE `red_oficinas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_red_ofi_edif_nombre` (`edificio_id`,`nombre`),
  ADD KEY `edificio_id` (`edificio_id`),
  ADD KEY `idx_red_ofi_destino` (`destino_id`);

--
-- Indices de la tabla `red_pisos`
--
ALTER TABLE `red_pisos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `edificio_id` (`edificio_id`);

--
-- Indices de la tabla `red_planos`
--
ALTER TABLE `red_planos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `piso_id` (`piso_id`);

--
-- Indices de la tabla `red_posiciones`
--
ALTER TABLE `red_posiciones`
  ADD PRIMARY KEY (`dispositivo_id`),
  ADD KEY `plano_id` (`plano_id`);

--
-- Indices de la tabla `red_posiciones_ext`
--
ALTER TABLE `red_posiciones_ext`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pos_ext` (`dispositivo_id`,`plano_id`),
  ADD KEY `idx_ext_plano` (`plano_id`),
  ADD KEY `idx_ext_disp` (`dispositivo_id`);

--
-- Indices de la tabla `red_tipos_dispositivo`
--
ALTER TABLE `red_tipos_dispositivo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tipo_unidad` (`unidad_id`,`tipo`),
  ADD KEY `idx_unidad` (`unidad_id`);

--
-- Indices de la tabla `respuestas`
--
ALTER TABLE `respuestas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_respuestas_lookup` (`unidad_id`,`file_rel`,`row_idx`),
  ADD KEY `idx_respuestas_updated_by` (`updated_by_id`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_roles_codigo` (`codigo`);

--
-- Indices de la tabla `roles_locales`
--
ALTER TABLE `roles_locales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_roles_locales` (`dni`,`unidad_id`),
  ADD KEY `idx_roles_locales_dni` (`dni`),
  ADD KEY `idx_roles_locales_unidad` (`unidad_id`),
  ADD KEY `idx_roles_locales_role` (`role_id`);

--
-- Indices de la tabla `rol_combate`
--
ALTER TABLE `rol_combate`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rc_unidad` (`unidad_id`);

--
-- Indices de la tabla `rol_combate_asignaciones`
--
ALTER TABLE `rol_combate_asignaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rca_unidad` (`unidad_id`),
  ADD KEY `idx_rca_rol` (`rol_id`),
  ADD KEY `idx_rca_personal` (`personal_id`),
  ADD KEY `idx_rca_created_by` (`created_by_id`);

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
-- Indices de la tabla `sanidad_partes_enfermo`
--
ALTER TABLE `sanidad_partes_enfermo`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sanidad_unidad` (`unidad_id`),
  ADD KEY `idx_sanidad_personal` (`personal_id`),
  ADD KEY `idx_sanidad_updated_by` (`updated_by_id`),
  ADD KEY `fk_sanidad_created_by` (`created_by_id`),
  ADD KEY `idx_sanidad_evento_fecha` (`unidad_id`,`personal_id`,`evento`,`inicio`,`fin`),
  ADD KEY `idx_sanidad_created_at` (`created_at`);

--
-- Indices de la tabla `unidades`
--
ALTER TABLE `unidades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_unidades_slug` (`slug`);

--
-- Indices de la tabla `usuario_roles`
--
ALTER TABLE `usuario_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_usuario_role` (`personal_id`,`role_id`,`unidad_id`,`destino_id`),
  ADD KEY `idx_ur_personal` (`personal_id`),
  ADD KEY `idx_ur_role` (`role_id`),
  ADD KEY `idx_ur_unidad` (`unidad_id`),
  ADD KEY `idx_ur_destino` (`destino_id`),
  ADD KEY `idx_ur_granted_by` (`granted_by_id`);

--
-- Indices de la tabla `xlsx_prefs`
--
ALTER TABLE `xlsx_prefs`
  ADD PRIMARY KEY (`unidad_id`,`file_rel`),
  ADD KEY `idx_xlsx_updated_by` (`updated_by_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `calendario_diario`
--
ALTER TABLE `calendario_diario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `calendario_tareas`
--
ALTER TABLE `calendario_tareas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `checklist`
--
ALTER TABLE `checklist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `checklist_form`
--
ALTER TABLE `checklist_form`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `destino`
--
ALTER TABLE `destino`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT de la tabla `documentos`
--
ALTER TABLE `documentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `educacion_tropa_actividades`
--
ALTER TABLE `educacion_tropa_actividades`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `educacion_tropa_personal_ciclos`
--
ALTER TABLE `educacion_tropa_personal_ciclos`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `inf_cat_estado_dispositivo`
--
ALTER TABLE `inf_cat_estado_dispositivo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `inf_dispositivo_detalle`
--
ALTER TABLE `inf_dispositivo_detalle`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `inf_redes`
--
ALTER TABLE `inf_redes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `items`
--
ALTER TABLE `items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `it_activos`
--
ALTER TABLE `it_activos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `it_edificios`
--
ALTER TABLE `it_edificios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `it_fuentes_fondos`
--
ALTER TABLE `it_fuentes_fondos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `it_internet`
--
ALTER TABLE `it_internet`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `it_mantenimientos`
--
ALTER TABLE `it_mantenimientos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `personal_documentos`
--
ALTER TABLE `personal_documentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `personal_eventos`
--
ALTER TABLE `personal_eventos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `personal_unidad`
--
ALTER TABLE `personal_unidad`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=439;

--
-- AUTO_INCREMENT de la tabla `red_dispositivos`
--
ALTER TABLE `red_dispositivos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `red_edificios`
--
ALTER TABLE `red_edificios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `red_edificio_meta`
--
ALTER TABLE `red_edificio_meta`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `red_enlaces`
--
ALTER TABLE `red_enlaces`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `red_oficinas`
--
ALTER TABLE `red_oficinas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `red_pisos`
--
ALTER TABLE `red_pisos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `red_planos`
--
ALTER TABLE `red_planos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `red_posiciones_ext`
--
ALTER TABLE `red_posiciones_ext`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `red_tipos_dispositivo`
--
ALTER TABLE `red_tipos_dispositivo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=417;

--
-- AUTO_INCREMENT de la tabla `respuestas`
--
ALTER TABLE `respuestas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `roles_locales`
--
ALTER TABLE `roles_locales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `rol_combate`
--
ALTER TABLE `rol_combate`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de la tabla `rol_combate_asignaciones`
--
ALTER TABLE `rol_combate_asignaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `s3_alocuciones`
--
ALTER TABLE `s3_alocuciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `s3_clases`
--
ALTER TABLE `s3_clases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `s3_cursos_regulares`
--
ALTER TABLE `s3_cursos_regulares`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `sanidad_partes_enfermo`
--
ALTER TABLE `sanidad_partes_enfermo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `unidades`
--
ALTER TABLE `unidades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `usuario_roles`
--
ALTER TABLE `usuario_roles`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `checklist`
--
ALTER TABLE `checklist`
  ADD CONSTRAINT `fk_checklist_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_checklist_updated_by` FOREIGN KEY (`updated_by_id`) REFERENCES `personal_unidad` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `checklist_form`
--
ALTER TABLE `checklist_form`
  ADD CONSTRAINT `fk_cf_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cf_updated_by` FOREIGN KEY (`updated_by_id`) REFERENCES `personal_unidad` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `destino`
--
ALTER TABLE `destino`
  ADD CONSTRAINT `fk_destino_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `documentos`
--
ALTER TABLE `documentos`
  ADD CONSTRAINT `fk_documentos_created_by` FOREIGN KEY (`created_by_id`) REFERENCES `personal_unidad` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_documentos_destino` FOREIGN KEY (`destino_id`) REFERENCES `destino` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_documentos_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_documentos_updated_by` FOREIGN KEY (`updated_by_id`) REFERENCES `personal_unidad` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `inf_dispositivo_detalle`
--
ALTER TABLE `inf_dispositivo_detalle`
  ADD CONSTRAINT `fk_det_edificio` FOREIGN KEY (`edificio_id`) REFERENCES `red_edificios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_det_estado` FOREIGN KEY (`estado_id`) REFERENCES `inf_cat_estado_dispositivo` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_det_oficina` FOREIGN KEY (`oficina_id`) REFERENCES `red_oficinas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_det_personal` FOREIGN KEY (`asignado_personal_id`) REFERENCES `personal_unidad` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_det_piso` FOREIGN KEY (`piso_id`) REFERENCES `red_pisos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_det_red` FOREIGN KEY (`red_dispositivo_id`) REFERENCES `red_dispositivos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_det_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `inf_redes`
--
ALTER TABLE `inf_redes`
  ADD CONSTRAINT `fk_inf_redes_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `items`
--
ALTER TABLE `items`
  ADD CONSTRAINT `fk_items_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `it_activos`
--
ALTER TABLE `it_activos`
  ADD CONSTRAINT `fk_it_activos_edif` FOREIGN KEY (`edificio_id`) REFERENCES `it_edificios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_it_activos_edificio` FOREIGN KEY (`edificio_id`) REFERENCES `red_edificios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_it_activos_fuente` FOREIGN KEY (`fuente_fondos_id`) REFERENCES `it_fuentes_fondos` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `it_internet`
--
ALTER TABLE `it_internet`
  ADD CONSTRAINT `fk_it_internet_edificio` FOREIGN KEY (`edificio_id`) REFERENCES `red_edificios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `it_mantenimientos`
--
ALTER TABLE `it_mantenimientos`
  ADD CONSTRAINT `fk_it_mant_activo` FOREIGN KEY (`activo_id`) REFERENCES `it_activos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_it_mant_edificio` FOREIGN KEY (`edificio_id`) REFERENCES `red_edificios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `personal_documentos`
--
ALTER TABLE `personal_documentos`
  ADD CONSTRAINT `fk_pd_created_by` FOREIGN KEY (`created_by_id`) REFERENCES `personal_unidad` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pd_evento` FOREIGN KEY (`evento_id`) REFERENCES `personal_eventos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pd_personal` FOREIGN KEY (`personal_id`) REFERENCES `personal_unidad` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pd_sanidad` FOREIGN KEY (`sanidad_id`) REFERENCES `sanidad_partes_enfermo` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pd_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `personal_eventos`
--
ALTER TABLE `personal_eventos`
  ADD CONSTRAINT `fk_pe_created_by` FOREIGN KEY (`created_by_id`) REFERENCES `personal_unidad` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pe_personal` FOREIGN KEY (`personal_id`) REFERENCES `personal_unidad` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pe_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pe_updated_by` FOREIGN KEY (`updated_by_id`) REFERENCES `personal_unidad` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `personal_unidad`
--
ALTER TABLE `personal_unidad`
  ADD CONSTRAINT `fk_personal_unidad_destino` FOREIGN KEY (`destino_id`) REFERENCES `destino` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_personal_unidad_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_personal_unidad_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_personal_unidad_updated_by` FOREIGN KEY (`updated_by_id`) REFERENCES `personal_unidad` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `red_dispositivos`
--
ALTER TABLE `red_dispositivos`
  ADD CONSTRAINT `fk_red_disp_piso` FOREIGN KEY (`piso_id`) REFERENCES `red_pisos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `red_edificios`
--
ALTER TABLE `red_edificios`
  ADD CONSTRAINT `fk_red_edif_it` FOREIGN KEY (`it_edificio_id`) REFERENCES `it_edificios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `red_enlaces`
--
ALTER TABLE `red_enlaces`
  ADD CONSTRAINT `fk_red_enl_destino` FOREIGN KEY (`destino_id`) REFERENCES `red_dispositivos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_red_enl_origen` FOREIGN KEY (`origen_id`) REFERENCES `red_dispositivos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_red_enl_plano` FOREIGN KEY (`plano_id`) REFERENCES `red_planos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `red_oficinas`
--
ALTER TABLE `red_oficinas`
  ADD CONSTRAINT `fk_red_ofi_destino` FOREIGN KEY (`destino_id`) REFERENCES `destino` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_red_oficinas_edificio` FOREIGN KEY (`edificio_id`) REFERENCES `red_edificios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `red_pisos`
--
ALTER TABLE `red_pisos`
  ADD CONSTRAINT `fk_red_pisos_edificio` FOREIGN KEY (`edificio_id`) REFERENCES `red_edificios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `red_planos`
--
ALTER TABLE `red_planos`
  ADD CONSTRAINT `fk_red_planos_piso` FOREIGN KEY (`piso_id`) REFERENCES `red_pisos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `red_posiciones`
--
ALTER TABLE `red_posiciones`
  ADD CONSTRAINT `fk_red_pos_disp` FOREIGN KEY (`dispositivo_id`) REFERENCES `red_dispositivos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_red_pos_plano` FOREIGN KEY (`plano_id`) REFERENCES `red_planos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `respuestas`
--
ALTER TABLE `respuestas`
  ADD CONSTRAINT `fk_respuestas_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_respuestas_updated_by` FOREIGN KEY (`updated_by_id`) REFERENCES `personal_unidad` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `roles_locales`
--
ALTER TABLE `roles_locales`
  ADD CONSTRAINT `fk_roles_locales_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_roles_locales_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `rol_combate`
--
ALTER TABLE `rol_combate`
  ADD CONSTRAINT `fk_rc_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `rol_combate_asignaciones`
--
ALTER TABLE `rol_combate_asignaciones`
  ADD CONSTRAINT `fk_rca_created_by` FOREIGN KEY (`created_by_id`) REFERENCES `personal_unidad` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rca_personal` FOREIGN KEY (`personal_id`) REFERENCES `personal_unidad` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rca_rol` FOREIGN KEY (`rol_id`) REFERENCES `rol_combate` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rca_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `sanidad_partes_enfermo`
--
ALTER TABLE `sanidad_partes_enfermo`
  ADD CONSTRAINT `fk_sanidad_created_by` FOREIGN KEY (`created_by_id`) REFERENCES `personal_unidad` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sanidad_personal` FOREIGN KEY (`personal_id`) REFERENCES `personal_unidad` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sanidad_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sanidad_updated_by` FOREIGN KEY (`updated_by_id`) REFERENCES `personal_unidad` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `usuario_roles`
--
ALTER TABLE `usuario_roles`
  ADD CONSTRAINT `fk_ur_destino` FOREIGN KEY (`destino_id`) REFERENCES `destino` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ur_granted_by` FOREIGN KEY (`granted_by_id`) REFERENCES `personal_unidad` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ur_personal` FOREIGN KEY (`personal_id`) REFERENCES `personal_unidad` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ur_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ur_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `xlsx_prefs`
--
ALTER TABLE `xlsx_prefs`
  ADD CONSTRAINT `fk_xlsx_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_xlsx_updated_by` FOREIGN KEY (`updated_by_id`) REFERENCES `personal_unidad` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
