<?php

declare(strict_types=1);

namespace SistemaVentas\Models;

final class UserModel extends BaseModel
{
    private const TABLE = 'Usuarios';

    public function all(bool $onlyActive = true): array
    {
        $conditions = ['r.Activo = 1'];
        $params = [];
        if ($onlyActive) {
            $conditions[] = 'u.Activo = 1';
        }
        $sql = 'SELECT u.*, r.NombreRol FROM ' . self::TABLE . ' u
                JOIN Roles r ON r.IdRol = u.IdRol
                WHERE ' . implode(' AND ', $conditions) . ' ORDER BY u.NombreUsuario';
        return $this->fetchAll($sql, $params);
    }

    public function find(int $id): ?array
    {
        $sql = 'SELECT u.*, r.NombreRol FROM ' . self::TABLE . ' u
                JOIN Roles r ON r.IdRol = u.IdRol
                WHERE u.IdUsuario = ? AND u.Activo = 1 AND r.Activo = 1';
        return $this->fetchOne($sql, [$id]);
    }

    public function findByUsername(string $username): ?array
    {
        $sql = 'SELECT u.*, r.NombreRol
                FROM ' . self::TABLE . ' u
                JOIN Roles r ON r.IdRol = u.IdRol
                WHERE u.NombreUsuario = ? AND u.Activo = 1 AND r.Activo = 1
                LIMIT 1';
        return $this->fetchOne($sql, [$username]);
    }

    public function create(array $data, ?int $userId = null): int
    {
        $sql = 'INSERT INTO ' . self::TABLE . ' (IdRol, NombreUsuario, Apellido, Clave, Activo)
                VALUES (:IdRol, :NombreUsuario, :Apellido, :Clave, :Activo)';
        $this->execute($sql, [
            ':IdRol'          => $data['IdRol'],
            ':NombreUsuario'  => $data['NombreUsuario'],
            ':Apellido'       => $data['Apellido'],
            ':Clave'          => $data['Clave'],
            ':Activo'         => $data['Activo'] ?? 1,
        ]);
        $id = (int) $this->lastInsertId();
        $this->logAction(self::TABLE, 'CREAR', $id, $data, $userId);
        return $id;
    }

    public function update(int $id, array $data, ?int $userId = null): int
    {
        $fields = [];
        $params = [];
        foreach (['IdRol', 'NombreUsuario', 'Apellido', 'Clave', 'Activo'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        if (empty($fields)) {
            return 0;
        }
        $params[':IdUsuario'] = $id;
        $sql = 'UPDATE ' . self::TABLE . ' SET ' . implode(', ', $fields) . ' WHERE IdUsuario = :IdUsuario AND Activo = 1';
        $rows = $this->execute($sql, $params);
        if ($rows > 0) {
            $this->logAction(self::TABLE, 'ACTUALIZAR', $id, $data, $userId);
        }
        return $rows;
    }

    public function deactivate(int $id, ?int $userId = null): int
    {
        $rows = $this->execute('UPDATE ' . self::TABLE . ' SET Activo = 0 WHERE IdUsuario = ? AND Activo = 1', [$id]);
        if ($rows > 0) {
            $this->logAction(self::TABLE, 'DESACTIVAR', $id, [], $userId);
        }
        return $rows;
    }

    public function softDelete(int $id, ?int $userId = null): int
    {
        $rows = $this->execute('UPDATE ' . self::TABLE . ' SET Activo = 0 WHERE IdUsuario = ? AND Activo = 1', [$id]);
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
