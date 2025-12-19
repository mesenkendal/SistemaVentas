<?php

declare(strict_types=1);

use SistemaVentas\Database\Connection;

require_once __DIR__ . '/../bootstrap.php';

if (!function_exists('db')) {
    /**
     * Devuelve la conexion PDO reutilizable.
     */
 function db(): \PDO
{
    $pdo = Connection::getInstance(); 
    $pdo->exec("SET time_zone = '-06:00';"); 
    return $pdo;
}

if (!function_exists('run_query')) {
    /**
     * Ejecuta consultas preparadas de manera segura.
     *
     * @param string $sql    Consulta SQL con placeholders.
     * @param array  $params Parametros para bind.
     */
    function run_query(string $sql, array $params = []): array
    {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}

if (!function_exists('execute_non_query')) {
    /**
     * Ejecuta INSERT/UPDATE/DELETE y devuelve filas afectadas.
     */
    function execute_non_query(string $sql, array $params = []): int
    {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
}
}