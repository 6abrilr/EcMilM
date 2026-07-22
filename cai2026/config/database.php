<?php
declare(strict_types=1);
function db(): PDO {
 static $pdo; if($pdo instanceof PDO)return $pdo;
 $host=getenv('CAI_DB_HOST')?:'127.0.0.1'; $port=getenv('CAI_DB_PORT')?:'3306';
 $name=getenv('CAI_DB_NAME')?:'cai2026'; $user=getenv('CAI_DB_USER')?:'root'; $pass=getenv('CAI_DB_PASS')?:'';
 $pdo=new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
 $pdo->exec("SET time_zone = '-03:00'"); return $pdo;
}

