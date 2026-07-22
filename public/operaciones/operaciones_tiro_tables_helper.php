<?php
declare(strict_types=1);

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    require_once __DIR__ . '/../../auth/bootstrap.php';
    require_login();
    http_response_code(204);
    exit;
}

function s3_tiro_ensure_tables(PDO $pdo): void
{
    $tables = [
        "CREATE TABLE IF NOT EXISTS s3_tiro_ami (
            id INT AUTO_INCREMENT PRIMARY KEY,
            grado VARCHAR(80) NOT NULL,
            nombre VARCHAR(180) NOT NULL,
            ejercicio VARCHAR(180) NOT NULL,
            resultado VARCHAR(20) NOT NULL DEFAULT 'NO_APROBO',
            fecha DATE NULL,
            observaciones TEXT NULL,
            documento VARCHAR(255) NULL,
            actualizado_por VARCHAR(120) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        "CREATE TABLE IF NOT EXISTS s3_tiro_b9 (
            id INT AUTO_INCREMENT PRIMARY KEY,
            grado VARCHAR(80) NOT NULL,
            nombre VARCHAR(180) NOT NULL,
            ejercicio VARCHAR(180) NOT NULL,
            resultado VARCHAR(20) NOT NULL DEFAULT 'NO_APROBO',
            fecha DATE NULL,
            observaciones TEXT NULL,
            documento VARCHAR(255) NULL,
            actualizado_por VARCHAR(120) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        "CREATE TABLE IF NOT EXISTS s3_tiro_ejercicios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            codigo VARCHAR(80) NOT NULL,
            descripcion TEXT NULL,
            fecha DATE NULL,
            responsable VARCHAR(180) NULL,
            observaciones TEXT NULL,
            documento VARCHAR(255) NULL,
            actualizado_por VARCHAR(120) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        "CREATE TABLE IF NOT EXISTS s3_tiro_municion (
            id INT AUTO_INCREMENT PRIMARY KEY,
            calibre VARCHAR(80) NOT NULL,
            cantidad INT NOT NULL DEFAULT 0,
            fecha DATE NULL,
            uso TEXT NULL,
            documento VARCHAR(255) NULL,
            actualizado_por VARCHAR(120) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    ];

    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }
}