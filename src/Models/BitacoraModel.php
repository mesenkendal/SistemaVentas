<?php

declare(strict_types=1);

namespace SistemaVentas\Models;

final class BitacoraModel extends BaseModel
{
    private const TABLE = 'Bitacora';

    public function log(string $table, string $action, int $recordId, array $data = [], ?int $userId = null): void
    {
        $sql = 'INSERT INTO ' . self::TABLE . ' (Tabla, Accion, RegistroId, Datos, IdUsuario)
                VALUES (:Tabla, :Accion, :RegistroId, :Datos, :IdUsuario)';
        $payload = empty($data) ? null : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->execute($sql, [
            ':Tabla'      => $table,
            ':Accion'     => strtoupper($action),
            ':RegistroId' => $recordId,
            ':Datos'      => $payload,
            ':IdUsuario'  => $userId,
        ]);
    }

    /**
     * Obtiene registros paginados aplicando filtros opcionales.
     */
    public function search(array $filters, int $limit = 20, int $offset = 0): array
    {
        [$whereClause, $params] = $this->buildFilters($filters);
        $limit = max(1, $limit);
        $offset = max(0, $offset);

        $sql = 'SELECT b.*, u.NombreUsuario
                FROM ' . self::TABLE . ' b
                LEFT JOIN Usuarios u ON u.IdUsuario = b.IdUsuario
                ' . $whereClause . '
                ORDER BY b.FechaEvento DESC
                LIMIT ' . $limit . ' OFFSET ' . $offset;

        return $this->fetchAll($sql, $params);
    }

    /**
     * Devuelve el conteo total de registros que cumplen con los filtros.
     */
    public function countRecords(array $filters): int
    {
        [$whereClause, $params] = $this->buildFilters($filters);
        $sql = 'SELECT COUNT(*) AS total FROM ' . self::TABLE . ' b ' . $whereClause;
        $result = $this->fetchOne($sql, $params);
        return (int) ($result['total'] ?? 0);
    }

    /**
     * Recupera registros para exportación con un límite de seguridad.
     */
    public function listForExport(array $filters, int $maxRows = 2000): array
    {
        [$whereClause, $params] = $this->buildFilters($filters);
        $maxRows = max(1, $maxRows);
        $sql = 'SELECT b.*, u.NombreUsuario
                FROM ' . self::TABLE . ' b
                LEFT JOIN Usuarios u ON u.IdUsuario = b.IdUsuario
                ' . $whereClause . '
                ORDER BY b.FechaEvento DESC
                LIMIT ' . $maxRows;

        return $this->fetchAll($sql, $params);
    }

    /**
     * Obtiene los nombres de tablas registrados en la bitácora.
     */
    public function distinctTables(): array
    {
        $rows = $this->fetchAll('SELECT DISTINCT Tabla FROM ' . self::TABLE . ' ORDER BY Tabla ASC');
        return array_values(array_filter(array_map(static fn($row) => $row['Tabla'] ?? null, $rows)));
    }

    /**
     * Obtiene las acciones registradas en la bitácora.
     */
    public function distinctActions(): array
    {
        $rows = $this->fetchAll('SELECT DISTINCT Accion FROM ' . self::TABLE . ' ORDER BY Accion ASC');
        return array_values(array_filter(array_map(static fn($row) => $row['Accion'] ?? null, $rows)));
    }

    /**
     * Construye el WHERE dinámico y parámetros para los filtros.
     */
    private function buildFilters(array $filters): array
    {
        $clauses = [];
        $params = [];

        if (!empty($filters['table'])) {
            $clauses[] = 'b.Tabla = :tabla';
            $params[':tabla'] = $filters['table'];
        }

        if (!empty($filters['action'])) {
            $clauses[] = 'b.Accion = :accion';
            $params[':accion'] = strtoupper((string) $filters['action']);
        }

        if (!empty($filters['user'])) {
            $clauses[] = 'b.IdUsuario = :usuario';
            $params[':usuario'] = (int) $filters['user'];
        }

        if (!empty($filters['date_from'])) {
            $clauses[] = 'DATE(b.FechaEvento) >= :desde';
            $params[':desde'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $clauses[] = 'DATE(b.FechaEvento) <= :hasta';
            $params[':hasta'] = $filters['date_to'];
        }

        $whereClause = $clauses ? 'WHERE ' . implode(' AND ', $clauses) : '';
        return [$whereClause, $params];
    }
}
