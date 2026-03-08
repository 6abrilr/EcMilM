<?php
// public/s3_tiro_tables_helper.php
declare(strict_types=1);

/**
 * Crea las tablas de TIRO si no existen.
 * No pisa nada existente.
 */
function s3_tiro_ensure_tables(PDO $pdo): void
{
    // AMI asignada
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS s3_tiro_ami (
            id INT AUTO_INCREMENT PRIMARY KEY,
            grado VARCHAR(50) DEFAULT NULL,
            nombre VARCHAR(255) DEFAULT NULL,
            ejercicio VARCHAR(100) DEFAULT NULL,
            resultado ENUM('APROBO','NO_APROBO') DEFAULT 'NO_APROBO',
            observaciones TEXT DEFAULT NULL,
            fecha DATE DEFAULT NULL,
            documento VARCHAR(255) DEFAULT NULL,
            creado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            actualizado_por VARCHAR(150) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Condición B9
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS s3_tiro_b9 (
            id INT AUTO_INCREMENT PRIMARY KEY,
            grado VARCHAR(50) DEFAULT NULL,
            nombre VARCHAR(255) DEFAULT NULL,
            ejercicio VARCHAR(100) DEFAULT NULL,
            resultado ENUM('APROBO','NO_APROBO') DEFAULT 'NO_APROBO',
            observaciones TEXT DEFAULT NULL,
            fecha DATE DEFAULT NULL,
            documento VARCHAR(255) DEFAULT NULL,
            creado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            actualizado_por VARCHAR(150) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Consumo de munición
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS s3_tiro_municion (
            id INT AUTO_INCREMENT PRIMARY KEY,
            calibre VARCHAR(50) DEFAULT NULL,
            cantidad INT DEFAULT NULL,
            fecha DATE DEFAULT NULL,
            uso VARCHAR(255) DEFAULT NULL,
            documento VARCHAR(255) DEFAULT NULL,
            creado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            actualizado_por VARCHAR(150) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Ejercicios de tiro
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS s3_tiro_ejercicios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            codigo VARCHAR(50) DEFAULT NULL,
            descripcion VARCHAR(255) DEFAULT NULL,
            participantes VARCHAR(100) DEFAULT NULL,
            resultado ENUM('APROBO','NO_APROBO') DEFAULT 'NO_APROBO',
            fecha DATE DEFAULT NULL,
            documento VARCHAR(255) DEFAULT NULL,
            creado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            actualizado_por VARCHAR(150) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}
