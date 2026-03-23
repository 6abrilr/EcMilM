<?php
declare(strict_types=1);

function operaciones_educacion_ensure_tables(PDO $pdo): void
{
    $sql = [
        <<<'SQL'
CREATE TABLE IF NOT EXISTS s3_clases (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    semana VARCHAR(10) DEFAULT NULL,
    fecha VARCHAR(50) DEFAULT NULL,
    clase_trabajo VARCHAR(100) DEFAULT NULL,
    tema TEXT DEFAULT NULL,
    responsable VARCHAR(255) DEFAULT NULL,
    participantes TEXT DEFAULT NULL,
    lugar VARCHAR(255) DEFAULT NULL,
    cumplio ENUM('', 'si', 'no', 'en_ejecucion') NOT NULL DEFAULT '',
    documento VARCHAR(255) DEFAULT NULL,
    participantes_pdf VARCHAR(255) DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    updated_by VARCHAR(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS s3_trabajos_gabinete (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    semana VARCHAR(10) DEFAULT NULL,
    tema TEXT DEFAULT NULL,
    responsable_grado VARCHAR(50) DEFAULT NULL,
    responsable_nombre VARCHAR(255) DEFAULT NULL,
    cumplio ENUM('', 'si', 'no', 'en_ejecucion') NOT NULL DEFAULT '',
    documento VARCHAR(255) DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    updated_by VARCHAR(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS s3_alocuciones (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nro INT DEFAULT NULL,
    fecha VARCHAR(100) DEFAULT NULL,
    acontecimiento TEXT DEFAULT NULL,
    responsable VARCHAR(255) DEFAULT NULL,
    cumplio ENUM('', 'si', 'no', 'en_ejecucion') NOT NULL DEFAULT '',
    documento VARCHAR(255) DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    updated_by VARCHAR(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS s3_cursos_regulares (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    sigla VARCHAR(20) DEFAULT NULL,
    denominacion VARCHAR(255) DEFAULT NULL,
    participantes TEXT DEFAULT NULL,
    desde VARCHAR(50) DEFAULT NULL,
    hasta VARCHAR(50) DEFAULT NULL,
    cumplio ENUM('', 'si', 'no', 'en_ejecucion') NOT NULL DEFAULT 'en_ejecucion',
    documento VARCHAR(255) DEFAULT NULL,
    participantes_pdf VARCHAR(255) DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    updated_by VARCHAR(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS s3_cursos_complementarios (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    sigla VARCHAR(20) DEFAULT NULL,
    denominacion VARCHAR(255) DEFAULT NULL,
    participantes TEXT DEFAULT NULL,
    desde VARCHAR(50) DEFAULT NULL,
    hasta VARCHAR(50) DEFAULT NULL,
    cumplio ENUM('', 'si', 'no', 'en_ejecucion') NOT NULL DEFAULT 'en_ejecucion',
    documento VARCHAR(255) DEFAULT NULL,
    participantes_pdf VARCHAR(255) DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    updated_by VARCHAR(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL,
    ];

    foreach ($sql as $statement) {
        $pdo->exec($statement);
    }

    $alter = [
        "ALTER TABLE s3_clases ADD COLUMN participantes_pdf VARCHAR(255) DEFAULT NULL",
        "ALTER TABLE s3_cursos_regulares ADD COLUMN participantes_pdf VARCHAR(255) DEFAULT NULL",
        "ALTER TABLE s3_cursos_complementarios ADD COLUMN participantes_pdf VARCHAR(255) DEFAULT NULL",
        "ALTER TABLE s3_clases ADD COLUMN documento VARCHAR(255) DEFAULT NULL",
        "ALTER TABLE s3_trabajos_gabinete ADD COLUMN documento VARCHAR(255) DEFAULT NULL",
        "ALTER TABLE s3_alocuciones ADD COLUMN documento VARCHAR(255) DEFAULT NULL",
        "ALTER TABLE s3_cursos_regulares ADD COLUMN documento VARCHAR(255) DEFAULT NULL",
        "ALTER TABLE s3_cursos_complementarios ADD COLUMN documento VARCHAR(255) DEFAULT NULL",
    ];

    foreach ($alter as $statement) {
        try {
            $pdo->exec($statement);
        } catch (Throwable $e) {
            // La columna ya existe o la instancia usa un esquema previo compatible.
        }
    }
}

function s3_ensure_tables(PDO $pdo): void
{
    operaciones_educacion_ensure_tables($pdo);
}
