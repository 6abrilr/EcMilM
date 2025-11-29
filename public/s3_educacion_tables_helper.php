<?php
// public/s3_educacion_tables_helper.php
declare(strict_types=1);

/*
 * ======================================================
 *   Helper para crear / mantener las tablas S-3
 * ======================================================
 */

function s3_ensure_tables(PDO $pdo): void {

    /* ========== CLASES ========== */
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS s3_clases (
          id INT AUTO_INCREMENT PRIMARY KEY,
          semana VARCHAR(10) DEFAULT NULL,
          fecha VARCHAR(50) DEFAULT NULL,
          clase_trabajo VARCHAR(100) DEFAULT NULL,
          tema TEXT DEFAULT NULL,
          responsable VARCHAR(255) DEFAULT NULL,
          participantes VARCHAR(255) DEFAULT NULL,
          lugar VARCHAR(255) DEFAULT NULL,
          cumplio ENUM('','si','no','en_ejecucion') DEFAULT '',
          documento VARCHAR(255) DEFAULT NULL,
          updated_at TIMESTAMP NULL DEFAULT NULL,
          updated_by VARCHAR(100) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    /* ========== TRABAJOS DE GABINETE ========== */
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS s3_trabajos_gabinete (
          id INT AUTO_INCREMENT PRIMARY KEY,
          semana VARCHAR(10) DEFAULT NULL,
          tema TEXT DEFAULT NULL,
          responsable_grado VARCHAR(50) DEFAULT NULL,
          responsable_nombre VARCHAR(255) DEFAULT NULL,
          cumplio ENUM('','si','no','en_ejecucion') DEFAULT '',
          documento VARCHAR(255) DEFAULT NULL,
          updated_at TIMESTAMP NULL DEFAULT NULL,
          updated_by VARCHAR(100) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    /* ========== ALOCUCIONES ========== */
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS s3_alocuciones (
          id INT AUTO_INCREMENT PRIMARY KEY,
          nro INT DEFAULT NULL,
          fecha VARCHAR(100) DEFAULT NULL,
          acontecimiento TEXT DEFAULT NULL,
          responsable VARCHAR(255) DEFAULT NULL,
          cumplio ENUM('','si','no','en_ejecucion') DEFAULT '',
          documento VARCHAR(255) DEFAULT NULL,
          updated_at TIMESTAMP NULL DEFAULT NULL,
          updated_by VARCHAR(100) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    /* ========== CURSOS REGULARES ========== */
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS s3_cursos_regulares (
          id INT AUTO_INCREMENT PRIMARY KEY,
          sigla VARCHAR(20) DEFAULT NULL,
          denominacion VARCHAR(255) DEFAULT NULL,
          participantes TEXT DEFAULT NULL,
          desde VARCHAR(50) DEFAULT NULL,
          hasta VARCHAR(50) DEFAULT NULL,
          cumplio ENUM('','si','no','en_ejecucion') DEFAULT 'en_ejecucion',
          documento VARCHAR(255) DEFAULT NULL,
          updated_at TIMESTAMP NULL DEFAULT NULL,
          updated_by VARCHAR(100) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}
