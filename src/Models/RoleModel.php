<?php

declare(strict_types=1);

namespace SistemaVentas\Models;

final class RoleModel extends BaseModel
{
    private const TABLE = 'Roles';

    public function all(): array
    {
        $sql = 'SELECT * FROM ' . self::TABLE . ' WHERE Activo = 1 ORDER BY NombreRol';
        return $this->fetchAll($sql);
    }

    public function find(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM ' . self::TABLE . ' WHERE IdRol = ? AND Activo = 1', [$id]);
    }

    public function create(string $nombre, ?string $descripcion = null, ?int $userId = null): int
    {
        $this->execute(
            'INSERT INTO ' . self::TABLE . ' (NombreRol, Descripcion) VALUES (?, ?)',
            [$nombre, $descripcion]
        );
        $id = (int) $this->lastInsertId();
        $this->logAction(self::TABLE, 'CREAR', $id, ['NombreRol' => $nombre], $userId);
        return $id;
    }

    public function update(int $id, array $data, ?int $userId = null): int
    {
        $fields = [];
        $params = [];

        if (array_key_exists('NombreRol', $data)) {
            $fields[] = 'NombreRol = ?';
            $params[] = $data['NombreRol'];
        }
        if (array_key_exists('Descripcion', $data)) {
            $fields[] = 'Descripcion = ?';
            $params[] = $data['Descripcion'];
        }

        if (empty($fields)) {
            return 0;
        }

        $params[] = $id;
        $sql = 'UPDATE ' . self::TABLE . ' SET ' . implode(', ', $fields) . ' WHERE IdRol = ? AND Activo = 1';
        $rows = $this->execute($sql, $params);
        if ($rows > 0) {
            $this->logAction(self::TABLE, 'ACTUALIZAR', $id, $data, $userId);
        }
        return $rows;
    }

    public function softDelete(int $id, ?int $userId = null): int
    {
        $rows = $this->execute('UPDATE ' . self::TABLE . ' SET Activo = 0 WHERE IdRol = ? AND Activo = 1', [$id]);
        if ($rows > 0) {
            $this->logAction(self::TABLE, 'ELIMINAR', $id, [], $userId);
        }
        return $rows;
    }

    public function delete(int $id, ?int $userId = null): int
    {
        return $this->softDelete($id, $userId);
    }
}
